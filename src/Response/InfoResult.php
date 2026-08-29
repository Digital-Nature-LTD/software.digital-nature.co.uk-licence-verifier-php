<?php

declare(strict_types=1);

namespace DigitalNature\LicenceVerifier\Response;

final class InfoResult
{
    public string $licenceKey;
    public string $productSlug;
    public string $status;
    public ?string $expiresAt;
    public ?int $activationLimit;
    public int $activationsUsed;
    /** @var LicenceDomain[] */
    public array $domains;
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
     * @param LicenceDomain[] $domains
     * @param string[] $addons
     */
    public function __construct(
        string $licenceKey,
        string $productSlug,
        string $status,
        ?string $expiresAt,
        ?int $activationLimit,
        int $activationsUsed,
        array $domains,
        ?string $package = null,
        array $addons = []
    ) {
        $this->licenceKey = $licenceKey;
        $this->productSlug = $productSlug;
        $this->status = $status;
        $this->expiresAt = $expiresAt;
        $this->activationLimit = $activationLimit;
        $this->activationsUsed = $activationsUsed;
        $this->domains = $domains;
        $this->package = $package;
        $this->addons = $addons;
    }
}
