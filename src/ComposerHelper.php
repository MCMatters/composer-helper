<?php

declare(strict_types=1);

namespace McMatters\ComposerHelper;

use Composer\Console\Application;
use McMatters\ComposerHelper\Exceptions\EmptyFileException;
use McMatters\ComposerHelper\Exceptions\FileNotFoundException;
use McMatters\ComposerHelper\Output\ArrayOutput;
use Symfony\Component\Console\Input\ArrayInput;

use function array_merge;
use function array_pop;
use function array_unique;
use function dirname;
use function file_exists;
use function file_get_contents;
use function is_dir;
use function is_readable;
use function json_decode;
use function preg_match;
use function rtrim;
use function stripos;
use function system;

use const false;
use const JSON_THROW_ON_ERROR;
use const PHP_OS_FAMILY;
use const true;

class ComposerHelper
{
    protected string $basePath;

    protected Application $composer;

    protected string $defaultVendorPath;

    protected string $defaultBinPath;

    /**
     * @throws \McMatters\ComposerHelper\Exceptions\FileNotFoundException
     */
    public function __construct(string $basePath = '')
    {
        $this->composer = new Application();
        $this->composer->setAutoExit(false);

        $this->basePath = rtrim($basePath ?: dirname(__DIR__, 4), '/');
        $this->checkFileExisting($this->getComposerJsonPath());

        $this->defaultVendorPath = "{$this->basePath}/vendor";
        $this->defaultBinPath = "{$this->defaultVendorPath}/bin";
    }

    /**
     * @throws \RuntimeException
     * @throws \McMatters\ComposerHelper\Exceptions\EmptyFileException
     * @throws \McMatters\ComposerHelper\Exceptions\FileNotFoundException
     * @throws \JsonException
     */
    public function getComposerJsonContent(): array
    {
        return $this->getFileContent($this->getComposerJsonPath());
    }

    /**
     * @throws \RuntimeException
     * @throws \McMatters\ComposerHelper\Exceptions\EmptyFileException
     * @throws \McMatters\ComposerHelper\Exceptions\FileNotFoundException
     * @throws \JsonException
     */
    public function getRequirements(): array
    {
        $config = $this->getComposerJsonContent();

        return $config['require'] ?? [];
    }

    /**
     * @throws \RuntimeException
     * @throws \McMatters\ComposerHelper\Exceptions\EmptyFileException
     * @throws \McMatters\ComposerHelper\Exceptions\FileNotFoundException
     * @throws \JsonException
     */
    public function getDevRequirements(): array
    {
        $config = $this->getComposerJsonContent();

        return $config['require-dev'] ?? [];
    }

    /**
     * @throws \RuntimeException
     * @throws \McMatters\ComposerHelper\Exceptions\EmptyFileException
     * @throws \McMatters\ComposerHelper\Exceptions\FileNotFoundException
     * @throws \JsonException
     */
    public function getAllRequirements(): array
    {
        $config = $this->getComposerJsonContent();

        return array_merge(
            $config['require'] ?? [],
            $config['require-dev'] ?? [],
        );
    }

    /**
     * @throws \RuntimeException
     * @throws \McMatters\ComposerHelper\Exceptions\EmptyFileException
     * @throws \McMatters\ComposerHelper\Exceptions\FileNotFoundException
     * @throws \JsonException
     */
    public function getInstalled(): array
    {
        return $this->getFileContent(
            "{$this->defaultVendorPath}/composer/installed.json",
        );
    }

    /**
     * Returns array with packages. The structure of a package:
     * [
     *     'name' => string,
     *     'version' => 'string',
     *     'latest' => 'string',
     *     'latest-status' => 'string', // Can be 'up-to-date',
     *     'semver-safe-update', 'update-possible'
     *     'description' => string,
     * ]
     *
     * @throws \Exception
     */
    public function getOutdated(): array
    {
        $result = $this->runCommand(
            'outdated',
            ['--format' => 'json', '-n', '-q'],
        );

        return $result['installed'] ?? [];
    }

    /**
     * @throws \McMatters\ComposerHelper\Exceptions\EmptyFileException
     * @throws \McMatters\ComposerHelper\Exceptions\FileNotFoundException
     * @throws \JsonException
     */
    public function getExtras(): array
    {
        $extras = [];

        foreach ($this->getInstalled() as $package) {
            if (empty($package['extra'])) {
                continue;
            }

            $extras[$package['name']] = $package['extra'];
        }

        return $extras;
    }

    /**
     * @throws \McMatters\ComposerHelper\Exceptions\EmptyFileException
     * @throws \McMatters\ComposerHelper\Exceptions\FileNotFoundException
     * @throws \JsonException
     */
    public function getExtensionRequirements(): array
    {
        $requirements = [];

        foreach ($this->getInstalled() as $package) {
            foreach ($package['require'] ?? [] as $requirement => $version) {
                if (preg_match('/^ext-(?<extension>[a-z0-9_]+)$/', $requirement, $match)) {
                    $requirements[$match['extension']][] = $version;
                }
            }
        }

        foreach ($requirements as $extension => $versions) {
            $requirements[$extension] = array_unique($versions);
        }

        return $requirements;
    }

    /**
     * @throws \McMatters\ComposerHelper\Exceptions\FileNotFoundException
     * @throws \McMatters\ComposerHelper\Exceptions\EmptyFileException
     * @throws \JsonException
     */
    public function getPhpRequirement(): ?string
    {
        $requirements = $this->getRequirements();

        return $requirements['php'] ?? null;
    }

    public function getComposer(): Application
    {
        return $this->composer;
    }

    public function getComposerVersion(): string
    {
        return $this->composer->getVersion();
    }

    public function getComposerJsonPath(): string
    {
        return "{$this->basePath}/composer.json";
    }

    /**
     * @throws \RuntimeException
     * @throws \McMatters\ComposerHelper\Exceptions\EmptyFileException
     * @throws \McMatters\ComposerHelper\Exceptions\FileNotFoundException
     * @throws \JsonException
     */
    public function getVendorPath(): string
    {
        $config = $this->getComposerJsonContent();

        if (!$config && !is_dir($this->defaultVendorPath)) {
            throw new FileNotFoundException('The vendor folder is missing.');
        }

        return $config['config']['vendor-dir'] ?? $this->defaultVendorPath;
    }

    /**
     * @throws \RuntimeException
     * @throws \McMatters\ComposerHelper\Exceptions\EmptyFileException
     * @throws \McMatters\ComposerHelper\Exceptions\FileNotFoundException
     * @throws \JsonException
     */
    public function getBinaryPath(): string
    {
        $config = $this->getComposerJsonContent();

        if (!$config && !is_dir($this->defaultBinPath)) {
            throw new FileNotFoundException('The bin folder is missing.');
        }

        return $config['config']['bin-dir'] ?? $this->defaultBinPath;
    }

    /**
     * @throws \RuntimeException
     * @throws \McMatters\ComposerHelper\Exceptions\EmptyFileException
     * @throws \McMatters\ComposerHelper\Exceptions\FileNotFoundException
     * @throws \JsonException
     */
    public function getBinary(string $bin): string
    {
        $command = 0 === stripos(PHP_OS_FAMILY, 'win') ? 'where' : 'which';
        $binaryPath = $this->getBinaryPath();

        return file_exists("{$binaryPath}/{$bin}")
            ? "{$binaryPath}/{$bin}"
            : system("{$command} {$bin}");
    }

    /**
     * @throws \Exception
     * @throws \JsonException
     */
    public function runCommand(string $command, array $args = []): array|string
    {
        $args = array_merge(['command' => $command], $args);

        $this->composer->doRun(new ArrayInput($args), $output = new ArrayOutput());

        $store = $output->getStore();

        $response = array_pop($store);

        return ($args['--format'] ?? $args['-f'] ?? null) === 'json'
            ? json_decode($response, true, 512, JSON_THROW_ON_ERROR)
            : $response;
    }

    /**
     * @throws \RuntimeException
     * @throws \McMatters\ComposerHelper\Exceptions\EmptyFileException
     * @throws \McMatters\ComposerHelper\Exceptions\FileNotFoundException
     * @throws \JsonException
     */
    protected function getFileContent(string $file): array
    {
        $this->checkFileExisting($file);

        $json = file_get_contents($file);

        if (!$json) {
            throw new EmptyFileException($file);
        }

        return json_decode($json, true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * @throws \McMatters\ComposerHelper\Exceptions\FileNotFoundException
     */
    protected function checkFileExisting(string $file): void
    {
        if (!file_exists($file) || !is_readable($file)) {
            throw new FileNotFoundException(
                "File '{$file}' not found or you do not have permissions to read it\n".
                'Have you run composer install?',
            );
        }
    }
}
