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

use PrestaShop\Module\Mainmenu\Cache\MenuCache;
use PrestaShop\Module\Mainmenu\Install\Installer;
use PrestaShop\Module\Mainmenu\Menu\CachedMenu;
use PrestaShop\Module\Mainmenu\Menu\CategoryThumbnails;
use PrestaShop\Module\Mainmenu\Menu\CurrentPage;
use PrestaShop\Module\Mainmenu\Menu\MenuBuilder;
use PrestaShop\Module\Mainmenu\Menu\MenuConfiguration;
use PrestaShop\Module\Mainmenu\Menu\MenuItem;
use PrestaShop\Module\Mainmenu\Menu\MenuItemType;
use PrestaShop\Module\Mainmenu\Menu\MenuTree;
use PrestaShop\Module\Mainmenu\Repository\CategoryRepository;
use PrestaShop\Module\Mainmenu\Repository\CmsRepository;
use PrestaShop\Module\Mainmenu\Repository\LinkRepository;
use PrestaShop\PrestaShop\Core\Module\WidgetInterface;

class Ps_MainMenu extends Module implements WidgetInterface
{
    private const TEMPLATE = 'module:ps_mainmenu/ps_mainmenu.tpl';

    protected $_html = '';

    /*
     * Spaces per depth in BO
     */
    protected $spacer_size = 5;

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

        $this->bootstrap = true;
        parent::__construct();

        $this->displayName = $this->trans('Main menu', [], 'Modules.Mainmenu.Admin');
        $this->description = $this->trans('Make it easy for your visitors to find their way on your store, select the right link and turn it into a menu item.', [], 'Modules.Mainmenu.Admin');
        $this->ps_versions_compliancy = ['min' => '9.0.0', 'max' => _PS_VERSION_];
    }

    public function isUsingNewTranslationSystem(): bool
    {
        return true;
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
        $this->getMenuConfiguration()->forgetItemsOfAllShops();
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

    /**
     * @return string[] Items being edited in the configuration form
     */
    private function getEditedMenuItems(): array
    {
        $items = Tools::getValue('items');

        return is_array($items) && count($items) ? $items : $this->getMenuConfiguration()->getItems();
    }

    public function getContent()
    {
        $id_lang = (int) Context::getContext()->language->id;
        $languages = $this->context->controller->getLanguages();
        $default_language = (int) Configuration::get('PS_LANG_DEFAULT');

        $labels = Tools::getValue('label') ? array_filter(Tools::getValue('label'), function ($value) {
            return (bool) strlen($value);
        }) : [];
        $links_label = Tools::getValue('link') ? array_filter(Tools::getValue('link'), function ($value) {
            return (bool) strlen($value);
        }) : [];
        $spacer = str_repeat('&nbsp;', $this->spacer_size);
        $divLangName = 'link_label';

        $update_cache = false;

        if (Tools::isSubmit('submitBlocktopmenu')) {
            $errors_update_shops = [];
            $items = Tools::getValue('items');
            $shops = Shop::getContextListShopID();

            foreach ($shops as $shop_id) {
                $updated = true;

                if (count($shops) == 1) {
                    $updated = $this->getMenuConfiguration()->saveItems(is_array($items) ? $items : [], (int) $shop_id);
                }

                if (!$updated) {
                    $shop = new Shop($shop_id);
                    $errors_update_shops[] = $shop->name;
                }
            }

            if (!count($errors_update_shops)) {
                $this->_html .= $this->displayConfirmation($this->trans('The settings have been updated.', [], 'Admin.Notifications.Success'));
            } else {
                $this->_html .= $this->displayError(sprintf($this->trans('Unable to update settings for the following shop(s): %s', [], 'Modules.Mainmenu.Admin'), implode(', ', $errors_update_shops)));
            }

            $update_cache = true;
        } else {
            if (Tools::isSubmit('submitBlocktopmenuLinks')) {
                $errors_add_link = [];

                foreach ($languages as $key => $val) {
                    $links_label[$val['id_lang']] = Tools::getValue('link_' . (int) $val['id_lang']);
                    $labels[$val['id_lang']] = Tools::getValue('label_' . (int) $val['id_lang']);
                }

                $count_links_label = count($links_label);
                $count_label = count($labels);

                if ($count_links_label || $count_label) {
                    if (!$count_links_label) {
                        $this->_html .= $this->displayError($this->trans('Please complete the "Link" field.', [], 'Modules.Mainmenu.Admin'));
                    } elseif (!$count_label) {
                        $this->_html .= $this->displayError($this->trans('Please add a label.', [], 'Modules.Mainmenu.Admin'));
                    } elseif (!isset($labels[$default_language])) {
                        $this->_html .= $this->displayError($this->trans('Please add a label for your default language.', [], 'Modules.Mainmenu.Admin'));
                    } else {
                        $shops = Shop::getContextListShopID();

                        foreach ($shops as $shop_id) {
                            $added = $this->getLinkRepository()->add($links_label, $labels, (bool) Tools::getValue('new_window', 0), (int) $shop_id);

                            if (!$added) {
                                $shop = new Shop($shop_id);
                                $errors_add_link[] = $shop->name;
                            }
                        }

                        if (!count($errors_add_link)) {
                            $this->_html .= $this->displayConfirmation($this->trans('The link has been added.', [], 'Modules.Mainmenu.Admin'));
                        } else {
                            $this->_html .= $this->displayError($this->trans('Unable to add link for the following shop(s): %s', [implode(', ', $errors_add_link)], 'Modules.Mainmenu.Admin'));
                        }
                    }
                }
                $update_cache = true;
            } elseif (Tools::isSubmit('deletelinksmenutop')) {
                $errors_delete_link = [];
                $id_linksmenutop = Tools::getValue('id_linksmenutop', 0);
                $shops = Shop::getContextListShopID();

                foreach ($shops as $shop_id) {
                    $deleted = $this->getLinkRepository()->delete((int) $id_linksmenutop, (int) $shop_id);
                    $this->getMenuConfiguration()->removeItem(new MenuItem(MenuItemType::Link, (int) $id_linksmenutop), (int) $shop_id);

                    if (!$deleted) {
                        $shop = new Shop($shop_id);
                        $errors_delete_link[] = $shop->name;
                    }
                }

                if (!count($errors_delete_link)) {
                    $this->_html .= $this->displayConfirmation($this->trans('The link has been removed.', [], 'Modules.Mainmenu.Admin'));
                } else {
                    $this->_html .= $this->displayError($this->trans('Unable to remove link for the following shop(s): %s', [implode(', ', $errors_delete_link)], 'Modules.Mainmenu.Admin'));
                }

                $update_cache = true;
            } elseif (Tools::isSubmit('updatelinksmenutop')) {
                $id_linksmenutop = (int) Tools::getValue('id_linksmenutop', 0);
                $id_shop = (int) Shop::getContextShopID();

                if (Tools::isSubmit('updatelink')) {
                    $link = [];
                    $label = [];
                    $new_window = (int) Tools::getValue('new_window', 0);

                    foreach (Language::getLanguages(false) as $lang) {
                        $link[$lang['id_lang']] = Tools::getValue('link_' . (int) $lang['id_lang']);
                        $label[$lang['id_lang']] = Tools::getValue('label_' . (int) $lang['id_lang']);
                    }

                    $this->getLinkRepository()->update($id_linksmenutop, $link, $label, (bool) $new_window, $id_shop);
                    $this->_html .= $this->displayConfirmation($this->trans('The link has been edited.', [], 'Modules.Mainmenu.Admin'));
                }
                $update_cache = true;
            }
        }

        if ($update_cache) {
            $this->clearMenuCache();
        }

        $shops = Shop::getContextListShopID();
        $links = [];

        if (count($shops) > 1) {
            $this->_html .= $this->getWarningMultishopHtml();
        }

        if (Shop::isFeatureActive()) {
            $this->_html .= $this->getCurrentShopInfoMsg();
        }

        $this->_html .= $this->renderForm() . $this->renderAddForm();

        foreach ($shops as $shop_id) {
            $links = array_merge($links, $this->getLinkRepository()->findAll($id_lang, (int) $shop_id));
        }

        if (!count($links)) {
            return $this->_html;
        }

        $this->_html .= $this->renderList();

        return $this->_html;
    }

    protected function getWarningMultishopHtml()
    {
        return '<p class="alert alert-warning">' .
                    $this->trans('You cannot manage top menu items from a "All Shops" or a "Group Shop" context, select directly the shop you want to edit', [], 'Modules.Mainmenu.Admin') .
                '</p>';
    }

    protected function getCurrentShopInfoMsg()
    {
        $shop_info = null;

        if (Shop::getContext() == Shop::CONTEXT_SHOP) {
            $shop_info = $this->trans('The modifications will be applied to shop: %s', [$this->context->shop->name], 'Modules.Mainmenu.Admin');
        } else {
            if (Shop::getContext() == Shop::CONTEXT_GROUP) {
                $shop_info = $this->trans('The modifications will be applied to this group: %s', [Shop::getContextShopGroup()->name], 'Modules.Mainmenu.Admin');
            } else {
                $shop_info = $this->trans('The modifications will be applied to all shops', [], 'Modules.Mainmenu.Admin');
            }
        }

        return '<div class="alert alert-info">' .
                    $shop_info .
                '</div>';
    }

    /**
     * @return string Select holding the items of the menu being edited
     */
    private function makeMenuOption(): string
    {
        $id_lang = (int) $this->context->language->id;
        $html = '<select multiple="multiple" name="items[]" id="items" style="width: 300px; height: 160px;">';

        foreach ($this->getEditedMenuItems() as $configured_item) {
            $item = MenuItem::fromString((string) $configured_item);
            $label = $item === null ? null : $this->getMenuItemLabel($item, $id_lang);
            if ($label !== null) {
                $html .= '<option selected="selected" value="' . $item . '">' . $label . '</option>' . PHP_EOL;
            }
        }

        return $html . '</select>';
    }

    /**
     * @return string|null Null when the item points to something which no longer exists
     */
    private function getMenuItemLabel(MenuItem $item, int $id_lang): ?string
    {
        switch ($item->type) {
            case MenuItemType::AllManufacturers:
                return $this->trans('All brands', [], 'Modules.Mainmenu.Admin');

            case MenuItemType::AllSuppliers:
                return $this->trans('All suppliers', [], 'Modules.Mainmenu.Admin');

            case MenuItemType::Link:
                $link = $this->getLinkRepository()->findWithFallback(
                    $item->id,
                    $id_lang,
                    (int) Shop::getContextShopID(),
                    (int) Configuration::get('PS_LANG_DEFAULT')
                );

                return $link === null ? null : Tools::safeOutput($link['label']);

            case MenuItemType::Category:
                $object = new Category($item->id, $id_lang);
                break;

            case MenuItemType::CmsCategory:
                $object = new CMSCategory($item->id, $id_lang);
                break;

            case MenuItemType::CmsPage:
                $object = new CMS($item->id, $id_lang);

                return Validate::isLoadedObject($object) ? $object->meta_title : null;

            case MenuItemType::Manufacturer:
                $object = new Manufacturer($item->id, $id_lang);
                break;

            case MenuItemType::Product:
                $object = new Product($item->id, false, $id_lang);
                break;

            case MenuItemType::Shop:
                $object = new Shop($item->id);
                break;

            case MenuItemType::Supplier:
                $object = new Supplier($item->id, $id_lang);
                break;
        }

        return Validate::isLoadedObject($object) ? $object->name : null;
    }

    /**
     * @param string[] $items_to_skip Items already in the menu
     *
     * @return string Options of the CMS categories and pages under a CMS category
     */
    private function getCMSOptions(CmsRepository $cmsRepository, int $id_parent, int $depth, array $items_to_skip): string
    {
        $html = '';
        $id_lang = (int) $this->context->language->id;
        $id_shop = (int) $this->context->shop->id;
        $spacer = str_repeat('&nbsp;', $this->spacer_size * $depth);

        foreach ($cmsRepository->getCategories($id_parent, $id_lang, $id_shop) as $category) {
            if (!in_array('CMS_CAT' . $category['id_cms_category'], $items_to_skip)) {
                $html .= '<option value="CMS_CAT' . $category['id_cms_category'] . '" style="font-weight: bold;">' . $spacer . $category['name'] . '</option>';
            }
            $html .= $this->getCMSOptions($cmsRepository, (int) $category['id_cms_category'], $depth + 1, $items_to_skip);
        }

        foreach ($cmsRepository->getPages($id_parent, $id_lang, $id_shop) as $page) {
            if (!in_array('CMS' . $page['id_cms'], $items_to_skip)) {
                $html .= '<option value="CMS' . $page['id_cms'] . '">' . $spacer . $page['meta_title'] . '</option>';
            }
        }

        return $html;
    }

    protected function generateCategoriesOption($categories, $items_to_skip = null)
    {
        $html = '';

        foreach ($categories as $key => $category) {
            if (isset($items_to_skip) /*&& !in_array('CAT'.(int)$category['id_category'], $items_to_skip)*/) {
                $shop = (object) Shop::getShop((int) $category['id_shop']);
                $html .= '<option value="CAT' . (int) $category['id_category'] . '">'
                    . str_repeat('&nbsp;', $this->spacer_size * (int) $category['level_depth']) . $category['name'] . ' (' . $shop->name . ')</option>';
            }

            if (isset($category['children']) && !empty($category['children'])) {
                $html .= $this->generateCategoriesOption($category['children'], $items_to_skip);
            }
        }

        return $html;
    }

    public function renderForm()
    {
        $shops = Shop::getContextListShopID();

        if (count($shops) == 1) {
            $fields_form = [
                'form' => [
                    'legend' => [
                        'title' => $this->trans('Menu Top Link', [], 'Modules.Mainmenu.Admin'),
                        'icon' => 'icon-link',
                    ],
                    'input' => [
                        [
                            'type' => 'link_choice',
                            'label' => '',
                            'name' => 'link',
                            'lang' => true,
                        ],
                    ],
                    'submit' => [
                        'name' => 'submitBlocktopmenu',
                        'title' => $this->trans('Save', [], 'Admin.Actions'),
                    ],
                ],
            ];
        } else {
            $fields_form = [
                'form' => [
                    'legend' => [
                        'title' => $this->trans('Menu Top Link', [], 'Modules.Mainmenu.Admin'),
                        'icon' => 'icon-link',
                    ],
                    'info' => '<div class="alert alert-warning">' .
                        $this->trans('All active products combinations quantities will be changed', [], 'Modules.Mainmenu.Admin') . '</div>',
                    'submit' => [
                        'name' => 'submitBlocktopmenu',
                        'title' => $this->trans('Save', [], 'Admin.Actions'),
                    ],
                ],
            ];
        }

        $helper = new HelperForm();
        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $lang = new Language((int) Configuration::get('PS_LANG_DEFAULT'));
        $helper->default_form_language = $lang->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') ? Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') : 0;
        $helper->module = $this;
        $helper->identifier = $this->identifier;
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false, [], ['configure' => $this->name, 'tab_module' => $this->tab, 'module_name' => $this->name]);
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->tpl_vars = [
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
            'choices' => $this->renderChoicesSelect(),
            'selected_links' => $this->makeMenuOption(),
        ];

        return $helper->generateForm([$fields_form]);
    }

    public function renderAddForm()
    {
        $fields_form = [
            'form' => [
                'legend' => [
                    'title' => Tools::getValue('id_linksmenutop') && !Tools::isSubmit('updatelink') ?
                        $this->trans('Update link', [], 'Modules.Mainmenu.Admin') : $this->trans('Add a new link', [], 'Modules.Mainmenu.Admin'),
                    'icon' => 'icon-link',
                ],
                'input' => [
                    [
                        'type' => 'text',
                        'label' => $this->trans('Label', [], 'Admin.Global'),
                        'name' => 'label',
                        'lang' => true,
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->trans('Link', [], 'Admin.Global'),
                        'placeholder' => 'http://www.example.com',
                        'name' => 'link',
                        'lang' => true,
                    ],
                    [
                        'type' => 'switch',
                        'label' => $this->trans('New window', [], 'Admin.Navigation.Header'),
                        'name' => 'new_window',
                        'is_bool' => true,
                        'values' => [
                            [
                                'id' => 'active_on',
                                'value' => 1,
                                'label' => $this->trans('Yes', [], 'Admin.Global'),
                            ],
                            [
                                'id' => 'active_off',
                                'value' => 0,
                                'label' => $this->trans('No', [], 'Admin.Global'),
                            ],
                        ],
                    ],
                ],
                'submit' => [
                    'name' => 'submitBlocktopmenuLinks',
                    'title' => $this->trans('Add', [], 'Admin.Actions'),
                ],
            ],
        ];

        $helper = new HelperForm();
        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $lang = new Language((int) Configuration::get('PS_LANG_DEFAULT'));
        $helper->default_form_language = $lang->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') ? Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') : 0;
        $helper->identifier = $this->identifier;
        $helper->fields_value = $this->getAddLinkFieldsValues();

        if (Tools::getValue('id_linksmenutop') && !Tools::isSubmit('updatelink')) {
            $fields_form['form']['submit'] = [
                'name' => 'updatelinksmenutop',
                'title' => $this->trans('Update', [], 'Admin.Actions'),
            ];
        }

        if (Tools::isSubmit('updatelinksmenutop')) {
            $fields_form['form']['input'][] = ['type' => 'hidden', 'name' => 'updatelink'];
            $fields_form['form']['input'][] = ['type' => 'hidden', 'name' => 'id_linksmenutop'];
            $helper->fields_value['updatelink'] = '';
        }

        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false, [], ['configure' => $this->name, 'tab_module' => $this->tab, 'module_name' => $this->name]);
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->languages = $this->context->controller->getLanguages();
        $helper->default_form_language = (int) $this->context->language->id;

        return $helper->generateForm([$fields_form]);
    }

    public function renderChoicesSelect()
    {
        $spacer = str_repeat('&nbsp;', $this->spacer_size);
        $items = $this->getEditedMenuItems();

        $html = '<select multiple="multiple" id="availableItems" style="width: 300px; height: 160px;">';
        $html .= '<optgroup label="' . $this->trans('CMS', [], 'Modules.Mainmenu.Admin') . '">';
        $html .= $this->getCMSOptions(new CmsRepository(Db::getInstance()), 0, 1, $items);
        $html .= '</optgroup>';

        // BEGIN SUPPLIER
        $html .= '<optgroup label="' . $this->trans('Supplier', [], 'Admin.Global') . '">';
        // Option to show all Suppliers
        $html .= '<option value="ALLSUP0">' . $this->trans('All suppliers', [], 'Modules.Mainmenu.Admin') . '</option>';
        $suppliers = Supplier::getSuppliers(false, $this->context->language->id);
        foreach ($suppliers as $supplier) {
            if (!in_array('SUP' . $supplier['id_supplier'], $items)) {
                $html .= '<option value="SUP' . $supplier['id_supplier'] . '">' . $spacer . $supplier['name'] . '</option>';
            }
        }
        $html .= '</optgroup>';

        // BEGIN Manufacturer
        $html .= '<optgroup label="' . $this->trans('Brand', [], 'Admin.Global') . '">';
        // Option to show all Manufacturers
        $html .= '<option value="ALLMAN0">' . $this->trans('All brands', [], 'Modules.Mainmenu.Admin') . '</option>';
        $manufacturers = Manufacturer::getManufacturers(false, $this->context->language->id);
        foreach ($manufacturers as $manufacturer) {
            if (!in_array('MAN' . $manufacturer['id_manufacturer'], $items)) {
                $html .= '<option value="MAN' . $manufacturer['id_manufacturer'] . '">' . $spacer . $manufacturer['name'] . '</option>';
            }
        }
        $html .= '</optgroup>';

        // BEGIN Categories
        $shop = new Shop((int) Shop::getContextShopID());
        $html .= '<optgroup label="' . $this->trans('Categories', [], 'Admin.Global') . '">';

        $shops_to_get = Shop::getContextListShopID();
        $categoryRepository = new CategoryRepository(Db::getInstance());

        foreach ($shops_to_get as $shop_id) {
            $html .= $this->generateCategoriesOption($categoryRepository->getNestedCategoriesOfShop((int) $shop_id, (int) $this->context->language->id), $items);
        }
        $html .= '</optgroup>';

        // BEGIN Shops
        if (Shop::isFeatureActive()) {
            $html .= '<optgroup label="' . $this->trans('Shops', [], 'Modules.Mainmenu.Admin') . '">';
            $shops = Shop::getShopsCollection();
            /** @var Shop $shop */
            foreach ($shops as $shop) {
                if (!$shop->setUrl() && !$shop->getBaseURL()) {
                    continue;
                }

                if (!in_array('SHOP' . (int) $shop->id, $items)) {
                    $html .= '<option value="SHOP' . (int) $shop->id . '">' . $spacer . $shop->name . '</option>';
                }
            }
            $html .= '</optgroup>';
        }

        // BEGIN Products
        $html .= '<optgroup label="' . $this->trans('Products', [], 'Admin.Global') . '">';
        $html .= '<option value="PRODUCT" style="font-style:italic">' . $spacer . $this->trans('Choose product ID', [], 'Modules.Mainmenu.Admin') . '</option>';
        $html .= '</optgroup>';

        // BEGIN Menu Top Links
        $html .= '<optgroup label="' . $this->trans('Menu Top Links', [], 'Modules.Mainmenu.Admin') . '">';
        $id_shop = (int) Shop::getContextShopID();
        foreach ($this->getLinkRepository()->findAll((int) $this->context->language->id, $id_shop) as $link) {
            if (in_array('LNK' . (int) $link['id_linksmenutop'], $items)) {
                continue;
            }
            if ($link['label'] == '') {
                $link = $this->getLinkRepository()->find((int) $link['id_linksmenutop'], (int) Configuration::get('PS_LANG_DEFAULT'), $id_shop);
            }
            $html .= '<option value="LNK' . (int) $link['id_linksmenutop'] . '">' . $spacer . Tools::safeOutput($link['label']) . '</option>';
        }
        $html .= '</optgroup>';
        $html .= '</select>';

        return $html;
    }

    public function getAddLinkFieldsValues()
    {
        $links_label_edit = '';
        $labels_edit = '';
        $new_window_edit = '';

        if (Tools::isSubmit('updatelinksmenutop')) {
            $link = $this->getLinkRepository()->getTranslations((int) Tools::getValue('id_linksmenutop'), (int) Shop::getContextShopID());

            foreach ($link['link'] as $key => $label) {
                $link['link'][$key] = Tools::htmlentitiesDecodeUTF8($label);
            }

            $links_label_edit = $link['link'];
            $labels_edit = $link['label'];
            $new_window_edit = $link['new_window'];
        }

        $fields_values = [
            'new_window' => Tools::getValue('new_window', $new_window_edit),
            'id_linksmenutop' => Tools::getValue('id_linksmenutop'),
        ];

        if (Tools::getValue('submitAddmodule')) {
            foreach (Language::getLanguages(false) as $lang) {
                $fields_values['label'][$lang['id_lang']] = '';
                $fields_values['link'][$lang['id_lang']] = '';
            }
        } else {
            foreach (Language::getLanguages(false) as $lang) {
                $fields_values['label'][$lang['id_lang']] = Tools::getValue('label_' . (int) $lang['id_lang'], isset($labels_edit[$lang['id_lang']]) ?
                    $labels_edit[$lang['id_lang']] : '');
                $fields_values['link'][$lang['id_lang']] = Tools::getValue('link_' . (int) $lang['id_lang'], isset($links_label_edit[$lang['id_lang']]) ?
                    $links_label_edit[$lang['id_lang']] : '');
            }
        }

        return $fields_values;
    }

    public function renderList()
    {
        $shops = Shop::getContextListShopID();
        $links = [];

        foreach ($shops as $shop_id) {
            $links = array_merge($links, $this->getLinkRepository()->findAll((int) $this->context->language->id, (int) $shop_id));
        }

        $fields_list = [
            'id_linksmenutop' => [
                'title' => $this->trans('Link ID', [], 'Modules.Mainmenu.Admin'),
                'type' => 'text',
            ],
            'name' => [
                'title' => $this->trans('Shop name', [], 'Admin.Shopparameters.Feature'),
                'type' => 'text',
            ],
            'label' => [
                'title' => $this->trans('Label', [], 'Admin.Global'),
                'type' => 'text',
            ],
            'link' => [
                'title' => $this->trans('Link', [], 'Admin.Global'),
                'type' => 'link',
            ],
            'new_window' => [
                'title' => $this->trans('New window', [], 'Admin.Navigation.Header'),
                'type' => 'bool',
                'align' => 'center',
                'active' => 'status',
            ],
        ];

        $helper = new HelperList();
        $helper->shopLinkType = '';
        $helper->simple_header = true;
        $helper->identifier = 'id_linksmenutop';
        $helper->table = 'linksmenutop';
        $helper->actions = ['edit', 'delete'];
        $helper->show_toolbar = false;
        $helper->module = $this;
        $helper->title = $this->trans('Link list', [], 'Modules.Mainmenu.Admin');
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', true, [], ['configure' => $this->name, 'tab_module' => $this->tab, 'module_name' => $this->name]);

        return $helper->generateList($links, $fields_list);
    }
}
