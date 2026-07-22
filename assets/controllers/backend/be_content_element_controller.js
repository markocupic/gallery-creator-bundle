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
 * Handles the "publish albums" checkbox logic inside the gallery creator content
 * element edit form:
 *  - the "check all" checkbox toggles every album checkbox,
 *  - clicking a single album checkbox syncs all inputs within the same <li>.
 *
 * The controller binds itself to the backend #main element via the static
 * afterLoad() hook, so no markup changes and no bootstrap script are needed.
 */
export default class extends Controller {
  static afterLoad(identifier, application) {
    attachControllerToMain(identifier, application);
  }

  connect() {
    this.checkAll = this.element.querySelector('#CheckAllGcPublishAlbums');

    // Nothing to do if the "check all" checkbox is not on the page
    if (!this.checkAll) {
      return;
    }

    this.albumFields = this.element.querySelectorAll('.album-control-field');

    this.onCheckAll = this.#onCheckAll.bind(this);
    this.onFieldClick = this.#onFieldClick.bind(this);

    this.checkAll.addEventListener('click', this.onCheckAll);
    this.albumFields.forEach((field) => field.addEventListener('click', this.onFieldClick));
  }

  disconnect() {
    this.checkAll?.removeEventListener('click', this.onCheckAll);
    this.albumFields?.forEach((field) => field.removeEventListener('click', this.onFieldClick));
  }

  /**
   * "Check all" checkbox: set every album checkbox to the same state.
   */
  #onCheckAll() {
    const isChecked = this.checkAll.checked;

    this.albumFields.forEach((el) => {
      el.checked = isChecked;
    });
  }

  /**
   * Single album checkbox: sync all inputs inside the same <li>.
   */
  #onFieldClick(event) {
    const field = event.currentTarget;
    const li = field.closest('li');

    if (!li) {
      return;
    }

    li.querySelectorAll('input').forEach((input) => {
      input.checked = field.checked;
    });
  }
}
