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
     * @param string $message
     * @param bool $newline
     *
     * @return void
     */
    protected function doWrite($message, $newline): void
    {
        $this->store[] = $message;
    }
}
