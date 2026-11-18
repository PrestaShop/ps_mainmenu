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
use PrestaShop\Module\MainMenu\Menu\MenuConfiguration;
use PrestaShop\Module\MainMenu\Menu\MenuItem;
use PrestaShop\Module\MainMenu\Menu\MenuItemType;

class MenuConfigurationTest extends TestCase
{
    public function testItOnlyKeepsMenuItemsInTheirCanonicalForm(): void
    {
        $this->assertSame(
            ['CAT3', 'PRD12', 'LNK1'],
            MenuConfiguration::normalize(['CAT3', ' PRD12 ', 'PRODUCT', '', 'FOO1', ['CAT4'], 'LNK01'])
        );
    }

    public function testRemovingALinkLeavesTheLinksSharingItsFirstDigits(): void
    {
        $this->assertSame(
            ['CAT3', 'LNK12', 'LNK10'],
            MenuConfiguration::without(['CAT3', 'LNK1', 'LNK12', 'LNK10'], new MenuItem(MenuItemType::Link, 1))
        );
    }

    public function testRemovingAnItemWhichIsNotInTheMenuChangesNothing(): void
    {
        $this->assertSame(
            ['CAT3', 'LNK12'],
            MenuConfiguration::without(['CAT3', 'LNK12'], new MenuItem(MenuItemType::Link, 2))
        );
    }
}
