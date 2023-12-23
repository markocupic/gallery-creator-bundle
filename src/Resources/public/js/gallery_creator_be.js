/*
 * This file is part of Gallery Creator Bundle.
 *
 * (c) Marko Cupic 2021 <m.cupic@gmx.ch>
 * @license GPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/gallery-creator-bundle
 */

(function ($) {
  window.addEvent('domready', function () {

    if (document.id('CheckAllGcPublishAlbums') !== null) {
      $('CheckAllGcPublishAlbums').addEvent('click', function () {
        if (this.checked) {
          $$('.album-control-field').each(function (el) {
            el.checked = true;
          });
        } else {
          $$('.album-control-field').each(function (el) {
            el.checked = false;
          });
        }
      });

      $$('.album-control-field').addEvent('click', function (el) {
        if (this.checked) {
          let inputs = $(this).getParents('li > input');
          inputs.each(function (el) {
            el.checked = true;
          });
        } else {
          let inputs = $(this).getParent('li').getElements('input');
          inputs.each(function (el) {
            el.checked = false;
          });
        }
      });
    }
  });
})(document.id);
