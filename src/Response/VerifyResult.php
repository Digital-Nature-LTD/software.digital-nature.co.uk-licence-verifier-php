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
    /**
     * The package this licence is on, or null for an ordinary product.
     *
     * Added when packages arrived. A verify service older than packages omits
     * it, so it arrives null rather than being absent — a consumer never has to
     * check whether the field exists.
     */
    public ?string $package;
    /**
     * Every add-on the licence grants — included in its package and bought
     * separately, deduped. Empty for an ordinary product.
     *
     * @var string[]
     */
    public array $addons;

    /**
     * @param string[] $addons
     */
    public function __construct(
        bool $valid,
        string $licenceKey,
        string $productSlug,
        string $status,
        ?string $expiresAt,
        ?string $package = null,
        array $addons = []
    ) {
        $this->valid = $valid;
        $this->licenceKey = $licenceKey;
        $this->productSlug = $productSlug;
        $this->status = $status;
        $this->expiresAt = $expiresAt;
        $this->package = $package;
        $this->addons = $addons;
    }
}
