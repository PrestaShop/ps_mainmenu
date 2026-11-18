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
use PrestaShop\Module\MainMenu\Menu\MenuItem;
use PrestaShop\Module\MainMenu\Menu\MenuItemType;

class MenuItemTest extends TestCase
{
    /**
     * @dataProvider provideValidItems
     */
    public function testItParsesEveryItemType(string $value, MenuItemType $type, int $id): void
    {
        $item = MenuItem::fromString($value);

        $this->assertNotNull($item);
        $this->assertSame($type, $item->type);
        $this->assertSame($id, $item->id);
    }

    public static function provideValidItems(): iterable
    {
        yield 'category' => ['CAT12', MenuItemType::Category, 12];
        yield 'product' => ['PRD7', MenuItemType::Product, 7];
        yield 'cms page' => ['CMS3', MenuItemType::CmsPage, 3];
        yield 'cms category is not a cms page' => ['CMS_CAT3', MenuItemType::CmsCategory, 3];
        yield 'all brands' => ['ALLMAN0', MenuItemType::AllManufacturers, 0];
        yield 'brand' => ['MAN5', MenuItemType::Manufacturer, 5];
        yield 'all suppliers' => ['ALLSUP0', MenuItemType::AllSuppliers, 0];
        yield 'supplier' => ['SUP5', MenuItemType::Supplier, 5];
        yield 'shop' => ['SHOP2', MenuItemType::Shop, 2];
        yield 'custom link' => ['LNK10', MenuItemType::Link, 10];
        yield 'surrounding spaces' => [' PRD12 ', MenuItemType::Product, 12];
        yield 'trailing characters, as the previous versions accepted' => ['PRD12abc', MenuItemType::Product, 12];
        yield 'leading zeros' => ['CAT007', MenuItemType::Category, 7];
    }

    /**
     * @dataProvider provideInvalidItems
     */
    public function testItRejectsWhatIsNotAnItem(string $value): void
    {
        $this->assertNull(MenuItem::fromString($value));
    }

    public static function provideInvalidItems(): iterable
    {
        yield 'empty' => [''];
        yield 'no identifier' => ['CAT'];
        yield 'no type' => ['12'];
        yield 'unknown type' => ['FOO12'];
        yield 'lowercase type' => ['cat12'];
        yield 'placeholder of the product field' => ['PRODUCT'];
    }

    public function testItIsWrittenInItsCanonicalForm(): void
    {
        $this->assertSame('CAT7', (string) MenuItem::fromString('CAT007'));
        $this->assertSame('CMS_CAT3', (string) new MenuItem(MenuItemType::CmsCategory, 3));
    }
}
