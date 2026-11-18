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

namespace PrestaShop\Module\MainMenu\Grid;

use PrestaShop\PrestaShop\Core\Grid\Action\Row\RowActionCollection;
use PrestaShop\PrestaShop\Core\Grid\Action\Row\Type\LinkRowAction;
use PrestaShop\PrestaShop\Core\Grid\Column\ColumnCollection;
use PrestaShop\PrestaShop\Core\Grid\Column\Type\Common\ActionColumn;
use PrestaShop\PrestaShop\Core\Grid\Column\Type\Common\BooleanColumn;
use PrestaShop\PrestaShop\Core\Grid\Column\Type\DataColumn;
use PrestaShop\PrestaShop\Core\Grid\Definition\Factory\AbstractGridDefinitionFactory;
use PrestaShop\PrestaShop\Core\Grid\Definition\Factory\DeleteActionTrait;

final class LinkGridDefinitionFactory extends AbstractGridDefinitionFactory
{
    use DeleteActionTrait;

    public const GRID_ID = 'ps_mainmenu_link';

    protected function getId()
    {
        return self::GRID_ID;
    }

    protected function getName()
    {
        return $this->trans('Link list', [], 'Modules.Mainmenu.Admin');
    }

    protected function getColumns()
    {
        return (new ColumnCollection())
            ->add(
                (new DataColumn('id_linksmenutop'))
                    ->setName($this->trans('Link ID', [], 'Modules.Mainmenu.Admin'))
                    ->setOptions(['field' => 'id_linksmenutop', 'sortable' => false])
            )
            ->add(
                (new DataColumn('shop_name'))
                    ->setName($this->trans('Shop name', [], 'Admin.Shopparameters.Feature'))
                    ->setOptions(['field' => 'shop_name', 'sortable' => false])
            )
            ->add(
                (new DataColumn('label'))
                    ->setName($this->trans('Label', [], 'Admin.Global'))
                    ->setOptions(['field' => 'label', 'sortable' => false])
            )
            ->add(
                (new DataColumn('link'))
                    ->setName($this->trans('Link', [], 'Admin.Global'))
                    ->setOptions(['field' => 'link', 'sortable' => false])
            )
            ->add(
                (new BooleanColumn('new_window'))
                    ->setName($this->trans('New window', [], 'Admin.Navigation.Header'))
                    ->setOptions([
                        'field' => 'new_window',
                        'true_name' => $this->trans('Yes', [], 'Admin.Global'),
                        'false_name' => $this->trans('No', [], 'Admin.Global'),
                    ])
            )
            ->add(
                (new ActionColumn('actions'))
                    ->setName($this->trans('Actions', [], 'Admin.Global'))
                    ->setOptions([
                        'actions' => (new RowActionCollection())
                            ->add(
                                (new LinkRowAction('edit'))
                                    ->setName($this->trans('Edit', [], 'Admin.Actions'))
                                    ->setIcon('edit')
                                    ->setOptions([
                                        'route' => 'ps_mainmenu_link_edit',
                                        'route_param_name' => 'linkId',
                                        'route_param_field' => 'id_linksmenutop',
                                    ])
                            )
                            ->add($this->buildDeleteAction('ps_mainmenu_link_delete', 'linkId', 'id_linksmenutop')),
                    ])
            );
    }
}
