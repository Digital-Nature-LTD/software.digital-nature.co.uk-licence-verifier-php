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

## WordPress plugin auto-updates

`WordPress\Updater` hooks a plugin into the WordPress update system so that new versions published to the Digital Nature store appear in **Dashboard → Updates** and can be installed with one click.

### Installation

Include the library in your plugin's `composer.json` alongside a PSR-18 client:

```bash
composer require digital-nature/licence-verifier guzzlehttp/guzzle nyholm/psr7
```

Load Composer's autoloader from your plugin's main file (if not already done by the plugin framework):

```php
require_once __DIR__ . '/vendor/autoload.php';
```

### Setup

Instantiate `Updater` once, early in your plugin's boot sequence (e.g. directly in the main plugin file or on the `init` hook):

```php
use DigitalNature\LicenceVerifier\LicenceVerifier;
use DigitalNature\LicenceVerifier\WordPress\Updater;
use GuzzleHttp\Client;
use Nyholm\Psr7\Factory\Psr17Factory;

$factory  = new Psr17Factory();
$verifier = new LicenceVerifier(
    'https://verify.software.digital-nature.co.uk',
    new Client(),
    $factory,
    $factory,
);

new Updater(
    __FILE__,                                 // absolute path to the plugin's main file
    'my-plugin/my-plugin.php',                // plugin slug (directory/filename.php)
    get_option('my_plugin_licence_key', ''),  // stored licence key
    $verifier,
    [
        'requires_php' => '7.4',  // minimum PHP version shown in the update UI
        'requires_wp'  => '6.0',  // minimum WordPress version
        'tested'       => '6.8',  // tested up to (shown in the update UI)
        'cache_hours'  => 12,     // how long to cache the update check (default: 12)
    ]
);
```

The constructor registers all required WordPress hooks automatically — no further wiring is needed.

### How it works

| WordPress hook | What it does |
|---|---|
| `pre_set_site_transient_update_plugins` | Checks for a newer version and injects it into the WP update transient |
| `plugins_api` | Supplies plugin name, version, and changelog for the "View version details" modal |
| `upgrader_process_complete` | Clears the cached update info after the plugin is updated |

Update checks are cached in WordPress transients for `cache_hours` to avoid hitting the API on every page load. The download URL passed to WordPress never expires — the verify service validates the licence key on each download request.

Errors (invalid licence, expired licence, network failure) are silently swallowed so they never break the WordPress admin.

## Requirements

- PHP 7.4 or later
- PSR-18 HTTP client
- PSR-17 request and stream factories
