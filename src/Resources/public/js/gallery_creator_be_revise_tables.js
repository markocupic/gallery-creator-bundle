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
        new GalleryCreatorBeReviseTables();
    });

    const GalleryCreatorBeReviseTables = new Class({

        /**
         * Array with als albumID's
         */
        albumIDS: null,

        /**
         * Count errors
         */
        errors: 0,

        /**
         * Count completed requests
         */
        intRequestDone: 0,

        /**
         * Message box
         */
        messageBox: false,

        /**
         * Status box
         */
        statusBox: false,

        /**
         * Button
         */
        button: false,

        /**
         * Checkbox
         */
        checkbox: false,

        /**
         * Label checkbox
         */
        labelCheckbox: false,

        /**
         * Constructor
         */
        initialize: function () {
            let self = this;
            document.id('main').addClass('gcReviseDatabase');

            self.button = document.id('reviseTableBtn');
            self.checkbox = $$('input[name=reviseDatabase]')[0];
            self.labelCheckbox = $$('label[for=reviseDatabase]')[0];

            self.messageBox = new Element('div#messageBox');
            self.messageBox.addClass('gc_message');
            self.messageBox.inject($$('.tl_formbody_submit')[0], 'before');

            self.statusBox = new Element('div#statusBox', {
                'class': 'tl_status_box'
            });
            self.statusBox.inject(self.messageBox);


            self.button.addEventListener('click', function (event) {
                if (self.checkbox.checked) {
                    event.preventDefault();
                    self.button.fade(0);
                    self.checkbox.fade(0);
                    self.labelCheckbox.fade(0);
                    self.start();
                }
            });
        },

        /**
         * Kick off!
         */
        start: function () {
            this.intRequestDone = null;
            this.errors = 0;
            this.albumIDS = null;

            // Reset message box
            $$('#messageBox > p, #messageBox>.tl_status_box > p').each(function (el) {
                el.destroy();
            });

            (new Element('p', {'text': 'Please wait a moment...'})).inject(this.statusBox);
            this.getAlbumIDS();
        },

        /**
         * Get all album ids
         */
        getAlbumIDS: function () {
            let self = this;
            let myRequest = new Request.JSON({

                url: document.URL + '&isAjaxRequest=true&checkTables=true&getAlbumIDS=true',

                method: 'get',

                onSuccess: function (responseText) {
                    if (!responseText) return;
                    if(responseText['ids']){
                        self.albumIDS = responseText['ids'];
                        self.reviseTables();
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
         * Display error messages in the head section of the backend.
         */
        reviseTables: function () {
            let self = this;

            if (self.albumIDS === null) {
                return;
            }

            self.albumIDS.each(function (albumId) {
                let myRequest = new Request.JSON({

                    url: document.URL + '&isAjaxRequest=true&checkTables=true&reviseTables=true&albumId=' + albumId,

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


                        arrError.each(function (errorMsg) {
                            let error = new Element('p', {
                                    'class': 'tl_error',
                                    text: errorMsg
                                }
                            );
                            error.inject(self.messageBox);
                            self.errors++;
                        });
                    },

                    onComplete: function () {

                        self.intRequestDone++;

                        // Display next message
                        (new Element('p', {'text': 'Check album with ID ' + albumId + '.'})).inject(self.statusBox);

                        // Show final message, when all requests have completed
                        if (self.intRequestDone == self.albumIDS.length) {
                            (function () {
                                (new Element('p', {'class': 'tl_confirm', 'text': 'Revised the gallery creator database tables. ' + self.errors.toString() + ' error(s) found.'})).inject(self.messageBox);
                                self.button.fade(1);
                                self.checkbox.checked = false;
                                self.checkbox.fade(1);
                                self.labelCheckbox.fade(1);
                            }.delay(1000));
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
