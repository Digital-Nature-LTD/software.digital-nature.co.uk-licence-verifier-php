<?php

declare(strict_types=1);

namespace DigitalNature\LicenceVerifier\Response;

class UpdateResult
{
    public bool $updateAvailable;
    public ?string $latestVersion;
    public ?string $downloadToken;
    /** Pre-constructed download URL. Pass directly to WordPress's package field or trigger a download. Valid for 5 minutes. */
    public ?string $downloadUrl;

    public function __construct(
        bool $updateAvailable,
        ?string $latestVersion,
        ?string $downloadToken,
        ?string $downloadUrl
    ) {
        $this->updateAvailable = $updateAvailable;
        $this->latestVersion   = $latestVersion;
        $this->downloadToken   = $downloadToken;
        $this->downloadUrl     = $downloadUrl;
    }
}
