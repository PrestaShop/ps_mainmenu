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

use Context;
use Dispatcher;
use Tools;

final class CurrentPage
{
    public function __construct(private readonly Context $context)
    {
    }

    /**
     * Matches the "page_identifier" of the menu node of the current page, if any.
     */
    public function getIdentifier(): string
    {
        $controller = Dispatcher::getInstance()->getController();

        if ($controller === 'cms' && ($id = Tools::getValue('id_cms'))) {
            return 'cms-page-' . $id;
        }
        if ($controller === 'category' && ($id = Tools::getValue('id_category'))) {
            return 'category-' . $id;
        }
        if ($controller === 'cms' && ($id = Tools::getValue('id_cms_category'))) {
            return 'cms-category-' . $id;
        }
        if ($controller === 'manufacturer') {
            return ($id = Tools::getValue('id_manufacturer')) ? 'manufacturer-' . $id : 'manufacturers';
        }
        if ($controller === 'supplier') {
            return ($id = Tools::getValue('id_supplier')) ? 'supplier-' . $id : 'suppliers';
        }
        if ($controller === 'product' && ($id = Tools::getValue('id_product'))) {
            return 'product-' . $id;
        }
        if ($controller === 'index') {
            return 'shop-' . $this->context->shop->id;
        }

        return ($_SERVER['REQUEST_SCHEME'] ?? 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? '') . ($_SERVER['REQUEST_URI'] ?? '');
    }
}
