<?php

declare(strict_types=1);

namespace DigitalNature\LicenceVerifier\Response;

final class ActivateResult
{
    public bool $activated;
    public string $domain;
    public string $domainType;
    public int $activationsUsed;
    public ?int $activationLimit;

    public function __construct(
        bool $activated,
        string $domain,
        string $domainType,
        int $activationsUsed,
        ?int $activationLimit
    ) {
        $this->activated = $activated;
        $this->domain = $domain;
        $this->domainType = $domainType;
        $this->activationsUsed = $activationsUsed;
        $this->activationLimit = $activationLimit;
    }
}
