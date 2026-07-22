const Encore = require('@symfony/webpack-encore');

Encore
    .setOutputPath('public/')
    .setPublicPath('/bundles/markocupicgallerycreator')
    .setManifestKeyPrefix('')

    .addEntry('stimulus_backend', './assets/stimulus_backend.js') // Register Stimulus controllers for the backend


    .copyFiles({
      from: './assets/images',
      to: 'images/[path][name].[ext]'
    })

    .disableSingleRuntimeChunk()
    .cleanupOutputBeforeBuild()
    .enableSourceMaps()
    .enableVersioning()

    // enables @babel/preset-env polyfills
    .configureBabelPresetEnv((config) => {
      config.useBuiltIns = 'usage';
      config.corejs = 3;
    })

    .enablePostCssLoader()
    // Preprocessing scss in css
    .enableSassLoader()
    .enablePostCssLoader()
    .addStyleEntry('css/backend', './assets/scss/backend.scss')
;

module.exports = Encore.getWebpackConfig();
