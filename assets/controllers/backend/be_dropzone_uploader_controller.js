/*
 * This file is part of Gallery Creator Bundle.
 *
 * (c) Marko Cupic <m.cupic@gmx.ch>
 * @license GPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/gallery-creator-bundle
 */

import {Controller} from '@hotwired/stimulus';

/**
 * Initializes the Dropzone uploader inside the album "file upload" widget.
 *
 * The Dropzone library itself is loaded via TL_JAVASCRIPT (see
 * GalleryCreatorAlbums::getFileUploadWidget), so we use the global here. The
 * data-controller attribute lives on the widget markup
 * (templates/Backend/be_gc_uploader.html.twig); Stimulus connects the
 * controller whenever the widget is rendered, including after Turbo visits.
 */
export default class extends Controller {
  static values = {
    url: String,
    paramName: {type: String, default: 'file'},
    maxFilesize: Number,
    acceptedFiles: String,
    fileTooBig: String,
    invalidType: String,
  };

  connect() {
    const Dropzone = window.Dropzone;

    if (typeof Dropzone === 'undefined') {
      console.error('Dropzone library is not loaded.');

      return;
    }

    // The Contao edit form is the actual drop target.
    this.form = document.getElementById('tl_gallery_creator_albums');

    if (!this.form) {
      return;
    }

    Dropzone.autoDiscover = false;

    // Guard against a leftover instance (e.g. after a Turbo restore visit).
    if (this.form.dropzone) {
      this.form.dropzone.destroy();
    }

    this.dropzone = new Dropzone(this.form, {
      url: this.hasUrlValue && this.urlValue ? this.urlValue : window.location.href,
      paramName: this.paramNameValue,
      maxFilesize: this.maxFilesizeValue,
      acceptedFiles: this.acceptedFilesValue,
      previewsContainer: this.element.querySelector('.dropzone-previews'),
      clickable: this.element.querySelector('.dropzone'),
      timeout: 0,
      dictFileTooBig: this.fileTooBigValue,
      dictInvalidFileType: this.invalidTypeValue,
    });

    this.dropzone.on('addedfile', () => {
      this.element.querySelectorAll('.dz-message').forEach((el) => {
        el.style.display = 'none';
      });
    });
  }

  beforeCache() {
    // Turbo snapshots the page before disconnect() runs, so tear Dropzone down
    // here to keep its injected DOM/classes out of the cached copy.
    this.#teardown();
  }

  disconnect() {
    this.#teardown();
  }

  #teardown() {
    this.dropzone?.destroy();
    this.dropzone = null;
  }
}
