<?php

declare(strict_types=1);

namespace DigitalNature\LicenceVerifier\Response;

class UpdateResult
{
    public bool $updateAvailable;
    public ?string $latestVersion;
    public ?string $downloadToken;
    /** Token-based download URL. Valid for 5 minutes. */
    public ?string $downloadUrl;
    /** Stable download URL using the licence key — no expiry. Use this as the WordPress package URL. */
    public ?string $stableDownloadUrl;
    public ?string $productName;
    public ?string $releaseNotes;

    public function __construct(
        bool $updateAvailable,
        ?string $latestVersion,
        ?string $downloadToken,
        ?string $downloadUrl,
        ?string $stableDownloadUrl = null,
        ?string $productName = null,
        ?string $releaseNotes = null
    ) {
        $this->updateAvailable   = $updateAvailable;
        $this->latestVersion     = $latestVersion;
        $this->downloadToken     = $downloadToken;
        $this->downloadUrl       = $downloadUrl;
        $this->stableDownloadUrl = $stableDownloadUrl;
        $this->productName       = $productName;
        $this->releaseNotes      = $releaseNotes;
    }
}
