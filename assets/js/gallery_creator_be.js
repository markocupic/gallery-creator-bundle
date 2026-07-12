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

  // Get the "check all" checkbox
  const checkAll = document.getElementById("CheckAllGcPublishAlbums");

  // If the checkbox does not exist, stop execution
  if (!checkAll) {
    return;
  }

  // Get all album control checkboxes
  const albumFields = document.querySelectorAll(".album-control-field");

  // ---------------------------------------------------------
  // Handle "Check all" checkbox
  // ---------------------------------------------------------
  checkAll.addEventListener("click", () => {
    const isChecked = checkAll.checked;

    // Set all album checkboxes to the same state
    albumFields.forEach(el => {
      el.checked = isChecked;
    });
  });

  // ---------------------------------------------------------
  // Handle individual album checkbox clicks
  // ---------------------------------------------------------
  albumFields.forEach(field => {
    field.addEventListener("click", () => {

      // Find the parent <li> element
      const li = field.closest("li");
      if (!li) return;

      // Get all input elements inside the same <li>
      const inputs = li.querySelectorAll("input");

      // Sync all inputs inside the <li> with the clicked checkbox
      inputs.forEach(input => {
        input.checked = field.checked;
      });
    });
  });

});

