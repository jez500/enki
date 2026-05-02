<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

class DuplicateSkillException extends RuntimeException
{
    public function __construct(public readonly string $slug)
    {
        parent::__construct('A skill with this identifier already exists.');
    }
}
