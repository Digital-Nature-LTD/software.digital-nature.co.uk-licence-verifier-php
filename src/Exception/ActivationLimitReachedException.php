<?php

declare(strict_types=1);

namespace DigitalNature\LicenceVerifier\Exception;

class ActivationLimitReachedException extends LicenceVerifierException
{
    public function __construct(string $message)
    {
        parent::__construct($message, 422);
    }
}
