import {Application} from '@hotwired/stimulus';
import {definitionForModuleAndIdentifier, identifierForContextKey} from '@hotwired/stimulus-webpack-helpers';

// Start the Stimulus application
const application = Application.start();
application.debug = process.env.NODE_ENV === 'development';

// Register all controllers with `gc--` prefix.
// Note: controllers are loaded eagerly (no lazy-controller-loader) so their
// static afterLoad() hook runs on registration and can attach the
// data-controller attribute to the DOM by itself.
const context = require.context(
    './controllers',
    true,
    /\.[jt]sx?$/
);

application.load(context.keys()
    .map((key) => {
        const identifier = identifierForContextKey(key);
        if (identifier) {
            return definitionForModuleAndIdentifier(context(key), `gc--${identifier}`);
        }
    }).filter((value) => value)
);
