# Hypercart Server Monitor - Security Overview

This document provides a brief overview of the key security measures implemented in the Hypercart Server Monitor plugin, with a focus on filesystem and data protection.

## Data Storage Directory

All persistent data, including benchmark metrics and state information, is stored in a dedicated directory to isolate it from other plugins and WordPress core files.

- **Location:** `wp-content/uploads/hypercart-server-monitor/`

This directory is protected by the following measures:

### 1. `.htaccess` File

On Apache servers, the plugin creates a `.htaccess` file inside the data directory with rules to deny all direct web access. This prevents anyone from accessing the JSON data files or other sensitive information by typing the URL into their browser.

The rules used are:
```apache
<IfModule mod_authz_core.c>
    Require all denied
</IfModule>
<IfModule !mod_authz_core.c>
    Deny from all
</IfModule>
```
This covers both Apache 2.4+ and older versions.

### 2. `index.html` File

To prevent directory listing on servers that might not be using Apache (like Nginx), or if `.htaccess` is not enabled, the plugin also creates an empty `index.html` file in the data directory. This ensures that visitors cannot see a list of files in the directory.

## Log File Security

The plugin uses the `Hypercart_Logger` library for logging, which stores logs in `wp-content/hypercart-logs/`. The log viewer in the admin dashboard has the following security measures:

- **Allowlist Validation:** Before reading any log file, the viewer strictly validates the requested filename against an allowlist of known, valid log files. Any attempt to access a file not on this list (e.g., via a manipulated URL) is rejected and logged as a security warning. This prevents directory traversal attacks.

## Why are these measures important?

While the data stored by this plugin is not highly sensitive (it contains server performance metrics), it is still good practice to protect it from public access. These measures provide defense-in-depth and follow WordPress security best practices.

If the "Log Handling and File Visibility" self-test in the Debug tab shows a "fail" status, it means one or both of the `.htaccess` and `index.html` files are missing. This could happen if the plugin did not have the correct permissions to create them during installation. You should check the file permissions for the `wp-content/uploads/hypercart-server-monitor/` directory to ensure it is writable by the web server.
