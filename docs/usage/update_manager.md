---
title: Update Manager
layout: default
parent: Usage
---

# Update Manager (Experimental)

{: .warning }
> The Update Manager is currently an **experimental feature**. It is disabled by default while user experience data is being gathered. Use with caution and always ensure you have proper backups before updating.

Part-DB includes an Update Manager that can automatically update Git-based installations to newer versions. The Update Manager provides both a web interface and CLI commands for managing updates, backups, and maintenance mode.

## Supported Installation Types

The Update Manager currently supports automatic updates only for **Git clone** installations. Other installation types show manual update instructions:

| Installation Type | Auto-Update | Instructions |
|-------------------|-------------|--------------|
| Git Clone | Yes | Automatic via CLI or Web UI |
| Docker | No | Pull new image: `docker-compose pull && docker-compose up -d` |
| ZIP Release | No | Download and extract new release manually |

## Enabling the Update Manager

By default, web-based updates and backup restore are **disabled** for security reasons. To enable them, add these settings to your `.env.local` file:

```bash
# Enable web-based updates (default: disabled)
DISABLE_WEB_UPDATES=0

# Enable backup restore via web interface (default: disabled)
DISABLE_BACKUP_RESTORE=0
```

{: .note }
> Even with web updates disabled, you can still use the CLI commands to perform updates.

## CLI Commands

### Update Command

Check for updates or perform an update:

```bash
# Check for available updates
php bin/console partdb:update --check

# Update to the latest version
php bin/console partdb:update

# Update to a specific version
php bin/console partdb:update v2.6.0

# Update without creating a backup first
php bin/console partdb:update --no-backup

# Force update without confirmation prompt
php bin/console partdb:update --force
```

### Maintenance Mode Command

Manually enable or disable maintenance mode:

```bash
# Enable maintenance mode with default message
php bin/console partdb:maintenance-mode --enable

# Enable with custom message
php bin/console partdb:maintenance-mode --enable "System maintenance until 6 PM"
php bin/console partdb:maintenance-mode --enable --message="Updating to v2.6.0"

# Disable maintenance mode
php bin/console partdb:maintenance-mode --disable

# Check current status
php bin/console partdb:maintenance-mode --status
```

## Web Interface

When web updates are enabled, the Update Manager is accessible at **System > Update Manager** (URL: `/system/update-manager`).

The web interface shows:
- Current version and installation type
- Available updates with release notes
- Precondition validation (Git, Composer, Yarn, permissions)
- Update history and logs
- Backup management

### Required Permissions

Users need the following permissions to access the Update Manager:

| Permission | Description |
|------------|-------------|
| `@system.show_updates` | View update status and available versions |
| `@system.manage_updates` | Perform updates and restore backups |

## Update Process

When an update is performed, the following steps are executed:

1. **Lock** - Acquire exclusive lock to prevent concurrent updates
2. **Maintenance Mode** - Enable maintenance mode to block user access
3. **Rollback Tag** - Create a Git tag for potential rollback
4. **Backup** - Create a full backup (optional but recommended)
5. **Git Fetch** - Fetch latest changes from origin
6. **Git Checkout** - Checkout the target version
7. **Composer Install** - Install/update PHP dependencies
8. **Yarn Install** - Install frontend dependencies
9. **Yarn Build** - Compile frontend assets
10. **Database Migrations** - Run any new migrations
11. **Cache Clear** - Clear the application cache
12. **Cache Warmup** - Rebuild the cache
13. **Maintenance Off** - Disable maintenance mode
14. **Unlock** - Release the update lock

If any step fails, the system automatically attempts to rollback to the previous version.

## Backup Management

The Update Manager automatically creates backups before updates. These backups are stored in `var/backups/` and include:

- Database dump (SQL file or SQLite database)
- Configuration files (`.env.local`, `parameters.yaml`, `banner.md`)
- Attachment files (`uploads/`, `public/media/`)

### Restoring from Backup

{: .warning }
> Backup restore is a destructive operation that will overwrite your current database. Only use this if you need to recover from a failed update.

If web restore is enabled (`DISABLE_BACKUP_RESTORE=0`), you can restore backups from the web interface. The restore process:

1. Enables maintenance mode
2. Extracts the backup
3. Restores the database
4. Optionally restores config and attachments
5. Clears and warms up the cache
6. Disables maintenance mode

## Troubleshooting

### Precondition Errors

Before updating, the system validates:

- **Git available**: Git must be installed and in PATH
- **No local changes**: Uncommitted changes must be committed or stashed
- **Composer available**: Composer must be installed and in PATH
- **Yarn available**: Yarn must be installed and in PATH
- **Write permissions**: `var/`, `vendor/`, and `public/` must be writable
- **Not already locked**: No other update can be in progress

### Stale Lock

If an update was interrupted and the lock file remains, it will automatically be removed after 1 hour. You can also manually delete `var/update.lock`.

### Viewing Update Logs

Update logs are stored in `var/log/updates/` and can be viewed from the web interface or directly on the server.

## Security Considerations

- **Disable web updates in production** unless you specifically need them
- The Update Manager requires shell access to run Git, Composer, and Yarn
- Backup files may contain sensitive data (database, config) - secure the `var/backups/` directory
- Consider running updates during maintenance windows with low user activity
