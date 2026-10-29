<?php

namespace App\Exceptions;

use Carbon\CarbonInterface;
use RuntimeException;

class StaleWriteException extends RuntimeException
{
    public function __construct(public readonly CarbonInterface $currentUpdatedAt)
    {
        parent::__construct('The page changed since base_updated_at ('.$currentUpdatedAt->toIso8601String().').');
    }
}
