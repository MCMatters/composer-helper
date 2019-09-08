<?php

declare(strict_types = 1);

namespace McMatters\ComposerHelper\Exceptions;

use Exception;

/**
 * Class FileNotFoundException
 *
 * @package McMatters\ComposerHelper\Exceptions
 */
class FileNotFoundException extends Exception
{
    /**
     * FileNotFoundException constructor.
     *
     * @param string $additional
     */
    public function __construct(string $additional = '')
    {
        parent::__construct('File not found.'.($additional ? " {$additional}" : ''));
    }
}
