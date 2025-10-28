module.exports = {
    testEnvironment: 'jsdom',
    roots: ['<rootDir>/js', '<rootDir>/tests'],
    testMatch: [
        '**/__tests__/**/*.js',
        '**/?(*.)+(spec|test).js'
    ],
    collectCoverageFrom: [
        'js/**/*.js',
        '!js/vendor/**',
        '!js/dist/**'
    ],
    coverageDirectory: 'coverage',
    coverageReporters: ['text', 'lcov', 'html'],
    setupFilesAfterEnv: ['<rootDir>/tests/setup.js'],
    moduleNameMapper: {
        '^@/(.*)$': '<rootDir>/js/$1',
        '^@css/(.*)$': '<rootDir>/css/$1',
        '^@templates/(.*)$': '<rootDir>/templates/$1'
    },
    transform: {
        '^.+\\.js$': 'babel-jest'
    },
    transformIgnorePatterns: [
        'node_modules/(?!(chart\.js|date-fns)/)'
    ],
    testPathIgnorePatterns: [
        '/node_modules/',
        '/dist/'
    ]
};
