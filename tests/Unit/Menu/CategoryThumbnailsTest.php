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

namespace Tests\Unit\Menu;

use PHPUnit\Framework\TestCase;
use PrestaShop\Module\MainMenu\Menu\CategoryThumbnails;

class CategoryThumbnailsTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/ps_mainmenu_' . uniqid();
        mkdir($this->directory);
        foreach (['3.jpg', '3-0_thumb.jpg', '3-1_thumb.jpg', '3-category_default.jpg', '33-0_thumb.jpg', '4_thumb.jpg'] as $file) {
            touch($this->directory . '/' . $file);
        }
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->directory . '/*') ?: []);
        rmdir($this->directory);
    }

    public function testItOnlyReturnsTheMenuThumbnailsOfTheCategory(): void
    {
        $thumbnails = new CategoryThumbnails($this->directory);

        $this->assertSame(['3-0_thumb.jpg', '3-1_thumb.jpg'], $thumbnails->getFiles(3));
        $this->assertSame(['33-0_thumb.jpg'], $thumbnails->getFiles(33));
        $this->assertSame([], $thumbnails->getFiles(4));
    }

    public function testAMissingDirectoryGivesNoThumbnail(): void
    {
        $this->assertSame([], (new CategoryThumbnails($this->directory . '/missing'))->getFiles(3));
    }
}
