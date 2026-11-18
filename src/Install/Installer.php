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

namespace PrestaShop\Module\MainMenu\Install;

use Configuration;
use Db;
use Module;
use PrestaShop\Module\MainMenu\Cache\MenuCache;
use PrestaShop\Module\MainMenu\Menu\MenuConfiguration;
use PrestaShop\Module\MainMenu\Repository\LinkRepository;

final class Installer
{
    public const HOOKS = [
        'actionCategoryUpdate',
        'actionMetaPageSave',
        'actionObjectCategoryAddAfter',
        'actionObjectCategoryDeleteAfter',
        'actionObjectCategoryUpdateAfter',
        'actionObjectCmsAddAfter',
        'actionObjectCmsDeleteAfter',
        'actionObjectCmsUpdateAfter',
        'actionObjectManufacturerAddAfter',
        'actionObjectManufacturerDeleteAfter',
        'actionObjectManufacturerUpdateAfter',
        'actionObjectProductDeleteAfter',
        'actionObjectProductUpdateAfter',
        'actionObjectSupplierAddAfter',
        'actionObjectSupplierDeleteAfter',
        'actionObjectSupplierUpdateAfter',
        'actionShopDataDuplication',
        'displayTop',
    ];

    private const DEFAULT_ITEMS = 'CAT3,CAT6,CAT9';

    public function __construct(
        private readonly Db $db,
        private readonly LinkRepository $linkRepository,
    ) {
    }

    public function install(Module $module): bool
    {
        return $this->registerHooks($module)
            && $this->linkRepository->createTables()
            && $this->declareMenuModifierHook()
            && Configuration::updateGlobalValue(MenuConfiguration::ITEMS_KEY, self::DEFAULT_ITEMS);
    }

    public function registerHooks(Module $module): bool
    {
        return (bool) $module->registerHook(self::HOOKS);
    }

    public function uninstall(): bool
    {
        return $this->linkRepository->dropTables()
            && Configuration::deleteByName(MenuConfiguration::ITEMS_KEY)
            && Configuration::deleteByName(MenuCache::VERSION_KEY);
    }

    private function declareMenuModifierHook(): bool
    {
        return $this->db->execute(
            'INSERT IGNORE INTO `' . _DB_PREFIX_ . 'hook` (`name`, `title`, `description`) VALUES
            ("actionMainMenuModifier", "Modify main menu view data", "This hook allows to alter main menu data")'
        );
    }
}
