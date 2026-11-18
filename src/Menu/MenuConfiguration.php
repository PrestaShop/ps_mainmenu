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

use Configuration;
use Db;
use Shop;

final class MenuConfiguration
{
    public const ITEMS_KEY = 'MOD_BLOCKTOPMENU_ITEMS';

    /**
     * @var string[]|null
     */
    private ?array $itemsOfAllShops = null;

    public function __construct(private readonly Db $db)
    {
    }

    /**
     * @return string[] Items of the menu of the shops in context, e.g. ["CAT3", "LNK1"]
     */
    public function getItems(): array
    {
        $items = [];
        foreach (Shop::getContextListShopID() as $idShop) {
            $value = (string) Configuration::get(self::ITEMS_KEY, null, Shop::getGroupFromShop($idShop), (int) $idShop);
            $items[] = $this->split($value);
        }

        return array_merge(...$items);
    }

    /**
     * @param string[] $items Values which are not menu items are left out
     */
    public function saveItems(array $items, int $idShop): bool
    {
        $this->itemsOfAllShops = null;

        return Configuration::updateValue(
            self::ITEMS_KEY,
            implode(',', self::normalize($items)),
            false,
            (int) Shop::getGroupFromShop($idShop),
            $idShop
        );
    }

    /**
     * @param array<mixed> $items
     *
     * @return string[] Valid items, in their canonical form
     */
    public static function normalize(array $items): array
    {
        $normalized = [];
        foreach ($items as $item) {
            $menuItem = is_scalar($item) ? MenuItem::fromString((string) $item) : null;
            if ($menuItem !== null) {
                $normalized[] = (string) $menuItem;
            }
        }

        return $normalized;
    }

    public function removeItem(MenuItem $item, int $idShop): void
    {
        $idShopGroup = (int) Shop::getGroupFromShop($idShop);
        $items = $this->split((string) Configuration::get(self::ITEMS_KEY, null, $idShopGroup, $idShop));
        $this->saveItems(self::without($items, $item), $idShop);
    }

    /**
     * @param string[] $items
     *
     * @return string[] The items but this one: removing LNK1 leaves LNK12 untouched
     */
    public static function without(array $items, MenuItem $item): array
    {
        return array_values(array_diff(self::normalize($items), [(string) $item]));
    }

    /**
     * Whether a menu of any shop shows one of these items, or an item of one of these types.
     *
     * @param string[] $items
     * @param MenuItemType[] $types
     */
    public function usesAnyOf(array $items, array $types = []): bool
    {
        foreach ($this->getItemsOfAllShops() as $configuredItem) {
            if (in_array($configuredItem, $items, true)) {
                return true;
            }

            $item = MenuItem::fromString($configuredItem);
            if ($item !== null && in_array($item->type, $types, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return string[]
     */
    private function getItemsOfAllShops(): array
    {
        if ($this->itemsOfAllShops === null) {
            $rows = $this->db->executeS(
                'SELECT `value` FROM `' . _DB_PREFIX_ . 'configuration` WHERE `name` = "' . pSQL(self::ITEMS_KEY) . '"'
            );
            $items = [];
            foreach ($rows ?: [] as $row) {
                $items[] = $this->split((string) $row['value']);
            }
            $this->itemsOfAllShops = array_merge(...$items);
        }

        return $this->itemsOfAllShops;
    }

    /**
     * @return string[]
     */
    private function split(string $value): array
    {
        return $value === '' ? [] : explode(',', $value);
    }
}
