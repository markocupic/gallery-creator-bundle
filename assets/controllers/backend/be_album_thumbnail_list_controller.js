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
 * Handles the album "thumbnail" widget in the Contao backend:
 *  - clicking a thumbnail radio marks its <li> as "checked",
 *  - the main thumbnail list can be reordered via drag & drop,
 *  - a new order is persisted through an AJAX GET request.
 *
 * The data-controller attribute lives directly on the widget markup
 * (templates/Backend/album_thumbnail_list.html.twig), so this controller needs
 * no bootstrap: Stimulus connects it whenever the widget is rendered, including
 * after Turbo navigations.
 */
export default class extends Controller {
  connect() {
    this.abortController = new AbortController();
    this.draggedEl = null;

    // The sortable list holds the album's own thumbnails.
    this.list = this.element.querySelector('#gcPreviewThumbList');

    this.#initCheckOnClick();
    this.#initDragAndDrop();
  }

  beforeCache() {
    // Turbo snapshots the page before disconnect() runs, so revert transient
    // visual state here to keep the cached copy clean. The reordered items and
    // the "checked" selection are intentionally kept (they mirror user/saved
    // state); only the in-flight drag highlight must go.
    this.element.querySelectorAll('li.dragging').forEach((li) => li.classList.remove('dragging'));
  }

  disconnect() {
    // Removes every listener registered with this signal in one go.
    this.abortController?.abort();
  }

  /**
   * Mark the clicked radio's <li> as "checked" and clear the others.
   * Covers both the album list and the child-album list inside the widget.
   */
  #initCheckOnClick() {
    const {signal} = this.abortController;

    this.element.querySelectorAll('input').forEach((input) => {
      input.addEventListener('click', () => {
        this.element.querySelectorAll('li').forEach((li) => li.classList.remove('checked'));
        input.closest('li')?.classList.add('checked');
      }, {signal});
    });
  }

  /**
   * Enable drag & drop reordering on the main thumbnail list.
   */
  #initDragAndDrop() {
    if (!this.list) {
      return;
    }

    const {signal} = this.abortController;

    this.list.querySelectorAll('li').forEach((li) => {
      li.draggable = true;

      li.addEventListener('dragstart', (e) => {
        this.draggedEl = li;
        li.classList.add('dragging');
        e.dataTransfer.effectAllowed = 'move';
        e.dataTransfer.setData('text/plain', '');
      }, {signal});

      li.addEventListener('dragend', () => {
        li.classList.remove('dragging');
        this.draggedEl = null;
        this.#sendNewOrder();
      }, {signal});

      li.addEventListener('dragover', (e) => {
        e.preventDefault();

        if (!this.draggedEl) {
          return;
        }

        const bounding = li.getBoundingClientRect();
        const offset = bounding.top + bounding.height / 2;

        if (e.clientY < offset) {
          this.list.insertBefore(this.draggedEl, li);
        } else {
          this.list.insertBefore(this.draggedEl, li.nextSibling);
        }
      }, {signal});
    });
  }

  /**
   * Persist the current order of the main thumbnail list.
   */
  #sendNewOrder() {
    const ids = [...this.list.querySelectorAll('li')].map((li) => li.getAttribute('data-id'));

    if (0 === ids.length) {
      return;
    }

    const url = `${document.URL}&isAjaxRequest=true&pictureSorting=${ids.join(',')}`;

    fetch(url, {method: 'GET'}).catch((err) => console.error('Error:', err));
  }
}
