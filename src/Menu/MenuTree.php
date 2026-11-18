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

namespace PrestaShop\Module\Mainmenu\Menu;

/**
 * Operations on the menu tree. Nodes stay plain arrays: theme templates read them as such.
 */
final class MenuTree
{
    /**
     * @param array<string, mixed> $fields
     *
     * @return array<string, mixed>
     */
    public static function makeNode(array $fields): array
    {
        return array_merge([
            'type' => '',
            'label' => '',
            'url' => '',
            'children' => [],
            'open_in_new_window' => false,
            'image_urls' => [],
            'page_identifier' => null,
        ], $fields);
    }

    /**
     * @param array<string, mixed> $node
     */
    public static function setDepth(array &$node, int $depth = 0): void
    {
        $node['depth'] = $depth;
        foreach ($node['children'] as &$child) {
            self::setDepth($child, $depth + 1);
        }
    }

    /**
     * @param array<string, mixed> $node
     */
    public static function markCurrentPage(array &$node, string $pageIdentifier): void
    {
        $node['current'] = ($pageIdentifier === $node['page_identifier']);
        foreach ($node['children'] as &$child) {
            self::markCurrentPage($child, $pageIdentifier);
        }
    }

    /**
     * @param array<string, mixed> $node
     *
     * @return array<string, bool> Identifiers of the pages of the tree, as keys
     */
    public static function getPageIdentifiers(array $node): array
    {
        $identifiers = $node['page_identifier'] === null ? [] : [$node['page_identifier'] => true];
        foreach ($node['children'] as $child) {
            $identifiers += self::getPageIdentifiers($child);
        }

        return $identifiers;
    }
}
