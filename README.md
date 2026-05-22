# digital-nature/licence-verifier

PHP client for the [Digital Nature](https://digital-nature.co.uk) licence verification API. Requires PHP 7.4+.

## Installation

```bash
composer require digital-nature/licence-verifier
```

You also need a [PSR-18](https://www.php-fig.org/psr/psr-18/) HTTP client and [PSR-17](https://www.php-fig.org/psr/psr-17/) factories. With Guzzle:

```bash
composer require guzzlehttp/guzzle nyholm/psr7
```

## Usage

```php
use DigitalNature\LicenceVerifier\LicenceVerifier;
use GuzzleHttp\Client;
use Nyholm\Psr7\Factory\Psr17Factory;

$factory  = new Psr17Factory();
$verifier = new LicenceVerifier(
    'https://verify.software.digital-nature.co.uk',
    new Client(),   // PSR-18 client
    $factory,       // PSR-17 request factory
    $factory,       // PSR-17 stream factory
);

// Check a licence is valid
$result = $verifier->verify('XXXX-XXXX-XXXX-XXXX');
// $result->valid, ->licenceKey, ->productSlug, ->status, ->expiresAt

// Activate a domain
$activation = $verifier->activate('XXXX-XXXX-XXXX-XXXX', 'example.com');
// $activation->activated, ->domain, ->domainType, ->activationsUsed, ->activationLimit

// Deactivate a domain
$verifier->deactivate('XXXX-XXXX-XXXX-XXXX', 'example.com');

// Get full licence info
$info = $verifier->info('XXXX-XXXX-XXXX-XXXX');
// $info->licenceKey, ->productSlug, ->status, ->activationsUsed, ->activationLimit, ->domains[]
```

## Options

The fifth constructor argument is `$cacheTtl` in milliseconds (default `30000`). Responses from `verify()` and `info()` are cached in-process for the duration of the request. Set to `0` to disable.

> **Note:** PHP-FPM processes are request-scoped, so the cache only persists within a single HTTP request. It avoids redundant calls when `verify()` is called multiple times in one execution.

## Error handling

All methods throw typed exceptions that extend `LicenceVerifierException`:

```php
use DigitalNature\LicenceVerifier\Exception\ActivationLimitReachedException;
use DigitalNature\LicenceVerifier\Exception\DomainAlreadyActiveException;
use DigitalNature\LicenceVerifier\Exception\LicenceExpiredException;
use DigitalNature\LicenceVerifier\Exception\LicenceInactiveException;
use DigitalNature\LicenceVerifier\Exception\LicenceNotFoundException;
use DigitalNature\LicenceVerifier\Exception\LicenceVerifierException;

try {
    $verifier->activate($key, $domain);
} catch (ActivationLimitReachedException $e) {
    // limit reached
} catch (LicenceNotFoundException $e) {
    // key doesn't exist
} catch (LicenceVerifierException $e) {
    // catch-all
}
```

## Requirements

- PHP 7.4 or later
- PSR-18 HTTP client
- PSR-17 request and stream factories
