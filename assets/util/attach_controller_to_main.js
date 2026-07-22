/*
 * This file is part of Gallery Creator Bundle.
 *
 * (c) Marko Cupic <m.cupic@gmx.ch>
 * @license GPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/gallery-creator-bundle
 */

/**
 * Attach a Stimulus controller identifier to the backend #main element and keep
 * it attached across Turbo navigations and Contao AJAX updates.
 *
 * The Contao backend is driven by Turbo, so a page visit replaces <body> (and
 * therefore #main) with fresh server markup without firing DOMContentLoaded.
 * The controller's static afterLoad() hook only runs once, so we re-apply the
 * data-controller attribute on every relevant lifecycle event. Re-attaching to
 * the new #main is intentional: Stimulus then reconnects the controller against
 * the freshly rendered DOM.
 *
 * @param {string} identifier  The (prefixed) Stimulus identifier, e.g. "gc--backend--be-check-tables".
 * @param {import('@hotwired/stimulus').Application} application
 */
export function attachControllerToMain(identifier, application) {
  const attribute = application.schema.controllerAttribute;

  const bind = () => {
    const main = document.getElementById('main');

    if (!main) {
      return;
    }

    const controllers = (main.getAttribute(attribute) || '')
        .split(/\s+/)
        .filter(Boolean);

    if (!controllers.includes(identifier)) {
      controllers.push(identifier);
      main.setAttribute(attribute, controllers.join(' '));
    }
  };

  // Initial page load
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bind);
  } else {
    bind();
  }

  // Turbo navigations and Contao AJAX partial updates
  ['turbo:render', 'turbo:frame-render', 'ajax_change'].forEach((event) => {
    document.addEventListener(event, bind);
  });
}
