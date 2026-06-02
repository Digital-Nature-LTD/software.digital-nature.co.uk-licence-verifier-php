<?php

declare(strict_types=1);

namespace DigitalNature\LicenceVerifier\Tests;

use DigitalNature\LicenceVerifier\Exception\ActivationLimitReachedException;
use DigitalNature\LicenceVerifier\Exception\DomainAlreadyActiveException;
use DigitalNature\LicenceVerifier\Exception\LicenceExpiredException;
use DigitalNature\LicenceVerifier\Exception\LicenceInactiveException;
use DigitalNature\LicenceVerifier\Exception\LicenceNotFoundException;
use DigitalNature\LicenceVerifier\LicenceVerifier;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\TestCase;

class LicenceVerifierTest extends TestCase
{
    private Psr17Factory $factory;

    protected function setUp(): void
    {
        $this->factory = new Psr17Factory();
    }

    /**
     * @param mixed $body
     */
    private function makeClient(int $status, $body, int $cacheTtl = 0): LicenceVerifier
    {
        return new LicenceVerifier(
            'https://verify.example.com',
            MockHttpClient::responding($status, $body),
            $this->factory,
            $this->factory,
            $cacheTtl
        );
    }

    // ── verify ────────────────────────────────────────────────────────────────

    public function testVerifyReturnsMappedResult(): void
    {
        $client = $this->makeClient(200, [
            'valid' => true, 'licence_key' => 'ABC-123', 'product_slug' => 'my-plugin',
            'status' => 'active', 'expires_at' => null,
        ]);

        $result = $client->verify('ABC-123');

        $this->assertTrue($result->valid);
        $this->assertSame('ABC-123', $result->licenceKey);
        $this->assertSame('my-plugin', $result->productSlug);
        $this->assertSame('active', $result->status);
        $this->assertNull($result->expiresAt);
    }

    public function testVerifyThrowsLicenceNotFoundOn404(): void
    {
        $this->expectException(LicenceNotFoundException::class);
        $this->makeClient(404, ['error' => 'Licence not found'])->verify('BAD');
    }

    public function testVerifyThrowsLicenceExpiredOn422Expired(): void
    {
        $this->expectException(LicenceExpiredException::class);
        $this->makeClient(422, ['error' => 'Licence has expired'])->verify('KEY');
    }

    public function testVerifyThrowsLicenceInactiveOn422Suspended(): void
    {
        $this->expectException(LicenceInactiveException::class);
        $this->makeClient(422, ['error' => 'Licence is suspended'])->verify('KEY');
    }

    // ── activate ──────────────────────────────────────────────────────────────

    public function testActivateReturnsMappedResult(): void
    {
        $client = $this->makeClient(200, [
            'activated' => true, 'domain' => 'example.com', 'domain_type' => 'production',
            'activations_used' => 1, 'activation_limit' => 2,
        ]);

        $result = $client->activate('KEY', 'example.com');

        $this->assertTrue($result->activated);
        $this->assertSame('example.com', $result->domain);
        $this->assertSame('production', $result->domainType);
        $this->assertSame(1, $result->activationsUsed);
        $this->assertSame(2, $result->activationLimit);
    }

    public function testActivateThrowsActivationLimitReachedOn422Limit(): void
    {
        $this->expectException(ActivationLimitReachedException::class);
        $this->makeClient(422, ['error' => 'Activation limit reached (2)'])->activate('KEY', 'site3.com');
    }

    public function testActivateThrowsDomainAlreadyActiveOn409(): void
    {
        $this->expectException(DomainAlreadyActiveException::class);
        $this->makeClient(409, ['error' => 'Domain already activated on this licence'])->activate('KEY', 'example.com');
    }

    public function testActivateThrowsLicenceNotFoundOn404(): void
    {
        $this->expectException(LicenceNotFoundException::class);
        $this->makeClient(404, ['error' => 'Licence not found'])->activate('BAD', 'example.com');
    }

    // ── deactivate ────────────────────────────────────────────────────────────

    public function testDeactivateReturnsMappedResult(): void
    {
        $client = $this->makeClient(200, ['deactivated' => true, 'domain' => 'example.com']);
        $result = $client->deactivate('KEY', 'example.com');

        $this->assertTrue($result->deactivated);
        $this->assertSame('example.com', $result->domain);
    }

    public function testDeactivateThrowsLicenceNotFoundOn404(): void
    {
        $this->expectException(LicenceNotFoundException::class);
        $this->makeClient(404, ['error' => 'Licence not found'])->deactivate('BAD', 'example.com');
    }

    // ── info ──────────────────────────────────────────────────────────────────

    public function testInfoReturnsMappedResultWithDomains(): void
    {
        $client = $this->makeClient(200, [
            'licence_key' => 'ABC-123', 'product_slug' => 'my-plugin', 'status' => 'active',
            'expires_at' => null, 'activation_limit' => 2, 'activations_used' => 1,
            'domains' => [
                ['domain' => 'example.com', 'domain_type' => 'production', 'activated_at' => '2026-01-01T00:00:00Z'],
            ],
        ]);

        $result = $client->info('ABC-123');

        $this->assertSame('ABC-123', $result->licenceKey);
        $this->assertSame(1, $result->activationsUsed);
        $this->assertCount(1, $result->domains);
        $this->assertSame('example.com', $result->domains[0]->domain);
        $this->assertSame('production', $result->domains[0]->domainType);
    }

    public function testInfoThrowsLicenceNotFoundOn404(): void
    {
        $this->expectException(LicenceNotFoundException::class);
        $this->makeClient(404, ['error' => 'Licence not found'])->info('BAD');
    }

    // ── checkForUpdate ────────────────────────────────────────────────────────

    public function testCheckForUpdateReturnsMappedResult(): void
    {
        $client = $this->makeClient(200, [
            'update_available' => true,
            'latest_version'   => '2.0.0',
            'download_token'   => 'tok_abc123',
        ]);

        $result = $client->checkForUpdate('KEY');

        $this->assertTrue($result->updateAvailable);
        $this->assertSame('2.0.0', $result->latestVersion);
        $this->assertSame('tok_abc123', $result->downloadToken);
        $this->assertSame('https://verify.example.com/download?token=tok_abc123', $result->downloadUrl);
    }

    public function testCheckForUpdateReturnsNullDownloadWhenNoToken(): void
    {
        $client = $this->makeClient(200, [
            'update_available' => false,
            'latest_version'   => null,
            'download_token'   => null,
        ]);

        $result = $client->checkForUpdate('KEY');

        $this->assertFalse($result->updateAvailable);
        $this->assertNull($result->latestVersion);
        $this->assertNull($result->downloadToken);
        $this->assertNull($result->downloadUrl);
    }

    public function testCheckForUpdatePassesCurrentVersionInQueryString(): void
    {
        $httpClient = MockHttpClient::responding(200, [
            'update_available' => false, 'latest_version' => '1.0.0', 'download_token' => null,
        ]);
        $verifier = new LicenceVerifier(
            'https://verify.example.com', $httpClient, $this->factory, $this->factory
        );

        $verifier->checkForUpdate('MY-KEY', '1.0.0');

        $this->assertCount(1, $httpClient->requests);
        $this->assertSame('GET', $httpClient->requests[0]['method']);
        $this->assertStringContainsString('licence_key=MY-KEY', $httpClient->requests[0]['url']);
        $this->assertStringContainsString('current_version=1.0.0', $httpClient->requests[0]['url']);
    }

    public function testCheckForUpdateThrowsLicenceNotFoundOn404(): void
    {
        $this->expectException(LicenceNotFoundException::class);
        $this->makeClient(404, ['error' => 'Licence not found'])->checkForUpdate('BAD');
    }

    public function testCheckForUpdateThrowsLicenceInactiveOn422(): void
    {
        $this->expectException(LicenceInactiveException::class);
        $this->makeClient(422, ['error' => 'Licence is suspended'])->checkForUpdate('KEY');
    }

    // ── caching ───────────────────────────────────────────────────────────────

    public function testVerifyServesCachedResultOnSecondCall(): void
    {
        $httpClient = MockHttpClient::responding(200, [
            'valid' => true, 'licence_key' => 'KEY', 'product_slug' => 'plugin',
            'status' => 'active', 'expires_at' => null,
        ]);
        $verifier = new LicenceVerifier(
            'https://verify.example.com', $httpClient, $this->factory, $this->factory, 60000
        );

        $verifier->verify('KEY');
        $verifier->verify('KEY');

        $this->assertCount(1, $httpClient->requests);
    }

    public function testActivateInvalidatesVerifyCache(): void
    {
        $verifyBody   = ['valid' => true, 'licence_key' => 'KEY', 'product_slug' => 'plugin', 'status' => 'active', 'expires_at' => null];
        $activateBody = ['activated' => true, 'domain' => 'example.com', 'domain_type' => 'production', 'activations_used' => 1, 'activation_limit' => 2];

        $factory  = new \Nyholm\Psr7\Factory\Psr17Factory();
        $makeRes  = static function (int $status, $body) use ($factory): \Psr\Http\Message\ResponseInterface {
            return new \Nyholm\Psr7\Response($status, ['Content-Type' => 'application/json'], (string) json_encode($body));
        };

        $httpClient = new MockHttpClient(
            $makeRes(200, $verifyBody),   // call 1: verify (will be cached)
            $makeRes(200, $activateBody), // call 2: activate (invalidates cache)
            $makeRes(200, $verifyBody)    // call 3: verify (cache miss — must re-fetch)
        );

        $verifier = new LicenceVerifier(
            'https://verify.example.com', $httpClient, $this->factory, $this->factory, 60000
        );

        $verifier->verify('KEY');
        $verifier->activate('KEY', 'example.com');
        $verifier->verify('KEY');

        $verifyCalls = array_filter($httpClient->requests, static function (array $r): bool {
            return parse_url($r['url'], PHP_URL_PATH) === '/verify';
        });

        $this->assertCount(2, $verifyCalls);
    }

    public function testCachingDisabledWhenTtlIsZero(): void
    {
        $httpClient = MockHttpClient::responding(200, [
            'valid' => true, 'licence_key' => 'KEY', 'product_slug' => 'plugin',
            'status' => 'active', 'expires_at' => null,
        ]);
        $verifier = new LicenceVerifier(
            'https://verify.example.com', $httpClient, $this->factory, $this->factory, 0
        );

        $verifier->verify('KEY');
        $verifier->verify('KEY');

        $this->assertCount(2, $httpClient->requests);
    }
}
