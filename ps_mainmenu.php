<?php
/**
 * 2007-2020 PrestaShop SA and Contributors
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License 3.0 (AFL-3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/AFL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to https://www.prestashop.com for more information.
 *
 * @author    PrestaShop SA <contact@prestashop.com>
 * @copyright 2007-2020 PrestaShop SA and Contributors
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 * International Registered Trademark & Property of PrestaShop SA
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

use PrestaShop\Module\MainMenu\Cache\MenuCache;
use PrestaShop\Module\MainMenu\Install\Installer;
use PrestaShop\Module\MainMenu\Menu\CachedMenu;
use PrestaShop\Module\MainMenu\Menu\CategoryThumbnails;
use PrestaShop\Module\MainMenu\Menu\CurrentPage;
use PrestaShop\Module\MainMenu\Menu\MenuBuilder;
use PrestaShop\Module\MainMenu\Menu\MenuConfiguration;
use PrestaShop\Module\MainMenu\Menu\MenuItem;
use PrestaShop\Module\MainMenu\Menu\MenuItemType;
use PrestaShop\Module\MainMenu\Menu\MenuTree;
use PrestaShop\Module\MainMenu\Repository\CmsRepository;
use PrestaShop\Module\MainMenu\Repository\LinkRepository;
use PrestaShop\PrestaShop\Adapter\SymfonyContainer;
use PrestaShop\PrestaShop\Core\Module\WidgetInterface;

class Ps_MainMenu extends Module implements WidgetInterface
{
    /**
     * Tab of the configuration page, in the Design menu.
     */
    public const TAB_CLASS_NAME = 'AdminPsMainMenu';

    private const TEMPLATE = 'module:ps_mainmenu/ps_mainmenu.tpl';

    private ?CachedMenu $cachedMenu = null;
    private ?LinkRepository $linkRepository = null;
    private ?MenuCache $menuCache = null;
    private ?MenuConfiguration $menuConfiguration = null;

    public function __construct()
    {
        $this->name = 'ps_mainmenu';
        $this->tab = 'front_office_features';
        $this->version = '3.0.0';
        $this->author = 'PrestaShop';

        $tabNames = [];
        foreach (Language::getLanguages(true) as $lang) {
            $tabNames[$lang['locale']] = $this->trans('Menu', [], 'Modules.Mainmenu.Admin', $lang['locale']);
        }
        $this->tabs = [
            [
                'route_name' => 'ps_mainmenu_configuration',
                'class_name' => self::TAB_CLASS_NAME,
                'visible' => true,
                'name' => $tabNames,
                'parent_class_name' => 'AdminParentThemes',
                'wording' => 'Menu',
                'wording_domain' => 'Modules.Mainmenu.Admin',
            ],
        ];

        $this->bootstrap = true;
        parent::__construct();

        $this->displayName = $this->trans('Main menu', [], 'Modules.Mainmenu.Admin');
        $this->description = $this->trans('Make it easy for your visitors to find their way on your store, select the right link and turn it into a menu item.', [], 'Modules.Mainmenu.Admin');
        $this->ps_versions_compliancy = ['min' => '9.0.0', 'max' => _PS_VERSION_];
    }

    public function getContent()
    {
        Tools::redirectAdmin(SymfonyContainer::getInstance()->get('router')->generate('ps_mainmenu_configuration'));
    }

    public function install(): bool
    {
        if (!parent::install()) {
            return false;
        }

        return $this->getInstaller()->install($this);
    }

    public function uninstall(): bool
    {
        $this->getMenuCache()->clear();

        return $this->getInstaller()->uninstall() && parent::uninstall();
    }

    /**
     * Keeps the menu and its custom links.
     */
    public function reset(): bool
    {
        $this->getMenuCache()->clear();

        return parent::uninstall() && parent::install() && $this->getInstaller()->registerHooks($this);
    }

    public function getWidgetVariables($hookName, array $configuration)
    {
        $menu = $this->getCachedMenu()->getTree();
        MenuTree::markCurrentPage($menu, (new CurrentPage($this->context))->getIdentifier());

        return $menu;
    }

    public function renderWidget($hookName, array $configuration)
    {
        $variant = $this->getCachedMarkupVariant();
        if ($variant !== null) {
            $markup = $this->getCachedMenu()->getMarkup($variant);
            if ($markup !== null) {
                return $markup;
            }
        }

        $menu = $this->getWidgetVariables($hookName, $configuration);

        Hook::exec('actionMainMenuModifier', ['menu' => &$menu]);

        $this->smarty->assign([
            'menu' => $menu,
        ]);

        $markup = $this->fetch(self::TEMPLATE);
        if ($variant !== null) {
            $this->getCachedMenu()->saveMarkup($variant, $markup);
        }

        return $markup;
    }

    /**
     * The markup is the same on every page which is not in the menu, so it is stored once for all of them.
     * It follows the Smarty cache setting of the shop, and is not used when a module alters the menu.
     *
     * @return string|null What the stored markup depends on, null when the menu must be rendered
     */
    private function getCachedMarkupVariant(): ?string
    {
        if (!Configuration::get('PS_SMARTY_CACHE')
            || (int) Configuration::get('PS_SMARTY_FORCE_COMPILE') === _PS_SMARTY_FORCE_COMPILE_
            || Hook::getHookModuleExecList('actionMainMenuModifier')
        ) {
            return null;
        }

        $template = $this->getTemplatePath('ps_mainmenu.tpl');
        if (!$template) {
            return null;
        }

        $pageIdentifiers = $this->getCachedMenu()->getPageIdentifiers();
        if (isset($pageIdentifiers[(new CurrentPage($this->context))->getIdentifier()])) {
            return null;
        }

        return $template . @filemtime($template);
    }

    public function hookActionObjectCategoryAddAfter(array $params): void
    {
        $this->clearMenuCacheForCategories();
    }

    public function hookActionObjectCategoryUpdateAfter(array $params): void
    {
        $this->clearMenuCacheForCategories();
    }

    public function hookActionObjectCategoryDeleteAfter(array $params): void
    {
        $this->clearMenuCacheForCategories();
    }

    public function hookActionCategoryUpdate(array $params): void
    {
        $this->clearMenuCacheForCategories();
    }

    public function hookActionObjectCmsAddAfter(array $params): void
    {
        $this->clearMenuCacheIfUsed([], [MenuItemType::CmsCategory]);
    }

    public function hookActionObjectCmsUpdateAfter(array $params): void
    {
        $this->clearMenuCacheForObject($params, MenuItemType::CmsPage, [], [MenuItemType::CmsCategory]);
    }

    public function hookActionObjectCmsDeleteAfter(array $params): void
    {
        $this->clearMenuCacheForObject($params, MenuItemType::CmsPage, [], [MenuItemType::CmsCategory]);
    }

    public function hookActionObjectManufacturerAddAfter(array $params): void
    {
        $this->clearMenuCacheIfUsed([MenuItemType::AllManufacturers->value . '0']);
    }

    public function hookActionObjectManufacturerUpdateAfter(array $params): void
    {
        $this->clearMenuCacheForObject($params, MenuItemType::Manufacturer, [MenuItemType::AllManufacturers->value . '0']);
    }

    public function hookActionObjectManufacturerDeleteAfter(array $params): void
    {
        $this->clearMenuCacheForObject($params, MenuItemType::Manufacturer, [MenuItemType::AllManufacturers->value . '0']);
    }

    public function hookActionObjectSupplierAddAfter(array $params): void
    {
        $this->clearMenuCacheIfUsed([MenuItemType::AllSuppliers->value . '0']);
    }

    public function hookActionObjectSupplierUpdateAfter(array $params): void
    {
        $this->clearMenuCacheForObject($params, MenuItemType::Supplier, [MenuItemType::AllSuppliers->value . '0']);
    }

    public function hookActionObjectSupplierDeleteAfter(array $params): void
    {
        $this->clearMenuCacheForObject($params, MenuItemType::Supplier, [MenuItemType::AllSuppliers->value . '0']);
    }

    public function hookActionObjectProductUpdateAfter(array $params): void
    {
        $this->clearMenuCacheForObject($params, MenuItemType::Product);
    }

    public function hookActionObjectProductDeleteAfter(array $params): void
    {
        $this->clearMenuCacheForObject($params, MenuItemType::Product);
    }

    public function hookActionMetaPageSave(array $params): void
    {
        $this->clearMenuCache();
    }

    public function hookActionShopDataDuplication(array $params): void
    {
        $this->getLinkRepository()->duplicate((int) $params['old_id_shop'], (int) $params['new_id_shop']);
    }

    /**
     * Product links can hold a category rewrite, so product items depend on categories as well.
     */
    private function clearMenuCacheForCategories(): void
    {
        $this->clearMenuCacheIfUsed([], [MenuItemType::Category, MenuItemType::Product]);
    }

    /**
     * @param array<string, mixed> $params Parameters of an actionObject*After hook
     * @param MenuItemType $type Type of the menu item pointing to this object
     * @param string[] $items Other menu items showing this object
     * @param MenuItemType[] $types Other types of menu items showing this object
     */
    private function clearMenuCacheForObject(array $params, MenuItemType $type, array $items = [], array $types = []): void
    {
        if (!isset($params['object']->id)) {
            $this->clearMenuCache();

            return;
        }

        $items[] = (string) new MenuItem($type, (int) $params['object']->id);
        $this->clearMenuCacheIfUsed($items, $types);
    }

    /**
     * Clears the cache only when a menu shows one of these items, or an item of one of these types.
     *
     * @param string[] $items
     * @param MenuItemType[] $types
     */
    private function clearMenuCacheIfUsed(array $items, array $types = []): void
    {
        if ($this->getMenuConfiguration()->usesAnyOf($items, $types)) {
            $this->clearMenuCache();
        }
    }

    private function clearMenuCache(): void
    {
        $this->getMenuCache()->clear();
    }

    private function getInstaller(): Installer
    {
        return new Installer(Db::getInstance(), $this->getLinkRepository());
    }

    private function getLinkRepository(): LinkRepository
    {
        return $this->linkRepository ??= new LinkRepository(Db::getInstance());
    }

    private function getMenuConfiguration(): MenuConfiguration
    {
        return $this->menuConfiguration ??= new MenuConfiguration(Db::getInstance());
    }

    private function getMenuCache(): MenuCache
    {
        return $this->menuCache ??= new MenuCache(_PS_CACHE_DIR_ . $this->name);
    }

    private function getCachedMenu(): CachedMenu
    {
        return $this->cachedMenu ??= new CachedMenu(
            $this->context,
            new MenuBuilder(
                $this->context,
                $this->getLinkRepository(),
                new CmsRepository(Db::getInstance()),
                new CategoryThumbnails(_PS_CAT_IMG_DIR_),
                $this->getTranslator()
            ),
            $this->getMenuConfiguration(),
            $this->getMenuCache()
        );
    }
}
