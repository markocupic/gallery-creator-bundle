/*
 * This file is part of Gallery Creator Bundle.
 *
 * (c) Marko Cupic 2024 <m.cupic@gmx.ch>
 * @license GPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/gallery-creator-bundle
 */


document.addEventListener("DOMContentLoaded", () => {
  new GalleryCreatorBeReviseTables();
});

class GalleryCreatorBeReviseTables {

  /**
   * Array with all album IDs
   */
  albumIDS = null;

  /**
   * Count errors
   */
  errors = 0;

  /**
   * Count completed requests
   */
  intRequestDone = 0;

  /**
   * Message box element
   */
  messageBox = null;

  /**
   * Status box element
   */
  statusBox = null;

  /**
   * Button element
   */
  button = null;

  /**
   * Checkbox element
   */
  checkbox = null;

  /**
   * Label element for checkbox
   */
  labelCheckbox = null;

  /**
   * Constructor
   */
  constructor() {
    const main = document.getElementById("main");
    if (main) {
      main.classList.add("gc-revise-database");
    }

    this.button = document.getElementById("reviseTableBtn");
    this.checkbox = document.querySelector("input[name=reviseDatabase]");
    this.labelCheckbox = document.querySelector("label[for=reviseDatabase]");

    // Create message box
    this.messageBox = document.createElement("div");
    this.messageBox.id = "messageBox";
    this.messageBox.classList.add("gc_message");

    const formSubmit = document.querySelector(".tl_formbody_submit");
    if (formSubmit) {
      formSubmit.insertAdjacentElement("beforebegin", this.messageBox);
    }

    // Create status box
    this.statusBox = document.createElement("div");
    this.statusBox.id = "statusBox";
    this.statusBox.classList.add("gc-check-tables-status-box");
    this.messageBox.appendChild(this.statusBox);

    // Button click handler
    this.button.addEventListener("click", (event) => {
      if (this.checkbox.checked) {
        event.preventDefault();

        // Fade out elements (CSS class required)
        this.button.classList.add("fade-out");
        this.checkbox.classList.add("fade-out");
        this.labelCheckbox.classList.add("fade-out");

        this.start();
      }
    });
  }

  /**
   * Kick off the process
   */
  start() {
    this.intRequestDone = 0;
    this.errors = 0;
    this.albumIDS = null;

    // Reset message box content
    this.messageBox.querySelectorAll("p").forEach(el => el.remove());
    this.statusBox.querySelectorAll("p").forEach(el => el.remove());

    // Initial message
    const p = document.createElement("p");
    p.textContent = "Please wait a moment...";
    this.statusBox.appendChild(p);

    this.getAlbumIDS();
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
        this.reviseTables();
      }
    } catch (err) {
      console.error("Error fetching album IDs:", err);
    }
  }

  /**
   * Fire a request for each album.
   * Display error messages in the backend.
   */
  async reviseTables() {
    if (!this.albumIDS) return;

    for (const albumId of this.albumIDS) {

      try {
        const response = await fetch(
            document.URL + "&isAjaxRequest=true&checkTables=true&reviseTables=true&albumId=" + albumId,
            {method: "GET"}
        );

        const data = await response.json();

        if (data && data.errors && data.errors.length > 0) {
          data.errors.forEach(msg => {
            const p = document.createElement("p");
            p.classList.add("tl_error");
            p.textContent = msg;
            this.messageBox.appendChild(p);
            this.errors++;
          });
        }

      } catch (err) {
        console.error("Error revising album:", err);
      }

      // Completed one request
      this.intRequestDone++;

      // Display status message
      const p = document.createElement("p");
      p.textContent = "Check album with ID " + albumId + ".";
      this.statusBox.appendChild(p);

      // If all requests are done
      if (this.intRequestDone === this.albumIDS.length) {
        setTimeout(() => {
          const finalMsg = document.createElement("p");
          finalMsg.classList.add("tl_confirm");
          finalMsg.textContent =
              "Revised the gallery creator database tables. " +
              this.errors + " error(s) found.";
          this.messageBox.appendChild(finalMsg);

          // Fade elements back in
          this.button.classList.remove("fade-out");
          this.checkbox.checked = false;
          this.checkbox.classList.remove("fade-out");
          this.labelCheckbox.classList.remove("fade-out");

        }, 1000);
      }
    }
  }
}
