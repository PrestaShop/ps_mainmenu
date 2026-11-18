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

namespace PrestaShop\Module\Mainmenu\Menu;

use Context;
use Customer;
use PrestaShop\Module\Mainmenu\Cache\MenuCache;

/**
 * The menu of the current language, shop and customer groups, built once then read from the cache.
 */
final class CachedMenu
{
    public function __construct(
        private readonly Context $context,
        private readonly MenuBuilder $builder,
        private readonly MenuConfiguration $configuration,
        private readonly MenuCache $cache,
    ) {
    }

    /**
     * @return array<string, mixed> Root node of the menu
     */
    public function getTree(): array
    {
        $file = $this->getFile('.json');
        $tree = $this->cache->readArray($file);
        if ($tree === null) {
            $tree = $this->builder->build($this->configuration->getItems(), $this->getCustomerGroups());
            $this->cache->writeArray($file, $tree);
        }

        return $tree;
    }

    /**
     * @return array<string, bool> Identifiers of the pages of the menu, as keys
     */
    public function getPageIdentifiers(): array
    {
        $file = $this->getFile('_pages.json');
        $identifiers = $this->cache->readArray($file);
        if ($identifiers === null) {
            $identifiers = MenuTree::getPageIdentifiers($this->getTree());
            $this->cache->writeArray($file, $identifiers);
        }

        return $identifiers;
    }

    /**
     * @param string $variant Whatever makes the markup differ for the same menu, e.g. the template
     */
    public function getMarkup(string $variant): ?string
    {
        return $this->cache->read($this->getFile('_' . md5($variant) . '.html'));
    }

    public function saveMarkup(string $variant, string $markup): void
    {
        $this->cache->write($this->getFile('_' . md5($variant) . '.html'), $markup);
    }

    private function getFile(string $suffix): string
    {
        return $this->cache->getFile(
            (int) $this->context->language->id,
            (int) $this->context->shop->id,
            $this->getCustomerGroups(),
            $suffix
        );
    }

    /**
     * @return int[]
     */
    private function getCustomerGroups(): array
    {
        return array_map('intval', Customer::getGroupsStatic($this->context->customer->id));
    }
}
