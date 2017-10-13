<?php

declare(strict_types = 1);

namespace McMatters\ComposerHelper;

use Composer\Console\Application;
use McMatters\ComposerHelper\Exceptions\EmptyFileException;
use McMatters\ComposerHelper\Exceptions\FileNotFoundException;
use McMatters\ComposerHelper\Output\ArrayOutput;
use RuntimeException;
use Symfony\Component\Console\Input\ArrayInput;
use const false, null, true, JSON_ERROR_NONE;
use function array_merge, array_pop, file_exists, file_get_contents, ini_get,
    ini_set, is_dir, is_readable, json_decode, json_last_error,
    json_last_error_msg, rtrim, set_time_limit, stripos, system;

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
     * @var Application
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
     * @throws FileNotFoundException
     */
    public function __construct(string $basePath = __DIR__.'/../../../..')
    {
        $this->composer = new Application();
        $this->composer->setAutoExit(false);

        $this->basePath = rtrim($basePath, '/');
        $this->checkFileExisting($this->getComposerConfigPath());

        $this->defaultVendorPath = "{$this->basePath}/vendor";
        $this->defaultBinPath = "{$this->defaultVendorPath}/bin";
    }

    /**
     * @return array
     * @throws RuntimeException
     * @throws EmptyFileException
     * @throws FileNotFoundException
     */
    public function getComposerConfig(): array
    {
        return $this->getFileContent($this->getComposerConfigPath());
    }

    /**
     * @return array
     * @throws RuntimeException
     * @throws EmptyFileException
     * @throws FileNotFoundException
     */
    public function getRequirements(): array
    {
        $config = $this->getComposerConfig();

        return $config['require'] ?? [];
    }

    /**
     * @return array
     * @throws RuntimeException
     * @throws EmptyFileException
     * @throws FileNotFoundException
     */
    public function getDevRequirements(): array
    {
        $config = $this->getComposerConfig();

        return $config['require-dev'] ?? [];
    }

    /**
     * @return array
     * @throws RuntimeException
     * @throws EmptyFileException
     * @throws FileNotFoundException
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
     * @throws RuntimeException
     * @throws EmptyFileException
     * @throws FileNotFoundException
     */
    public function getAllInstalled(): array
    {
        return $this->getFileContent(
            "{$this->defaultVendorPath}/composer/installed.json"
        );
    }

    /**
     * @return array
     * Returns array with packages. The structure of a package:
     * [
     *     'name' => string,
     *     'version' => 'string',
     *     'latest' => 'string',
     *     'latest-status' => 'string', // Can be 'up-to-date', 'semver-safe-update', 'update-possible'
     *     'description' => string,
     * ]
     */
    public function getOutdated(): array
    {
        $this->longOperations();

        $this->composer->doRun(
            new ArrayInput([
                'command' => 'outdated', '-q', '-n', '--format' => 'json'
            ]),
            $output = new ArrayOutput()
        );

        $store = $output->getStore();

        $json = array_pop($store);

        $this->longOperations(true);

        $content = json_decode($json, true);

        return $content['installed'] ?? [];
    }

    /**
     * @return string
     * @throws FileNotFoundException
     */
    public function getComposerConfigPath(): string
    {
        return "{$this->basePath}/composer.json";
    }

    /**
     * @return string
     * @throws RuntimeException
     * @throws EmptyFileException
     * @throws FileNotFoundException
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
     * @throws RuntimeException
     * @throws EmptyFileException
     * @throws FileNotFoundException
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
     * @throws RuntimeException
     * @throws EmptyFileException
     * @throws FileNotFoundException
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
     * @param string $file
     *
     * @return array
     * @throws RuntimeException
     * @throws EmptyFileException
     * @throws FileNotFoundException
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
     * @throws FileNotFoundException
     */
    protected function checkFileExisting(string $file)
    {
        if (!file_exists($file) || !is_readable($file)) {
            throw new FileNotFoundException(
                "File '{$file}' not found or you do not have permissions to read it"
            );
        }
    }

    /**
     * @param bool $revert
     */
    protected function longOperations(bool $revert = false)
    {
        static $memory, $time;

        if ($revert) {
            if (null !== $memory) {
                ini_set('memory_limit', $memory);
            }

            if (null !== $time) {
                set_time_limit($time);
            }
        } else {
            $memory = ini_get('memory_limit');
            $time = ini_get('max_execution_time');

            set_time_limit(0);
            ini_set('memory_limit', '4096M');
        }
    }
}
