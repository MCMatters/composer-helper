<?php

declare(strict_types=1);

namespace McMatters\ComposerHelper\Exceptions;

use Exception;

class EmptyFileException extends Exception
{
    public function __construct(string $file)
    {
        parent::__construct("File '{$file}' is empty.");
    }
}
