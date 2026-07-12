const Encore = require('@symfony/webpack-encore');

Encore
    .setOutputPath('public/')
    .setPublicPath('/bundles/markocupicgallerycreator')
    .setManifestKeyPrefix('')

    .copyFiles({
      from: './assets/images',
      to: 'images/[path][name].[ext]'
    })

    .copyFiles({
      from: './assets/js',
      to: 'js/[path][name].[hash:8].[ext]'
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
