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

namespace PrestaShop\Module\Mainmenu\Repository;

use Db;
use DbQuery;

final class CategoryRepository
{
    public function __construct(private readonly Db $db)
    {
    }

    /**
     * Unlike Category::getNestedCategories(), reads the categories of a given shop whatever the shop in context,
     * and includes the disabled ones.
     *
     * @return array<int, array<string, mixed>> Category tree, children under the "children" key
     */
    public function getNestedCategoriesOfShop(int $idShop, int $idLang): array
    {
        $query = (new DbQuery())
            ->select('c.*, cl.*')
            ->from('category', 'c')
            ->innerJoin('category_shop', 'cs', 'cs.`id_category` = c.`id_category` AND cs.`id_shop` = ' . $idShop)
            ->leftJoin('category_lang', 'cl', 'c.`id_category` = cl.`id_category` AND cl.`id_shop` = ' . $idShop)
            ->where('cl.`id_lang` = ' . $idLang)
            ->orderBy('c.`level_depth` ASC, cs.`position` ASC');

        $categories = [];
        $rowsById = [];
        foreach ($this->db->executeS($query) ?: [] as $row) {
            $current = &$rowsById[$row['id_category']];
            $current = $row;

            if ((int) $row['id_parent'] === 0) {
                $categories[$row['id_category']] = &$current;
            } else {
                $rowsById[$row['id_parent']]['children'][$row['id_category']] = &$current;
            }
            unset($current);
        }

        return $categories;
    }
}
