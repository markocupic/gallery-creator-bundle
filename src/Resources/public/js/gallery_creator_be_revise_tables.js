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
      document.id('main').addClass('gc_revise_database');

      this.button = document.id('reviseTableBtn');
      this.checkbox = $$('input[name=revise_database]')[0];
      this.labelCheckbox = $$('label[for=revise_database]')[0];

      this.messageBox = new Element('div#messageBox');
      this.messageBox.addClass('gc_message');
      this.messageBox.inject($$('.tl_formbody_submit')[0], 'before');

      this.statusBox = new Element('p#statusBox', {
        'class': 'tl_status_box'
      });
      this.statusBox.inject(this.messageBox);


      this.button.addEvent('click', function () {
        if (self.checkbox.checked) {
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
      this.errors = null;
      this.albumIDS = null;
      $$('#messageBox .tl_error').each(function (el) {
        el.destroy();
      });
      this.statusBox.set('text', 'Please wait a moment...');
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

          let responseString = responseText.albumIDS.toString();
          if (responseString != '') {
            self.albumIDS = responseString.split(",");
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

      if (this.albumIDS === null) {
        return;
      }

      this.albumIDS.each(function (albumId) {
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
            let arrError = responseText.errors.toString().split('***');


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
            self.statusBox.set('text', 'Check album with ID ' + albumId + '.');

            // Show final message, when all requests have completed
            if (self.intRequestDone == self.albumIDS.length) {
              (function () {
                self.statusBox.set('text', 'Database cleaned up. ' + self.errors.toInt().toString() + ' errors found.');
                self.button.fade(1);
                self.checkbox.checked = false;
                self.checkbox.fade(1);
                self.labelCheckbox.fade(1);
              }.delay(3000));
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

