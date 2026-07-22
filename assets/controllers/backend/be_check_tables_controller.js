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
 * Periodically checks the gallery creator album tables and shows errors/status
 * boxes in the Contao backend.
 *
 * The controller binds itself to the backend #main element via the static
 * afterLoad() hook, so no markup changes and no bootstrap script are needed.
 * Because the Stimulus app is loaded across the whole backend, connect() bails
 * out unless we are on the gallery creator album listing.
 */
export default class extends Controller {
  static values = {
    interval: {type: Number, default: 3600}, // seconds between two checks (60 min)
    cookieName: {type: String, default: 'contao_gallery_creator_be'},
    url: String, // optional; falls back to document.URL
    checkingLabel: {type: String, default: 'Check album with ID %id%.'},
    completedLabel: {type: String, default: 'Check completed.'},
  };

  static afterLoad(identifier, application) {
    attachControllerToMain(identifier, application);
  }

  connect() {
    // The Stimulus app runs on every backend page, but this check only makes
    // sense on the gallery creator album listing.
    if (!this.#isAlbumListing()) {
      return;
    }

    this.albumIds = null;
    this.countTests = 0;
    this.timeouts = new Set();

    this.element.classList.add('gc-check-tables');

    if (this.#shouldCheck()) {
      this.#getAlbumIds();
    }
  }

  /**
   * Mirrors the former server-side gate: only run on "do=gallery_creator"
   * and either the module root listing or the album table view.
   */
  #isAlbumListing() {
    const params = new URLSearchParams(window.location.search);

    if ('gallery_creator' !== params.get('do')) {
      return false;
    }

    const size = [...params.keys()].length;

    if (1 === size) {
      return true;
    }

    return 2 === size && 'tl_gallery_creator_albums' === params.get('table');
  }

  disconnect() {
    // Cancel any pending status-box timeouts
    this.timeouts?.forEach((id) => window.clearTimeout(id));
    this.timeouts?.clear();

    // Remove all status-box elements
    const elements = document.querySelectorAll('.gc-check-tables-error-box, .gc-check-tables-status-box');

    for (const el of elements) {
      el.remove();
    }
  }

  get baseUrl() {
    return this.hasUrlValue && this.urlValue ? this.urlValue : document.URL;
  }

  /**
   * Decide whether a check is due, based on the timestamp stored in the cookie.
   */
  #shouldCheck() {
    const now = Math.floor(Date.now() / 1000);
    const cookie = this.#readCookie(this.cookieNameValue);

    if (!cookie) {
      this.#writeCookie(this.cookieNameValue, {tableCheck: {lastCheck: now}});

      return true;
    }

    const lastCheck = cookie.tableCheck?.lastCheck;

    if (lastCheck && now - lastCheck < this.intervalValue) {
      return false;
    }

    cookie.tableCheck = {...cookie.tableCheck, lastCheck: now};
    this.#writeCookie(this.cookieNameValue, cookie);

    return true;
  }

  #readCookie(name) {
    const match = document.cookie.match(new RegExp('(^| )' + name + '=([^;]+)'));

    if (!match) {
      return null;
    }

    try {
      return JSON.parse(atob(match[2]));
    } catch {
      return null;
    }
  }

  #writeCookie(name, obj) {
    document.cookie = `${name}=${btoa(JSON.stringify(obj))}; path=/`;
  }

  /**
   * Fetch all album IDs, then check each album.
   */
  async #getAlbumIds() {
    try {
      const response = await fetch(`/_gallery_creator/revise_table/get_album_ids`, {method: 'GET'});
      const data = await response.json();

      if (data?.ids) {
        this.albumIds = data.ids;
        await this.#checkTables();
      }
    } catch (err) {
      console.error('Error fetching album IDs:', err);
    }
  }

  /**
   * Fire a request for each album and display error/status messages.
   */
  async #checkTables() {
    if (!this.albumIds) {
      return;
    }

    for (const albumId of this.albumIds) {
      this.countTests++;
      const counter = this.countTests;

      try {
        const response = await fetch(`/_gallery_creator/revise_table/check_album/${albumId}`, {method: 'GET'});
        const data = await response.json();

        if (data?.errors?.length > 0) {
          this.#displayErrors(data.errors);
        }
      } catch (err) {
        console.error('Error checking album:', err);
      }

      this.#displayStatusBox(albumId);

      // If this was the last album
      if (counter === this.albumIds.length) {
        document.querySelector('.gc-check-tables-status-box')?.remove();
        this.#displayFinalStatusBox();
        this.#setTimeout(() => document.getElementById('statusBoxChecksCompleted')?.remove(), 100000);
      }
    }
  }

  #displayErrors(errors) {
    let messageBox = document.querySelector('.gc--check-tables-error-box');

    if (!messageBox) {
      messageBox = document.createElement('div');
      messageBox.classList.add('tl_message');
      messageBox.classList.add('gc--check-tables-error-box');

      document.getElementById('tl_buttons')?.insertAdjacentElement('afterend', messageBox);
    }

    errors.forEach((msg) => {
      const p = document.createElement('p');
      p.classList.add('tl_error');
      p.textContent = msg;
      messageBox.appendChild(p);
    });
  }

  #displayStatusBox(albumId) {
    // Remove previous status boxes
    document.querySelectorAll('.gc-check-tables-status-box').forEach((el) => el.remove());

    const statusBox = document.createElement('div');
    statusBox.id = `statusBox${albumId}`;
    statusBox.classList.add('gc-check-tables-status-box');
    const message = this.checkingLabelValue.replace('%id%', albumId);
    statusBox.innerHTML = `<p>${message}</p>`;
    document.getElementById('tl_buttons')?.insertAdjacentElement('afterend', statusBox);

    this.#setTimeout(() => document.getElementById(`statusBox${albumId}`)?.remove(), 1000);
  }

  #displayFinalStatusBox() {
    const statusBox = document.createElement('div');
    statusBox.id = 'statusBoxChecksCompleted';
    statusBox.classList.add('gc-check-tables-status-box');
    statusBox.innerHTML = `<p>${this.completedLabelValue}</p>`;

    document.getElementById('tl_buttons')?.insertAdjacentElement('afterend', statusBox);
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
