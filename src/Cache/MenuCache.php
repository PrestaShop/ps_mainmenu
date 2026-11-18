<?php
/**
 * Copyright since 2007 PrestaShop SA and Contributors
 * PrestaShop is an International Registered Trademark & Property of PrestaShop SA
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License version 3.0
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/AFL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * @author    PrestaShop SA and Contributors <contact@prestashop.com>
 * @copyright Since 2007 PrestaShop SA and Contributors
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License version 3.0
 */

declare(strict_types=1);

namespace PrestaShop\Module\MainMenu\Cache;

use Configuration;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * File cache of the menu. Files carry a version which changes on each clear,
 * so that a request which started before the clear cannot leave an outdated menu behind it.
 */
final class MenuCache
{
    public const VERSION_KEY = 'MOD_BLOCKTOPMENU_CACHE_VERSION';

    public function __construct(private readonly string $directory)
    {
    }

    /**
     * @param int[] $customerGroups
     * @param string $suffix Distinguishes the files of one menu, extension included
     */
    public function getFile(int $idLang, int $idShop, array $customerGroups, string $suffix): string
    {
        $directory = $this->directory . DIRECTORY_SEPARATOR . implode('_', array_map('intval', $customerGroups));
        if (!is_dir($directory)) {
            @mkdir($directory, 0775, true);
        }
        $version = preg_replace('/[^a-z0-9]/i', '', (string) Configuration::getGlobalValue(self::VERSION_KEY));

        return $directory . DIRECTORY_SEPARATOR . 'menu_' . $idLang . '_' . $idShop . '_v' . $version . $suffix;
    }

    public function read(string $file): ?string
    {
        $content = @file_get_contents($file);

        return $content === false ? null : $content;
    }

    /**
     * @return array<mixed>|null
     */
    public function readArray(string $file): ?array
    {
        $data = json_decode((string) $this->read($file), true);

        return is_array($data) ? $data : null;
    }

    public function write(string $file, string $content): void
    {
        // Written aside then renamed, so that a concurrent request never reads a partial file
        $temporaryFile = $file . uniqid('.', true) . '.tmp';
        if (@file_put_contents($temporaryFile, $content) === false || !@rename($temporaryFile, $file)) {
            @unlink($temporaryFile);
        }
    }

    /**
     * @param array<mixed> $data
     */
    public function writeArray(string $file, array $data): void
    {
        $this->write($file, (string) json_encode($data));
    }

    public function clear(): void
    {
        Configuration::updateGlobalValue(self::VERSION_KEY, uniqid());

        if (!is_dir($this->directory)) {
            return;
        }

        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->directory, FilesystemIterator::SKIP_DOTS)
        );
        foreach ($files as $file) {
            if ($file->isFile() && in_array($file->getExtension(), ['json', 'html', 'tmp'], true)) {
                @unlink($file->getPathname());
            }
        }
    }
}
