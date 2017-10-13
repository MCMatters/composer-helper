<?php

declare(strict_types = 1);

namespace McMatters\ComposerHelper\Output;

use Symfony\Component\Console\Output\Output;

/**
 * Class ArrayOutput
 *
 * @package McMatters\ComposerHelper\Output
 */
class ArrayOutput extends Output
{
    /**
     * @var array
     */
    protected $store = [];

    /**
     * @return array
     */
    public function getStore(): array
    {
        $store = $this->store;

        $this->store = [];

        return $store;
    }

    /**
     * Writes a message to the output.
     *
     * @param string $message A message to write to the output
     * @param bool $newline Whether to add a newline or not
     */
    protected function doWrite($message, $newline)
    {
        $this->store[] = $message;
    }
}
