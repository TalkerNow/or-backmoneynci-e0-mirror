<?php

namespace App\Exceptions;

use Exception;

class FrozenDataLockedException extends Exception
{
    public function __construct(int $userId)
    {
        parent::__construct("Les données carrière du client {$userId} sont gelées et ne peuvent pas être modifiées. Utilisez l'endpoint /lock pour déverrouiller avant toute modification.");
    }
}
