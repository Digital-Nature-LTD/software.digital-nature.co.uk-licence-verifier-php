<?php

declare(strict_types=1);

namespace DigitalNature\LicenceVerifier\Response;

/**
 * One add-on held on a FREE TRIAL, and when it stops being granted.
 *
 * `addons` on the result already leaves out a trial that has ended, so a plugin
 * that only gates on `addons` still enforces the end. Read this to show how
 * many days are left.
 */
final class TrialGrant
{
    /** The add-on slug, as it appears in `addons`. */
    public string $addon;
    /** ISO 8601. From this moment the add-on is no longer granted. */
    public string $endsAt;

    public function __construct(string $addon, string $endsAt)
    {
        $this->addon = $addon;
        $this->endsAt = $endsAt;
    }
}
