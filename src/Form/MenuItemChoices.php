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

namespace PrestaShop\Module\MainMenu\Form;

use Category;
use CMS;
use CMSCategory;
use Manufacturer;
use PrestaShop\Module\MainMenu\Menu\MenuItem;
use PrestaShop\Module\MainMenu\Menu\MenuItemType;
use PrestaShop\Module\MainMenu\Repository\CategoryRepository;
use PrestaShop\Module\MainMenu\Repository\CmsRepository;
use PrestaShop\Module\MainMenu\Repository\LinkRepository;
use Product;
use Shop;
use Supplier;
use Symfony\Contracts\Translation\TranslatorInterface;
use Validate;

/**
 * Items shown by the configuration page: the ones of the menu, and the ones which can be added to it.
 */
final class MenuItemChoices
{
    private const INDENTATION = "\u{00A0}\u{00A0}\u{00A0}\u{00A0}\u{00A0}";

    private int $idLang = 0;
    private int $idDefaultLang = 0;
    private int $idShop = 0;

    public function __construct(
        private readonly LinkRepository $linkRepository,
        private readonly CmsRepository $cmsRepository,
        private readonly CategoryRepository $categoryRepository,
        private readonly TranslatorInterface $translator,
    ) {
    }

    /**
     * The menu belongs to one shop: the choices are those of this shop, labelled in this language.
     */
    public function forShop(int $idShop, int $idLang, int $idDefaultLang): self
    {
        $choices = clone $this;
        $choices->idShop = $idShop;
        $choices->idLang = $idLang;
        $choices->idDefaultLang = $idDefaultLang;

        return $choices;
    }

    /**
     * @param string[] $items Items of the menu
     *
     * @return array<string, string> Labels by item; items pointing to something which no longer exists are left out
     */
    public function getSelected(array $items): array
    {
        $selected = [];
        foreach ($items as $value) {
            $item = MenuItem::fromString((string) $value);
            $label = $item === null ? null : $this->getLabel($item);
            if ($label !== null) {
                $selected[(string) $item] = $label;
            }
        }

        return $selected;
    }

    /**
     * @return array<string, array<string, string>> Labels by item, by group
     */
    public function getAvailable(): array
    {
        $groups = [
            $this->trans('CMS') => $this->getCmsChoices(0, 1),
            $this->trans('Supplier', 'Admin.Global') => $this->getSupplierChoices(),
            $this->trans('Brand', 'Admin.Global') => $this->getManufacturerChoices(),
            $this->trans('Categories', 'Admin.Global') => $this->getCategoryChoices(),
        ];
        if (Shop::isFeatureActive()) {
            $groups[$this->trans('Shops')] = $this->getShopChoices();
        }
        $groups[$this->trans('Menu Top Links')] = $this->getLinkChoices();

        return $groups;
    }

    private function getLabel(MenuItem $item): ?string
    {
        switch ($item->type) {
            case MenuItemType::AllManufacturers:
                return $this->trans('All brands');

            case MenuItemType::AllSuppliers:
                return $this->trans('All suppliers');

            case MenuItemType::Link:
                $link = $this->linkRepository->findWithFallback($item->id, $this->idLang, $this->idShop, $this->idDefaultLang);

                return $link === null ? null : (string) $link['label'];

            case MenuItemType::CmsPage:
                $page = new CMS($item->id, $this->idLang);

                return Validate::isLoadedObject($page) ? (string) $page->meta_title : null;

            case MenuItemType::Category:
                $object = new Category($item->id, $this->idLang);
                break;

            case MenuItemType::CmsCategory:
                $object = new CMSCategory($item->id, $this->idLang);
                break;

            case MenuItemType::Manufacturer:
                $object = new Manufacturer($item->id, $this->idLang);
                break;

            case MenuItemType::Product:
                $object = new Product($item->id, false, $this->idLang);
                break;

            case MenuItemType::Shop:
                $object = new Shop($item->id);
                break;

            case MenuItemType::Supplier:
                $object = new Supplier($item->id, $this->idLang);
                break;
        }

        return Validate::isLoadedObject($object) ? (string) $object->name : null;
    }

    /**
     * @return array<string, string>
     */
    private function getCmsChoices(int $idParent, int $depth): array
    {
        $choices = [];
        $indentation = str_repeat(self::INDENTATION, $depth);

        foreach ($this->cmsRepository->getCategories($idParent, $this->idLang, $this->idShop) as $category) {
            $idCategory = (int) $category['id_cms_category'];
            $choices[(string) new MenuItem(MenuItemType::CmsCategory, $idCategory)] = $indentation . $category['name'];
            $choices += $this->getCmsChoices($idCategory, $depth + 1);
        }

        foreach ($this->cmsRepository->getPages($idParent, $this->idLang, $this->idShop) as $page) {
            $choices[(string) new MenuItem(MenuItemType::CmsPage, (int) $page['id_cms'])] = $indentation . $page['meta_title'];
        }

        return $choices;
    }

    /**
     * @return array<string, string>
     */
    private function getSupplierChoices(): array
    {
        $choices = [(string) new MenuItem(MenuItemType::AllSuppliers, 0) => $this->trans('All suppliers')];
        foreach (Supplier::getSuppliers(false, $this->idLang) ?: [] as $supplier) {
            $choices[(string) new MenuItem(MenuItemType::Supplier, (int) $supplier['id_supplier'])] = self::INDENTATION . $supplier['name'];
        }

        return $choices;
    }

    /**
     * @return array<string, string>
     */
    private function getManufacturerChoices(): array
    {
        $choices = [(string) new MenuItem(MenuItemType::AllManufacturers, 0) => $this->trans('All brands')];
        foreach (Manufacturer::getManufacturers(false, $this->idLang) ?: [] as $manufacturer) {
            $choices[(string) new MenuItem(MenuItemType::Manufacturer, (int) $manufacturer['id_manufacturer'])] = self::INDENTATION . $manufacturer['name'];
        }

        return $choices;
    }

    /**
     * @return array<string, string>
     */
    private function getCategoryChoices(): array
    {
        return $this->flattenCategories($this->categoryRepository->getNestedCategoriesOfShop($this->idShop, $this->idLang));
    }

    /**
     * @param array<int, array<string, mixed>> $categories
     *
     * @return array<string, string>
     */
    private function flattenCategories(array $categories): array
    {
        $choices = [];
        foreach ($categories as $category) {
            if (isset($category['id_category'])) {
                $choices[(string) new MenuItem(MenuItemType::Category, (int) $category['id_category'])]
                    = str_repeat(self::INDENTATION, (int) $category['level_depth']) . $category['name'];
            }
            if (!empty($category['children'])) {
                $choices += $this->flattenCategories($category['children']);
            }
        }

        return $choices;
    }

    /**
     * @return array<string, string>
     */
    private function getShopChoices(): array
    {
        $choices = [];
        foreach (Shop::getShopsCollection() as $shop) {
            /** @var Shop $shop */
            if ($shop->setUrl() || $shop->getBaseURL()) {
                $choices[(string) new MenuItem(MenuItemType::Shop, (int) $shop->id)] = self::INDENTATION . $shop->name;
            }
        }

        return $choices;
    }

    /**
     * @return array<string, string>
     */
    private function getLinkChoices(): array
    {
        $choices = [];
        foreach ($this->linkRepository->findAll($this->idLang, $this->idShop) as $link) {
            $idLink = (int) $link['id_linksmenutop'];
            if ((string) $link['label'] === '') {
                $link = $this->linkRepository->find($idLink, $this->idDefaultLang, $this->idShop) ?? $link;
            }
            $choices[(string) new MenuItem(MenuItemType::Link, $idLink)] = self::INDENTATION . $link['label'];
        }

        return $choices;
    }

    private function trans(string $message, string $domain = 'Modules.Mainmenu.Admin'): string
    {
        return $this->translator->trans($message, [], $domain);
    }
}
