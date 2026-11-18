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

final class CmsRepository
{
    public function __construct(private readonly Db $db)
    {
    }

    /**
     * @return array<int, array<string, mixed>> Direct sub-categories of a CMS category
     */
    public function getCategories(int $idParent, int $idLang, int $idShop): array
    {
        $query = (new DbQuery())
            ->select('c.`id_cms_category`, c.`id_parent`, c.`level_depth`, c.`active`, c.`position`, cl.`name`, cl.`link_rewrite`')
            ->from('cms_category', 'c')
            ->innerJoin('cms_category_shop', 'cs', 'c.`id_cms_category` = cs.`id_cms_category`')
            ->innerJoin('cms_category_lang', 'cl', 'c.`id_cms_category` = cl.`id_cms_category`')
            ->where('cl.`id_lang` = ' . $idLang)
            ->where('c.`id_parent` = ' . $idParent)
            ->where('cs.`id_shop` = ' . $idShop)
            ->where('cl.`id_shop` = ' . $idShop);

        return $this->db->executeS($query) ?: [];
    }

    /**
     * @return array<int, array<string, mixed>> Active pages of a CMS category
     */
    public function getPages(int $idCmsCategory, int $idLang, int $idShop): array
    {
        $query = (new DbQuery())
            ->select('c.`id_cms`, cl.`meta_title`, cl.`link_rewrite`')
            ->from('cms', 'c')
            ->innerJoin('cms_shop', 'cs', 'c.`id_cms` = cs.`id_cms`')
            ->innerJoin('cms_lang', 'cl', 'c.`id_cms` = cl.`id_cms`')
            ->where('c.`id_cms_category` = ' . $idCmsCategory)
            ->where('cs.`id_shop` = ' . $idShop)
            ->where('cl.`id_lang` = ' . $idLang)
            ->where('cl.`id_shop` = ' . $idShop)
            ->where('c.`active` = 1')
            ->orderBy('c.`position`');

        return $this->db->executeS($query) ?: [];
    }
}
