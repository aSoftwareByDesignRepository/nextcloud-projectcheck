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
            lucide: './js/lucide.js',
            common: './js/common/index.js'
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
                        /^(#)?app-navigation$/
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
            splitChunks: {
                chunks: 'all',
                cacheGroups: {
                    vendor: {
                        test: /[\\/]node_modules[\\/]/,
                        name: 'vendors',
                        chunks: 'all',
                        priority: 10
                    },
                    shared: {
                        name: 'shared',
                        minChunks: 2,
                        chunks: 'all',
                        priority: 5,
                        reuseExistingChunk: true
                    },
                    styles: {
                        name: 'styles',
                        test: /\.css$/,
                        chunks: 'all',
                        enforce: true
                    }
                }
            },
            runtimeChunk: 'single'
        },
        devtool: isProduction ? 'source-map' : 'source-map',
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
        }
    };
};
