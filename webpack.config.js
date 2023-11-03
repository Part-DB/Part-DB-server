/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony)
 *
 * Copyright (C) 2019 - 2023 Jan Böhmer (https://github.com/jbtronics)
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA
 *
 */

var Encore = require('@symfony/webpack-encore');

const zlib = require('zlib');
const CompressionPlugin = require("compression-webpack-plugin");
const BundleAnalyzerPlugin = require('webpack-bundle-analyzer').BundleAnalyzerPlugin;
const { CKEditorTranslationsPlugin } = require( '@ckeditor/ckeditor5-dev-translations' );
const { styles } = require( '@ckeditor/ckeditor5-dev-utils' );

// Manually configure the runtime environment if not already configured yet by the "encore" command.
// It's useful when you use tools that rely on webpack.config.js file.
if (!Encore.isRuntimeEnvironmentConfigured()) {
    Encore.configureRuntimeEnvironment(process.env.NODE_ENV || 'dev');
}

Encore
    // directory where compiled assets will be stored
    .setOutputPath('public/build/')
    // This value doesn't matter, as the public path is set to auto later down. This is just to prevent a warning
    .setPublicPath('/build')
    // only needed for CDN's or subdirectory deploy (this should not be needeed, as we use auto public path)
    //.setManifestKeyPrefix('build/')

    /*
     * ENTRY CONFIG
     *
     * Add 1 entry for each "page" of your app
     * (including one that's included on every page - e.g. "app")
     *
     * Each entry will result in one JavaScript file (e.g. app.js)
     * and one CSS file (e.g. app.css) if you JavaScript imports CSS.
     */
    .addEntry('app', './assets/js/app.js')
    .addEntry('webauthn_tfa', './assets/js/webauthn_tfa.js')

    //.addEntry('page1', './assets/js/page1.js')
    //.addEntry('page2', './assets/js/page2.js')

    // When enabled, Webpack "splits" your files into smaller pieces for greater optimization.
    .splitEntryChunks()

    // enables the Symfony UX Stimulus bridge (used in assets/bootstrap.js)
    .enableStimulusBridge('./assets/controllers.json')

    // will require an extra script tag for runtime.js
    // but, you probably want this, unless you're building a single-page app
    .enableSingleRuntimeChunk()

    /*
     * FEATURE CONFIG
     *
     * Enable & configure other features below. For a full
     * list of features, see:
     * https://symfony.com/doc/current/frontend.html#adding-more-features
     */
    .cleanupOutputBeforeBuild()
    .enableBuildNotifications()
    .enableSourceMaps(!Encore.isProduction())
    // enables hashed filenames (e.g. app.abc123.css)
    //.enableVersioning(Encore.isProduction())
    .enableVersioning()



    // configure Babel
    // .configureBabel((config) => {
    //     config.plugins.push('@babel/a-babel-plugin');
    // })

    // enables and configure @babel/preset-env polyfills
    .configureBabelPresetEnv((config) => {
        config.useBuiltIns = 'usage';
        config.corejs = '3.23';
    })
    // enables Sass/SCSS support
    //.enableSassLoader()

    // uncomment if you use TypeScript
    .enableTypeScriptLoader()

    // uncomment if you use React
    //.enableReactPreset()

    // uncomment to get integrity="..." attributes on your script & link tags
    // requires WebpackEncoreBundle 1.4 or higher
    .enableIntegrityHashes(Encore.isProduction())

    // uncomment if you're having problems with a jQuery plugin
    .autoProvidejQuery()

    .addPlugin( new CKEditorTranslationsPlugin( {
        // See https://ckeditor.com/docs/ckeditor5/latest/features/ui-language.html
        language: 'en',
        addMainLanguageTranslationsToAllAssets: true,
        additionalLanguages: 'all',
        outputDirectory: 'ckeditor_translations'
    } ) )

    // Use raw-loader for CKEditor 5 SVG files.
    .addRule( {
        test: /ckeditor5-[^/\\]+[/\\]theme[/\\]icons[/\\][^/\\]+\.svg$/,
        loader: 'raw-loader'
    } )

    // Configure other image loaders to exclude CKEditor 5 SVG files.
    .configureLoaderRule( 'images', loader => {
        loader.exclude = /ckeditor5-[^/\\]+[/\\]theme[/\\]icons[/\\][^/\\]+\.svg$/;
    } )

    // Configure PostCSS loader.
    .addLoader({
        test: /ckeditor5-[^/\\]+[/\\]theme[/\\].+\.css$/,
        loader: 'postcss-loader',
        options: {
            postcssOptions: styles.getPostCssConfig( {
                themeImporter: {
                    themePath: require.resolve( '@ckeditor/ckeditor5-theme-lark' )
                },
                minify: true
            } )
        }
    } )

;

//These are all the themes that are available in bootswatch
const AVAILABLE_THEMES = ['bootstrap', 'cerulean', 'cosmo', 'cyborg', 'darkly', 'flatly', 'journal',
    'litera', 'lumen', 'lux', 'materia', 'minty', 'morph', 'pulse', 'quartz', 'sandstone', 'simplex', 'sketchy', 'slate', 'solar',
    'spacelab', 'superhero', 'united', 'vapor', 'yeti', 'zephyr'];

for (const theme of AVAILABLE_THEMES) {
    Encore.addEntry('theme_' + theme, './assets/themes/'+theme+'.js');
}


if (Encore.isProduction()) {
    Encore.addPlugin(new CompressionPlugin({
        filename: '[path][base].br',
        algorithm: 'brotliCompress',
        test: /\.(js|css|html|svg)$/,
        compressionOptions: {
            // zlib’s `level` option matches Brotli’s `BROTLI_PARAM_QUALITY` option.
            level: 11,
        },
        //threshold: 10240,
        minRatio: 0.8,
        deleteOriginalAssets: false,
    }))

        .addPlugin(new CompressionPlugin({
            filename: '[path][base].gz',
            algorithm: 'gzip',
            test: /\.(js|css|html|svg)$/,
            deleteOriginalAssets: false,
        }))
}

if (Encore.isDev()) {
    //Only uncomment if needed, as this cause problems with Github actions (job does not finish)
    Encore.addPlugin(new BundleAnalyzerPlugin());
}


module.exports = Encore.getWebpackConfig();

//Enable webpack auto public path (this only works in combination with WebpackAutoPathSubscriber!!)
//We do it here to supress a warning caused by webpack Encore
module.exports.output.publicPath = 'auto';