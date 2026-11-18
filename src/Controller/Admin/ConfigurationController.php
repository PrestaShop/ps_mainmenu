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

namespace PrestaShop\Module\MainMenu\Controller\Admin;

use PrestaShop\Module\MainMenu\Cache\MenuCache;
use PrestaShop\Module\MainMenu\Form\MenuItemChoices;
use PrestaShop\Module\MainMenu\Form\Type\LinkType;
use PrestaShop\Module\MainMenu\Form\Type\MenuItemsType;
use PrestaShop\Module\MainMenu\Grid\LinkFilters;
use PrestaShop\Module\MainMenu\Menu\MenuConfiguration;
use PrestaShop\Module\MainMenu\Menu\MenuItem;
use PrestaShop\Module\MainMenu\Menu\MenuItemType;
use PrestaShop\Module\MainMenu\Repository\LinkRepository;
use PrestaShop\PrestaShop\Core\Grid\GridFactoryInterface;
use PrestaShopBundle\Controller\Admin\PrestaShopAdminController;
use PrestaShopBundle\Security\Attribute\AdminSecurity;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Configuration page of the module: items of the menu and custom links.
 * The menu belongs to one shop, so nothing can be changed from the "All shops" or a shop group context.
 */
class ConfigurationController extends PrestaShopAdminController
{
    private const INDEX_ROUTE = 'ps_mainmenu_configuration';

    #[AdminSecurity("is_granted('read', request.get('_legacy_controller'))", redirectRoute: 'admin_homepage')]
    public function indexAction(
        Request $request,
        MenuConfiguration $menuConfiguration,
        MenuItemChoices $menuItemChoices,
        #[Autowire(service: 'ps_mainmenu.grid.link_grid_factory')]
        GridFactoryInterface $linkGridFactory,
    ): Response {
        $itemsForm = $this->createForm(MenuItemsType::class, ['items' => $menuConfiguration->getItems()]);

        return $this->renderConfiguration($request, $itemsForm, $menuItemChoices, $linkGridFactory);
    }

    #[AdminSecurity("is_granted('update', request.get('_legacy_controller'))", redirectRoute: self::INDEX_ROUTE)]
    public function saveItemsAction(
        Request $request,
        MenuConfiguration $menuConfiguration,
        MenuCache $menuCache,
        MenuItemChoices $menuItemChoices,
        #[Autowire(service: 'ps_mainmenu.grid.link_grid_factory')]
        GridFactoryInterface $linkGridFactory,
    ): Response {
        if (!$this->getShopContext()->isSingleShopContext()) {
            return $this->redirectToRoute(self::INDEX_ROUTE);
        }

        $itemsForm = $this->createForm(MenuItemsType::class);
        $itemsForm->handleRequest($request);

        if ($itemsForm->isSubmitted() && $itemsForm->isValid()) {
            $items = $itemsForm->getData()['items'] ?? [];
            if ($menuConfiguration->saveItems($items, $this->getShopContext()->getId())) {
                $menuCache->clear();
                $this->addFlash('success', $this->trans('The settings have been updated.', [], 'Admin.Notifications.Success'));

                return $this->redirectToRoute(self::INDEX_ROUTE);
            }
            $this->addFlash('error', $this->trans('Unable to update settings.', [], 'Admin.Notifications.Error'));
        }

        return $this->renderConfiguration($request, $itemsForm, $menuItemChoices, $linkGridFactory);
    }

    #[AdminSecurity("is_granted('create', request.get('_legacy_controller'))", redirectRoute: self::INDEX_ROUTE)]
    public function createLinkAction(Request $request, LinkRepository $linkRepository, MenuCache $menuCache): Response
    {
        if (!$this->getShopContext()->isSingleShopContext()) {
            return $this->redirectToRoute(self::INDEX_ROUTE);
        }

        $form = $this->createForm(LinkType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            if ($linkRepository->add($this->toStrings($data['link']), $this->toStrings($data['label']), (bool) $data['new_window'], $this->getShopContext()->getId())) {
                $menuCache->clear();
                $this->addFlash('success', $this->trans('The link has been added.', [], 'Modules.Mainmenu.Admin'));

                return $this->redirectToRoute(self::INDEX_ROUTE);
            }
            $this->addFlash('error', $this->trans('An error occurred while creating an object.', [], 'Admin.Notifications.Error'));
        }

        return $this->renderLinkForm($request, $form, $this->trans('Add a new link', [], 'Modules.Mainmenu.Admin'));
    }

    #[AdminSecurity("is_granted('update', request.get('_legacy_controller'))", redirectRoute: self::INDEX_ROUTE)]
    public function editLinkAction(Request $request, int $linkId, LinkRepository $linkRepository, MenuCache $menuCache): Response
    {
        $idShop = $this->getShopContext()->getId();
        $translations = $this->getShopContext()->isSingleShopContext() ? $linkRepository->getTranslations($linkId, $idShop) : null;
        if ($translations === null) {
            return $this->redirectToRoute(self::INDEX_ROUTE);
        }

        $form = $this->createForm(LinkType::class, $translations);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $linkRepository->update($linkId, $this->toStrings($data['link']), $this->toStrings($data['label']), (bool) $data['new_window'], $idShop);
            $menuCache->clear();
            $this->addFlash('success', $this->trans('The link has been edited.', [], 'Modules.Mainmenu.Admin'));

            return $this->redirectToRoute(self::INDEX_ROUTE);
        }

        return $this->renderLinkForm($request, $form, $this->trans('Update link', [], 'Modules.Mainmenu.Admin'));
    }

    #[AdminSecurity("is_granted('delete', request.get('_legacy_controller'))", redirectRoute: self::INDEX_ROUTE)]
    public function deleteLinkAction(
        int $linkId,
        LinkRepository $linkRepository,
        MenuConfiguration $menuConfiguration,
        MenuCache $menuCache,
    ): RedirectResponse {
        if (!$this->getShopContext()->isSingleShopContext()) {
            return $this->redirectToRoute(self::INDEX_ROUTE);
        }

        $idShop = $this->getShopContext()->getId();
        if ($linkRepository->delete($linkId, $idShop)) {
            $menuConfiguration->removeItem(new MenuItem(MenuItemType::Link, $linkId), $idShop);
            $menuCache->clear();
            $this->addFlash('success', $this->trans('The link has been removed.', [], 'Modules.Mainmenu.Admin'));
        } else {
            $this->addFlash('error', $this->trans('An error occurred during deletion.', [], 'Admin.Notifications.Error'));
        }

        return $this->redirectToRoute(self::INDEX_ROUTE);
    }

    private function renderConfiguration(
        Request $request,
        FormInterface $itemsForm,
        MenuItemChoices $menuItemChoices,
        GridFactoryInterface $linkGridFactory,
    ): Response {
        $shopContext = $this->getShopContext();
        $items = MenuConfiguration::normalize($itemsForm->getData()['items'] ?? []);
        $linkGrid = $linkGridFactory->getGrid(new LinkFilters($this->getLanguageContext()->getId(), $shopContext->getId()));

        return $this->render('@Modules/ps_mainmenu/views/templates/admin/configuration.html.twig', [
            'itemsForm' => $itemsForm->createView(),
            'selectedItems' => $menuItemChoices->getSelected($items),
            'availableItems' => $menuItemChoices->getAvailable($items),
            'linkGrid' => $this->presentGrid($linkGrid),
            'isSingleShopContext' => $shopContext->isSingleShopContext(),
            'shopName' => $shopContext->getName(),
            'isMultiShopUsed' => $shopContext->isMultiShopUsed(),
            'enableSidebar' => true,
            'help_link' => $this->generateSidebarLink($request->attributes->get('_legacy_controller')),
            'layoutTitle' => $this->trans('Main menu', [], 'Modules.Mainmenu.Admin'),
            'layoutHeaderToolbarBtn' => $shopContext->isSingleShopContext() ? [
                'add' => [
                    'href' => $this->generateUrl('ps_mainmenu_link_create'),
                    'desc' => $this->trans('Add a new link', [], 'Modules.Mainmenu.Admin'),
                    'icon' => 'add_circle_outline',
                ],
            ] : [],
        ]);
    }

    private function renderLinkForm(Request $request, FormInterface $form, string $title): Response
    {
        return $this->render('@Modules/ps_mainmenu/views/templates/admin/link_form.html.twig', [
            'linkForm' => $form->createView(),
            'title' => $title,
            'enableSidebar' => true,
            'help_link' => $this->generateSidebarLink($request->attributes->get('_legacy_controller')),
            'layoutTitle' => $title,
        ]);
    }

    /**
     * @param array<int, string|null>|null $translations Values of a translatable field, null for the languages left empty
     *
     * @return array<int, string>
     */
    private function toStrings(?array $translations): array
    {
        return array_map('strval', $translations ?? []);
    }
}
