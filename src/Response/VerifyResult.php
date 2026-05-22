<?php

declare(strict_types=1);

namespace DigitalNature\LicenceVerifier\Response;

final class VerifyResult
{
    public bool $valid;
    public string $licenceKey;
    public string $productSlug;
    public string $status;
    public ?string $expiresAt;

    public function __construct(
        bool $valid,
        string $licenceKey,
        string $productSlug,
        string $status,
        ?string $expiresAt
    ) {
        $this->valid = $valid;
        $this->licenceKey = $licenceKey;
        $this->productSlug = $productSlug;
        $this->status = $status;
        $this->expiresAt = $expiresAt;
    }
}
