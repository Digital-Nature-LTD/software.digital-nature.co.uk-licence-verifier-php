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
     * @param LicenceDomain[] $domains
     */
    public function __construct(
        string $licenceKey,
        string $productSlug,
        string $status,
        ?string $expiresAt,
        ?int $activationLimit,
        int $activationsUsed,
        array $domains
    ) {
        $this->licenceKey = $licenceKey;
        $this->productSlug = $productSlug;
        $this->status = $status;
        $this->expiresAt = $expiresAt;
        $this->activationLimit = $activationLimit;
        $this->activationsUsed = $activationsUsed;
        $this->domains = $domains;
    }
}
