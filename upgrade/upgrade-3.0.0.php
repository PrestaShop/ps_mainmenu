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
 * This script can run while the class of the previous version is the one loaded in memory:
 * it only relies on the module directory and on the Module API of the core.
 *
 * @param Module $module
 *
 * @return bool
 */
function upgrade_module_3_0_0($module)
{
    $moduleDirectory = dirname(__DIR__);

    // Files of the previous versions which are no longer shipped
    foreach (['ps_menutoplinks.class.php'] as $legacyFile) {
        if (is_file($moduleDirectory . '/' . $legacyFile)) {
            @unlink($moduleDirectory . '/' . $legacyFile);
        }
    }

    // Cache files of the previous versions, whatever their name
    $cacheDirectory = _PS_CACHE_DIR_ . 'ps_mainmenu';
    if (is_dir($cacheDirectory)) {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($cacheDirectory, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($files as $file) {
            $file->isDir() ? @rmdir($file->getPathname()) : @unlink($file->getPathname());
        }
    }
    Configuration::updateGlobalValue('MOD_BLOCKTOPMENU_CACHE_VERSION', uniqid());

    // A product which has just been created cannot be in a menu
    $module->unregisterHook('actionObjectProductAddAfter');

    return true;
}
