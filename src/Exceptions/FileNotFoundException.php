<?php

declare(strict_types=1);

namespace McMatters\ComposerHelper\Exceptions;

use Exception;

class FileNotFoundException extends Exception
{
    public function __construct(string $additional = '')
    {
        parent::__construct('File not found.'.($additional ? " {$additional}" : ''));
    }
}
