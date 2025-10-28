# Changelog

All notable changes to the `projectcontrol` app will be documented in this file.

This project adheres to [Semantic Versioning](https://semver.org/) and the format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).

## [1.1.1] - 2025-09-22
### Added
- Project detail: show project type text label next to the icon.
- Employees: initial management screens and supporting backend (foundational work).
- Customers: analytics API and statistics styling foundations.

### Changed
#### Dashboard
- Dashboard/project cards: left status accent now uses inset shadow (prevents clipping by overflow).
- Tooltips for project type icons no longer get cut off.

#### Projects list
- Refined priority/budget badges to consistent pill sizing and alignment.
- Project name and badges placed on one row; long names truncate with ellipsis.
- Minor table spacing and hover/popup fixes for time entries affecting list views.

#### Project detail
- Unified budget progress bar styling with other views (8px height, border, consistent gradients and rounded corners).

#### Translations/JS
- Updated English/German labels; enhanced dashboard JS interactions.

### Fixed
- Progress bar visual separation and clipping issues across views.
- Various layout polish for search inputs and popups.

### Upgrade notes
- Upload the new `projectcontrol` folder and visit Nextcloud as admin; the app update will run automatically.
- No breaking schema changes; existing data is preserved. Clear browser cache if styles look out of date.

## [1.1.0] - 2025-09-01
### Added
- Initial public version with projects, customers, time entries, and budget tracking.

[1.1.1]: https://github.com/aSoftwareByDesignRepository/nextcloud-projectcontroll/compare/v1.1.0...v1.1.1
[1.1.0]: https://github.com/aSoftwareByDesignRepository/nextcloud-projectcontroll/releases/tag/v1.1.0

# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2024-12-19

### Added
- Initial release of Project Control app
- Complete project management functionality
- Customer management system with contact information
- Time tracking with manual entry creation and editing
- Budget management with real-time consumption monitoring
- Dashboard with comprehensive project overview
- REST API for all major entities (projects, customers, time entries)
- Multi-language support (English, German)
- Responsive design for mobile and desktop
- Real-time budget impact warnings
- Project status management and workflow
- Team member assignment and role management
- Search and filtering capabilities across all modules
- Export functionality for reports
- Admin settings for global configuration
- User preferences and personal settings
- Database migrations for clean installation
- Comprehensive documentation and README

### Features
- **Project Management**: Create, edit, and manage projects with detailed information
- **Customer Management**: Complete customer database with contact management
- **Time Tracking**: Manual time entry with project association and cost calculation
- **Budget Control**: Real-time budget monitoring with warning thresholds
- **Dashboard**: Comprehensive overview with statistics and quick actions
- **Reporting**: Time and cost reporting with export capabilities
- **API Integration**: Full REST API for external integrations
- **Multi-language**: Support for English and German languages
- **Responsive Design**: Works on desktop, tablet, and mobile devices

### Technical
- Built for Nextcloud 25+ compatibility
- PHP 8.1+ requirement
- MySQL/MariaDB database support
- Modern JavaScript with ES6+ features
- CSS Grid and Flexbox for responsive layouts
- Progressive Web App capabilities
- Service Worker for offline functionality
- Comprehensive error handling and validation
- Security-first approach with CSRF protection
- Performance optimized with lazy loading

## [0.9.0] - 2024-12-18

### Added
- Enhanced time entry form with readonly hourly rate functionality
- Budget impact warnings positioned above time entry fields
- Improved contrast and spacing for budget warning messages
- Remaining hours display in project budget overview
- Project budget information section in time entry forms

### Changed
- Improved user experience in time entry creation
- Better visual feedback for budget-related warnings
- Enhanced form layout and spacing

### Fixed
- Budget warning message positioning
- Contrast issues in warning messages
- Spacing problems in form layouts

## [0.8.0] - 2024-12-17

### Added
- Project budget overview integration
- Real-time budget calculations
- Budget warning system with thresholds
- Cost tracking and reporting
- Financial analytics dashboard

### Changed
- Enhanced project management with budget controls
- Improved dashboard with financial metrics
- Better integration between time tracking and budget management

## [0.7.0] - 2024-12-16

### Added
- Time tracking functionality
- Manual time entry creation and editing
- Project-based time tracking
- Hourly rate calculation
- Time entry search and filtering

### Changed
- Enhanced project management workflow
- Improved user interface for time tracking
- Better integration with project data

## [0.6.0] - 2024-12-15

### Added
- Customer management system
- Customer-project relationships
- Contact person management
- Customer analytics and reporting

### Changed
- Enhanced project creation with customer selection
- Improved data relationships
- Better organization of project data

## [0.5.0] - 2024-12-14

### Added
- Project management core functionality
- Project creation and editing
- Project status management
- Team member assignment
- Project categorization and tagging

### Changed
- Initial project management implementation
- Basic user interface development
- Database schema design

## [0.1.0] - 2024-12-13

### Added
- Initial project setup
- Basic app structure
- Nextcloud integration
- Development environment setup
- Initial documentation

---

## Development Notes

### Version 1.0.0 Release Notes
This is the first stable release of the Project Control app for Nextcloud. The app provides comprehensive project management capabilities with time tracking and budget control features.

### Key Features in 1.0.0
- Complete project lifecycle management
- Customer relationship management
- Time tracking and cost calculation
- Budget monitoring and warnings
- Comprehensive dashboard and reporting
- Full REST API for integrations
- Multi-language support
- Responsive design

### Installation Requirements
- Nextcloud 25 or higher
- PHP 8.1 or higher
- MySQL/MariaDB database
- Minimum 256MB PHP memory limit
- 50MB disk space for app files

### Upgrade Notes
This is the initial release, so no upgrade path is needed. For future versions, upgrade instructions will be provided in the release notes.

### Breaking Changes
None - this is the initial release.

### Deprecations
None - this is the initial release.

### Security
- All user inputs are validated and sanitized
- CSRF protection implemented
- SQL injection prevention
- XSS protection
- Secure file handling

### Performance
- Optimized database queries
- Lazy loading for large datasets
- Efficient caching mechanisms
- Minimal resource usage
- Fast page load times

---

**Author**: Alexander Mäule  
**Company**: Software by Design GbR  
**License**: AGPL-3.0-or-later  
**Website**: https://software-by-design.de  
**Support**: https://github.com/aSoftwareByDesignRepository/nextcloud-projectcontroll/issues
