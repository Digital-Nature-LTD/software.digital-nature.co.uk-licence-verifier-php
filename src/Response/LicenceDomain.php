<?php

declare(strict_types=1);

namespace DigitalNature\LicenceVerifier\Response;

final class LicenceDomain
{
    public string $domain;
    public string $domainType;
    public string $activatedAt;

    public function __construct(string $domain, string $domainType, string $activatedAt)
    {
        $this->domain = $domain;
        $this->domainType = $domainType;
        $this->activatedAt = $activatedAt;
    }
}
