<?php

declare(strict_types=1);

namespace DigitalNature\LicenceVerifier\Response;

final class DeactivateResult
{
    public bool $deactivated;
    public string $domain;

    public function __construct(bool $deactivated, string $domain)
    {
        $this->deactivated = $deactivated;
        $this->domain = $domain;
    }
}
