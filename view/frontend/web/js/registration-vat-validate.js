/**
 * Copyright © Byte8 Ltd. All rights reserved.
 * See LICENSE.txt for license details.
 *
 * Live VAT-validate button for the storefront registration / account-edit
 * form. Standalone (no Knockout) because the registration form is server
 * -rendered PHTML rather than a UI component tree.
 *
 * Reads the VAT from `input[name="taxvat"]`, derives the country code from
 * a 2-letter ISO prefix in the entered VAT (e.g. `GB1234…`) or falls back
 * to the merchant-configured requester country, calls the same REST
 * endpoint the checkout uses, and renders the result inline.
 */
define([
    'jquery',
    'mage/url',
    'mage/translate'
], function ($, urlBuilder, $t) {
    'use strict';

    var STATUS_VALID = 'valid';
    var STATUS_INVALID = 'invalid';
    var STATUS_UNAVAILABLE = 'unavailable';

    var SOURCE_LABEL = {
        hmrc: 'HMRC',
        vies: 'VIES',
        uid_che: 'UID-Register'
    };

    var STATUS_LABEL = {
        valid: 'Valid',
        invalid: 'Invalid',
        unavailable: 'Unavailable'
    };

    var STATUS_CLASS = {
        valid: 'byte8-vat-status--valid',
        invalid: 'byte8-vat-status--invalid',
        unavailable: 'byte8-vat-status--unavailable'
    };

    function escape(value) {
        return $('<div>').text(String(value == null ? '' : value)).html();
    }

    function deriveCountry(rawVat, fallbackCountry) {
        var clean = String(rawVat || '')
            .replace(/[^A-Za-z0-9]/g, '')
            .toUpperCase();
        if (clean.length >= 2 && /^[A-Z]{2}/.test(clean)) {
            return { country: clean.substring(0, 2), vat: clean.substring(2) };
        }
        return { country: (fallbackCountry || '').toUpperCase(), vat: clean };
    }

    function render(container, opts) {
        var $field   = opts.$vatField;
        var $wrap    = $('<div class="byte8-vat-validate"></div>');
        var $row     = $('<div class="byte8-vat-validate__row"></div>');
        var $button  = $('<button type="button" class="action secondary byte8-vat-validate__button"></button>')
            .text($t('Validate VAT Number'));
        var $error   = $('<div class="byte8-vat-validate__error message message-error error" style="display:none;"></div>');
        var $result  = $('<div class="byte8-vat-validate__result" style="display:none;"></div>');

        $row.append($button);
        $wrap.append($row).append($error).append($result);
        $field.after($wrap);

        var inflightController = null;

        $button.on('click', function () {
            var rawVat = $field.find('input[name="taxvat"]').val()
                || $('input[name="taxvat"]').first().val()
                || '';
            var split = deriveCountry(rawVat, opts.fallbackCountry);

            $error.hide().text('');
            $result.hide().empty();

            if (!split.country) {
                $error.text($t('No country prefix in the VAT number, and no default country is configured.')).show();
                return;
            }
            if (!split.vat) {
                $error.text($t('Please enter a VAT number to validate.')).show();
                return;
            }

            if (inflightController) {
                try { inflightController.abort(); } catch (e) { /* noop */ }
            }
            var controller = (typeof AbortController !== 'undefined') ? new AbortController() : null;
            inflightController = controller;

            $button.prop('disabled', true).text($t('Checking…'));

            var url = urlBuilder.build(
                'rest/V1/byte8-vat-validator/validate/'
                + encodeURIComponent(split.country)
                + '/'
                + encodeURIComponent(split.vat)
            );

            var fetchOpts = {
                method: 'GET',
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json' }
            };
            if (controller) { fetchOpts.signal = controller.signal; }

            fetch(url, fetchOpts)
                .then(function (response) {
                    return response.json().then(function (body) {
                        return { ok: response.ok, body: body };
                    });
                })
                .then(function (envelope) {
                    if (!envelope.ok) {
                        $error.text((envelope.body && envelope.body.message) || $t('VAT validation request failed.')).show();
                        return;
                    }
                    renderResult($result, envelope.body);
                })
                .catch(function (e) {
                    if (e && e.name === 'AbortError') { return; }
                    $error.text($t('VAT validation request failed: ') + (e && e.message ? e.message : '')).show();
                })
                .finally(function () {
                    $button.prop('disabled', false).text($t('Validate VAT Number'));
                    if (inflightController === controller) {
                        inflightController = null;
                    }
                });
        });
    }

    function renderResult($result, body) {
        var status = body && body.status ? body.status : '';
        var statusLabel = STATUS_LABEL[status] || status;
        var statusClass = STATUS_CLASS[status] || '';
        var sourceLabel = body && body.source ? (SOURCE_LABEL[body.source] || body.source) : '';

        var html  = '<div class="byte8-vat-validate__headline">';
        html     += '<strong>' + escape($t(statusLabel)) + '</strong>';
        if (body.country_code && body.vat_number) {
            html += ' <span class="byte8-vat-validate__number">' + escape(body.country_code + body.vat_number) + '</span>';
        }
        if (sourceLabel) {
            html += ' <span class="byte8-vat-validate__source">· ' + escape(sourceLabel) + '</span>';
        }
        html     += '</div>';

        if (body.name) {
            html += '<div class="byte8-vat-validate__name">' + escape(body.name) + '</div>';
        }
        if (body.address) {
            html += '<div class="byte8-vat-validate__address">' + escape(body.address) + '</div>';
        }
        if (body.request_identifier) {
            html += '<div class="byte8-vat-validate__ref">' + escape($t('Reference')) + ': <code>' + escape(body.request_identifier) + '</code></div>';
        }
        if (status === STATUS_INVALID && body.message) {
            html += '<div class="byte8-vat-validate__hint">' + escape(body.message) + '</div>';
        }
        if (status === STATUS_UNAVAILABLE) {
            html += '<div class="byte8-vat-validate__hint">'
                + escape($t('The validation service is temporarily unavailable. You can still proceed; we will revalidate in the background.'))
                + '</div>';
        }

        $result
            .removeClass('byte8-vat-status--valid byte8-vat-status--invalid byte8-vat-status--unavailable')
            .addClass(statusClass)
            .html(html)
            .show();
    }

    return function (config) {
        var fallbackCountry = (config && config.fallbackCountry) || '';

        $(function () {
            var $vatInput = $('input[name="taxvat"]').first();
            if (!$vatInput.length) { return; }

            var $vatField = $vatInput.closest('.field');
            if (!$vatField.length) { $vatField = $vatInput.parent(); }

            // Idempotent: don't double-render if the script fires twice.
            if ($vatField.next('.byte8-vat-validate').length) { return; }

            render($vatField, {
                $vatField: $vatField,
                fallbackCountry: fallbackCountry
            });
        });
    };
});
