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

namespace PrestaShop\Module\MainMenu\Form\Type;

use PrestaShop\PrestaShop\Core\ConstraintValidator\Constraints\DefaultLanguage;
use PrestaShopBundle\Form\Admin\Type\SwitchType;
use PrestaShopBundle\Form\Admin\Type\TranslatableType;
use PrestaShopBundle\Form\Admin\Type\TranslatorAwareType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\Length;

/**
 * A custom link of the menu.
 */
final class LinkType extends TranslatorAwareType
{
    private const MAX_LENGTH = 128;

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('label', TranslatableType::class, [
                'label' => $this->trans('Label', 'Admin.Global'),
                'type' => TextType::class,
                'constraints' => [new DefaultLanguage()],
                'options' => ['constraints' => [new Length(['max' => self::MAX_LENGTH])]],
            ])
            ->add('link', TranslatableType::class, [
                'label' => $this->trans('Link', 'Admin.Global'),
                'type' => TextType::class,
                'constraints' => [new DefaultLanguage()],
                'options' => [
                    'attr' => ['placeholder' => 'http://www.example.com'],
                    'constraints' => [new Length(['max' => self::MAX_LENGTH])],
                ],
            ])
            ->add('new_window', SwitchType::class, [
                'label' => $this->trans('New window', 'Admin.Navigation.Header'),
                'required' => false,
            ]);
    }
}
