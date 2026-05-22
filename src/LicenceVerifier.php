<?php

declare(strict_types=1);

namespace DigitalNature\LicenceVerifier;

use DigitalNature\LicenceVerifier\Exception\ActivationLimitReachedException;
use DigitalNature\LicenceVerifier\Exception\DomainAlreadyActiveException;
use DigitalNature\LicenceVerifier\Exception\LicenceExpiredException;
use DigitalNature\LicenceVerifier\Exception\LicenceInactiveException;
use DigitalNature\LicenceVerifier\Exception\LicenceNotFoundException;
use DigitalNature\LicenceVerifier\Exception\LicenceVerifierException;
use DigitalNature\LicenceVerifier\Response\ActivateResult;
use DigitalNature\LicenceVerifier\Response\DeactivateResult;
use DigitalNature\LicenceVerifier\Response\InfoResult;
use DigitalNature\LicenceVerifier\Response\LicenceDomain;
use DigitalNature\LicenceVerifier\Response\VerifyResult;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

class LicenceVerifier
{
    private string $baseUrl;
    private ClientInterface $httpClient;
    private RequestFactoryInterface $requestFactory;
    private StreamFactoryInterface $streamFactory;
    private int $cacheTtl;

    /** @var array<string, array{value: array<mixed>, expires: float}> */
    private array $cache = [];

    /**
     * @param string                  $baseUrl        Base URL of the verify service
     * @param ClientInterface         $httpClient     PSR-18 HTTP client
     * @param RequestFactoryInterface $requestFactory PSR-17 request factory
     * @param StreamFactoryInterface  $streamFactory  PSR-17 stream factory
     * @param int                     $cacheTtl       Cache TTL in milliseconds for verify/info (0 = disabled)
     */
    public function __construct(
        string $baseUrl,
        ClientInterface $httpClient,
        RequestFactoryInterface $requestFactory,
        StreamFactoryInterface $streamFactory,
        int $cacheTtl = 30000
    ) {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->httpClient = $httpClient;
        $this->requestFactory = $requestFactory;
        $this->streamFactory = $streamFactory;
        $this->cacheTtl = $cacheTtl;
    }

    public function verify(string $licenceKey): VerifyResult
    {
        $cacheKey = 'verify:' . $licenceKey;
        $cached = $this->getCache($cacheKey);

        if ($cached !== null) {
            return new VerifyResult(
                (bool) $cached['valid'],
                (string) $cached['licence_key'],
                (string) $cached['product_slug'],
                (string) $cached['status'],
                isset($cached['expires_at']) ? (string) $cached['expires_at'] : null
            );
        }

        $data = $this->post('/verify', ['licence_key' => $licenceKey]);
        $this->setCache($cacheKey, $data);

        return new VerifyResult(
            (bool) $data['valid'],
            (string) $data['licence_key'],
            (string) $data['product_slug'],
            (string) $data['status'],
            isset($data['expires_at']) ? (string) $data['expires_at'] : null
        );
    }

    public function activate(string $licenceKey, string $domain): ActivateResult
    {
        $data = $this->post('/activate', ['licence_key' => $licenceKey, 'domain' => $domain]);
        $this->invalidate('verify:' . $licenceKey, 'info:' . $licenceKey);

        return new ActivateResult(
            (bool) $data['activated'],
            (string) $data['domain'],
            (string) $data['domain_type'],
            (int) $data['activations_used'],
            isset($data['activation_limit']) ? (int) $data['activation_limit'] : null
        );
    }

    public function deactivate(string $licenceKey, string $domain): DeactivateResult
    {
        $data = $this->post('/deactivate', ['licence_key' => $licenceKey, 'domain' => $domain]);
        $this->invalidate('verify:' . $licenceKey, 'info:' . $licenceKey);

        return new DeactivateResult((bool) $data['deactivated'], (string) $data['domain']);
    }

    public function info(string $licenceKey): InfoResult
    {
        $cacheKey = 'info:' . $licenceKey;
        $cached = $this->getCache($cacheKey);

        if ($cached !== null) {
            return $this->buildInfoResult($cached);
        }

        $data = $this->get('/info?licence_key=' . urlencode($licenceKey));
        $this->setCache($cacheKey, $data);

        return $this->buildInfoResult($data);
    }

    /** @param array<mixed> $data */
    private function buildInfoResult(array $data): InfoResult
    {
        $domains = [];
        foreach ((array) ($data['domains'] ?? []) as $d) {
            $d = (array) $d;
            $domains[] = new LicenceDomain(
                (string) $d['domain'],
                (string) $d['domain_type'],
                (string) $d['activated_at']
            );
        }

        return new InfoResult(
            (string) $data['licence_key'],
            (string) $data['product_slug'],
            (string) $data['status'],
            isset($data['expires_at']) ? (string) $data['expires_at'] : null,
            isset($data['activation_limit']) ? (int) $data['activation_limit'] : null,
            (int) $data['activations_used'],
            $domains
        );
    }

    /**
     * @param array<string, mixed> $body
     * @return array<mixed>
     */
    private function post(string $path, array $body): array
    {
        $stream = $this->streamFactory->createStream((string) json_encode($body));
        $request = $this->requestFactory->createRequest('POST', $this->baseUrl . $path)
            ->withHeader('Content-Type', 'application/json')
            ->withBody($stream);

        $response = $this->httpClient->sendRequest($request);
        $data = (array) json_decode((string) $response->getBody(), true);

        if ($response->getStatusCode() >= 400) {
            $this->throwError($response->getStatusCode(), $data);
        }

        return $data;
    }

    /** @return array<mixed> */
    private function get(string $path): array
    {
        $request = $this->requestFactory->createRequest('GET', $this->baseUrl . $path);
        $response = $this->httpClient->sendRequest($request);
        $data = (array) json_decode((string) $response->getBody(), true);

        if ($response->getStatusCode() >= 400) {
            $this->throwError($response->getStatusCode(), $data);
        }

        return $data;
    }

    /** @param array<mixed> $data */
    private function throwError(int $status, array $data): void
    {
        $message = isset($data['error']) ? (string) $data['error'] : 'Unknown error';

        if ($status === 404) {
            throw new LicenceNotFoundException();
        }
        if ($status === 409) {
            throw new DomainAlreadyActiveException($message);
        }
        if ($status === 422) {
            if (stripos($message, 'expired') !== false) {
                throw new LicenceExpiredException();
            }
            if (stripos($message, 'limit') !== false) {
                throw new ActivationLimitReachedException($message);
            }
            throw new LicenceInactiveException($message);
        }

        throw new LicenceVerifierException($message, $status);
    }

    /** @return array<mixed>|null */
    private function getCache(string $key): ?array
    {
        if (!isset($this->cache[$key])) {
            return null;
        }
        if ((microtime(true) * 1000) > $this->cache[$key]['expires']) {
            unset($this->cache[$key]);
            return null;
        }
        return $this->cache[$key]['value'];
    }

    /** @param array<mixed> $value */
    private function setCache(string $key, array $value): void
    {
        if ($this->cacheTtl > 0) {
            $this->cache[$key] = [
                'value'   => $value,
                'expires' => microtime(true) * 1000 + $this->cacheTtl,
            ];
        }
    }

    private function invalidate(string ...$keys): void
    {
        foreach ($keys as $key) {
            unset($this->cache[$key]);
        }
    }
}
