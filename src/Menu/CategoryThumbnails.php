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

namespace PrestaShop\Module\MainMenu\Menu;

final class CategoryThumbnails
{
    /**
     * @var array<int, string[]>|null
     */
    private ?array $filesByCategory = null;

    public function __construct(private readonly string $directory)
    {
    }

    /**
     * @return string[] Menu thumbnail files of the category
     */
    public function getFiles(int $idCategory): array
    {
        if ($this->filesByCategory === null) {
            $this->filesByCategory = [];
            $files = is_dir($this->directory) ? scandir($this->directory) : [];
            foreach ($files ?: [] as $file) {
                if (preg_match('/^([0-9]+)-([0-9])?_thumb.jpg/i', $file, $matches) === 1) {
                    $this->filesByCategory[(int) $matches[1]][] = $file;
                }
            }
        }

        return $this->filesByCategory[$idCategory] ?? [];
    }
}
