/*
 * This file is part of Gallery Creator Bundle.
 *
 * (c) Marko Cupic 2024 <m.cupic@gmx.ch>
 * @license MIT
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/gallery-creator-bundle
 */

// Dollar Safe Mode
(function ($) {
    window.addEvent('domready', function () {
        var objGalleryCreator = new GalleryCreatorBeCheckTables();
        objGalleryCreator.start();
    });


    /**
     * Class GalleryCreatorBeCheckTables
     *
     * Provide methods to check tables
     * @copyright  Marko Cupic 2015
     * @author     Marko Cupic <m.cupic@gmx.ch>
     */
    var GalleryCreatorBeCheckTables = new Class({

        /**
         * Array with als albumID's
         */
        albumIDS: null,

        /**
         * constructor
         */
        initialize: function () {
            document.id('main').addClass('gc_check_tables');
        },

        /**
         * kick off!
         */
        start: function () {

            // Run next check after 10'
            let now = Math.floor(new Date().getTime() / 1000);
            let doCheck = true;
            let intervall = 600;
            let objCookie = null;

            if (Cookie.read('ContaoGalleryCreatorBe')) {
                objCookie = JSON.decode(atob(Cookie.read('ContaoGalleryCreatorBe')));
                if (objCookie.tableCheck.lastCheck) {
                    if (now - objCookie.tableCheck.lastCheck < intervall) {
                        doCheck = false;
                    } else {
                        objCookie.tableCheck.lastCheck = now;
                        Cookie.write('ContaoGalleryCreatorBe', btoa(JSON.encode(objCookie)), {path: Contao.path});
                    }
                } else {
                    objCookie.tableCheck.lastCheck = now;
                    Cookie.write('ContaoGalleryCreatorBe', btoa(JSON.encode(objCookie)), {path: Contao.path});
                }
            } else {
                objCookie = {
                    tableCheck: {
                        lastCheck: now
                    }
                };
                Cookie.write('ContaoGalleryCreatorBe', btoa(JSON.encode(objCookie)), {path: Contao.path});
            }

            if (doCheck === true) {
                this.getAlbumIDS();
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

                    let responseString = responseText.albumIDS.toString();
                    if (responseString !== '') {
                        self.albumIDS = responseString.split(",");
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
            if (this.albumIDS === null) {
                return;
            }
            this.albumIDS.each(function (albumId) {

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
                        if (responseText.errors.toString() === '') {
                            return;
                        }
                        let arrError = responseText.errors.toString().split('***');
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
                        if ($$('.tl_status_box')) {
                            $$('.tl_status_box').each(function (el) {
                                el.destroy();
                            });
                        }

                        // Inject status box into DOM
                        $$('#tl_listing .tl_folder_top')[0].setStyle('position', 'relative');
                        let statusBox = new Element('p#statusBox' + albumId, {
                            'class': 'tl_status_box',
                            text: 'Check album with ID ' + albumId + '.'
                        });
                        statusBox.inject($$('.tl_folder_top')[0]);

                        // Delete the last status box after 10s of delay
                        (function () {
                            if (document.id('statusBox' + albumId)) {
                                document.id('statusBox' + albumId).destroy();
                            }
                        }.delay(10000));
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

