# ProjectControl Component Library

## Table of Contents

1. [Overview](#overview)
2. [Button Components](#button-components)
3. [Form Components](#form-components)
4. [Card Components](#card-components)
5. [Table Components](#table-components)
6. [Alert Components](#alert-components)
7. [Modal Components](#modal-components)
8. [Navigation Components](#navigation-components)
9. [Layout Components](#layout-components)
10. [Utility Components](#utility-components)

## Overview

This document provides detailed documentation for all reusable components in the ProjectControl application. Each component includes:

- **Description**: What the component does
- **Props/Attributes**: Available options and their types
- **Examples**: Code examples showing different use cases
- **Accessibility**: ARIA attributes and keyboard navigation
- **Styling**: Available CSS classes and customization options

## Button Components

### Primary Button

**Description**: Main action button with primary styling.

**Attributes**:
- `disabled` - Disables the button
- `type` - Button type (button, submit, reset)
- `aria-label` - Screen reader label

**Examples**:

```html
<!-- Basic primary button -->
<button class="btn btn-primary">Save Changes</button>

<!-- With icon -->
<button class="btn btn-primary">
    <i class="icon icon-save"></i>
    Save Changes
</button>

<!-- Disabled state -->
<button class="btn btn-primary" disabled>Save Changes</button>

<!-- Loading state -->
<button class="btn btn-primary loading">
    <span class="loading-spinner"></span>
    Saving...
</button>
```

**Accessibility**:
```html
<button class="btn btn-primary" aria-label="Save project changes">
    Save Changes
</button>
```

### Secondary Button

**Description**: Secondary action button with less emphasis.

**Examples**:
```html
<button class="btn btn-secondary">Cancel</button>
<button class="btn btn-secondary" disabled>Cancel</button>
```

### Danger Button

**Description**: Button for destructive actions.

**Examples**:
```html
<button class="btn btn-danger">Delete Project</button>
<button class="btn btn-danger btn-sm">Delete</button>
```

### Button Sizes

**Available sizes**: `btn-sm`, `btn-md` (default), `btn-lg`

```html
<button class="btn btn-primary btn-sm">Small</button>
<button class="btn btn-primary">Default</button>
<button class="btn btn-primary btn-lg">Large</button>
```

### Button Groups

**Description**: Group related buttons together.

```html
<div class="btn-group">
    <button class="btn btn-secondary">Left</button>
    <button class="btn btn-secondary">Middle</button>
    <button class="btn btn-secondary">Right</button>
</div>
```

## Form Components

### Form Group

**Description**: Container for form fields with label and help text.

**Examples**:
```html
<div class="form-group">
    <label class="form-label" for="email">Email Address</label>
    <input type="email" class="form-control" id="email" placeholder="Enter your email">
    <div class="form-help">We'll never share your email with anyone else.</div>
</div>
```

### Form Control

**Description**: Base input styling for all form elements.

**Examples**:
```html
<!-- Text input -->
<input type="text" class="form-control" placeholder="Enter text">

<!-- Email input -->
<input type="email" class="form-control" placeholder="Enter email">

<!-- Password input -->
<input type="password" class="form-control" placeholder="Enter password">

<!-- Textarea -->
<textarea class="form-control" rows="3" placeholder="Enter description"></textarea>

<!-- Select -->
<select class="form-control">
    <option>Choose an option</option>
    <option>Option 1</option>
    <option>Option 2</option>
</select>
```

### Form Validation States

**Available states**: `form-control-valid`, `form-control-invalid`

```html
<!-- Valid state -->
<div class="form-group">
    <label class="form-label" for="valid-email">Valid Email</label>
    <input type="email" class="form-control form-control-valid" id="valid-email" value="user@example.com">
    <div class="form-feedback form-feedback-valid">Email is valid!</div>
</div>

<!-- Invalid state -->
<div class="form-group">
    <label class="form-label" for="invalid-email">Invalid Email</label>
    <input type="email" class="form-control form-control-invalid" id="invalid-email" value="invalid-email">
    <div class="form-feedback form-feedback-invalid">Please enter a valid email address.</div>
</div>
```

### Form Layouts

**Available layouts**: `form-stacked` (default), `form-horizontal`, `form-inline`

```html
<!-- Stacked layout (default) -->
<form class="form form-stacked">
    <div class="form-group">
        <label class="form-label" for="name">Name</label>
        <input type="text" class="form-control" id="name">
    </div>
    <div class="form-group">
        <label class="form-label" for="email">Email</label>
        <input type="email" class="form-control" id="email">
    </div>
</form>

<!-- Horizontal layout -->
<form class="form form-horizontal">
    <div class="form-group">
        <label class="form-label" for="name">Name</label>
        <div class="form-control-wrapper">
            <input type="text" class="form-control" id="name">
        </div>
    </div>
</form>

<!-- Inline layout -->
<form class="form form-inline">
    <div class="form-group">
        <label class="form-label" for="search">Search</label>
        <input type="text" class="form-control" id="search">
    </div>
    <button type="submit" class="btn btn-primary">Search</button>
</form>
```

## Card Components

### Basic Card

**Description**: Container for content with header, body, and footer sections.

**Examples**:
```html
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Project Details</h3>
    </div>
    <div class="card-body">
        <p>This is the main content of the card.</p>
        <p>You can put any content here.</p>
    </div>
    <div class="card-footer">
        <button class="btn btn-primary">Save</button>
        <button class="btn btn-secondary">Cancel</button>
    </div>
</div>
```

### Card Variants

**Available variants**: `card-elevated`, `card-outlined`, `card-flat`

```html
<!-- Elevated card with shadow -->
<div class="card card-elevated">
    <div class="card-body">Elevated content</div>
</div>

<!-- Outlined card with border -->
<div class="card card-outlined">
    <div class="card-body">Outlined content</div>
</div>

<!-- Flat card without shadow or border -->
<div class="card card-flat">
    <div class="card-body">Flat content</div>
</div>
```

### Card with Image

```html
<div class="card">
    <img src="project-image.jpg" class="card-image" alt="Project screenshot">
    <div class="card-body">
        <h3 class="card-title">Project Name</h3>
        <p class="card-text">Project description goes here.</p>
    </div>
</div>
```

### Interactive Card

```html
<div class="card card-interactive">
    <div class="card-body">
        <h3 class="card-title">Clickable Card</h3>
        <p>This card can be clicked to navigate.</p>
    </div>
</div>
```

## Table Components

### Basic Table

**Description**: Responsive table for displaying tabular data.

**Examples**:
```html
<table class="table">
    <thead>
        <tr>
            <th>Name</th>
            <th>Email</th>
            <th>Role</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>John Doe</td>
            <td>john@example.com</td>
            <td>Admin</td>
            <td>
                <button class="btn btn-sm btn-secondary">Edit</button>
                <button class="btn btn-sm btn-danger">Delete</button>
            </td>
        </tr>
        <tr>
            <td>Jane Smith</td>
            <td>jane@example.com</td>
            <td>User</td>
            <td>
                <button class="btn btn-sm btn-secondary">Edit</button>
                <button class="btn btn-sm btn-danger">Delete</button>
            </td>
        </tr>
    </tbody>
</table>
```

### Table Variants

**Available variants**: `table-striped`, `table-bordered`, `table-hover`, `table-compact`

```html
<!-- Striped table -->
<table class="table table-striped">
    <!-- Table content -->
</table>

<!-- Bordered table -->
<table class="table table-bordered">
    <!-- Table content -->
</table>

<!-- Hover effects -->
<table class="table table-hover">
    <!-- Table content -->
</table>

<!-- Compact table -->
<table class="table table-compact">
    <!-- Table content -->
</table>
```

### Responsive Table

```html
<div class="table-responsive">
    <table class="table">
        <!-- Table content -->
    </table>
</div>
```

### Sortable Table

```html
<table class="table table-sortable">
    <thead>
        <tr>
            <th class="sortable" data-sort="name">
                Name
                <span class="sort-indicator"></span>
            </th>
            <th class="sortable" data-sort="email">
                Email
                <span class="sort-indicator"></span>
            </th>
        </tr>
    </thead>
    <tbody>
        <!-- Table rows -->
    </tbody>
</table>
```

## Alert Components

### Alert Types

**Available types**: `alert-success`, `alert-info`, `alert-warning`, `alert-error`

**Examples**:
```html
<!-- Success alert -->
<div class="alert alert-success">
    <i class="icon icon-check"></i>
    <span class="alert-message">Project saved successfully!</span>
</div>

<!-- Info alert -->
<div class="alert alert-info">
    <i class="icon icon-info"></i>
    <span class="alert-message">New features are available.</span>
</div>

<!-- Warning alert -->
<div class="alert alert-warning">
    <i class="icon icon-warning"></i>
    <span class="alert-message">Please review your changes before saving.</span>
</div>

<!-- Error alert -->
<div class="alert alert-error">
    <i class="icon icon-error"></i>
    <span class="alert-message">Failed to save project. Please try again.</span>
</div>
```

### Dismissible Alert

```html
<div class="alert alert-info alert-dismissible">
    <i class="icon icon-info"></i>
    <span class="alert-message">This alert can be dismissed.</span>
    <button class="alert-close" aria-label="Close alert">×</button>
</div>
```

### Alert with Actions

```html
<div class="alert alert-warning">
    <i class="icon icon-warning"></i>
    <span class="alert-message">You have unsaved changes.</span>
    <div class="alert-actions">
        <button class="btn btn-sm btn-primary">Save</button>
        <button class="btn btn-sm btn-secondary">Discard</button>
    </div>
</div>
```

## Modal Components

### Basic Modal

**Description**: Overlay dialog for focused interactions.

**Examples**:
```html
<!-- Modal trigger -->
<button class="btn btn-primary" data-modal="exampleModal">Open Modal</button>

<!-- Modal -->
<div class="modal" id="exampleModal">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Modal Title</h3>
                <button class="modal-close" aria-label="Close modal">×</button>
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

### Modal Sizes

**Available sizes**: `modal-sm`, `modal-md` (default), `modal-lg`, `modal-xl`

```html
<div class="modal" id="smallModal">
    <div class="modal-dialog modal-sm">
        <!-- Modal content -->
    </div>
</div>

<div class="modal" id="largeModal">
    <div class="modal-dialog modal-lg">
        <!-- Modal content -->
    </div>
</div>
```

### Modal with Form

```html
<div class="modal" id="formModal">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Add New Project</h3>
                <button class="modal-close" aria-label="Close modal">×</button>
            </div>
            <div class="modal-body">
                <form class="form form-stacked">
                    <div class="form-group">
                        <label class="form-label" for="projectName">Project Name</label>
                        <input type="text" class="form-control" id="projectName" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="projectDescription">Description</label>
                        <textarea class="form-control" id="projectDescription" rows="3"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button class="btn btn-primary" type="submit">Create Project</button>
            </div>
        </div>
    </div>
</div>
```

## Navigation Components

### Primary Navigation

**Description**: Main application navigation.

**Examples**:
```html
<nav class="nav nav-primary">
    <ul class="nav-list">
        <li class="nav-item">
            <a href="/dashboard" class="nav-link nav-link-active">
                <i class="icon icon-dashboard"></i>
                Dashboard
            </a>
        </li>
        <li class="nav-item">
            <a href="/projects" class="nav-link">
                <i class="icon icon-projects"></i>
                Projects
            </a>
        </li>
        <li class="nav-item">
            <a href="/time-entries" class="nav-link">
                <i class="icon icon-time"></i>
                Time Entries
            </a>
        </li>
        <li class="nav-item">
            <a href="/customers" class="nav-link">
                <i class="icon icon-customers"></i>
                Customers
            </a>
        </li>
    </ul>
</nav>
```

### Breadcrumbs

**Description**: Navigation breadcrumbs for deep pages.

```html
<nav class="breadcrumb" aria-label="Breadcrumb">
    <ol class="breadcrumb-list">
        <li class="breadcrumb-item">
            <a href="/" class="breadcrumb-link">Home</a>
        </li>
        <li class="breadcrumb-item">
            <a href="/projects" class="breadcrumb-link">Projects</a>
        </li>
        <li class="breadcrumb-item breadcrumb-item-active">
            <span>Project Details</span>
        </li>
    </ol>
</nav>
```

### Tabs

**Description**: Tabbed navigation for content sections.

```html
<div class="tabs">
    <ul class="tab-list" role="tablist">
        <li class="tab-item" role="presentation">
            <button class="tab-button tab-button-active" role="tab" aria-selected="true">
                Overview
            </button>
        </li>
        <li class="tab-item" role="presentation">
            <button class="tab-button" role="tab" aria-selected="false">
                Time Entries
            </button>
        </li>
        <li class="tab-item" role="presentation">
            <button class="tab-button" role="tab" aria-selected="false">
                Settings
            </button>
        </li>
    </ul>
    <div class="tab-content">
        <div class="tab-panel tab-panel-active" role="tabpanel">
            <h3>Overview Content</h3>
            <p>This is the overview tab content.</p>
        </div>
        <div class="tab-panel" role="tabpanel">
            <h3>Time Entries Content</h3>
            <p>This is the time entries tab content.</p>
        </div>
        <div class="tab-panel" role="tabpanel">
            <h3>Settings Content</h3>
            <p>This is the settings tab content.</p>
        </div>
    </div>
</div>
```

## Layout Components

### Container

**Description**: Responsive container for content width management.

```html
<div class="container">
    <h1>Page Content</h1>
    <p>This content is constrained to a maximum width.</p>
</div>

<div class="container container-fluid">
    <p>This content spans the full width.</p>
</div>
```

### Grid System

**Description**: CSS Grid-based layout system.

```html
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
    <div class="card">Card 1</div>
    <div class="card">Card 2</div>
    <div class="card">Card 3</div>
</div>
```

### Flexbox Utilities

```html
<div class="flex justify-between items-center">
    <h2>Page Title</h2>
    <button class="btn btn-primary">Action</button>
</div>

<div class="flex flex-col md:flex-row gap-4">
    <div class="flex-1">Left content</div>
    <div class="flex-1">Right content</div>
</div>
```

### Sidebar Layout

```html
<div class="layout layout-sidebar">
    <aside class="sidebar">
        <nav class="nav nav-primary">
            <!-- Navigation items -->
        </nav>
    </aside>
    <main class="main-content">
        <div class="container">
            <!-- Main content -->
        </div>
    </main>
</div>
```

## Utility Components

### Loading Spinner

**Description**: Animated loading indicator.

```html
<!-- Default spinner -->
<div class="loading-spinner"></div>

<!-- Spinner with text -->
<div class="loading">
    <div class="loading-spinner"></div>
    <span class="loading-text">Loading...</span>
</div>

<!-- Button with spinner -->
<button class="btn btn-primary loading">
    <div class="loading-spinner"></div>
    <span>Saving...</span>
</button>
```

### Empty State

**Description**: Component for when there's no data to display.

```html
<div class="empty-state">
    <div class="empty-state-icon">
        <i class="icon icon-empty"></i>
    </div>
    <h3 class="empty-state-title">No projects found</h3>
    <p class="empty-state-description">
        Get started by creating your first project.
    </p>
    <button class="btn btn-primary">Create Project</button>
</div>
```

### Tooltip

**Description**: Hover tooltip for additional information.

```html
<button class="btn btn-secondary" data-tooltip="This will delete the project permanently">
    Delete Project
</button>

<span data-tooltip="Help text here">Help</span>
```

### Badge

**Description**: Small status indicators.

```html
<span class="badge badge-success">Active</span>
<span class="badge badge-warning">Pending</span>
<span class="badge badge-error">Error</span>
<span class="badge badge-info">New</span>

<!-- Badge with count -->
<span class="badge badge-primary">5</span>
```

### Divider

**Description**: Visual separator between content sections.

```html
<div class="content">
    <h2>Section 1</h2>
    <p>Content here...</p>
</div>

<hr class="divider">

<div class="content">
    <h2>Section 2</h2>
    <p>More content...</p>
</div>
```

This component library provides a comprehensive set of reusable UI components that follow consistent design patterns and accessibility guidelines. All components are designed to work together seamlessly while maintaining flexibility for customization.
