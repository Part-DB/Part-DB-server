/*
 * This file is part of Part-DB (https://github.com/Part-DB/Part-DB-symfony)
 *
 * Copyright (C) 2019 Jan Böhmer (https://github.com/jbtronics)
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
const CopyPlugin = require('copy-webpack-plugin');

const zlib = require('zlib');
const CompressionPlugin = require("compression-webpack-plugin");
const BundleAnalyzerPlugin = require('webpack-bundle-analyzer').BundleAnalyzerPlugin;

// Manually configure the runtime environment if not already configured yet by the "encore" command.
// It's useful when you use tools that rely on webpack.config.js file.
if (!Encore.isRuntimeEnvironmentConfigured()) {
    Encore.configureRuntimeEnvironment(process.env.NODE_ENV || 'dev');
}

Encore
    // directory where compiled assets will be stored
    .setOutputPath('public/build/')
    // public path used by the web server to access the output path
    .setPublicPath('/build')
    // only needed for CDN's or sub-directory deploy
    //.setManifestKeyPrefix('build/')

    /**
     * If you are putting Part-DB into a sub directory you have to uncomment these lines and
     * replace "part-db/" with your path to Part-DB
     */
    //.setPublicPath('/part-db/build')
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
    .addEntry('ru2ftwofactor', './assets/js/u2f_auth.js')

    // enables the Symfony UX Stimulus bridge (used in assets/bootstrap.js)
    .enableStimulusBridge('./assets/controllers.json')
    //.addEntry('page1', './assets/js/page1.js')
    //.addEntry('page2', './assets/js/page2.js')

    // When enabled, Webpack "splits" your files into smaller pieces for greater optimization.
    .splitEntryChunks()

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

    .configureBabel((config) => {
        config.plugins.push('@babel/plugin-proposal-class-properties');
    })

    // enables @babel/preset-env polyfills
    .configureBabelPresetEnv((config) => {
        config.useBuiltIns = 'usage';
        config.corejs = 3;
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
    .addPlugin(new CopyPlugin({
        patterns: [
            {
                from: 'node_modules/bootswatch/dist/*/*.min.css',
                to({ context, absoluteFilename }) {
                    const regexp = /.*([\/\\])(.+)([\/\\]).*\.css$/g;
                    const array = [...absoluteFilename.matchAll(regexp)];
                    return 'themes/'+array[0][2]+'[ext]';
                }
            },
            {
                from: 'node_modules/bootstrap/dist/css/bootstrap.min.css',
                to: 'themes/bootstrap.css'
            }
        ]}))

    // uncomment if you're having problems with a jQuery plugin
    .autoProvidejQuery()

;

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
    //Encore.addPlugin(new BundleAnalyzerPlugin());
}


module.exports = Encore.getWebpackConfig();
