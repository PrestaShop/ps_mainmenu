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
(function () {
  'use strict';

  function initLinkGrid() {
    var component = window.prestashop && window.prestashop.component;

    if (!component || !document.getElementById('ps_mainmenu_link_grid')) {
      return;
    }

    var grid = new component.Grid('ps_mainmenu_link');
    grid.addExtension(new component.GridExtensions.LinkRowActionExtension());
    grid.addExtension(new component.GridExtensions.SubmitRowActionExtension());
  }

  function initMenuItems() {
    var container = document.querySelector('.js-menu-items');

    if (!container) {
      return;
    }

    var selected = container.querySelector('.js-menu-items-selected');
    var available = container.querySelector('.js-menu-items-available');
    var product = container.querySelector('.js-menu-items-product');
    var values = document.querySelector('.js-menu-items-values');

    // The form field is a collection of hidden inputs, rebuilt from the list in its displayed order
    function serialize() {
      values.textContent = '';
      Array.prototype.forEach.call(selected.options, function (option, index) {
        var input = document.createElement('input');
        input.type = 'hidden';
        input.name = container.dataset.fieldName + '[' + index + ']';
        input.value = option.value;
        values.appendChild(input);
      });
    }

    function isSelected(value) {
      return Array.prototype.some.call(selected.options, function (option) {
        return option.value === value;
      });
    }

    function select(value, label) {
      if (isSelected(value)) {
        return;
      }
      selected.appendChild(new Option(label.trim(), value));
    }

    function add() {
      Array.prototype.slice.call(available.selectedOptions).forEach(function (option) {
        select(option.value, option.textContent);
        option.hidden = true;
        option.selected = false;
      });
      serialize();
    }

    function remove() {
      Array.prototype.slice.call(selected.selectedOptions).forEach(function (option) {
        var source = Array.prototype.filter.call(available.options, function (candidate) {
          return candidate.value === option.value;
        })[0];

        if (source) {
          source.hidden = false;
        }
        option.remove();
      });
      serialize();
    }

    function addProduct() {
      var id = parseInt(product.value, 10);

      if (!id || id < 1) {
        product.focus();

        return;
      }
      select('PRD' + id, product.dataset.label + id);
      product.value = '';
      serialize();
    }

    function move(up) {
      var options = Array.prototype.slice.call(selected.selectedOptions);

      if (options.length !== 1) {
        return;
      }

      var option = options[0];
      var sibling = up ? option.previousElementSibling : option.nextElementSibling;

      if (sibling) {
        selected.insertBefore(option, up ? sibling : sibling.nextElementSibling);
      }
      serialize();
    }

    container.querySelector('.js-menu-items-add').addEventListener('click', add);
    container.querySelector('.js-menu-items-remove').addEventListener('click', remove);
    container.querySelector('.js-menu-items-add-product').addEventListener('click', addProduct);
    container.querySelector('.js-menu-items-up').addEventListener('click', function () {
      move(true);
    });
    container.querySelector('.js-menu-items-down').addEventListener('click', function () {
      move(false);
    });
    available.addEventListener('dblclick', add);
    selected.addEventListener('dblclick', remove);
    product.addEventListener('keydown', function (event) {
      if (event.key === 'Enter') {
        event.preventDefault();
        addProduct();
      }
    });

    serialize();
  }

  document.addEventListener('DOMContentLoaded', initMenuItems);

  // The grid components are exposed by a jQuery ready handler of the back office, which runs after DOMContentLoaded
  window.$(initLinkGrid);
})();
