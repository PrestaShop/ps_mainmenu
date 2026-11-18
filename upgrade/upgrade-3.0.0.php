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
if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * This script can run while the class of the previous version is the one loaded in memory, and the
 * autoloader of the module was registered before the new files were extracted: no class of src/ and
 * no member added to the module class in 3.0.0 can be used here. It only relies on the module
 * directory and on the API of the core.
 *
 * @param Module $module
 *
 * @return bool
 */
function upgrade_module_3_0_0($module)
{
    $moduleDirectory = dirname(__DIR__);

    // Files of the previous versions which are no longer shipped: an update extracts the new files over the old ones
    foreach (['ps_menutoplinks.class.php', 'views/templates/admin/_configure'] as $legacyPath) {
        ps_mainmenu_3_0_0_remove($moduleDirectory . '/' . $legacyPath);
    }

    // Cache files of the previous versions, whatever their name
    ps_mainmenu_3_0_0_remove(_PS_CACHE_DIR_ . 'ps_mainmenu');
    Configuration::updateGlobalValue('MOD_BLOCKTOPMENU_CACHE_VERSION', uniqid());

    // A product which has just been created cannot be in a menu
    $module->unregisterHook('actionObjectProductAddAfter');

    return ps_mainmenu_3_0_0_install_tab();
}

/**
 * Tabs declared by a module are only registered when it is installed or enabled, not when it is upgraded.
 *
 * @return bool
 */
function ps_mainmenu_3_0_0_install_tab()
{
    $className = 'AdminPsMainMenu';
    if (Tab::getIdFromClassName($className)) {
        return true;
    }

    $tab = new Tab();
    $tab->class_name = $className;
    $tab->route_name = 'ps_mainmenu_configuration';
    $tab->module = 'ps_mainmenu';
    $tab->active = true;
    $tab->enabled = true;
    $tab->id_parent = (int) Tab::getIdFromClassName('AdminParentThemes');
    $tab->wording = 'Menu';
    $tab->wording_domain = 'Modules.Mainmenu.Admin';
    foreach (Language::getLanguages(false) as $language) {
        $tab->name[(int) $language['id_lang']] = 'Menu';
    }

    return (bool) $tab->add();
}

/**
 * @param string $path File or directory
 */
function ps_mainmenu_3_0_0_remove($path)
{
    if (is_file($path)) {
        @unlink($path);

        return;
    }
    if (!is_dir($path)) {
        return;
    }

    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($files as $file) {
        $file->isDir() ? @rmdir($file->getPathname()) : @unlink($file->getPathname());
    }
    @rmdir($path);
}
