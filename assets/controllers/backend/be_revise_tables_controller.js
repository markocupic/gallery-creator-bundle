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
import {attachControllerToMain} from '../../util/attach_controller_to_main.js';

/**
 * Revises (checks/cleans) the gallery creator database tables when the backend
 * user clicks the "revise" button.
 *
 * The controller binds itself to the backend #main element via the static
 * afterLoad() hook, so no markup changes and no bootstrap script are needed.
 */
export default class extends Controller {
  static afterLoad(identifier, application) {
    attachControllerToMain(identifier, application);
  }

  static values = {
    waitLabel: {type: String, default: 'Please wait a moment...'},
    checkingLabel: {type: String, default: 'Check album with ID %id%.'},
    resultLabel: {type: String, default: 'Revised the gallery creator database tables. %count% error(s) found.'},
  };

  connect() {
    this.timeouts = new Set();
    this.errors = 0;
    this.requestsDone = 0;
    this.albumIds = null;

    this.element.classList.add('gc-revise-database');

    this.button = document.getElementById('reviseTableBtn');
    this.checkbox = document.querySelector('input[name=reviseDatabase]');
    this.labelCheckbox = document.querySelector('label[for=reviseDatabase]');

    // Nothing to do if the revise button is not on the page
    if (!this.button) {
      return;
    }

    // Create the message box with a nested status box
    this.messageBox = document.createElement('div');
    this.messageBox.id = 'messageBox';
    this.messageBox.classList.add('gc_message');

    document.querySelector('.tl_formbody_submit')?.insertAdjacentElement('beforebegin', this.messageBox);

    this.statusBox = document.createElement('div');
    this.statusBox.id = 'statusBox';
    this.statusBox.classList.add('gc-check-tables-status-box');
    this.messageBox.appendChild(this.statusBox);

    this.onButtonClick = this.#onButtonClick.bind(this);
    this.button.addEventListener('click', this.onButtonClick);
  }

  disconnect() {
    this.button?.removeEventListener('click', this.onButtonClick);
    this.timeouts.forEach((id) => window.clearTimeout(id));
    this.timeouts.clear();
    this.messageBox?.remove();
  }

  #onButtonClick(event) {
    event.preventDefault();

    // Fade out the controls (CSS class required)
    this.button.classList.add('fade-out');
    this.checkbox?.classList.add('fade-out');
    this.labelCheckbox?.classList.add('fade-out');

    const csrfToken = document.querySelector('input[name="REQUEST_TOKEN"]')?.value ?? '';

    this.#run(csrfToken);
  }

  async #run(csrfToken) {
    this.errors = 0;
    this.requestsDone = 0;
    this.albumIds = null;

    // Reset the boxes
    this.messageBox.querySelectorAll('p').forEach((el) => el.remove());
    this.statusBox.querySelectorAll('p').forEach((el) => el.remove());

    const waiting = document.createElement('p');
    waiting.textContent = this.waitLabelValue;
    this.statusBox.appendChild(waiting);

    await this.#getAlbumIds();
    await this.#reviseTables(csrfToken);
  }

  async #getAlbumIds() {
    try {
      const response = await fetch('/_gallery_creator/revise_table/get_album_ids', {method: 'GET'});
      const data = await response.json();

      if (data?.ids) {
        this.albumIds = data.ids;
      }
    } catch (err) {
      console.error('Error fetching album IDs:', err);
    }
  }

  async #reviseTables(csrfToken) {
    if (!this.albumIds) {
      console.error('No album IDs found.');

      return;
    }

    for (const albumId of this.albumIds) {
      try {
        let response;

        if (this.checkbox?.checked) {
          response = await fetch(`/_gallery_creator/revise_table/revise_album/${albumId}`, {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: new URLSearchParams({
              FORM_SUBMIT: 'tl_gallery_creator_albums',
              REQUEST_TOKEN: csrfToken,
              reviseTables: 'true',
              cleanDb: '1',
              albumId: albumId,
            }),
          });
        } else {
          response = await fetch(`/_gallery_creator/revise_table/check_album/${albumId}`, {method: 'GET'});
        }

        const data = await response.json();

        if (data?.errors?.length > 0) {
          data.errors.forEach((msg) => {
            const p = document.createElement('p');
            p.classList.add('tl_error');
            p.textContent = msg;
            this.messageBox.appendChild(p);
            this.errors++;
          });
        }
      } catch (err) {
        console.error('Error revising album:', err);
      }

      this.requestsDone++;

      // Show a transient status message for the current album
      this.statusBox.innerHTML = '';
      const status = document.createElement('div');
      const message = this.checkingLabelValue.replace('%id%', albumId);
      status.innerHTML = `<p>${message}</p>`;
      this.statusBox.appendChild(status);
      this.#setTimeout(() => status.remove(), 1000);

      // All requests are done
      if (this.requestsDone === this.albumIds.length) {
        this.#setTimeout(() => this.#finish(), 1000);
      }
    }
  }

  #finish() {
    const finalMsg = document.createElement('p');
    finalMsg.classList.add('tl_confirm');
    finalMsg.textContent = this.resultLabelValue.replace('%count%', this.errors);
    this.messageBox.appendChild(finalMsg);

    // Fade the controls back in
    this.button.classList.remove('fade-out');

    if (this.checkbox) {
      this.checkbox.checked = false;
      this.checkbox.classList.remove('fade-out');
    }

    this.labelCheckbox?.classList.remove('fade-out');
  }

  /**
   * Register a timeout so it can be cancelled on disconnect().
   */
  #setTimeout(callback, delay) {
    const id = window.setTimeout(() => {
      this.timeouts.delete(id);
      callback();
    }, delay);

    this.timeouts.add(id);
  }
}
