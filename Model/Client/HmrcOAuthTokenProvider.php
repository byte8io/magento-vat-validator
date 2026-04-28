<?php
/**
 * Copyright © Byte8 Ltd. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Byte8\VatValidator\Model\Client;

use Byte8\VatValidator\Model\Config;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\HTTP\Client\CurlFactory;
use Psr\Log\LoggerInterface;

/**
 * Fetches and caches HMRC OAuth 2.0 client_credentials access tokens.
 *
 * HMRC application-restricted endpoints (e.g. "Check a UK VAT number" v2.0)
 * require a short-lived bearer token obtained from /oauth/token. We cache
 * the token in Magento's default cache for slightly less than its returned
 * expires_in, so checkout requests never wait on the token endpoint.
 */
class HmrcOAuthTokenProvider
{
    private const CACHE_KEY_PREFIX = 'byte8_vat_validator_hmrc_oauth_';
    private const CACHE_TAG = 'BYTE8_VAT_VALIDATOR_HMRC_OAUTH';
    private const SAFETY_MARGIN_SECONDS = 60;
    private const SCOPE = 'read:vat';

    public function __construct(
        private readonly Config $config,
        private readonly CurlFactory $curlFactory,
        private readonly CacheInterface $cache,
        private readonly LoggerInterface $logger
    ) {
    }

    public function getAccessToken(?int $storeId = null): ?string
    {
        $clientId = $this->config->getHmrcClientId($storeId);
        $clientSecret = $this->config->getHmrcClientSecret($storeId);
        if ($clientId === null || $clientSecret === null) {
            return null;
        }

        $cacheKey = self::CACHE_KEY_PREFIX . sha1($clientId);
        $cached = $this->cache->load($cacheKey);
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $token = $this->fetchToken($clientId, $clientSecret, $storeId);
        if ($token === null) {
            return null;
        }

        $lifetime = max(self::SAFETY_MARGIN_SECONDS, $token['expires_in'] - self::SAFETY_MARGIN_SECONDS);
        $this->cache->save($token['access_token'], $cacheKey, [self::CACHE_TAG], $lifetime);

        return $token['access_token'];
    }

    /**
     * @return array{access_token:string,expires_in:int}|null
     */
    private function fetchToken(string $clientId, string $clientSecret, ?int $storeId): ?array
    {
        $endpoint = $this->config->getHmrcTokenEndpoint($storeId);
        if ($endpoint === '') {
            $this->logger->error('HMRC token endpoint is not configured.');

            return null;
        }

        try {
            $curl = $this->curlFactory->create();
            $curl->setTimeout($this->config->getTimeout($storeId));
            $curl->setOption(CURLOPT_CONNECTTIMEOUT, $this->config->getConnectTimeout($storeId));
            $curl->addHeader('Accept', 'application/json');
            $curl->addHeader('Content-Type', 'application/x-www-form-urlencoded');
            $curl->post($endpoint, http_build_query([
                'grant_type' => 'client_credentials',
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'scope' => self::SCOPE,
            ]));

            $status = $curl->getStatus();
            $body = (string) $curl->getBody();

            if ($status < 200 || $status >= 300) {
                $this->logger->error(sprintf(
                    'HMRC OAuth token request failed: HTTP %d. Verify client_id/client_secret and that the application is subscribed to "Check a UK VAT number" v2.0.',
                    $status
                ));

                return null;
            }

            $data = json_decode($body, true);
            if (!is_array($data) || empty($data['access_token']) || !isset($data['expires_in'])) {
                $this->logger->error('HMRC OAuth token response was malformed.');

                return null;
            }

            return [
                'access_token' => (string) $data['access_token'],
                'expires_in' => (int) $data['expires_in'],
            ];
        } catch (\Throwable $e) {
            $this->logger->error('HMRC OAuth token fetch error: ' . $e->getMessage());

            return null;
        }
    }
}
