<?php

declare(strict_types=1);

namespace DigitalNature\LicenceVerifier\WordPress;

use DigitalNature\LicenceVerifier\Exception\LicenceVerifierException;
use DigitalNature\LicenceVerifier\LicenceVerifier;

/**
 * Hooks a plugin into the WordPress automatic-update system using the
 * Digital Nature licensing and verify service.
 *
 * Usage (in the plugin's main file):
 *
 *   $verifier = new \DigitalNature\LicenceVerifier\LicenceVerifier(
 *       'https://verify.software.digital-nature.co.uk',
 *       $psrHttpClient, $psrRequestFactory, $psrStreamFactory
 *   );
 *
 *   new \DigitalNature\LicenceVerifier\WordPress\Updater(
 *       __FILE__,
 *       'my-plugin/my-plugin.php',
 *       get_option('my_plugin_licence_key'),
 *       $verifier,
 *       ['requires_php' => '7.4', 'tested' => '6.8']
 *   );
 */
class Updater
{
    private string $pluginFile;
    private string $slug;
    private string $licenceKey;
    private LicenceVerifier $verifier;
    private string $cacheKey;
    private int    $cacheHours;
    private string $requiresPhp;
    private string $tested;
    private string $requiresWp;

    public function __construct(
        string $pluginFile,
        string $slug,
        string $licenceKey,
        LicenceVerifier $verifier,
        array  $options = []
    ) {
        $this->pluginFile  = $pluginFile;
        $this->slug        = $slug;
        $this->licenceKey  = $licenceKey;
        $this->verifier    = $verifier;
        $this->cacheKey    = 'dn_updater_' . md5($licenceKey . $slug);
        $this->cacheHours  = (int) ($options['cache_hours'] ?? 12);
        $this->requiresPhp = (string) ($options['requires_php'] ?? '7.4');
        $this->tested      = (string) ($options['tested'] ?? '');
        $this->requiresWp  = (string) ($options['requires_wp'] ?? '5.0');

        add_filter('pre_set_site_transient_update_plugins', [$this, 'checkUpdate']);
        add_filter('plugins_api', [$this, 'pluginInfo'], 20, 3);
        add_action('upgrader_process_complete', [$this, 'purgeCache'], 10, 2);
    }

    /**
     * Hooked to pre_set_site_transient_update_plugins.
     * Injects update data for this plugin when a newer version is available.
     *
     * @param mixed $transient
     * @return mixed
     */
    public function checkUpdate($transient)
    {
        if (empty($transient->checked)) {
            return $transient;
        }

        $installedVersion = $transient->checked[$this->slug] ?? '';
        $result = $this->fetchUpdateInfo($installedVersion);

        if ($result === null || !$result->updateAvailable || $result->latestVersion === null) {
            return $transient;
        }

        $stableUrl = $result->stableDownloadUrl;

        $update = (object) [
            'id'           => $this->slug,
            'slug'         => dirname($this->slug),
            'plugin'       => $this->slug,
            'new_version'  => $result->latestVersion,
            'url'          => $stableUrl,
            'package'      => $stableUrl,
            'icons'        => [],
            'banners'      => [],
            'banners_rtl'  => [],
            'requires_php' => $this->requiresPhp,
            'requires'     => $this->requiresWp,
        ];

        if ($this->tested !== '') {
            $update->tested = $this->tested;
        }

        $transient->response[$this->slug] = $update;

        return $transient;
    }

    /**
     * Hooked to plugins_api.
     * Provides plugin information for the "View version details" modal.
     *
     * @param mixed  $result
     * @param string $action
     * @param mixed  $args
     * @return mixed
     */
    public function pluginInfo($result, string $action, $args)
    {
        if ($action !== 'plugin_information') {
            return $result;
        }

        if (!isset($args->slug) || $args->slug !== dirname($this->slug)) {
            return $result;
        }

        $installedVersion = $this->getInstalledVersion();
        $updateResult = $this->fetchUpdateInfo($installedVersion);

        if ($updateResult === null) {
            return $result;
        }

        $pluginData  = get_plugin_data($this->pluginFile, false, false);
        $name        = $updateResult->productName ?? ($pluginData['Name'] ?? $this->slug);

        $obj                = new \stdClass();
        $obj->name          = $name;
        $obj->slug          = dirname($this->slug);
        $obj->version       = $updateResult->latestVersion ?? $installedVersion;
        $obj->author        = $pluginData['Author'] ?? '';
        $obj->homepage      = $pluginData['PluginURI'] ?? '';
        $obj->download_link = $updateResult->stableDownloadUrl;
        $obj->requires      = $this->requiresWp;
        $obj->requires_php  = $this->requiresPhp;
        $obj->sections      = [
            'changelog' => $updateResult->releaseNotes ?? '',
        ];

        if ($this->tested !== '') {
            $obj->tested = $this->tested;
        }

        return $obj;
    }

    /**
     * Hooked to upgrader_process_complete.
     * Clears cached update info after this plugin is updated so the next
     * check fetches fresh data.
     *
     * @param mixed $upgrader
     * @param array<string, mixed> $hookExtra
     */
    public function purgeCache($upgrader, array $hookExtra): void
    {
        if (
            isset($hookExtra['type'], $hookExtra['plugins']) &&
            $hookExtra['type'] === 'plugin' &&
            in_array($this->slug, (array) $hookExtra['plugins'], true)
        ) {
            delete_transient($this->cacheKey);
        }
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    /**
     * Returns cached update info, fetching from the API if stale.
     * Returns null on any API error so the rest of WP is unaffected.
     *
     * @return \DigitalNature\LicenceVerifier\Response\UpdateResult|null
     */
    private function fetchUpdateInfo(string $installedVersion)
    {
        $cached = get_transient($this->cacheKey);
        if ($cached !== false && $cached instanceof \DigitalNature\LicenceVerifier\Response\UpdateResult) {
            return $cached;
        }

        try {
            $result = $this->verifier->checkForUpdate($this->licenceKey, $installedVersion ?: null);
        } catch (LicenceVerifierException $e) {
            // Invalid/expired/unknown licence — cache a null sentinel so we don't
            // hammer the API on every page load.
            set_transient($this->cacheKey, null, $this->cacheHours * HOUR_IN_SECONDS);
            return null;
        } catch (\Throwable $e) {
            // Network failure or unexpected error — skip silently, no cache write.
            return null;
        }

        set_transient($this->cacheKey, $result, $this->cacheHours * HOUR_IN_SECONDS);

        return $result;
    }

    private function getInstalledVersion(): string
    {
        if (!function_exists('get_plugin_data')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $data = get_plugin_data($this->pluginFile, false, false);

        return $data['Version'] ?? '';
    }
}
