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

use Doctrine\DBAL\Query\QueryBuilder;
use PrestaShop\PrestaShop\Core\Grid\Query\AbstractDoctrineQueryBuilder;
use PrestaShop\PrestaShop\Core\Grid\Search\SearchCriteriaInterface;

/**
 * Custom links of a shop, with their label and URL in a language.
 */
final class LinkQueryBuilder extends AbstractDoctrineQueryBuilder
{
    public function getSearchQueryBuilder(SearchCriteriaInterface $searchCriteria)
    {
        $queryBuilder = $this->getQueryBuilder($searchCriteria->getFilters())
            ->select('l.id_linksmenutop, l.new_window, s.name AS shop_name, ll.label, ll.link')
            ->orderBy('l.id_linksmenutop', 'ASC');

        if ($searchCriteria->getLimit() > 0) {
            $queryBuilder
                ->setFirstResult((int) $searchCriteria->getOffset())
                ->setMaxResults((int) $searchCriteria->getLimit());
        }

        return $queryBuilder;
    }

    public function getCountQueryBuilder(SearchCriteriaInterface $searchCriteria)
    {
        return $this->getQueryBuilder($searchCriteria->getFilters())->select('COUNT(l.id_linksmenutop)');
    }

    /**
     * @param array<string, mixed> $filters
     */
    private function getQueryBuilder(array $filters): QueryBuilder
    {
        return $this->connection->createQueryBuilder()
            ->from($this->dbPrefix . 'linksmenutop', 'l')
            ->leftJoin(
                'l',
                $this->dbPrefix . 'linksmenutop_lang',
                'll',
                'l.id_linksmenutop = ll.id_linksmenutop AND ll.id_lang = :id_lang AND ll.id_shop = :id_shop'
            )
            ->leftJoin('l', $this->dbPrefix . 'shop', 's', 'l.id_shop = s.id_shop')
            ->where('l.id_shop IN (0, :id_shop)')
            ->setParameter('id_lang', (int) ($filters['id_lang'] ?? 0))
            ->setParameter('id_shop', (int) ($filters['id_shop'] ?? 0));
    }
}
