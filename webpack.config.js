const path = require('path');
const MiniCssExtractPlugin = require('mini-css-extract-plugin');
const CssMinimizerPlugin = require('css-minimizer-webpack-plugin');
const TerserPlugin = require('terser-webpack-plugin');
const { PurgeCSSPlugin } = require('purgecss-webpack-plugin');
const glob = require('glob');

module.exports = (env, argv) => {
    const isProduction = argv.mode === 'production';

    return {
        entry: {
            projects: './js/projects.js',
            dashboard: './js/dashboard.js',
            settings: './js/settings.js',
            customers: './js/customers.js',
            'customer-form': './js/customer-form.js',
            'customer-detail': './js/customer-detail.js',
            'time-entries': './js/time-entries.js',
            'time-entry-form': './js/time-entry-form.js',
            'time-entry-detail': './js/time-entry-detail.js',
            'project-form': './js/project-form.js',
            'datepicker': './js/common/datepicker.js'
        },
        output: {
            path: path.resolve(__dirname, 'dist'),
            filename: isProduction ? '[name].[contenthash].js' : '[name].js',
            clean: true,
            assetModuleFilename: isProduction ? 'assets/[name].[contenthash][ext]' : 'assets/[name][ext]'
        },
        module: {
            rules: [
                {
                    test: /\.js$/,
                    exclude: /node_modules/,
                    use: {
                        loader: 'babel-loader',
                        options: {
                            presets: ['@babel/preset-env'],
                            cacheDirectory: true
                        }
                    }
                },
                {
                    test: /\.css$/,
                    use: [
                        MiniCssExtractPlugin.loader,
                        {
                            loader: 'css-loader',
                            options: {
                                importLoaders: 1,
                                sourceMap: !isProduction
                            }
                        },
                        {
                            loader: 'postcss-loader',
                            options: {
                                postcssOptions: {
                                    plugins: [
                                        'autoprefixer',
                                        ...(isProduction ? ['cssnano'] : [])
                                    ]
                                },
                                sourceMap: !isProduction
                            }
                        }
                    ]
                },
                {
                    test: /\.(png|jpg|jpeg|gif|svg|woff|woff2|eot|ttf|otf)$/,
                    type: 'asset',
                    parser: {
                        dataUrlCondition: {
                            maxSize: 8 * 1024 // 8kb
                        }
                    }
                }
            ]
        },
        plugins: [
            new MiniCssExtractPlugin({
                filename: isProduction ? '[name].[contenthash].css' : '[name].css'
            }),
            ...(isProduction ? [
                new PurgeCSSPlugin({
                    paths: glob.sync(`${path.join(__dirname, 'templates')}/**/*.php`, { nodir: true }),
                    safelist: [
                        /^nc-/,
                        /^icon-/,
                        /^oc-/,
                        /^app-/,
                        /^button/,
                        /^primary/,
                        /^theme-/,
                        /^loading/,
                        /^fade/,
                        /^slide/,
                        /^modal/,
                        /^tooltip/,
                        /^popover/,
                        /^alert/,
                        /^toast/,
                        /^notification/,
                        /^spinner/,
                        /^skeleton/,
                        /^animate/,
                        /^transition/,
                        /^transform/,
                        /^scale/,
                        /^rotate/,
                        /^translate/,
                        /^opacity/,
                        /^visibility/,
                        /^display/,
                        /^position/,
                        /^top/,
                        /^right/,
                        /^bottom/,
                        /^left/,
                        /^z-index/,
                        /^overflow/,
                        /^clip/,
                        /^resize/,
                        /^cursor/,
                        /^user-select/,
                        /^pointer-events/,
                        /^touch-action/,
                        /^will-change/,
                        /^backface-visibility/,
                        /^perspective/,
                        /^transform-style/,
                        /^transform-origin/,
                        /^transition-property/,
                        /^transition-duration/,
                        /^transition-timing-function/,
                        /^transition-delay/,
                        /^animation-name/,
                        /^animation-duration/,
                        /^animation-timing-function/,
                        /^animation-delay/,
                        /^animation-iteration-count/,
                        /^animation-direction/,
                        /^animation-fill-mode/,
                        /^animation-play-state/,
                        /^(#)?app-content$/,
                        /^(#)?app-navigation$/,
                        // Datepicker classes
                        /^projectcheck-datepicker/
                    ]
                })
            ] : [])
        ],
        resolve: {
            extensions: ['.js', '.css'],
            alias: {
                '@': path.resolve(__dirname, 'js/'),
                '@css': path.resolve(__dirname, 'css/'),
                '@templates': path.resolve(__dirname, 'templates/')
            }
        },
        optimization: {
            minimize: isProduction,
            minimizer: [
                new TerserPlugin({
                    terserOptions: {
                        compress: {
                            drop_console: isProduction,
                            drop_debugger: isProduction
                        },
                        // Critical: Do not use eval in minification
                        ecma: 5,
                        output: {
                            ascii_only: true
                        }
                    },
                    extractComments: false
                }),
                new CssMinimizerPlugin({
                    minimizerOptions: {
                        preset: [
                            'default',
                            {
                                discardComments: { removeAll: true },
                                normalizeWhitespace: isProduction,
                                colormin: isProduction,
                                minifyFontValues: isProduction,
                                minifySelectors: isProduction
                            }
                        ]
                    }
                })
            ],
            // CRITICAL: Completely disable code splitting to avoid eval()/Function() CSP issues
            splitChunks: false,
            // CRITICAL: Disable runtime chunk to avoid eval() CSP issues
            runtimeChunk: false
        },
        // CRITICAL: Disable source maps to avoid eval() CSP issues
        devtool: false,
        cache: {
            type: 'filesystem',
            buildDependencies: {
                config: [__filename]
            }
        },
        performance: {
            hints: isProduction ? 'warning' : false,
            maxEntrypointSize: 512000,
            maxAssetSize: 512000
        },
        // CRITICAL: Disable all chunk loading mechanisms
        experiments: {
            topLevelAwait: false
        }
    };
};
