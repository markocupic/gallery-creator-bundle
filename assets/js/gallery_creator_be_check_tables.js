/*
 * This file is part of Gallery Creator Bundle.
 *
 * (c) Marko Cupic <m.cupic@gmx.ch>
 * @license GPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/gallery-creator-bundle
 */

document.addEventListener("DOMContentLoaded", () => {
  const objGalleryCreator = new GalleryCreatorBeCheckTables();
  objGalleryCreator.start();
});

class GalleryCreatorBeCheckTables {

  /**
   * Array with all album IDs
   */
  albumIDS = null;

  /**
   * Count album check requests
   */
  countTests = 0;

  /**
   * Constructor
   */
  constructor() {
    const main = document.getElementById("main");
    if (main) {
      main.classList.add("gc-check-tables");
    }
  }

  /**
   * Kick off the process
   */
  start() {
    const now = Math.floor(Date.now() / 1000);
    const interval = 3600; // 60 minutes
    let doCheck = true;

    const cookieName = "contao_gallery_creator_be";
    const cookieValue = this.readCookie(cookieName);

    let cookieObj;

    // Initialize cookie structure if missing
    if (!cookieValue) {
      cookieObj = {tableCheck: {lastCheck: now}};
      this.writeCookie(cookieName, cookieObj);
    } else {
      cookieObj = cookieValue;

      // Check if last check is older than interval
      if (cookieObj.tableCheck.lastCheck) {
        if (now - cookieObj.tableCheck.lastCheck < interval) {
          doCheck = false;
        } else {
          cookieObj.tableCheck.lastCheck = now;
          this.writeCookie(cookieName, cookieObj);
        }
      } else {
        cookieObj.tableCheck.lastCheck = now;
        this.writeCookie(cookieName, cookieObj);
      }
    }

    if (doCheck) {
      this.getAlbumIDS();
    }
  }

  /**
   * Read cookie and decode JSON
   */
  readCookie(name) {
    const match = document.cookie.match(new RegExp("(^| )" + name + "=([^;]+)"));
    if (!match) return null;

    try {
      return JSON.parse(atob(match[2]));
    } catch {
      return null;
    }
  }

  /**
   * Write cookie with encoded JSON
   */
  writeCookie(name, obj) {
    const encoded = btoa(JSON.stringify(obj));
    document.cookie = `${name}=${encoded}; path=/`;
  }

  /**
   * Get all album IDs via AJAX
   */
  async getAlbumIDS() {
    try {
      const response = await fetch(
          document.URL + "&isAjaxRequest=true&checkTables=true&getAlbumIDS=true",
          {method: "GET"}
      );

      const data = await response.json();
      if (data && data.ids) {
        this.albumIDS = data.ids;
        this.checkTables();
      }
    } catch (err) {
      console.error("Error fetching album IDs:", err);
    }
  }

  /**
   * Fire a request for each album.
   * Display error messages in the backend header.
   */
  async checkTables() {
    if (!this.albumIDS) return;

    for (const albumId of this.albumIDS) {
      this.countTests++;
      const counter = this.countTests;

      try {
        const response = await fetch(
            document.URL + "&isAjaxRequest=true&checkTables=true&albumId=" + albumId,
            {method: "GET"}
        );

        const data = await response.json();
        if (data && data.errors && data.errors.length > 0) {
          this.displayErrors(data.errors);
        }
      } catch (err) {
        console.error("Error checking album:", err);
      }

      this.displayStatusBox(albumId);

      // If this was the last album
      if (counter === this.albumIDS.length) {
        setTimeout(() => {
          this.displayFinalStatusBox();
        }, 2000);

        setTimeout(() => {
          const finalBox = document.getElementById("statusBoxChecksCompleted");
          if (finalBox) finalBox.remove();
        }, 20000);
      }
    }
  }

  /**
   * Display error messages in backend
   */
  displayErrors(errors) {
    let messageBox = document.querySelector(".tl_message");
    // Create message box if missing
    if (!messageBox) {
      messageBox = document.createElement("div");
      messageBox.classList.add("tl_message");

      const buttons = document.getElementById("tl_buttons");
      if (buttons) {
        buttons.insertAdjacentElement("afterend", messageBox);
      }
    }

    // Append error messages
    errors.forEach(msg => {
      const p = document.createElement("p");
      p.classList.add("tl_error");
      p.textContent = msg;
      messageBox.appendChild(p);
    });
  }

  /**
   * Display temporary status box for each album
   */
  displayStatusBox(albumId) {
    // Remove previous status boxes
    document.querySelectorAll(".gc-check-tables-status-box").forEach(el => el.remove());

    const folderTop = document.querySelector("#tl_listing .tl_folder_top");
    if (!folderTop) return;

    folderTop.style.position = "relative";

    const statusBox = document.createElement("p");
    statusBox.id = "statusBox" + albumId;
    statusBox.classList.add("gc-check-tables-status-box");
    statusBox.textContent = "Check album with ID " + albumId + ".";

    folderTop.appendChild(statusBox);

    setTimeout(() => {
      const el = document.getElementById("statusBox" + albumId);
      if (el) el.remove();
    }, 1000);
  }

  /**
   * Display final "all checks completed" box
   */
  displayFinalStatusBox() {
    const folderTop = document.querySelector("#tl_listing .tl_folder_top");
    if (!folderTop) return;

    const statusBox = document.createElement("p");
    statusBox.id = "statusBoxChecksCompleted";
    statusBox.classList.add("gc-check-tables-status-box");
    statusBox.textContent = "All checks successfully completed.";

    folderTop.appendChild(statusBox);
  }
}
