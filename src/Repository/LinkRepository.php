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

namespace PrestaShop\Module\MainMenu\Repository;

use Db;
use DbQuery;

/**
 * Custom links of the menu (tables linksmenutop and linksmenutop_lang).
 */
final class LinkRepository
{
    public function __construct(private readonly Db $db)
    {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findAll(int $idLang, int $idShop): array
    {
        return $this->db->executeS($this->getQuery($idLang, $idShop)) ?: [];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(int $idLink, int $idLang, int $idShop): ?array
    {
        $rows = $this->db->executeS($this->getQuery($idLang, $idShop)->where('l.`id_linksmenutop` = ' . $idLink));

        return $rows ? $rows[0] : null;
    }

    /**
     * Same as find(), with the label and URL of the fallback language when the link has no label in the requested one.
     *
     * @return array<string, mixed>|null
     */
    public function findWithFallback(int $idLink, int $idLang, int $idShop, int $idFallbackLang): ?array
    {
        $link = $this->find($idLink, $idLang, $idShop);
        if ($link !== null && (string) $link['label'] === '') {
            return $this->find($idLink, $idFallbackLang, $idShop);
        }

        return $link;
    }

    /**
     * @return array{link: array<int, string>, label: array<int, string>, new_window: bool}|null Values of each language, null when the link does not exist
     */
    public function getTranslations(int $idLink, int $idShop): ?array
    {
        $query = (new DbQuery())
            ->select('l.`new_window`, ll.`link`, ll.`label`, ll.`id_lang`')
            ->from('linksmenutop', 'l')
            ->leftJoin('linksmenutop_lang', 'll', 'l.`id_linksmenutop` = ll.`id_linksmenutop` AND ll.`id_shop` = ' . $idShop)
            ->where('l.`id_linksmenutop` = ' . $idLink)
            ->where('l.`id_shop` IN (0, ' . $idShop . ')');

        $rows = $this->db->executeS($query) ?: [];
        if (!$rows) {
            return null;
        }

        $translations = ['link' => [], 'label' => [], 'new_window' => false];
        foreach ($rows as $row) {
            if ($row['id_lang'] !== null) {
                $translations['link'][(int) $row['id_lang']] = (string) $row['link'];
                $translations['label'][(int) $row['id_lang']] = (string) $row['label'];
            }
            $translations['new_window'] = (bool) $row['new_window'];
        }

        return $translations;
    }

    /**
     * @param array<int, string> $links URL of each language
     * @param array<int, string> $labels Label of each language
     */
    public function add(array $links, array $labels, bool $newWindow, int $idShop): bool
    {
        if (!$this->db->insert('linksmenutop', ['new_window' => (int) $newWindow, 'id_shop' => $idShop])) {
            return false;
        }
        $idLink = (int) $this->db->Insert_ID();

        $result = true;
        foreach ($labels as $idLang => $label) {
            $result = $this->db->insert('linksmenutop_lang', [
                'id_linksmenutop' => $idLink,
                'id_lang' => (int) $idLang,
                'id_shop' => $idShop,
                'label' => pSQL($label),
                'link' => pSQL($links[$idLang] ?? ''),
            ]) && $result;
        }

        return $result;
    }

    /**
     * @param array<int, string> $links URL of each language
     * @param array<int, string> $labels Label of each language
     */
    public function update(int $idLink, array $links, array $labels, bool $newWindow, int $idShop): bool
    {
        $result = $this->db->update(
            'linksmenutop',
            ['new_window' => (int) $newWindow, 'id_shop' => $idShop],
            '`id_linksmenutop` = ' . $idLink
        );

        // Rows are replaced rather than updated: a language added after the link was created has no row yet
        foreach ($labels as $idLang => $label) {
            $result = $this->db->delete('linksmenutop_lang', '`id_linksmenutop` = ' . $idLink . ' AND `id_lang` = ' . (int) $idLang)
                && $this->db->insert('linksmenutop_lang', [
                    'id_linksmenutop' => $idLink,
                    'id_lang' => (int) $idLang,
                    'id_shop' => $idShop,
                    'label' => pSQL((string) $label),
                    'link' => pSQL((string) ($links[$idLang] ?? '')),
                ])
                && $result;
        }

        return $result;
    }

    /**
     * @return bool False when the link is not one of this shop
     */
    public function delete(int $idLink, int $idShop): bool
    {
        $isLinkOfShop = (bool) $this->db->getValue(
            (new DbQuery())
                ->select('1')
                ->from('linksmenutop')
                ->where('`id_linksmenutop` = ' . $idLink)
                ->where('`id_shop` = ' . $idShop)
        );

        return $isLinkOfShop
            && $this->db->delete('linksmenutop', '`id_linksmenutop` = ' . $idLink . ' AND `id_shop` = ' . $idShop)
            && $this->db->delete('linksmenutop_lang', '`id_linksmenutop` = ' . $idLink);
    }

    /**
     * Copies the links of a shop to a new shop.
     */
    public function duplicate(int $idShopFrom, int $idShopTo): void
    {
        $links = $this->db->executeS(
            (new DbQuery())->select('`id_linksmenutop`, `new_window`')->from('linksmenutop')->where('`id_shop` = ' . $idShopFrom)
        );

        foreach ($links ?: [] as $link) {
            $translations = $this->db->executeS(
                (new DbQuery())
                    ->select('`id_lang`, `label`, `link`')
                    ->from('linksmenutop_lang')
                    ->where('`id_linksmenutop` = ' . (int) $link['id_linksmenutop'])
                    ->where('`id_shop` = ' . $idShopFrom)
            ) ?: [];

            $this->add(
                array_column($translations, 'link', 'id_lang'),
                array_column($translations, 'label', 'id_lang'),
                (bool) $link['new_window'],
                $idShopTo
            );
        }
    }

    public function createTables(): bool
    {
        return $this->db->execute('
            CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'linksmenutop` (
                `id_linksmenutop` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                `id_shop` INT(11) UNSIGNED NOT NULL,
                `new_window` TINYINT(1) NOT NULL,
                INDEX (`id_shop`)
            ) ENGINE = ' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8mb4;')
            && $this->db->execute('
            CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'linksmenutop_lang` (
                `id_linksmenutop` INT(11) UNSIGNED NOT NULL,
                `id_lang` INT(11) UNSIGNED NOT NULL,
                `id_shop` INT(11) UNSIGNED NOT NULL,
                `label` VARCHAR(128) NOT NULL,
                `link` VARCHAR(128) NOT NULL,
                INDEX (`id_linksmenutop`, `id_lang`, `id_shop`)
            ) ENGINE = ' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8mb4;');
    }

    public function dropTables(): bool
    {
        return $this->db->execute('DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'linksmenutop`, `' . _DB_PREFIX_ . 'linksmenutop_lang`');
    }

    private function getQuery(int $idLang, int $idShop): DbQuery
    {
        return (new DbQuery())
            ->select('l.`id_linksmenutop`, l.`new_window`, s.`name`, ll.`link`, ll.`label`')
            ->from('linksmenutop', 'l')
            ->leftJoin(
                'linksmenutop_lang',
                'll',
                'l.`id_linksmenutop` = ll.`id_linksmenutop` AND ll.`id_lang` = ' . $idLang . ' AND ll.`id_shop` = ' . $idShop
            )
            ->leftJoin('shop', 's', 'l.`id_shop` = s.`id_shop`')
            ->where('l.`id_shop` IN (0, ' . $idShop . ')');
    }
}
