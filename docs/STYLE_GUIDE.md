# ProjectControl Style Guide

## Table of Contents

1. [Design Principles](#design-principles)
2. [Color System](#color-system)
3. [Typography](#typography)
4. [Spacing & Layout](#spacing--layout)
5. [Components](#components)
6. [CSS Architecture](#css-architecture)
7. [JavaScript Guidelines](#javascript-guidelines)
8. [Accessibility](#accessibility)
9. [Performance](#performance)
10. [Best Practices](#best-practices)

## Design Principles

### Core Values
- **Consistency**: All components follow the same design patterns
- **Accessibility**: WCAG 2.1 AA compliance for all users
- **Performance**: Fast loading and smooth interactions
- **Maintainability**: Clean, well-documented code
- **Responsiveness**: Works seamlessly across all devices

### Design Philosophy
- Mobile-first responsive design
- Progressive enhancement
- Semantic HTML structure
- CSS custom properties for theming
- Component-based architecture

## Color System

### Primary Colors
```css
--color-primary: #0082c9;        /* Nextcloud Blue */
--color-primary-hover: #006aa3;  /* Darker Blue */
--color-primary-light: #4da6ff;  /* Lighter Blue */
```

### Secondary Colors
```css
--color-secondary: #6c757d;      /* Gray */
--color-secondary-hover: #5a6268; /* Darker Gray */
--color-accent: #28a745;         /* Success Green */
--color-warning: #ffc107;        /* Warning Yellow */
--color-danger: #dc3545;         /* Danger Red */
```

### Semantic Colors
```css
--color-success: #28a745;
--color-info: #17a2b8;
--color-warning: #ffc107;
--color-error: #dc3545;
```

### Background Colors
```css
--color-background: #ffffff;
--color-background-secondary: #f8f9fa;
--color-background-tertiary: #e9ecef;
```

### Text Colors
```css
--color-text: #333333;
--color-text-secondary: #6c757d;
--color-text-muted: #9ca3af;
--color-text-inverse: #ffffff;
```

### Border Colors
```css
--color-border: #e1e5e9;
--color-border-focus: #0082c9;
--color-border-error: #dc3545;
```

### Dark Theme Colors
```css
[data-theme="dark"] {
    --color-background: #1a1a1a;
    --color-background-secondary: #2a2a2a;
    --color-text: #ffffff;
    --color-text-secondary: #cccccc;
    --color-border: #404040;
}
```

## Typography

### Font Stack
```css
--font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
--font-family-mono: 'SF Mono', Monaco, 'Cascadia Code', 'Roboto Mono', Consolas, 'Courier New', monospace;
```

### Font Sizes
```css
--font-size-xs: 0.75rem;    /* 12px */
--font-size-sm: 0.875rem;   /* 14px */
--font-size-base: 1rem;     /* 16px */
--font-size-lg: 1.125rem;   /* 18px */
--font-size-xl: 1.25rem;    /* 20px */
--font-size-2xl: 1.5rem;    /* 24px */
--font-size-3xl: 1.875rem;  /* 30px */
--font-size-4xl: 2.25rem;   /* 36px */
```

### Font Weights
```css
--font-weight-light: 300;
--font-weight-normal: 400;
--font-weight-medium: 500;
--font-weight-semibold: 600;
--font-weight-bold: 700;
```

### Line Heights
```css
--line-height-tight: 1.25;
--line-height-normal: 1.5;
--line-height-relaxed: 1.75;
```

### Heading Styles
```css
h1 {
    font-size: var(--font-size-3xl);
    font-weight: var(--font-weight-bold);
    line-height: var(--line-height-tight);
    margin-bottom: 1rem;
}

h2 {
    font-size: var(--font-size-2xl);
    font-weight: var(--font-weight-semibold);
    line-height: var(--line-height-tight);
    margin-bottom: 0.75rem;
}

h3 {
    font-size: var(--font-size-xl);
    font-weight: var(--font-weight-semibold);
    line-height: var(--line-height-normal);
    margin-bottom: 0.5rem;
}
```

## Spacing & Layout

### Spacing Scale
```css
--spacing-0: 0;
--spacing-1: 0.25rem;   /* 4px */
--spacing-2: 0.5rem;    /* 8px */
--spacing-3: 0.75rem;   /* 12px */
--spacing-4: 1rem;      /* 16px */
--spacing-5: 1.25rem;   /* 20px */
--spacing-6: 1.5rem;    /* 24px */
--spacing-8: 2rem;      /* 32px */
--spacing-10: 2.5rem;   /* 40px */
--spacing-12: 3rem;     /* 48px */
--spacing-16: 4rem;     /* 64px */
--spacing-20: 5rem;     /* 80px */
```

### Container Widths
```css
--container-sm: 640px;
--container-md: 768px;
--container-lg: 1024px;
--container-xl: 1280px;
--container-2xl: 1536px;
```

### Breakpoints
```css
--breakpoint-sm: 640px;
--breakpoint-md: 768px;
--breakpoint-lg: 1024px;
--breakpoint-xl: 1280px;
--breakpoint-2xl: 1536px;
```

### Border Radius
```css
--border-radius-sm: 0.125rem;  /* 2px */
--border-radius: 0.25rem;      /* 4px */
--border-radius-md: 0.375rem;  /* 6px */
--border-radius-lg: 0.5rem;    /* 8px */
--border-radius-xl: 0.75rem;   /* 12px */
--border-radius-full: 9999px;
```

### Shadows
```css
--shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
--shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
--shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
--shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
--shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
```

## Components

### Buttons

#### Primary Button
```html
<button class="btn btn-primary">Primary Action</button>
```

#### Secondary Button
```html
<button class="btn btn-secondary">Secondary Action</button>
```

#### Button Sizes
```html
<button class="btn btn-primary btn-sm">Small</button>
<button class="btn btn-primary">Default</button>
<button class="btn btn-primary btn-lg">Large</button>
```

#### Button States
```html
<button class="btn btn-primary" disabled>Disabled</button>
<button class="btn btn-primary loading">Loading</button>
```

### Forms

#### Form Group
```html
<div class="form-group">
    <label class="form-label" for="email">Email Address</label>
    <input type="email" class="form-control" id="email" placeholder="Enter your email">
    <div class="form-help">We'll never share your email with anyone else.</div>
</div>
```

#### Form Validation States
```html
<input type="email" class="form-control form-control-valid" value="valid@example.com">
<input type="email" class="form-control form-control-invalid" value="invalid-email">
```

#### Form Layouts
```html
<!-- Stacked Layout -->
<form class="form form-stacked">
    <!-- Form fields -->
</form>

<!-- Horizontal Layout -->
<form class="form form-horizontal">
    <!-- Form fields -->
</form>

<!-- Inline Layout -->
<form class="form form-inline">
    <!-- Form fields -->
</form>
```

### Cards

#### Basic Card
```html
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Card Title</h3>
    </div>
    <div class="card-body">
        <p>Card content goes here.</p>
    </div>
    <div class="card-footer">
        <button class="btn btn-primary">Action</button>
    </div>
</div>
```

#### Card Variants
```html
<div class="card card-elevated">Elevated Card</div>
<div class="card card-outlined">Outlined Card</div>
<div class="card card-flat">Flat Card</div>
```

### Tables

#### Basic Table
```html
<table class="table">
    <thead>
        <tr>
            <th>Name</th>
            <th>Email</th>
            <th>Role</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>John Doe</td>
            <td>john@example.com</td>
            <td>Admin</td>
        </tr>
    </tbody>
</table>
```

#### Table Variants
```html
<table class="table table-striped">Striped Table</table>
<table class="table table-bordered">Bordered Table</table>
<table class="table table-hover">Hover Table</table>
<table class="table table-compact">Compact Table</table>
```

### Alerts

#### Alert Types
```html
<div class="alert alert-success">Success message</div>
<div class="alert alert-info">Info message</div>
<div class="alert alert-warning">Warning message</div>
<div class="alert alert-error">Error message</div>
```

#### Dismissible Alert
```html
<div class="alert alert-info alert-dismissible">
    <span class="alert-message">This alert can be dismissed.</span>
    <button class="alert-close" aria-label="Close">×</button>
</div>
```

### Modals

#### Basic Modal
```html
<div class="modal" id="exampleModal">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Modal Title</h3>
                <button class="modal-close" aria-label="Close">×</button>
            </div>
            <div class="modal-body">
                <p>Modal content goes here.</p>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button class="btn btn-primary">Save Changes</button>
            </div>
        </div>
    </div>
</div>
```

### Navigation

#### Primary Navigation
```html
<nav class="nav nav-primary">
    <ul class="nav-list">
        <li class="nav-item">
            <a href="#" class="nav-link nav-link-active">Dashboard</a>
        </li>
        <li class="nav-item">
            <a href="#" class="nav-link">Projects</a>
        </li>
        <li class="nav-item">
            <a href="#" class="nav-link">Time Entries</a>
        </li>
    </ul>
</nav>
```

#### Breadcrumbs
```html
<nav class="breadcrumb" aria-label="Breadcrumb">
    <ol class="breadcrumb-list">
        <li class="breadcrumb-item">
            <a href="#" class="breadcrumb-link">Home</a>
        </li>
        <li class="breadcrumb-item">
            <a href="#" class="breadcrumb-link">Projects</a>
        </li>
        <li class="breadcrumb-item breadcrumb-item-active">
            <span>Project Details</span>
        </li>
    </ol>
</nav>
```

## CSS Architecture

### File Structure
```
css/
├── common/
│   ├── base.css          # Base styles and resets
│   ├── colors.css        # Color variables
│   ├── typography.css    # Typography system
│   ├── layout.css        # Layout and grid system
│   ├── components.css    # Reusable components
│   ├── utilities.css     # Utility classes
│   ├── animations.css    # Animations and transitions
│   ├── accessibility.css # Accessibility enhancements
│   └── print.css         # Print styles
├── projects.css          # Project-specific styles
├── customers.css         # Customer-specific styles
├── time-entries.css      # Time entry styles
├── dashboard.css         # Dashboard styles
└── settings.css          # Settings styles
```

### Naming Conventions

#### BEM Methodology
```css
.block {}
.block__element {}
.block__element--modifier {}
.block--modifier {}
```

#### Examples
```css
.card {}
.card__header {}
.card__title {}
.card__body {}
.card__footer {}
.card--elevated {}
.card--outlined {}

.btn {}
.btn--primary {}
.btn--secondary {}
.btn--large {}
.btn--disabled {}
```

### CSS Custom Properties
```css
/* Define variables in :root */
:root {
    --color-primary: #0082c9;
    --spacing-base: 1rem;
    --border-radius: 0.25rem;
}

/* Use variables in components */
.btn {
    background-color: var(--color-primary);
    padding: var(--spacing-base);
    border-radius: var(--border-radius);
}

/* Override for themes */
[data-theme="dark"] {
    --color-primary: #4da6ff;
}
```

### Utility Classes
```css
/* Spacing utilities */
.m-0 { margin: 0; }
.m-1 { margin: 0.25rem; }
.m-2 { margin: 0.5rem; }
.m-4 { margin: 1rem; }

.p-0 { padding: 0; }
.p-1 { padding: 0.25rem; }
.p-2 { padding: 0.5rem; }
.p-4 { padding: 1rem; }

/* Text utilities */
.text-left { text-align: left; }
.text-center { text-align: center; }
.text-right { text-align: right; }

.text-sm { font-size: 0.875rem; }
.text-base { font-size: 1rem; }
.text-lg { font-size: 1.125rem; }

/* Display utilities */
.d-none { display: none; }
.d-block { display: block; }
.d-inline { display: inline; }
.d-inline-block { display: inline-block; }
.d-flex { display: flex; }
.d-grid { display: grid; }
```

## JavaScript Guidelines

### Module Structure
```javascript
// Use ES6 modules
import { ComponentManager } from './components.js';
import { ValidationManager } from './validation.js';

// Export classes and functions
export class MyComponent {
    constructor() {
        this.init();
    }
    
    init() {
        // Initialize component
    }
}
```

### Event Handling
```javascript
// Use event delegation when possible
document.addEventListener('click', (event) => {
    if (event.target.matches('.btn')) {
        handleButtonClick(event);
    }
});

// Use custom events for component communication
element.dispatchEvent(new CustomEvent('component:updated', {
    detail: { data: 'value' }
}));
```

### Error Handling
```javascript
// Use try-catch for async operations
async function fetchData() {
    try {
        const response = await fetch('/api/data');
        const data = await response.json();
        return data;
    } catch (error) {
        console.error('Failed to fetch data:', error);
        throw error;
    }
}
```

### Performance
```javascript
// Use debouncing for frequent events
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

// Use throttling for scroll events
function throttle(func, limit) {
    let inThrottle;
    return function() {
        const args = arguments;
        const context = this;
        if (!inThrottle) {
            func.apply(context, args);
            inThrottle = true;
            setTimeout(() => inThrottle = false, limit);
        }
    };
}
```

## Accessibility

### ARIA Labels
```html
<button aria-label="Close modal" class="modal-close">×</button>
<input aria-describedby="email-help" type="email" id="email">
<div id="email-help">Enter your email address</div>
```

### Focus Management
```css
/* Visible focus indicators */
.btn:focus,
.form-control:focus {
    outline: 2px solid var(--color-primary);
    outline-offset: 2px;
}

/* Skip links */
.skip-link {
    position: absolute;
    top: -40px;
    left: 6px;
    background: var(--color-primary);
    color: white;
    padding: 8px;
    text-decoration: none;
    z-index: 1000;
}

.skip-link:focus {
    top: 6px;
}
```

### Keyboard Navigation
```javascript
// Handle keyboard navigation
element.addEventListener('keydown', (event) => {
    switch (event.key) {
        case 'Enter':
        case ' ':
            event.preventDefault();
            handleAction();
            break;
        case 'Escape':
            closeModal();
            break;
    }
});
```

### Screen Reader Support
```html
<!-- Use semantic HTML -->
<nav role="navigation" aria-label="Main navigation">
    <ul role="menubar">
        <li role="none">
            <a role="menuitem" href="#">Dashboard</a>
        </li>
    </ul>
</nav>

<!-- Hide content visually but keep it accessible -->
.sr-only {
    position: absolute;
    width: 1px;
    height: 1px;
    padding: 0;
    margin: -1px;
    overflow: hidden;
    clip: rect(0, 0, 0, 0);
    white-space: nowrap;
    border: 0;
}
```

## Performance

### CSS Optimization
```css
/* Use efficient selectors */
.card .title { } /* Good */
.card > .title { } /* Better */

/* Avoid deep nesting */
.card .header .title .text { } /* Bad */
.card-title { } /* Good */

/* Use CSS custom properties for dynamic values */
.element {
    transform: translateX(var(--translate-x, 0));
}
```

### JavaScript Optimization
```javascript
// Use event delegation
document.addEventListener('click', handleClick);

// Cache DOM queries
const elements = document.querySelectorAll('.item');
elements.forEach(element => {
    // Work with element
});

// Use requestAnimationFrame for animations
function animate() {
    requestAnimationFrame(() => {
        // Animation code
    });
}
```

### Image Optimization
```html
<!-- Use responsive images -->
<img src="image.jpg" 
     srcset="image-300w.jpg 300w, image-600w.jpg 600w, image-900w.jpg 900w"
     sizes="(max-width: 600px) 300px, (max-width: 900px) 600px, 900px"
     alt="Description">

<!-- Use lazy loading -->
<img src="image.jpg" loading="lazy" alt="Description">
```

## Best Practices

### HTML Structure
```html
<!-- Use semantic HTML -->
<main>
    <header>
        <h1>Page Title</h1>
        <nav aria-label="Breadcrumb">
            <!-- Breadcrumbs -->
        </nav>
    </header>
    
    <section>
        <h2>Section Title</h2>
        <!-- Content -->
    </section>
    
    <aside>
        <!-- Sidebar content -->
    </aside>
</main>
```

### CSS Organization
```css
/* Group related styles */
/* Layout */
.container { }
.grid { }
.flex { }

/* Components */
.btn { }
.card { }
.form { }

/* Utilities */
.text-center { }
.m-0 { }
.d-none { }
```

### JavaScript Organization
```javascript
// Use consistent naming
const userData = getUserData();
const isValid = validateForm();
const handleSubmit = () => { };

// Use descriptive variable names
const projectList = getProjects();
const activeProject = getActiveProject();
const projectCount = projectList.length;
```

### Testing
```javascript
// Write unit tests for components
describe('Button Component', () => {
    test('renders with correct text', () => {
        const button = render(<Button>Click me</Button>);
        expect(button).toHaveTextContent('Click me');
    });
    
    test('calls onClick when clicked', () => {
        const handleClick = jest.fn();
        const button = render(<Button onClick={handleClick}>Click me</Button>);
        button.click();
        expect(handleClick).toHaveBeenCalled();
    });
});
```

### Documentation
```javascript
/**
 * Validates a form field
 * @param {string} value - The field value to validate
 * @param {string} type - The field type (email, required, etc.)
 * @returns {boolean} - Whether the field is valid
 */
function validateField(value, type) {
    // Validation logic
}
```

This style guide should be used as a reference for all development work on the ProjectControl application. It ensures consistency, maintainability, and accessibility across the entire codebase.
