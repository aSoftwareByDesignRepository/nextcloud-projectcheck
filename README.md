
## 1.2.2 - 2025-09-23
- Fix: Edit time entry updates existing entries (form + AJAX)
- Fix: Robust date/number parsing in updates
- Chore: CSV export preserves all filters
- Chore: i18n additions across dashboards and details

# Project Control App for Nextcloud

A comprehensive project management and time tracking application for Nextcloud.

## Features

### Project Management
- Create and manage projects with detailed information
- Track project status, priority, and deadlines
- Manage project budgets and hourly rates
- Team member management and role assignment
- Project categorization and tagging

### Customer Management
- Complete customer database with contact information
- Customer-project relationships
- Contact person management
- Customer analytics and reporting

### Time Tracking
- Manual time entry creation and editing
- Project-based time tracking
- Hourly rate calculation and cost tracking
- Time entry search and filtering
- Time summary and reporting

### Budget Management
- Real-time budget consumption calculation
- Budget warning thresholds
- Cost tracking and reporting
- Financial analytics

### Dashboard and Reporting
- Comprehensive dashboard with project overview
- Real-time statistics and metrics
- Project performance tracking
- Time and cost reporting

## Installation

### Requirements
- Nextcloud 25 or higher
- PHP 8.0 or higher
- MySQL/MariaDB database

### Installation Steps
1. Download the app files to your Nextcloud `apps` directory
2. Enable the app in Nextcloud admin settings
3. Run the database migrations automatically
4. Configure app settings as needed

## Configuration

### Admin Settings
- Default hourly rate for new projects
- Budget warning threshold percentage
- Maximum projects per user
- Enable/disable time tracking
- Enable/disable customer management
- Enable/disable budget tracking

### User Preferences
- Personal default hourly rate
- Dashboard refresh interval
- Show/hide completed projects
- Time entry reminders
- Email notifications
- Default time entry duration

## Usage

### Creating Projects
1. Navigate to Projects section
2. Click "Create Project"
3. Fill in project details (name, description, customer, etc.)
4. Set budget and hourly rate
5. Add team members
6. Save the project

### Managing Customers
1. Go to Customers section
2. Click "Create Customer"
3. Enter customer information
4. Add contact details
5. Save customer

### Time Tracking
1. Navigate to Time Entries
2. Click "Create Time Entry"
3. Select project and date
4. Enter hours and description
5. Save time entry

### Dashboard
- View project overview and statistics
- Monitor budget consumption
- Track time and costs
- Access quick actions

## API

The app provides a REST API for integration:

### Projects
- `GET /api/projects` - List projects
- `POST /api/projects` - Create project
- `GET /api/projects/{id}` - Get project details
- `PUT /api/projects/{id}` - Update project
- `DELETE /api/projects/{id}` - Delete project

### Customers
- `GET /api/customers` - List customers
- `POST /api/customers` - Create customer
- `GET /api/customers/{id}` - Get customer details
- `PUT /api/customers/{id}` - Update customer
- `DELETE /api/customers/{id}` - Delete customer

### Time Entries
- `GET /api/time-entries` - List time entries
- `POST /api/time-entries` - Create time entry
- `GET /api/time-entries/{id}` - Get time entry details
- `PUT /api/time-entries/{id}` - Update time entry
- `DELETE /api/time-entries/{id}` - Delete time entry

## Development

### Project Structure
```
projectcontrol/
├── appinfo/           # App configuration
├── lib/              # PHP classes
│   ├── Controller/   # Controllers
│   ├── Service/      # Business logic
│   ├── Db/          # Database entities and mappers
│   ├── Exception/   # Custom exceptions
│   ├── Settings/    # Settings classes
│   ├── Listener/    # Event listeners
│   └── Dashboard/   # Dashboard widgets
├── templates/        # PHP templates
├── css/             # Stylesheets
├── js/              # JavaScript files
├── l10n/            # Translation files
└── migrations/      # Database migrations
```

### Database Schema
- `oc_projects` - Project information
- `oc_customers` - Customer data
- `oc_time_entries` - Time tracking data
- `oc_project_members` - Team member relationships

### Contributing
1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Add tests if applicable
5. Submit a pull request

## License

This app is licensed under the AGPL-3.0-or-later license.

## Support

For support and questions:
- Check the documentation
- Report issues on GitHub
- Contact the development team

## Changelog

### Version 1.2.1
- Export: CSV now includes all active filters (project, user, project type, date range, search)
- Edit Time Entry: saving an edit updates the original entry (method override + POST route)
- Translations: additional DE/EN keys for headers, placeholders, and units (hours, /hour)

### Version 1.2.0
- Dashboard: Show project type text next to icon in Project Type Analysis
- Dashboard: Keep long time entry descriptions truncated on hover
- Translations: Audit across dashboard, projects, customers, header/footer, layout, errors, and forms; added missing l10n keys (EN/DE)
- Minor UI text consistency and accessibility improvements

### Version 1.0.0
- Initial release
- Complete project management functionality
- Customer management system
- Time tracking features
- Budget management
- Dashboard and reporting
- Nextcloud integration
