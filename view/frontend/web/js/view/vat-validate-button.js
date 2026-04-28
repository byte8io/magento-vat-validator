/**
 * Copyright © Byte8 Ltd. All rights reserved.
 * See LICENSE.txt for license details.
 */

define([
    'jquery',
    'ko',
    'uiComponent',
    'mage/url',
    'mage/translate',
    'Magento_Checkout/js/model/quote'
], function ($, ko, Component, urlBuilder, $t, quote) {
    'use strict';

    var STATUS_VALID = 'valid';
    var STATUS_INVALID = 'invalid';
    var STATUS_UNAVAILABLE = 'unavailable';

    return Component.extend({
        defaults: {
            template: 'Byte8_VatValidator/vat-validate-button',
            addressType: 'shipping',
            isLoading: false,
            result: null,
            errorMessage: null,
            lastChecked: null
        },

        initialize: function () {
            var self = this;
            this._super();
            this.observe(['isLoading', 'result', 'errorMessage', 'lastChecked']);
            this.inflightController = null;

            // Anchor the rendered DOM directly under the vat_id input. The
            // checkout's address fieldset is built asynchronously from EAV
            // attributes, so we retry until the field appears, then keep an
            // eye on quote.shippingAddress changes to re-anchor when the
            // form re-renders (e.g. when the user navigates back to the
            // shipping step from payment).
            this._anchorRetries = 0;
            this._scheduleAnchor();

            quote.shippingAddress.subscribe(function () {
                self._anchorRetries = 0;
                self._scheduleAnchor();
            });

            return this;
        },

        _scheduleAnchor: function () {
            var self = this;
            var attempt = function () {
                if (self._tryAnchor()) {
                    return;
                }
                self._anchorRetries += 1;
                if (self._anchorRetries < 30) { // ~6 seconds total
                    setTimeout(attempt, 200);
                }
            };
            setTimeout(attempt, 100);
        },

        _tryAnchor: function () {
            var $component = $('.byte8-vat-validate').first();
            var $vatInput  = $('input[name="vat_id"]').first();
            if (!$component.length || !$vatInput.length) {
                return false;
            }
            var $vatField = $vatInput.closest('.field');
            if (!$vatField.length) {
                $vatField = $vatInput.parent();
            }
            if ($vatField.next('.byte8-vat-validate').length) {
                return true; // already anchored
            }
            $component.insertAfter($vatField);

            return true;
        },

        readVat: function () {
            // Live form values take precedence — the user may have edited
            // the field after a previous save, so the persisted quote
            // address would otherwise return stale data. We only fall back
            // to the saved quote address when the input is empty (e.g. the
            // user removed the VAT field from the form layout but the
            // address still carries one).
            var liveCountry = $('select[name="country_id"]').first().val() || '';
            var liveVat     = $('input[name="vat_id"]').first().val() || '';

            var country = String(liveCountry).trim();
            var vat     = String(liveVat).trim();

            if (!country || !vat) {
                var address = this.addressType === 'billing'
                    ? quote.billingAddress()
                    : quote.shippingAddress();
                if (!country && address && address.countryId) {
                    country = String(address.countryId).trim();
                }
                if (!vat && address && address.vatId) {
                    vat = String(address.vatId).trim();
                }
            }

            return {
                country: country.toUpperCase(),
                vat: vat
            };
        },

        validate: function () {
            var self = this;
            var input = this.readVat();

            this.errorMessage(null);
            this.result(null);

            if (!input.country) {
                this.errorMessage($t('Please select a country first.'));

                return;
            }

            if (!input.vat) {
                this.errorMessage($t('Please enter a VAT number to validate.'));

                return;
            }

            if (this.inflightController) {
                try { this.inflightController.abort(); } catch (e) { /* noop */ }
            }
            var controller = (typeof AbortController !== 'undefined')
                ? new AbortController()
                : null;
            this.inflightController = controller;

            this.isLoading(true);
            var url = urlBuilder.build(
                'rest/V1/byte8-vat-validator/validate/'
                + encodeURIComponent(input.country)
                + '/'
                + encodeURIComponent(input.vat)
            );

            var fetchOptions = {
                method: 'GET',
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json' }
            };
            if (controller) {
                fetchOptions.signal = controller.signal;
            }

            fetch(url, fetchOptions)
                .then(function (response) {
                    return response.json().then(function (body) {
                        return { ok: response.ok, body: body };
                    });
                })
                .then(function (envelope) {
                    if (!envelope.ok) {
                        var msg = (envelope.body && envelope.body.message)
                            ? envelope.body.message
                            : $t('VAT validation request failed.');
                        self.errorMessage(msg);
                        self.result(null);

                        return;
                    }

                    self.result(envelope.body);
                    self.lastChecked(input.country + input.vat);
                })
                .catch(function (e) {
                    if (e && e.name === 'AbortError') {
                        return;
                    }
                    self.errorMessage($t('VAT validation request failed: ') + (e && e.message ? e.message : ''));
                })
                .finally(function () {
                    self.isLoading(false);
                    if (self.inflightController === controller) {
                        self.inflightController = null;
                    }
                });
        },

        statusLabel: function () {
            var r = this.result();
            if (!r) {
                return null;
            }
            switch (r.status) {
                case STATUS_VALID:    return $t('Valid');
                case STATUS_INVALID:  return $t('Invalid');
                case STATUS_UNAVAILABLE: return $t('Unavailable');
                default: return r.status;
            }
        },

        statusClass: function () {
            var r = this.result();
            if (!r) { return ''; }
            switch (r.status) {
                case STATUS_VALID:    return 'byte8-vat-status--valid';
                case STATUS_INVALID:  return 'byte8-vat-status--invalid';
                case STATUS_UNAVAILABLE: return 'byte8-vat-status--unavailable';
                default: return '';
            }
        },

        sourceLabel: function () {
            var r = this.result();
            if (!r || !r.source) { return ''; }
            switch (r.source) {
                case 'hmrc':    return 'HMRC';
                case 'vies':    return 'VIES';
                case 'uid_che': return 'UID-Register';
                default:        return r.source;
            }
        }
    });
});
