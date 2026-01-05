const Encore = require('@symfony/webpack-encore');

if (!Encore.isRuntimeEnvironmentConfigured()) {
    Encore.configureRuntimeEnvironment(process.env.NODE_ENV || 'dev');
}

Encore
    // Directory where compiled assets will be stored
    .setOutputPath('Resources/public/')
    // Public path used by the web server to access the output path
    .setPublicPath('/bundles/autocomplete')
    .setManifestKeyPrefix('bundles/autocomplete')

    // Entries
    .addEntry('ssr_autocomplete_controller', './assets/controllers/ssr_autocomplete_controller.js')

    // CSS entries for each theme
    .addStyleEntry('theme/default', './assets/styles/theme/default.css')
    .addStyleEntry('theme/bootstrap-5', './assets/styles/theme/bootstrap-5.css')
    .addStyleEntry('theme/dark', './assets/styles/theme/dark.css')
    .addStyleEntry('theme/cards', './assets/styles/theme/cards.css')

    // Features
    .splitEntryChunks()
    .enableSingleRuntimeChunk()
    .cleanupOutputBeforeBuild()
    .enableBuildNotifications()
    .enableSourceMaps(!Encore.isProduction())
    // Disable versioning so filenames stay predictable
    .enableVersioning(false)

    // Uncomment if you use TypeScript
    // .enableTypeScriptLoader()

    // Babel config
    .configureBabelPresetEnv((config) => {
        config.useBuiltIns = 'usage';
        config.corejs = 3;
    })
;

module.exports = Encore.getWebpackConfig();
