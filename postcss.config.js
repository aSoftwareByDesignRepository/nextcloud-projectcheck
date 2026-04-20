module.exports = {
    plugins: {
        autoprefixer: {
            flexbox: 'no-2009'
        },
        cssnano: {
            preset: ['default', {
                discardComments: {
                    removeAll: true
                },
                normalizeWhitespace: true,
                colormin: true,
                minifyFontValues: true,
                minifySelectors: true,
                mergeLonghand: true,
                mergeRules: true,
                reduceIdents: false,
                reduceInitial: true,
                reduceTransforms: true,
                uniqueSelectors: true,
                zindex: false
            }]
        }
    }
};
