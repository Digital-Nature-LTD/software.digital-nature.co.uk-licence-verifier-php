<?php

declare(strict_types=1);

namespace DigitalNature\LicenceVerifier\Response;

class UpdateResult
{
    public function __construct(
        public readonly bool $updateAvailable,
        public readonly ?string $latestVersion,
        public readonly ?string $downloadToken,
        /** Pre-constructed download URL. Pass directly to WordPress's package field or trigger a download. Valid for 5 minutes. */
        public readonly ?string $downloadUrl,
    ) {}
}
