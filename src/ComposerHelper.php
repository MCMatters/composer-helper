<?php

declare(strict_types=1);

namespace McMatters\ComposerHelper;

use Composer\Console\Application;
use McMatters\ComposerHelper\Exceptions\EmptyFileException;
use McMatters\ComposerHelper\Exceptions\FileNotFoundException;
use McMatters\ComposerHelper\Output\ArrayOutput;
use RuntimeException;
use Symfony\Component\Console\Input\ArrayInput;

use function array_merge, array_pop, array_unique, dirname, file_exists,
    file_get_contents, ini_set, is_dir, is_readable, json_decode,
    json_last_error, json_last_error_msg, preg_match, rtrim, set_time_limit,
    stripos, system;

use const false, true, JSON_ERROR_NONE;

/**
 * Class ComposerHelper
 *
 * @package McMatters\ComposerHelper
 */
class ComposerHelper
{
    /**
     * @var string
     */
    protected $basePath;

    /**
     * @var \Composer\Console\Application
     */
    protected $composer;

    /**
     * @var string
     */
    protected $defaultVendorPath;

    /**
     * @var string
     */
    protected $defaultBinPath;

    /**
     * ComposerHelper constructor.
     *
     * @param string $basePath
     *
     * @throws \McMatters\ComposerHelper\Exceptions\FileNotFoundException
     */
    public function __construct(string $basePath = '')
    {
        $this->composer = new Application();
        $this->composer->setAutoExit(false);

        $this->basePath = rtrim($basePath ?: dirname(__DIR__, 4), '/');
        $this->checkFileExisting($this->getComposerConfigPath());

        $this->defaultVendorPath = "{$this->basePath}/vendor";
        $this->defaultBinPath = "{$this->defaultVendorPath}/bin";
    }

    /**
     * @return array
     *
     * @throws \RuntimeException
     * @throws \McMatters\ComposerHelper\Exceptions\EmptyFileException
     * @throws \McMatters\ComposerHelper\Exceptions\FileNotFoundException
     */
    public function getComposerConfig(): array
    {
        return $this->getFileContent($this->getComposerConfigPath());
    }

    /**
     * @return array
     *
     * @throws \RuntimeException
     * @throws \McMatters\ComposerHelper\Exceptions\EmptyFileException
     * @throws \McMatters\ComposerHelper\Exceptions\FileNotFoundException
     */
    public function getRequirements(): array
    {
        $config = $this->getComposerConfig();

        return $config['require'] ?? [];
    }

    /**
     * @return array
     *
     * @throws \RuntimeException
     * @throws \McMatters\ComposerHelper\Exceptions\EmptyFileException
     * @throws \McMatters\ComposerHelper\Exceptions\FileNotFoundException
     */
    public function getDevRequirements(): array
    {
        $config = $this->getComposerConfig();

        return $config['require-dev'] ?? [];
    }

    /**
     * @return array
     *
     * @throws \RuntimeException
     * @throws \McMatters\ComposerHelper\Exceptions\EmptyFileException
     * @throws \McMatters\ComposerHelper\Exceptions\FileNotFoundException
     */
    public function getAllRequirements(): array
    {
        $config = $this->getComposerConfig();

        return array_merge(
            $config['require'] ?? [],
            $config['require-dev'] ?? []
        );
    }

    /**
     * @return array
     *
     * @throws \RuntimeException
     * @throws \McMatters\ComposerHelper\Exceptions\EmptyFileException
     * @throws \McMatters\ComposerHelper\Exceptions\FileNotFoundException
     */
    public function getAllInstalled(): array
    {
        return $this->getFileContent(
            "{$this->defaultVendorPath}/composer/installed.json"
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
     * @return array
     *
     * @throws \Exception
     */
    public function getOutdated(): array
    {
        $result = $this->runCommand('outdated', ['-q', '-n']);

        return $result['installed'] ?? [];
    }

    /**
     * @return array
     *
     * @throws \McMatters\ComposerHelper\Exceptions\EmptyFileException
     * @throws \McMatters\ComposerHelper\Exceptions\FileNotFoundException
     */
    public function getAllExtra(): array
    {
        $extra = [];

        foreach ($this->getAllInstalled() as $package) {
            if (empty($package['extra'])) {
                continue;
            }

            $extra[$package['name']] = $package['extra'];
        }

        return $extra;
    }

    /**
     * @return array
     *
     * @throws \McMatters\ComposerHelper\Exceptions\EmptyFileException
     * @throws \McMatters\ComposerHelper\Exceptions\FileNotFoundException
     */
    public function getAllExtensionRequirements(): array
    {
        $requirements = [];

        foreach ($this->getAllInstalled() as $package) {
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
     * @return string
     */
    public function getComposerConfigPath(): string
    {
        return "{$this->basePath}/composer.json";
    }

    /**
     * @return string
     *
     * @throws \RuntimeException
     * @throws \McMatters\ComposerHelper\Exceptions\EmptyFileException
     * @throws \McMatters\ComposerHelper\Exceptions\FileNotFoundException
     */
    public function getVendorPath(): string
    {
        $config = $this->getComposerConfig();

        if (!$config && !is_dir($this->defaultVendorPath)) {
            throw new FileNotFoundException('The vendor folder is missing.');
        }

        return $config['config']['vendor-dir'] ?? $this->defaultVendorPath;
    }

    /**
     * @return string
     *
     * @throws \RuntimeException
     * @throws \McMatters\ComposerHelper\Exceptions\EmptyFileException
     * @throws \McMatters\ComposerHelper\Exceptions\FileNotFoundException
     */
    public function getBinaryPath(): string
    {
        $config = $this->getComposerConfig();

        if (!$config && !is_dir($this->defaultBinPath)) {
            throw new FileNotFoundException('The bin folder is missing.');
        }

        return $config['config']['bin-dir'] ?? $this->defaultBinPath;
    }

    /**
     * @param string $bin
     *
     * @return string
     *
     * @throws \RuntimeException
     * @throws \McMatters\ComposerHelper\Exceptions\EmptyFileException
     * @throws \McMatters\ComposerHelper\Exceptions\FileNotFoundException
     */
    public function getBinary(string $bin): string
    {
        $command = 0 === stripos(PHP_OS, 'win') ? 'where' : 'which';
        $binaryPath = $this->getBinaryPath();

        return file_exists("{$binaryPath}/{$bin}")
            ? "{$binaryPath}/{$bin}"
            : system("{$command} {$bin}");
    }

    /**
     * @param string $command
     * @param array $args
     *
     * @return array
     *
     * @throws \Exception
     */
    public function runCommand(string $command, array $args = []): array
    {
        $this->longOperations();

        $args = array_merge(
            ['command' => $command, '--format' => 'json', '-n', '-q'],
            $args
        );

        $this->composer->doRun(new ArrayInput($args), $output = new ArrayOutput());

        $store = $output->getStore();

        $json = array_pop($store);

        return json_decode($json, true);
    }

    /**
     * @param string $file
     *
     * @return array
     *
     * @throws \RuntimeException
     * @throws \McMatters\ComposerHelper\Exceptions\EmptyFileException
     * @throws \McMatters\ComposerHelper\Exceptions\FileNotFoundException
     */
    protected function getFileContent(string $file): array
    {
        $this->checkFileExisting($file);

        $json = file_get_contents($file);

        if (!$json) {
            throw new EmptyFileException($file);
        }

        $content = json_decode($json, true);

        if (!$content || json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException(json_last_error_msg());
        }

        return $content;
    }

    /**
     * @param string $file
     *
     * @return void
     *
     * @throws \McMatters\ComposerHelper\Exceptions\FileNotFoundException
     */
    protected function checkFileExisting(string $file): void
    {
        if (!file_exists($file) || !is_readable($file)) {
            throw new FileNotFoundException(
                "File '{$file}' not found or you do not have permissions to read it\n".
                'Have you run composer install?'
            );
        }
    }

    /**
     * @return void
     */
    protected function longOperations(): void
    {
        set_time_limit(0);
        ini_set('memory_limit', '4096M');
    }
}
