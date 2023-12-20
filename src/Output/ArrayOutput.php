<?php

declare(strict_types=1);

namespace McMatters\ComposerHelper\Output;

use Symfony\Component\Console\Output\Output;

class ArrayOutput extends Output
{
    protected array $store = [];

    public function getStore(): array
    {
        $store = $this->store;

        $this->store = [];

        return $store;
    }

    protected function doWrite(string $message, bool $newline): void
    {
        $this->store[] = $message;
    }
}
