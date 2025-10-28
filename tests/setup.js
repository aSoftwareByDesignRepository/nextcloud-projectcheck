// Jest test setup file
// Configure test environment and global mocks

// Mock DOM APIs that might not be available in jsdom
global.ResizeObserver = jest.fn().mockImplementation(() => ({
    observe: jest.fn(),
    unobserve: jest.fn(),
    disconnect: jest.fn(),
}));

global.IntersectionObserver = jest.fn().mockImplementation(() => ({
    observe: jest.fn(),
    unobserve: jest.fn(),
    disconnect: jest.fn(),
}));

global.PerformanceObserver = jest.fn().mockImplementation(() => ({
    observe: jest.fn(),
    disconnect: jest.fn(),
}));

// Mock service worker
global.ServiceWorkerRegistration = jest.fn().mockImplementation(() => ({
    showNotification: jest.fn(),
    update: jest.fn(),
    unregister: jest.fn(),
}));

// Mock caches API
global.caches = {
    open: jest.fn(),
    keys: jest.fn(),
    delete: jest.fn(),
    match: jest.fn(),
};

// Mock localStorage
const localStorageMock = {
    getItem: jest.fn(),
    setItem: jest.fn(),
    removeItem: jest.fn(),
    clear: jest.fn(),
    length: 0,
};
global.localStorage = localStorageMock;

// Mock sessionStorage
const sessionStorageMock = {
    getItem: jest.fn(),
    setItem: jest.fn(),
    removeItem: jest.fn(),
    clear: jest.fn(),
    length: 0,
};
global.sessionStorage = sessionStorageMock;

// Mock fetch
global.fetch = jest.fn();

// Mock console methods to reduce noise in tests
global.console = {
    ...console,
    log: jest.fn(),
    warn: jest.fn(),
    error: jest.fn(),
};

// Mock window.matchMedia
Object.defineProperty(window, 'matchMedia', {
    writable: true,
    value: jest.fn().mockImplementation(query => ({
        matches: false,
        media: query,
        onchange: null,
        addListener: jest.fn(), // deprecated
        removeListener: jest.fn(), // deprecated
        addEventListener: jest.fn(),
        removeEventListener: jest.fn(),
        dispatchEvent: jest.fn(),
    })),
});

// Mock window.getComputedStyle
Object.defineProperty(window, 'getComputedStyle', {
    value: jest.fn().mockImplementation(() => ({
        getPropertyValue: jest.fn(),
    })),
});

// Mock document.createRange
document.createRange = () => ({
    setStart: () => {},
    setEnd: () => {},
    commonAncestorContainer: {
        nodeName: 'BODY',
        ownerDocument: document,
    },
});

// Mock window.scrollTo
window.scrollTo = jest.fn();

// Mock window.requestAnimationFrame
window.requestAnimationFrame = jest.fn(cb => setTimeout(cb, 0));

// Mock window.cancelAnimationFrame
window.cancelAnimationFrame = jest.fn();

// Mock window.URL.createObjectURL
window.URL.createObjectURL = jest.fn();

// Mock window.URL.revokeObjectURL
window.URL.revokeObjectURL = jest.fn();

// Set up test environment
beforeEach(() => {
    // Clear all mocks before each test
    jest.clearAllMocks();
    
    // Reset localStorage and sessionStorage
    localStorageMock.getItem.mockReturnValue(null);
    sessionStorageMock.getItem.mockReturnValue(null);
    
    // Reset fetch mock
    fetch.mockClear();
    
    // Reset console mocks
    console.log.mockClear();
    console.warn.mockClear();
    console.error.mockClear();
});

// Clean up after tests
afterEach(() => {
    // Clean up any timers
    jest.clearAllTimers();
});
