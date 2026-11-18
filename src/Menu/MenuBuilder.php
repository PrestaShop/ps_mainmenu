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

use Category;
use CMS;
use CMSCategory;
use Configuration;
use Context;
use Manufacturer;
use PrestaShop\Module\MainMenu\Repository\CmsRepository;
use PrestaShop\Module\MainMenu\Repository\LinkRepository;
use Product;
use Shop;
use Supplier;
use Symfony\Contracts\Translation\TranslatorInterface;
use Tools;
use Validate;

/**
 * Builds the menu tree from the configured items. The result does not depend on the current page, so it can be cached.
 */
final class MenuBuilder
{
    public function __construct(
        private readonly Context $context,
        private readonly LinkRepository $linkRepository,
        private readonly CmsRepository $cmsRepository,
        private readonly CategoryThumbnails $categoryThumbnails,
        private readonly TranslatorInterface $translator,
    ) {
    }

    /**
     * @param string[] $items Configured items, e.g. ["CAT3", "LNK1"]
     * @param int[] $customerGroups
     *
     * @return array<string, mixed> Root node of the menu
     */
    public function build(array $items, array $customerGroups): array
    {
        $root = MenuTree::makeNode(['label' => null, 'type' => 'root']);

        foreach ($items as $configuredItem) {
            $item = MenuItem::fromString((string) $configuredItem);
            if ($item !== null) {
                array_push($root['children'], ...$this->buildItem($item, $customerGroups));
            }
        }

        MenuTree::setDepth($root);

        return $root;
    }

    /**
     * @param int[] $customerGroups
     *
     * @return array<int, array<string, mixed>>
     */
    private function buildItem(MenuItem $item, array $customerGroups): array
    {
        $idLang = (int) $this->context->language->id;
        $link = $this->context->link;

        switch ($item->type) {
            case MenuItemType::Category:
                return $this->buildCategories(Category::getNestedCategories($item->id, $idLang, false, $customerGroups));

            case MenuItemType::Product:
                $product = new Product($item->id, false, $idLang);

                return $product->id ? [MenuTree::makeNode([
                    'type' => 'product',
                    'page_identifier' => 'product-' . $product->id,
                    'label' => $product->name,
                    'url' => $product->getLink(),
                ])] : [];

            case MenuItemType::CmsPage:
                $pages = CMS::getLinks($idLang, [$item->id]);

                return count($pages) ? [MenuTree::makeNode([
                    'type' => 'cms-page',
                    'page_identifier' => 'cms-page-' . $item->id,
                    'label' => $pages[0]['meta_title'],
                    'url' => $pages[0]['link'],
                ])] : [];

            case MenuItemType::CmsCategory:
                return [$this->buildCmsCategory($item->id, $idLang)];

            case MenuItemType::AllManufacturers:
                $children = [];
                foreach (Manufacturer::getManufacturers() as $manufacturer) {
                    $children[] = MenuTree::makeNode([
                        'type' => 'manufacturer',
                        'page_identifier' => 'manufacturer-' . $manufacturer['id_manufacturer'],
                        'label' => $manufacturer['name'],
                        'url' => $link->getManufacturerLink((int) $manufacturer['id_manufacturer'], Tools::str2url($manufacturer['name']), $idLang),
                    ]);
                }

                return [MenuTree::makeNode([
                    'type' => 'manufacturers',
                    'page_identifier' => 'manufacturers',
                    'label' => $this->translator->trans('All brands', [], 'Modules.Mainmenu.Admin'),
                    'url' => $link->getPageLink('manufacturer'),
                    'children' => $children,
                ])];

            case MenuItemType::Manufacturer:
                $manufacturer = new Manufacturer($item->id, $idLang);

                return $manufacturer->id ? [MenuTree::makeNode([
                    'type' => 'manufacturer',
                    'page_identifier' => 'manufacturer-' . $manufacturer->id,
                    'label' => $manufacturer->name,
                    'url' => $link->getManufacturerLink($manufacturer, null, $idLang),
                ])] : [];

            case MenuItemType::AllSuppliers:
                $children = [];
                foreach (Supplier::getSuppliers() as $supplier) {
                    $children[] = MenuTree::makeNode([
                        'type' => 'supplier',
                        'page_identifier' => 'supplier-' . $supplier['id_supplier'],
                        'label' => $supplier['name'],
                        'url' => $link->getSupplierLink((int) $supplier['id_supplier'], Tools::str2url($supplier['name']), $idLang),
                    ]);
                }

                return [MenuTree::makeNode([
                    'type' => 'suppliers',
                    'page_identifier' => 'suppliers',
                    'label' => $this->translator->trans('All suppliers', [], 'Modules.Mainmenu.Admin'),
                    'url' => $link->getPageLink('supplier'),
                    'children' => $children,
                ])];

            case MenuItemType::Supplier:
                $supplier = new Supplier($item->id, $idLang);

                return $supplier->id ? [MenuTree::makeNode([
                    'type' => 'supplier',
                    'page_identifier' => 'supplier-' . $supplier->id,
                    'label' => $supplier->name,
                    'url' => $link->getSupplierLink($supplier, null, $idLang),
                ])] : [];

            case MenuItemType::Shop:
                $shop = new Shop($item->id);

                return Validate::isLoadedObject($shop) ? [MenuTree::makeNode([
                    'type' => 'shop',
                    'page_identifier' => 'shop-' . $item->id,
                    'label' => $shop->name,
                    'url' => $shop->getBaseURL(),
                ])] : [];

            case MenuItemType::Link:
                $customLink = $this->linkRepository->findWithFallback(
                    $item->id,
                    $idLang,
                    (int) Shop::getContextShopID(),
                    (int) Configuration::get('PS_LANG_DEFAULT')
                );

                return $customLink !== null ? [MenuTree::makeNode([
                    'type' => 'link',
                    'page_identifier' => 'lnk-' . Tools::str2url($customLink['label']),
                    'label' => $customLink['label'],
                    'url' => $customLink['link'],
                    'open_in_new_window' => $customLink['new_window'],
                ])] : [];
        }
    }

    /**
     * @param array<int, array<string, mixed>> $categories Rows of Category::getNestedCategories(), already restricted to the customer groups
     *
     * @return array<int, array<string, mixed>>
     */
    private function buildCategories(array $categories): array
    {
        $nodes = [];
        $link = $this->context->link;

        foreach ($categories as $category) {
            if (!$category['active']) {
                continue;
            }

            $idCategory = (int) $category['id_category'];
            $imageUrls = [];
            foreach ($this->categoryThumbnails->getFiles($idCategory) as $file) {
                $imageUrls[] = $link->getMediaLink(_THEME_CAT_DIR_ . $file);
            }

            $nodes[] = MenuTree::makeNode([
                'type' => 'category',
                'page_identifier' => 'category-' . $category['id_category'],
                'label' => $category['name'],
                'url' => $category['level_depth'] > 1
                    ? $link->getCategoryLink($idCategory, $category['link_rewrite'])
                    : $link->getPageLink('index'),
                'current' => false,
                'image_urls' => $imageUrls,
                'children' => empty($category['children']) ? [] : $this->buildCategories($category['children']),
            ]);
        }

        return $nodes;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildCmsCategory(int $idCmsCategory, int $idLang): array
    {
        $idShop = (int) $this->context->shop->id;
        $category = new CMSCategory($idCmsCategory, $idLang);
        $children = [];

        foreach ($this->cmsRepository->getCategories($idCmsCategory, $idLang, $idShop) as $subCategory) {
            $children[] = $this->buildCmsCategory((int) $subCategory['id_cms_category'], $idLang);
        }

        foreach ($this->cmsRepository->getPages($idCmsCategory, $idLang, $idShop) as $page) {
            $children[] = MenuTree::makeNode([
                'type' => 'cms-page',
                'page_identifier' => 'cms-page-' . $page['id_cms'],
                'label' => $page['meta_title'],
                'url' => $this->context->link->getCMSLink((int) $page['id_cms'], $page['link_rewrite'], null, $idLang),
            ]);
        }

        return MenuTree::makeNode([
            'type' => 'cms-category',
            'page_identifier' => 'cms-category-' . $idCmsCategory,
            'label' => $category->name,
            'url' => $category->getLink(),
            'children' => $children,
        ]);
    }
}
