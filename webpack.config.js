const Encore = require('@symfony/webpack-encore');

if (!Encore.isRuntimeEnvironmentConfigured()) {
    Encore.configureRuntimeEnvironment(process.env.NODE_ENV || 'dev');
}

Encore
    // Directory where compiled assets will be stored
    .setOutputPath('public/')
    // Public path used by the web server to access the output path
    .setPublicPath('/bundles/autocomplete')
    .setManifestKeyPrefix('bundles/autocomplete')

    // Entries
    .addEntry('ssr_autocomplete_controller', './assets/controllers/ssr_autocomplete_controller.js')

    // Unified CSS with all themes
    .addStyleEntry('autocomplete', './assets/styles/autocomplete.css')

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
