/*
 * This file is part of Gallery Creator Bundle.
 *
 * (c) Marko Cupic 2021 <m.cupic@gmx.ch>
 * @license GPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/gallery-creator-bundle
 */

// Dollar Safe Mode
(function ($) {
    window.addEvent('domready', function () {
        let objGalleryCreator = new GalleryCreatorBeCheckTables();
        objGalleryCreator.start();
    });

    const GalleryCreatorBeCheckTables = new Class({

        /**
         * Array with als albumID's
         */
        albumIDS: null,

        /**
         * Count album check requests
         */
        countTests: 0,

        /**
         * constructor
         */
        initialize: function () {
            document.id('main').addClass('gc-check-tables');
        },

        /**
         * kick off!
         */
        start: function () {

            let self = this;

            // Run next check after 60'
            let now = Math.floor(new Date().getTime() / 1000);
            let doCheck = true;
            let intervall = 3600;
            let objCookie = null;

            if (Cookie.read('contao_gallery_creator_be')) {
                objCookie = JSON.decode(atob(Cookie.read('contao_gallery_creator_be')));
                if (objCookie.tableCheck.lastCheck) {
                    if (now - objCookie.tableCheck.lastCheck < intervall) {
                        doCheck = false;
                    } else {
                        objCookie.tableCheck.lastCheck = now;
                        Cookie.write('contao_gallery_creator_be', btoa(JSON.encode(objCookie)), {path: Contao.path});
                    }
                } else {
                    objCookie.tableCheck.lastCheck = now;
                    Cookie.write('contao_gallery_creator_be', btoa(JSON.encode(objCookie)), {path: Contao.path});
                }
            } else {
                objCookie = {
                    tableCheck: {
                        lastCheck: now
                    }
                };
                Cookie.write('contao_gallery_creator_be', btoa(JSON.encode(objCookie)), {path: Contao.path});
            }

            if (doCheck === true) {
                self.getAlbumIDS();
            }
        },

        /**
         * Get all album ids.
         */
        getAlbumIDS: function () {
            let self = this;
            let myRequest = new Request.JSON({

                url: document.URL + '&isAjaxRequest=true&checkTables=true&getAlbumIDS=true',
                method: 'get',

                onSuccess: function (responseText) {
                    if (!responseText) return;
                    if (responseText['ids']) {
                        self.albumIDS = responseText['ids'];
                        self.checkTables();
                    }
                },

                onError: function () {
                    //
                }
            });
            // Fire request (get AlbumIDS)
            myRequest.send();
        },

        /**
         * Fire a request for each album.
         * Display error messages in the head section of the backend
         */
        checkTables: function () {
            let self = this;

            if (self.albumIDS === null) {
                return;
            }

            self.albumIDS.each(function (albumId) {
                self.countTests++;
                let counter = self.countTests;
                let myRequest = new Request.JSON({

                    url: document.URL + '&isAjaxRequest=true&checkTables=true&albumId=' + albumId,

                    method: 'get',

                    // Any calls made to start while the request is running will be chained up,
                    // and will take place as soon as the current request has finished,
                    // one after another.
                    chain: true,

                    onSuccess: function (responseText) {
                        if (!responseText) {
                            return;
                        }

                        if (responseText.errors.toString() == '') {
                            return;
                        }

                        let arrError = responseText.errors;

                        if (!$$('.tl_message')[0]) {
                            let messageBox = new Element('div');
                            messageBox.addClass('tl_message');
                            messageBox.inject(document.id('tl_buttons'), 'after');
                        }
                        arrError.each(function (errorMsg) {
                            let error = new Element('p', {
                                    'class': 'tl_error',
                                    text: errorMsg
                                }
                            );
                            error.inject($$('.tl_message')[0]);
                        });
                    },

                    onComplete: function () {
                        // Destroy previous status boxes
                        if ($$('.gc-check-tables-status-box')) {
                            $$('.gc-check-tables-status-box').each(function (el) {
                                el.destroy();
                            });
                        }

                        // Inject status box into DOM
                        $$('#tl_listing .tl_folder_top')[0].setStyle('position', 'relative');
                        let statusBox = new Element('p#statusBox' + albumId, {
                            'class': 'gc-check-tables-status-box',
                            text: 'Check album with ID ' + albumId + '.',
                        });

                        statusBox.inject($$('.tl_folder_top')[0]);

                        setTimeout(function () {
                            if (document.id('statusBox' + albumId)) {
                                document.id('statusBox' + albumId).destroy();
                            }
                        }, 1000);

                        if (counter === self.albumIDS.length) {
                            setTimeout(function () {
                                let statusBox = new Element('p#statusBoxChecksCompleted', {
                                    'class': 'gc-check-tables-status-box',
                                    text: 'All checks completed.',
                                });
                                statusBox.inject($$('.tl_folder_top')[0]);
                            }, 2000);

                            // Delete the last status box after 20s of delay
                            (function () {
                                if (document.id('statusBoxChecksCompleted')) {
                                    document.id('statusBoxChecksCompleted').destroy();
                                }
                            }.delay(20000));
                        }
                    },

                    onError: function () {
                        //
                    }
                });
                // Fire request
                myRequest.send();

            });
        }
    });
})(document.id);

