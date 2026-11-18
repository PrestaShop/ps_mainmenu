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
use PrestaShop\Module\MainMenu\Menu\MenuTree;

class MenuTreeTest extends TestCase
{
    public function testANodeHasEveryKeyTheTemplatesRead(): void
    {
        $node = MenuTree::makeNode(['label' => 'Clothes', 'page_identifier' => 'category-3']);

        $this->assertSame(
            ['type', 'label', 'url', 'children', 'open_in_new_window', 'image_urls', 'page_identifier'],
            array_keys($node)
        );
        $this->assertSame('Clothes', $node['label']);
        $this->assertSame([], $node['children']);
        $this->assertFalse($node['open_in_new_window']);
    }

    public function testDepthIsSetOnEveryLevel(): void
    {
        $tree = $this->makeTree();
        MenuTree::setDepth($tree);

        $this->assertSame(0, $tree['depth']);
        $this->assertSame(1, $tree['children'][0]['depth']);
        $this->assertSame(2, $tree['children'][0]['children'][0]['depth']);
        $this->assertSame(1, $tree['children'][1]['depth']);
    }

    public function testOnlyTheNodesOfTheCurrentPageAreMarked(): void
    {
        $tree = $this->makeTree();
        MenuTree::markCurrentPage($tree, 'category-4');

        $this->assertFalse($tree['current']);
        $this->assertFalse($tree['children'][0]['current']);
        $this->assertTrue($tree['children'][0]['children'][0]['current']);
        $this->assertFalse($tree['children'][1]['current']);
    }

    public function testNoNodeIsMarkedOnAPageWhichIsNotInTheMenu(): void
    {
        $tree = $this->makeTree();
        MenuTree::markCurrentPage($tree, 'http://localhost/cart');

        $this->assertFalse($tree['current']);
        $this->assertFalse($tree['children'][0]['children'][0]['current']);
    }

    public function testPageIdentifiersAreCollectedOnEveryLevel(): void
    {
        $this->assertSame(
            ['category-3' => true, 'category-4' => true, 'cms-page-1' => true],
            MenuTree::getPageIdentifiers($this->makeTree())
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function makeTree(): array
    {
        return MenuTree::makeNode([
            'type' => 'root',
            'children' => [
                MenuTree::makeNode([
                    'page_identifier' => 'category-3',
                    'children' => [MenuTree::makeNode(['page_identifier' => 'category-4'])],
                ]),
                MenuTree::makeNode(['page_identifier' => 'cms-page-1']),
            ],
        ]);
    }
}
