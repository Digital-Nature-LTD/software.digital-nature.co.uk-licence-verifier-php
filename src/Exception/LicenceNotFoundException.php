<?php

declare(strict_types=1);

namespace DigitalNature\LicenceVerifier\Exception;

class LicenceNotFoundException extends LicenceVerifierException
{
    public function __construct()
    {
        parent::__construct('Licence not found', 404);
    }
}
