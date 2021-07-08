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

use PrestaShop\PrestaShop\Core\Module\WidgetInterface;

require dirname(__FILE__) . '/ps_menutoplinks.class.php';

class Ps_MainMenu extends Module implements WidgetInterface
{
    /**
     * @var string Name of the module running on PS 1.6.x. Used for data migration.
     */
    const PS_16_EQUIVALENT_MODULE = 'blocktopmenu';

    const MENU_JSON_CACHE_KEY = 'MOD_BLOCKTOPMENU_MENU_JSON';

    protected $_menu = '';
    protected $_html = '';
    protected $user_groups;

    /*
     * Pattern for matching config values
     */
    protected $pattern = '/^([A-Z_]*)[0-9]+/';

    /*
     * Name of the controller
     * Used to set item selected or not in top menu
     */
    protected $page_name = '';

    /*
     * Spaces per depth in BO
     */
    protected $spacer_size = '5';

    /*
     * Array of files from the category image directory
     */
    private $imageFiles;

    public function __construct()
    {
        $this->name = 'ps_mainmenu';
        $this->tab = 'front_office_features';
        $this->version = '2.3.0';
        $this->author = 'PrestaShop';
        $this->imageFiles = null;

        $this->bootstrap = true;
        parent::__construct();

        $this->displayName = $this->trans('Main menu', [], 'Modules.Mainmenu.Admin');
        $this->description = $this->trans('Make it easy for your visitors to find their way on your store, select the right link and turn it into a menu item.', [], 'Modules.Mainmenu.Admin');
        $this->ps_versions_compliancy = ['min' => '1.7.1.0', 'max' => _PS_VERSION_];
    }

    public function install($delete_params = true)
    {
        if (!parent::install() ||
            !$this->registerHook('actionObjectCategoryUpdateAfter') ||
            !$this->registerHook('actionObjectCategoryDeleteAfter') ||
            !$this->registerHook('actionObjectCategoryAddAfter') ||
            !$this->registerHook('actionObjectCmsUpdateAfter') ||
            !$this->registerHook('actionObjectCmsDeleteAfter') ||
            !$this->registerHook('actionObjectCmsAddAfter') ||
            !$this->registerHook('actionObjectSupplierUpdateAfter') ||
            !$this->registerHook('actionObjectSupplierDeleteAfter') ||
            !$this->registerHook('actionObjectSupplierAddAfter') ||
            !$this->registerHook('actionObjectManufacturerUpdateAfter') ||
            !$this->registerHook('actionObjectManufacturerDeleteAfter') ||
            !$this->registerHook('actionObjectManufacturerAddAfter') ||
            !$this->registerHook('actionObjectProductUpdateAfter') ||
            !$this->registerHook('actionObjectProductDeleteAfter') ||
            !$this->registerHook('actionObjectProductAddAfter') ||
            !$this->registerHook('categoryUpdate') ||
            !$this->registerHook('actionShopDataDuplication') ||
            !$this->registerHook('displayTop')) {
            return false;
        }

        if ($delete_params) {
            if ($this->uninstallPrestaShop16Module()) {
                Configuration::deleteByName('MOD_BLOCKTOPMENU_SEARCH');

                return true;
            }
            if (!$this->installDb() || !Configuration::updateGlobalValue('MOD_BLOCKTOPMENU_ITEMS', 'CAT3,CAT6,CAT9')) {
                return false;
            }
        }

        return true;
    }

    public function installDb()
    {
        return Db::getInstance()->execute('
		CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'linksmenutop` (
			`id_linksmenutop` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
			`id_shop` INT(11) UNSIGNED NOT NULL,
			`new_window` TINYINT( 1 ) NOT NULL,
			INDEX (`id_shop`)
		) ENGINE = ' . _MYSQL_ENGINE_ . ' CHARACTER SET utf8 COLLATE utf8_general_ci;') &&
            Db::getInstance()->execute('
			 CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'linksmenutop_lang` (
			`id_linksmenutop` INT(11) UNSIGNED NOT NULL,
			`id_lang` INT(11) UNSIGNED NOT NULL,
			`id_shop` INT(11) UNSIGNED NOT NULL,
			`label` VARCHAR( 128 ) NOT NULL ,
			`link` VARCHAR( 128 ) NOT NULL ,
			INDEX ( `id_linksmenutop` , `id_lang`, `id_shop`)
		) ENGINE = ' . _MYSQL_ENGINE_ . ' CHARACTER SET utf8 COLLATE utf8_general_ci;');
    }

    public function uninstall($delete_params = true)
    {
        if (!parent::uninstall()) {
            return false;
        }

        $this->clearMenuCache();

        if ($delete_params) {
            if (!$this->uninstallDB() || !Configuration::deleteByName('MOD_BLOCKTOPMENU_ITEMS')) {
                return false;
            }
        }

        return true;
    }

    protected function uninstallDb()
    {
        Db::getInstance()->execute('DROP TABLE `' . _DB_PREFIX_ . 'linksmenutop`');
        Db::getInstance()->execute('DROP TABLE `' . _DB_PREFIX_ . 'linksmenutop_lang`');

        return true;
    }

    public function reset()
    {
        if (!$this->uninstall(false)) {
            return false;
        }
        if (!$this->install(false)) {
            return false;
        }

        return true;
    }

    /**
     * Migrate data from 1.6 equivalent module (if applicable), then uninstall
     */
    public function uninstallPrestaShop16Module()
    {
        if (!Module::isInstalled(self::PS_16_EQUIVALENT_MODULE)) {
            return false;
        }
        $oldModule = Module::getInstanceByName(self::PS_16_EQUIVALENT_MODULE);
        if ($oldModule) {
            // This closure calls the parent class to prevent data to be erased
            // It allows the new module to be configured without migration
            $parentUninstallClosure = function () {
                return parent::uninstall();
            };
            $parentUninstallClosure = $parentUninstallClosure->bindTo($oldModule, get_class($oldModule));
            $parentUninstallClosure();
        }

        return true;
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
                $shop_group_id = Shop::getGroupFromShop($shop_id);
                $updated = true;

                if (count($shops) == 1) {
                    if (is_array($items) && count($items)) {
                        $updated = Configuration::updateValue('MOD_BLOCKTOPMENU_ITEMS', (string) implode(',', $items), false, (int) $shop_group_id, (int) $shop_id);
                    } else {
                        $updated = Configuration::updateValue('MOD_BLOCKTOPMENU_ITEMS', '', false, (int) $shop_group_id, (int) $shop_id);
                    }
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
                            $added = Ps_MenuTopLinks::add($links_label, $labels, Tools::getValue('new_window', 0), (int) $shop_id);

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
                    $deleted = Ps_MenuTopLinks::remove($id_linksmenutop, (int) $shop_id);
                    Configuration::updateValue('MOD_BLOCKTOPMENU_ITEMS', str_replace(['LNK' . $id_linksmenutop . ',', 'LNK' . $id_linksmenutop], '', Configuration::get('MOD_BLOCKTOPMENU_ITEMS')));

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

                    Ps_MenuTopLinks::update($link, $label, $new_window, (int) $id_shop, (int) $id_linksmenutop);
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
            $links = array_merge($links, Ps_MenuTopLinks::gets((int) $id_lang, null, (int) $shop_id));
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

    protected function getMenuItems()
    {
        $items = Tools::getValue('items');
        if (is_array($items) && count($items)) {
            return $items;
        } else {
            $shops = Shop::getContextListShopID();
            $conf = null;

            if (count($shops) > 1) {
                foreach ($shops as $key => $shop_id) {
                    $shop_group_id = Shop::getGroupFromShop($shop_id);
                    $conf .= (string) ($key > 1 ? ',' : '') . Configuration::get('MOD_BLOCKTOPMENU_ITEMS', null, $shop_group_id, $shop_id);
                }
            } else {
                $shop_id = (int) $shops[0];
                $shop_group_id = Shop::getGroupFromShop($shop_id);
                $conf = Configuration::get('MOD_BLOCKTOPMENU_ITEMS', null, $shop_group_id, $shop_id);
            }

            if (strlen($conf)) {
                return explode(',', $conf);
            } else {
                return [];
            }
        }
    }

    protected function makeMenuOption()
    {
        $id_shop = (int) Shop::getContextShopID();

        $menu_item = $this->getMenuItems();
        $id_lang = (int) $this->context->language->id;

        $html = '<select multiple="multiple" name="items[]" id="items" style="width: 300px; height: 160px;">';
        foreach ($menu_item as $item) {
            if (!$item) {
                continue;
            }

            preg_match($this->pattern, $item, $values);
            $id = (int) substr($item, strlen($values[1]), strlen($item));

            switch (substr($item, 0, strlen($values[1]))) {
                case 'CAT':
                    $category = new Category((int) $id, (int) $id_lang);
                    if (Validate::isLoadedObject($category)) {
                        $html .= '<option selected="selected" value="CAT' . $id . '">' . $category->name . '</option>' . PHP_EOL;
                    }
                    break;

                case 'PRD':
                    $product = new Product((int) $id, true, (int) $id_lang);
                    if (Validate::isLoadedObject($product)) {
                        $html .= '<option selected="selected" value="PRD' . $id . '">' . $product->name . '</option>' . PHP_EOL;
                    }
                    break;

                case 'CMS':
                    $cms = new CMS((int) $id, (int) $id_lang);
                    if (Validate::isLoadedObject($cms)) {
                        $html .= '<option selected="selected" value="CMS' . $id . '">' . $cms->meta_title . '</option>' . PHP_EOL;
                    }
                    break;

                case 'CMS_CAT':
                    $category = new CMSCategory((int) $id, (int) $id_lang);
                    if (Validate::isLoadedObject($category)) {
                        $html .= '<option selected="selected" value="CMS_CAT' . $id . '">' . $category->name . '</option>' . PHP_EOL;
                    }
                    break;

                // Case to handle the option to show all Manufacturers
                case 'ALLMAN':
                    $html .= '<option selected="selected" value="ALLMAN0">' . $this->trans('All brands', [], 'Modules.Mainmenu.Admin') . '</option>' . PHP_EOL;
                    break;

                case 'MAN':
                    $manufacturer = new Manufacturer((int) $id, (int) $id_lang);
                    if (Validate::isLoadedObject($manufacturer)) {
                        $html .= '<option selected="selected" value="MAN' . $id . '">' . $manufacturer->name . '</option>' . PHP_EOL;
                    }
                    break;

                // Case to handle the option to show all Suppliers
                case 'ALLSUP':
                    $html .= '<option selected="selected" value="ALLSUP0">' . $this->trans('All suppliers', [], 'Modules.Mainmenu.Admin') . '</option>' . PHP_EOL;
                    break;

                case 'SUP':
                    $supplier = new Supplier((int) $id, (int) $id_lang);
                    if (Validate::isLoadedObject($supplier)) {
                        $html .= '<option selected="selected" value="SUP' . $id . '">' . $supplier->name . '</option>' . PHP_EOL;
                    }
                    break;

                case 'LNK':
                    $link = Ps_MenuTopLinks::get((int) $id, (int) $id_lang, (int) $id_shop);
                    if (count($link)) {
                        if (!isset($link[0]['label']) || ($link[0]['label'] == '')) {
                            $default_language = Configuration::get('PS_LANG_DEFAULT');
                            $link = Ps_MenuTopLinks::get($link[0]['id_linksmenutop'], (int) $default_language, (int) Shop::getContextShopID());
                        }
                        $html .= '<option selected="selected" value="LNK' . (int) $link[0]['id_linksmenutop'] . '">' . Tools::safeOutput($link[0]['label']) . '</option>';
                    }
                    break;

                case 'SHOP':
                    $shop = new Shop((int) $id);
                    if (Validate::isLoadedObject($shop)) {
                        $html .= '<option selected="selected" value="SHOP' . (int) $id . '">' . $shop->name . '</option>' . PHP_EOL;
                    }
                    break;
            }
        }

        return $html . '</select>';
    }

    protected function makeNode(array $fields)
    {
        $defaults = [
            'type' => '',
            'label' => '',
            'url' => '',
            'children' => [],
            'open_in_new_window' => false,
            'image_urls' => [],
            'page_identifier' => null,
        ];

        return array_merge($defaults, $fields);
    }

    protected function generateCMSCategoriesMenu($id_cms_category, $id_lang)
    {
        $category = new CMSCategory($id_cms_category, $id_lang);

        $rawSubCategories = $this->getCMSCategories(false, $id_cms_category, $id_lang);
        $rawSubPages = $this->getCMSPages($id_cms_category);

        $subCategories = array_map(function ($category) use ($id_lang) {
            return $this->generateCMSCategoriesMenu($category['id_cms_category'], $id_lang);
        }, $rawSubCategories);

        $subPages = array_map(function ($page) use ($id_lang) {
            return $this->makeNode([
                'type' => 'cms-page',
                'page_identifier' => 'cms-page-' . $page['id_cms'],
                'label' => $page['meta_title'],
                'url' => $this->context->link->getCMSLink(
                    new CMS($page['id_cms'], $id_lang),
                    null, null,
                    $id_lang
                ),
            ]);
        }, $rawSubPages);

        $node = $this->makeNode([
            'type' => 'cms-category',
            'page_identifier' => 'cms-category-' . $id_cms_category,
            'label' => $category->name,
            'url' => $category->getLink(),
            'children' => array_merge($subCategories, $subPages),
        ]);

        return $node;
    }

    protected function makeMenu()
    {
        $root_node = $this->makeNode([
            'label' => null,
            'type' => 'root',
            'children' => [],
        ]);

        $menu_items = $this->getMenuItems();
        $id_lang = (int) $this->context->language->id;
        $id_shop = (int) Shop::getContextShopID();

        foreach ($menu_items as $item) {
            if (!$item) {
                continue;
            }

            preg_match($this->pattern, $item, $value);
            $id = (int) substr($item, strlen($value[1]), strlen($item));

            switch (substr($item, 0, strlen($value[1]))) {
                case 'CAT':
                    $categories = $this->generateCategoriesMenu(
                        Category::getNestedCategories($id, $id_lang, false, $this->user_groups)
                    );
                    $root_node['children'] = array_merge($root_node['children'], $categories);
                    break;

                case 'PRD':
                    $product = new Product((int) $id, true, (int) $id_lang);
                    if ($product->id) {
                        $root_node['children'][] = $this->makeNode([
                            'type' => 'product',
                            'page_identifier' => 'product-' . $product->id,
                            'label' => $product->name,
                            'url' => $product->getLink(),
                        ]);
                    }
                    break;

                case 'CMS':
                    $cms = CMS::getLinks((int) $id_lang, [$id]);
                    if (count($cms)) {
                        $root_node['children'][] = $this->makeNode([
                            'type' => 'cms-page',
                            'page_identifier' => 'cms-page-' . $id,
                            'label' => $cms[0]['meta_title'],
                            'url' => $cms[0]['link'],
                        ]);
                    }
                    break;

                case 'CMS_CAT':
                    $root_node['children'][] = $this->generateCMSCategoriesMenu((int) $id, (int) $id_lang);
                    break;

                // Case to handle the option to show all Manufacturers
                case 'ALLMAN':
                    $children = array_map(function ($manufacturer) use ($id_lang) {
                        return $this->makeNode([
                            'type' => 'manufacturer',
                            'page_identifier' => 'manufacturer-' . $manufacturer['id_manufacturer'],
                            'label' => $manufacturer['name'],
                            'url' => $this->context->link->getManufacturerLink(
                                new Manufacturer($manufacturer['id_manufacturer'], $id_lang),
                                null,
                                $id_lang
                            ),
                        ]);
                    }, Manufacturer::getManufacturers());

                    $root_node['children'][] = $this->makeNode([
                        'type' => 'manufacturers',
                        'page_identifier' => 'manufacturers',
                        'label' => $this->trans('All brands', [], 'Modules.Mainmenu.Admin'),
                        'url' => $this->context->link->getPageLink('manufacturer'),
                        'children' => $children,
                    ]);
                    break;

                case 'MAN':
                    $manufacturer = new Manufacturer($id, $id_lang);
                    if ($manufacturer->id) {
                        $root_node['children'][] = $this->makeNode([
                            'type' => 'manufacturer',
                            'page_identifier' => 'manufacturer-' . $manufacturer->id,
                            'label' => $manufacturer->name,
                            'url' => $this->context->link->getManufacturerLink(
                                $manufacturer,
                                null,
                                $id_lang
                            ),
                        ]);
                    }
                    break;

                // Case to handle the option to show all Suppliers
                case 'ALLSUP':
                    $children = array_map(function ($supplier) use ($id_lang) {
                        return $this->makeNode([
                            'type' => 'supplier',
                            'page_identifier' => 'supplier-' . $supplier['id_supplier'],
                            'label' => $supplier['name'],
                            'url' => $this->context->link->getSupplierLink(
                                new Supplier($supplier['id_supplier'], $id_lang),
                                null,
                                $id_lang
                            ),
                        ]);
                    }, Supplier::getSuppliers());

                    $root_node['children'][] = $this->makeNode([
                        'type' => 'suppliers',
                        'page_identifier' => 'suppliers',
                        'label' => $this->trans('All suppliers', [], 'Modules.Mainmenu.Admin'),
                        'url' => $this->context->link->getPageLink('supplier'),
                        'children' => $children,
                    ]);
                    break;

                case 'SUP':
                    $supplier = new Supplier($id, $id_lang);
                    if ($supplier->id) {
                        $root_node['children'][] = $this->makeNode([
                            'type' => 'supplier',
                            'page_identifier' => 'supplier-' . $supplier->id,
                            'label' => $supplier->name,
                            'url' => $this->context->link->getSupplierLink(
                                $supplier,
                                null,
                                $id_lang
                            ),
                        ]);
                    }
                    break;

                case 'SHOP':
                    $shop = new Shop((int) $id);
                    if (Validate::isLoadedObject($shop)) {
                        $root_node['children'][] = $this->makeNode([
                            'type' => 'shop',
                            'page_identifier' => 'shop-' . $id,
                            'label' => $shop->name,
                            'url' => $shop->getBaseURL(),
                        ]);
                    }
                    break;
                case 'LNK':
                    $link = Ps_MenuTopLinks::get($id, $id_lang, $id_shop);
                    if (!empty($link)) {
                        if (!isset($link[0]['label']) || ($link[0]['label'] == '')) {
                            $default_language = Configuration::get('PS_LANG_DEFAULT');
                            $link = Ps_MenuTopLinks::get($link[0]['id_linksmenutop'], $default_language, (int) Shop::getContextShopID());
                        }
                        $root_node['children'][] = $this->makeNode([
                            'type' => 'link',
                            'page_identifier' => 'lnk-' . Tools::str2url($link[0]['label']),
                            'label' => $link[0]['label'],
                            'url' => $link[0]['link'],
                            'open_in_new_window' => $link[0]['new_window'],
                        ]);
                    }
                    break;
            }
        }

        return $this->mapTree(function ($node, $depth) {
            $node['depth'] = $depth;

            return $node;
        }, $root_node);
    }

    protected function mapTree(callable $cb, array $node, $depth = 0)
    {
        $node['children'] = array_map(function ($child) use ($cb, $depth) {
            return $this->mapTree($cb, $child, $depth + 1);
        }, $node['children']);

        return $cb($node, $depth);
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

    protected function generateCategoriesMenu($categories, $is_children = 0)
    {
        $nodes = [];

        foreach ($categories as $key => $category) {
            $node = $this->makeNode([]);
            if ($category['level_depth'] > 1) {
                $cat = new Category($category['id_category']);
                $link = $cat->getLink();
                // Check if customer is set and check access
                if (Validate::isLoadedObject($this->context->customer) && !$cat->checkAccess($this->context->customer->id)) {
                    continue;
                }
            } else {
                $link = $this->context->link->getPageLink('index');
            }

            $node['url'] = $link;
            $node['type'] = 'category';
            $node['page_identifier'] = 'category-' . $category['id_category'];

            /* Whenever a category is not active we shouldnt display it to customer */
            if ((bool) $category['active'] === false) {
                continue;
            }

            $current = $this->page_name == 'category' && (int) Tools::getValue('id_category') == (int) $category['id_category'];
            $node['current'] = $current;
            $node['label'] = $category['name'];
            $node['image_urls'] = [];

            if (isset($category['children']) && !empty($category['children'])) {
                $node['children'] = $this->generateCategoriesMenu($category['children'], 1);
            }

            if ($this->imageFiles === null) {
                $this->imageFiles = scandir(_PS_CAT_IMG_DIR_);
            }

            if (count(preg_grep('/^' . $category['id_category'] . '-([0-9])?_thumb.jpg/i', $this->imageFiles)) > 0) {
                foreach ($this->imageFiles as $file) {
                    if (preg_match('/^' . $category['id_category'] . '-([0-9])?_thumb.jpg/i', $file) === 1) {
                        $image_url = $this->context->link->getMediaLink(_THEME_CAT_DIR_ . $file);
                        $node['image_urls'][] = $image_url;
                    }
                }
            }

            $nodes[] = $node;
        }

        return $nodes;
    }

    protected function getCMSOptions($parent = 0, $depth = 1, $id_lang = false, $items_to_skip = null, $id_shop = false)
    {
        $html = '';
        $id_lang = $id_lang ? (int) $id_lang : (int) Context::getContext()->language->id;
        $id_shop = ($id_shop !== false) ? $id_shop : Context::getContext()->shop->id;
        $categories = $this->getCMSCategories(false, (int) $parent, (int) $id_lang, (int) $id_shop);
        $pages = $this->getCMSPages((int) $parent, (int) $id_shop, (int) $id_lang);

        $spacer = str_repeat('&nbsp;', $this->spacer_size * (int) $depth);

        foreach ($categories as $category) {
            if (isset($items_to_skip) && !in_array('CMS_CAT' . $category['id_cms_category'], $items_to_skip)) {
                $html .= '<option value="CMS_CAT' . $category['id_cms_category'] . '" style="font-weight: bold;">' . $spacer . $category['name'] . '</option>';
            }
            $html .= $this->getCMSOptions($category['id_cms_category'], (int) $depth + 1, (int) $id_lang, $items_to_skip);
        }

        foreach ($pages as $page) {
            if (isset($items_to_skip) && !in_array('CMS' . $page['id_cms'], $items_to_skip)) {
                $html .= '<option value="CMS' . $page['id_cms'] . '">' . $spacer . $page['meta_title'] . '</option>';
            }
        }

        return $html;
    }

    protected function getCacheId($name = null)
    {
        $page_name = in_array($this->page_name, ['category', 'supplier', 'manufacturer', 'cms', 'product']) ? $this->page_name : 'index';

        return parent::getCacheId() . '|' . $page_name . ($page_name != 'index' ? '|' . (int) Tools::getValue('id_' . $page_name) : '');
    }

    protected function getCMSCategories($recursive = false, $parent = 1, $id_lang = false, $id_shop = false)
    {
        $id_lang = $id_lang ? (int) $id_lang : (int) Context::getContext()->language->id;
        $id_shop = ($id_shop !== false) ? $id_shop : Context::getContext()->shop->id;
        $join_shop = '';
        $where_shop = '';

        if (Tools::version_compare(_PS_VERSION_, '1.6.0.12', '>=') == true) {
            $join_shop = ' INNER JOIN `' . _DB_PREFIX_ . 'cms_category_shop` cs
			ON (bcp.`id_cms_category` = cs.`id_cms_category`)';
            $where_shop = ' AND cs.`id_shop` = ' . (int) $id_shop . ' AND cl.`id_shop` = ' . (int) $id_shop;
        }

        if ($recursive === false) {
            $sql = 'SELECT bcp.`id_cms_category`, bcp.`id_parent`, bcp.`level_depth`, bcp.`active`, bcp.`position`, cl.`name`, cl.`link_rewrite`
				FROM `' . _DB_PREFIX_ . 'cms_category` bcp' .
                $join_shop . '
				INNER JOIN `' . _DB_PREFIX_ . 'cms_category_lang` cl
				ON (bcp.`id_cms_category` = cl.`id_cms_category`)
				WHERE cl.`id_lang` = ' . (int) $id_lang . '
				AND bcp.`id_parent` = ' . (int) $parent .
                $where_shop;

            return Db::getInstance()->executeS($sql);
        } else {
            $sql = 'SELECT bcp.`id_cms_category`, bcp.`id_parent`, bcp.`level_depth`, bcp.`active`, bcp.`position`, cl.`name`, cl.`link_rewrite`
				FROM `' . _DB_PREFIX_ . 'cms_category` bcp' .
                $join_shop . '
				INNER JOIN `' . _DB_PREFIX_ . 'cms_category_lang` cl
				ON (bcp.`id_cms_category` = cl.`id_cms_category`)
				WHERE cl.`id_lang` = ' . (int) $id_lang . '
				AND bcp.`id_parent` = ' . (int) $parent .
                $where_shop;

            $results = Db::getInstance()->executeS($sql);
            foreach ($results as $result) {
                $sub_categories = $this->getCMSCategories(true, $result['id_cms_category'], (int) $id_lang);
                if ($sub_categories && count($sub_categories) > 0) {
                    $result['sub_categories'] = $sub_categories;
                }
                $categories[] = $result;
            }

            return isset($categories) ? $categories : false;
        }
    }

    protected function getCMSPages($id_cms_category, $id_shop = false, $id_lang = false)
    {
        $id_shop = ($id_shop !== false) ? (int) $id_shop : (int) Context::getContext()->shop->id;
        $id_lang = $id_lang ? (int) $id_lang : (int) Context::getContext()->language->id;

        $where_shop = '';
        if (Tools::version_compare(_PS_VERSION_, '1.6.0.12', '>=') == true) {
            $where_shop = ' AND cl.`id_shop` = ' . (int) $id_shop;
        }

        $sql = 'SELECT c.`id_cms`, cl.`meta_title`, cl.`link_rewrite`
			FROM `' . _DB_PREFIX_ . 'cms` c
			INNER JOIN `' . _DB_PREFIX_ . 'cms_shop` cs
			ON (c.`id_cms` = cs.`id_cms`)
			INNER JOIN `' . _DB_PREFIX_ . 'cms_lang` cl
			ON (c.`id_cms` = cl.`id_cms`)
			WHERE c.`id_cms_category` = ' . (int) $id_cms_category . '
			AND cs.`id_shop` = ' . (int) $id_shop . '
			AND cl.`id_lang` = ' . (int) $id_lang .
            $where_shop . '
			AND c.`active` = 1
			ORDER BY `position`';

        return Db::getInstance()->executeS($sql);
    }

    public function hookActionObjectCategoryAddAfter($params)
    {
        $this->clearMenuCache();
    }

    public function hookActionObjectCategoryUpdateAfter($params)
    {
        $this->clearMenuCache();
    }

    public function hookActionObjectCategoryDeleteAfter($params)
    {
        $this->clearMenuCache();
    }

    public function hookActionObjectCmsUpdateAfter($params)
    {
        $this->clearMenuCache();
    }

    public function hookActionObjectCmsDeleteAfter($params)
    {
        $this->clearMenuCache();
    }

    public function hookActionObjectCmsAddAfter($params)
    {
        $this->clearMenuCache();
    }

    public function hookActionObjectSupplierUpdateAfter($params)
    {
        $this->clearMenuCache();
    }

    public function hookActionObjectSupplierDeleteAfter($params)
    {
        $this->clearMenuCache();
    }

    public function hookActionObjectSupplierAddAfter($params)
    {
        $this->clearMenuCache();
    }

    public function hookActionObjectManufacturerUpdateAfter($params)
    {
        $this->clearMenuCache();
    }

    public function hookActionObjectManufacturerDeleteAfter($params)
    {
        $this->clearMenuCache();
    }

    public function hookActionObjectManufacturerAddAfter($params)
    {
        $this->clearMenuCache();
    }

    public function hookActionObjectProductUpdateAfter($params)
    {
        $this->clearMenuCache();
    }

    public function hookActionObjectProductDeleteAfter($params)
    {
        $this->clearMenuCache();
    }

    public function hookActionObjectProductAddAfter($params)
    {
        $this->clearMenuCache();
    }

    public function hookCategoryUpdate($params)
    {
        $this->clearMenuCache();
    }

    protected function getCacheDirectory()
    {
        $dir = _PS_CACHE_DIR_ . 'ps_mainmenu';

        if (isset($this->context->customer)) {
            $groups = $this->context->customer->getGroups();
            if (!empty($groups)) {
                $dir .= '/' . implode('_', $groups);
            }
        }
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        return $dir;
    }

    protected function clearMenuCache()
    {
        $this->cleanMenuCacheDirectory(_PS_CACHE_DIR_ . 'ps_mainmenu');
    }

    private function cleanMenuCacheDirectory($dir)
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) as $entry) {
            if (in_array($entry, ['.', '..'])) {
                continue;
            }
            $path = $dir . DIRECTORY_SEPARATOR . $entry;
            if (is_dir($path)) {
                $this->cleanMenuCacheDirectory($path);
            } elseif (preg_match('/\.json$/', $entry)) {
                unlink($path);
            }
        }
    }

    public function hookActionShopDataDuplication($params)
    {
        /** @var array<int, array{'id_linksmenutop': int, 'id_shop': int, 'new_window': int}> $linksmenutop */
        $linksmenutop = Db::getInstance()->executeS(
            'SELECT * FROM ' . _DB_PREFIX_ . 'linksmenutop '
            . 'WHERE id_shop = ' . (int) $params['old_id_shop']
        );

        foreach ($linksmenutop as $id => $link) {
            Db::getInstance()->execute('
				INSERT IGNORE INTO ' . _DB_PREFIX_ . 'linksmenutop (id_linksmenutop, id_shop, new_window)
				VALUES (null, ' . (int) $params['new_id_shop'] . ', ' . (int) $link['new_window'] . ')');

            $linksmenutop[$id]['new_id_linksmenutop'] = Db::getInstance()->Insert_ID();
        }

        foreach ($linksmenutop as $id => $link) {
            $lang = Db::getInstance()->executeS('
					SELECT id_lang, ' . (int) $params['new_id_shop'] . ', label, link
					FROM ' . _DB_PREFIX_ . 'linksmenutop_lang
					WHERE id_linksmenutop = ' . (int) $link['id_linksmenutop'] . ' AND id_shop = ' . (int) $params['old_id_shop']);

            foreach ($lang as $l) {
                Db::getInstance()->execute('
					INSERT IGNORE INTO ' . _DB_PREFIX_ . 'linksmenutop_lang (id_linksmenutop, id_lang, id_shop, label, link)
					VALUES (' . (int) $link['new_id_linksmenutop'] . ', ' . (int) $l['id_lang'] . ', ' . (int) $params['new_id_shop'] . ', ' . (int) $l['label'] . ', ' . (int) $l['link'] . ' )');
            }
        }
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
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false) .
            '&configure=' . $this->name . '&tab_module=' . $this->tab . '&module_name=' . $this->name;
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

        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false) .
            '&configure=' . $this->name . '&tab_module=' . $this->tab . '&module_name=' . $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->languages = $this->context->controller->getLanguages();
        $helper->default_form_language = (int) $this->context->language->id;

        return $helper->generateForm([$fields_form]);
    }

    public function renderChoicesSelect()
    {
        $spacer = str_repeat('&nbsp;', $this->spacer_size);
        $items = $this->getMenuItems();

        $html = '<select multiple="multiple" id="availableItems" style="width: 300px; height: 160px;">';
        $html .= '<optgroup label="' . $this->trans('CMS', [], 'Modules.Mainmenu.Admin') . '">';
        $html .= $this->getCMSOptions(0, 1, $this->context->language->id, $items);
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

        foreach ($shops_to_get as $shop_id) {
            $html .= $this->generateCategoriesOption($this->customGetNestedCategories($shop_id, null, (int) $this->context->language->id, false), $items);
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
        $links = Ps_MenuTopLinks::gets($this->context->language->id, null, (int) Shop::getContextShopID());
        foreach ($links as $link) {
            if ($link['label'] == '') {
                $default_language = Configuration::get('PS_LANG_DEFAULT');
                $link = Ps_MenuTopLinks::get($link['id_linksmenutop'], $default_language, (int) Shop::getContextShopID());
                if (!in_array('LNK' . (int) $link[0]['id_linksmenutop'], $items)) {
                    $html .= '<option value="LNK' . (int) $link[0]['id_linksmenutop'] . '">' . $spacer . Tools::safeOutput($link[0]['label']) . '</option>';
                }
            } elseif (!in_array('LNK' . (int) $link['id_linksmenutop'], $items)) {
                $html .= '<option value="LNK' . (int) $link['id_linksmenutop'] . '">' . $spacer . Tools::safeOutput($link['label']) . '</option>';
            }
        }
        $html .= '</optgroup>';
        $html .= '</select>';

        return $html;
    }

    public function customGetNestedCategories($shop_id, $root_category = null, $id_lang = false, $active = false, $groups = null, $use_shop_restriction = true, $sql_filter = '', $sql_sort = '', $sql_limit = '')
    {
        if (isset($root_category) && !Validate::isInt($root_category)) {
            exit(Tools::displayError());
        }

        if (!Validate::isBool($active)) {
            exit(Tools::displayError());
        }

        if (isset($groups) && Group::isFeatureActive() && !is_array($groups)) {
            $groups = (array) $groups;
        }

        $cache_id = 'Category::getNestedCategories_' . md5((int) $shop_id . (int) $root_category . (int) $id_lang . (int) $active . (int) $active
            . (isset($groups) && Group::isFeatureActive() ? implode('', $groups) : ''));

        if (!Cache::isStored($cache_id)) {
            $result = Db::getInstance()->executeS('
							SELECT c.*, cl.*
				FROM `' . _DB_PREFIX_ . 'category` c
				INNER JOIN `' . _DB_PREFIX_ . 'category_shop` category_shop ON (category_shop.`id_category` = c.`id_category` AND category_shop.`id_shop` = "' . (int) $shop_id . '")
				LEFT JOIN `' . _DB_PREFIX_ . 'category_lang` cl ON (c.`id_category` = cl.`id_category` AND cl.`id_shop` = "' . (int) $shop_id . '")
				WHERE 1 ' . $sql_filter . ' ' . ($id_lang ? 'AND cl.`id_lang` = ' . (int) $id_lang : '') . '
				' . ($active ? ' AND (c.`active` = 1 OR c.`is_root_category` = 1)' : '') . '
				' . (isset($groups) && Group::isFeatureActive() ? ' AND cg.`id_group` IN (' . implode(',', $groups) . ')' : '') . '
				' . (!$id_lang || (isset($groups) && Group::isFeatureActive()) ? ' GROUP BY c.`id_category`' : '') . '
				' . ($sql_sort != '' ? $sql_sort : ' ORDER BY c.`level_depth` ASC') . '
				' . ($sql_sort == '' && $use_shop_restriction ? ', category_shop.`position` ASC' : '') . '
				' . ($sql_limit != '' ? $sql_limit : '')
            );

            $categories = [];
            $buff = [];

            foreach ($result as $row) {
                $current = &$buff[$row['id_category']];
                $current = $row;

                if ($row['id_parent'] == 0) {
                    $categories[$row['id_category']] = &$current;
                } else {
                    $buff[$row['id_parent']]['children'][$row['id_category']] = &$current;
                }
            }

            Cache::store($cache_id, $categories);
        }

        return Cache::retrieve($cache_id);
    }

    public function getAddLinkFieldsValues()
    {
        $links_label_edit = '';
        $labels_edit = '';
        $new_window_edit = '';

        if (Tools::isSubmit('updatelinksmenutop')) {
            $link = Ps_MenuTopLinks::getLinkLang(Tools::getValue('id_linksmenutop'), (int) Shop::getContextShopID());

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
            $links = array_merge($links, Ps_MenuTopLinks::gets((int) $this->context->language->id, null, (int) $shop_id));
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
        $helper->currentIndex = AdminController::$currentIndex . '&configure=' . $this->name;

        return $helper->generateList($links, $fields_list);
    }

    private function getCurrentPageIdentifier()
    {
        $controllerName = Dispatcher::getInstance()->getController();
        if ($controllerName === 'cms' && ($id = Tools::getValue('id_cms'))) {
            return 'cms-page-' . $id;
        } elseif ($controllerName === 'category' && ($id = Tools::getValue('id_category'))) {
            return 'category-' . $id;
        } elseif ($controllerName === 'cms' && ($id = Tools::getValue('id_cms_category'))) {
            return 'cms-category-' . $id;
        } elseif ($controllerName === 'manufacturer' && ($id = Tools::getValue('id_manufacturer'))) {
            return 'manufacturer-' . $id;
        } elseif ($controllerName === 'manufacturer') {
            return 'manufacturers';
        } elseif ($controllerName === 'supplier' && ($id = Tools::getValue('id_supplier'))) {
            return 'supplier-' . $id;
        } elseif ($controllerName === 'supplier') {
            return 'suppliers';
        } elseif ($controllerName === 'product' && ($id = Tools::getValue('id_product'))) {
            return 'product-' . $id;
        } elseif ($controllerName === 'index') {
            return 'shop-' . $this->context->shop->id;
        } else {
            $scheme = 'http';
            if (array_key_exists('REQUEST_SCHEME', $_SERVER)) {
                $scheme = $_SERVER['REQUEST_SCHEME'];
            }

            return "$scheme://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
        }
    }

    public function getWidgetVariables($hookName, array $configuration)
    {
        $id_lang = $this->context->language->id;
        $id_shop = $this->context->shop->id;

        $this->user_groups = Customer::getGroupsStatic($this->context->customer->id);
        $groupsKey = empty($this->user_groups) ? '' : '_' . join('_', $this->user_groups);
        $key = self::MENU_JSON_CACHE_KEY . '_' . $id_lang . '_' . $id_shop . $groupsKey . '.json';
        $cacheDir = $this->getCacheDirectory();
        $cacheFile = $cacheDir . DIRECTORY_SEPARATOR . $key;
        $menu = json_decode(@file_get_contents($cacheFile), true);
        if (!is_array($menu) || json_last_error() !== JSON_ERROR_NONE) {
            $menu = $this->makeMenu();
            if (!is_dir($cacheDir)) {
                mkdir($cacheDir);
            }
            file_put_contents($cacheFile, json_encode($menu));
        }

        $page_identifier = $this->getCurrentPageIdentifier();
        // Mark the current page
        return $this->mapTree(function (array $node) use ($page_identifier) {
            $node['current'] = ($page_identifier === $node['page_identifier']);

            return $node;
        }, $menu);
    }

    public function renderWidget($hookName, array $configuration)
    {
        $this->smarty->assign([
            'menu' => $this->getWidgetVariables($hookName, $configuration),
        ]);

        return $this->fetch('module:ps_mainmenu/ps_mainmenu.tpl');
    }
}
