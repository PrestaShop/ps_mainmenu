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

final class MenuItem
{
    public function __construct(
        public readonly MenuItemType $type,
        public readonly int $id,
    ) {
    }

    /**
     * Whatever follows the identifier is ignored, as menus saved by the previous versions can hold such values.
     */
    public static function fromString(string $item): ?self
    {
        if (preg_match('/^([A-Z_]+)([0-9]+)/', trim($item), $matches) !== 1) {
            return null;
        }

        $type = MenuItemType::tryFrom($matches[1]);

        return $type === null ? null : new self($type, (int) $matches[2]);
    }

    public function __toString(): string
    {
        return $this->type->value . $this->id;
    }
}
