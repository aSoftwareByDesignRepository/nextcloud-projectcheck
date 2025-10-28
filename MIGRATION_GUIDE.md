# Migration Guide: projectcontrol → ProjectCheck

## Overview

This app has been renamed from **projectcontrol** to **ProjectCheck** (app ID: `projectcheck`). All your existing data will be automatically migrated when you install the new app.

## What Changed

### App Identity
- **App ID**: `projectcontrol` → `projectcheck`
- **App Name**: "Project Control" → "ProjectCheck"
- **Namespace**: `OCA\ProjectControl` → `OCA\ProjectCheck`
- **Version**: 1.2.9 → 2.0.0

### Database Tables
All tables have been renamed with the `projectcheck_` prefix:
- `oc_projects` → `oc_projectcheck_projects`
- `oc_customers` → `oc_projectcheck_customers`
- `oc_time_entries` → `oc_projectcheck_time_entries`
- `oc_project_members` → `oc_projectcheck_project_members`

### Data Migration
The migration is **fully automatic** and runs when you first enable the app. It:

1. ✅ Creates new tables with the `projectcheck_` prefix
2. ✅ Copies ALL data from old tables to new ones (preserving IDs and relationships)
3. ✅ Migrates app configuration settings
4. ✅ Migrates all user preferences
5. ✅ Is idempotent (safe to run multiple times)

**IMPORTANT**: The migration **copies** data, it does NOT delete the old tables. Your original data remains intact as a backup.

## Installation Steps

### Step 1: Disable Old App (if installed)
```bash
cd /path/to/nextcloud
php occ app:disable projectcontrol
```

### Step 2: Enable New App
```bash
php occ app:enable projectcheck
```

The migration will run automatically during enablement.

### Step 3: Verify Migration
Check the migration output for confirmation:
```bash
php occ migrations:status projectcheck
```

You should see:
- "Migrated X projects"
- "Migrated X customers"
- "Migrated X time entries"
- "Migrated X project members"
- "Migrated X app config values"
- "Migrated X user preferences"

## What Gets Migrated

### ✅ All Project Data
- Project names, descriptions, budgets
- Hourly rates and available hours
- Categories, priorities, statuses
- Start/end dates and tags
- Created by/at timestamps

### ✅ All Customer Data
- Customer names and contact information
- Email, phone, address
- All associations with projects

### ✅ All Time Entries
- Hours logged, hourly rates
- Descriptions and dates
- User assignments

### ✅ All Team Members
- Project-user associations
- Roles and custom hourly rates
- Assignment history

### ✅ All Settings
- App configuration (default rates, thresholds, etc.)
- User preferences (display options, notifications, etc.)

## Safety Features

1. **Non-Destructive**: Old tables remain untouched
2. **Idempotent**: Can be run multiple times safely
3. **Data Integrity**: All foreign key relationships preserved
4. **Rollback Ready**: You can disable ProjectCheck and re-enable projectcontrol if needed

## Rollback (if needed)

If you need to rollback:
```bash
php occ app:disable projectcheck
php occ app:enable projectcontrol
```

Your original data is still in the old tables and will work immediately.

## Post-Migration

### Old App Cleanup (Optional)
After verifying everything works correctly, you can optionally remove the old app:

```bash
# Only do this after confirming migration success!
rm -rf apps/projectcontrol
```

### Old Data Cleanup (Optional)
After several days/weeks of running ProjectCheck successfully, you can optionally drop the old tables:

```sql
-- CAUTION: Only run this after thorough testing!
DROP TABLE oc_projects;
DROP TABLE oc_customers;
DROP TABLE oc_time_entries;
DROP TABLE oc_project_members;
```

**⚠️ WARNING**: Only delete old tables after confirming everything works perfectly for an extended period.

## Troubleshooting

### Migration didn't run
- Check: `php occ migrations:status projectcheck`
- Force run: `php occ migrations:migrate projectcheck`

### Data missing
- Check old tables still exist: `SHOW TABLES LIKE '%project%';`
- Verify new tables: `SHOW TABLES LIKE 'oc_projectcheck_%';`
- Check migration logs in `data/nextcloud.log`

### Settings not migrated
Check the `oc_appconfig` and `oc_preferences` tables:
```sql
SELECT * FROM oc_appconfig WHERE appid = 'projectcheck';
SELECT * FROM oc_preferences WHERE appid = 'projectcheck';
```

## Support

If you encounter any issues:
1. Check `data/nextcloud.log` for error messages
2. Verify old data still exists in original tables
3. You can safely rollback to projectcontrol
4. Contact support with migration log output

## Technical Details

### Migration File
The migration is implemented in:
`lib/Migration/Version2000Date202501280001.php`

This file:
- Creates new schema with proper indexes
- Migrates all data while preserving IDs
- Migrates configuration atomically
- Includes comprehensive error handling

### Database Schema
All new tables use the same structure as the old ones, just with different names. This ensures 100% compatibility and zero data loss.

