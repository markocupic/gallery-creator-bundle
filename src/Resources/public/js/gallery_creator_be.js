/*
 * This file is part of Gallery Creator Bundle (extension for the Contao CMS).
 *
 * (c) Marko Cupic
 *
 * @license MIT
 */

(function ($) {
    window.addEvent('domready', function () {

        if (document.id('check_all_gc_publish_albums') !== null) {
            $('check_all_gc_publish_albums').addEvent('click', function () {
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
                    var inputs = $(this).getParents('li > input');
                    inputs.each(function (el) {
                        el.checked = true;
                    });
                } else {
                    var inputs = $(this).getParent('li').getElements('input');
                    inputs.each(function (el) {
                        el.checked = false;
                    });
                }
            });
        }
    });
})(document.id);