<?php

namespace App\Cms\Install;

use RuntimeException;
use Throwable;

class InstallationFailed extends RuntimeException
{
    public function __construct(string $message, public readonly array $log = [], ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
}
