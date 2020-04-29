<?php

declare(strict_types=1);

namespace McMatters\ComposerHelper\Exceptions;

use Exception;

/**
 * Class EmptyFileException
 *
 * @package McMatters\ComposerHelper\Exceptions
 */
class EmptyFileException extends Exception
{
    /**
     * EmptyFileException constructor.
     *
     * @param string $file
     */
    public function __construct(string $file)
    {
        parent::__construct("File {$file} is empty.");
    }
}
