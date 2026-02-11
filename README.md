# MainWP AI1WM Backup Manager

[![License: GPL v2](https://img.shields.io/badge/License-GPL%20v2-blue.svg)](https://www.gnu.org/licenses/gpl-2.0)
[![Version](https://img.shields.io/badge/version-1.1.3-green.svg)](https://github.com/kinou-p/mainwp-ai1wm-manager/releases)
[![MainWP](https://img.shields.io/badge/MainWP-Compatible-orange.svg)](https://mainwp.com/)

**Manage All-in-One WP Migration backups on child sites directly from your MainWP Dashboard.**

A powerful MainWP extension that allows you to create, list, download, and delete AI1WM backups across all your child sites from a centralized dashboard. Perfect for agencies and site managers handling multiple WordPress installations.

![Dashboard Preview](https://via.placeholder.com/800x400?text=MainWP+AI1WM+Manager+Dashboard)

---

## ✨ Features

- 🎯 **Centralized Backup Management** - Control all AI1WM backups from one dashboard
- 🚀 **Bulk Operations** - Create backups on multiple sites simultaneously
- 📥 **One-Click Downloads** - Download backups directly from the dashboard
- 🔒 **Secure Transfers** - Temporary token-based download system
- 📊 **Real-Time Statistics** - Track backup counts and latest backup dates
- 🔄 **Auto-Updates** - Automatic updates via GitHub releases
- 📝 **Activity Logs** - Track all backup operations with detailed logging
- 🎨 **Modern UI** - Clean, responsive interface with dark theme
- ⚡ **Performance Optimized** - Smart retry logic and concurrent operations
- 🛡️ **Security First** - .htaccess protection and path traversal prevention

---

## 📋 Requirements

### Dashboard Requirements (MainWP Server)
- WordPress 5.8 or higher
- MainWP Dashboard plugin 4.0 or higher
- PHP 7.4 or higher
- MySQL 5.6 or higher

### Child Site Requirements
- WordPress 5.8 or higher
- MainWP Child plugin 4.0 or higher
- All-in-One WP Migration plugin (free or premium)
- PHP 7.4 or higher

---

## 🚀 Installation

### Step 1: Install on MainWP Dashboard

1. **Download the Dashboard Plugin**
   - Go to [Releases](https://github.com/kinou-p/mainwp-ai1wm-manager/releases)
   - Download `mainwp-ai1wm-manager.zip`

2. **Install via WordPress Admin**
   - Go to **Plugins → Add New → Upload Plugin**
   - Choose the downloaded `mainwp-ai1wm-manager.zip`
   - Click **Install Now**
   - Click **Activate Plugin**

3. **Access the Dashboard**
   - Navigate to **MainWP → AI1WM Backups**
   - You'll see all your connected child sites

### Step 2: Install on Child Sites

You have **two options** for installing the child plugin:

#### Option A: Via MainWP Dashboard (Recommended)

1. Download `mainwp-ai1wm-manager-child.zip` from [Releases](https://github.com/kinou-p/mainwp-ai1wm-manager/releases)
2. In MainWP Dashboard, go to **Sites → Install Plugins**
3. Select all child sites (or specific ones)
4. Upload `mainwp-ai1wm-manager-child.zip`
5. Click **Install & Activate**

#### Option B: Manual Installation on Each Site

1. Download `mainwp-ai1wm-manager-child.zip`
2. On each child site, go to **Plugins → Add New → Upload Plugin**
3. Upload and activate the plugin

### Step 3: Verify Installation

1. In MainWP Dashboard, go to **MainWP → AI1WM Backups**
2. Click on a site's dropdown arrow
3. If the child plugin is installed correctly, you'll see the backup list
4. If you see "Plugin enfant non détecté", the child plugin is not installed on that site

---

## 🎮 Usage

### Creating Backups

**Single Site:**
- Click the **"Créer Backup"** button next to a site
- Wait for confirmation (backups are created asynchronously)
- Refresh the backup list after 30-60 seconds

**Multiple Sites (Bulk):**
- Check the boxes next to sites you want to backup
- Click **"Backup sélection"** in the toolbar
- Monitor progress in the progress bar
- Operations are processed 3 sites at a time to avoid overload

### Viewing Backups

- Click the dropdown arrow (▼) next to a site name
- Backups are sorted by date (newest first)
- View backup name, date, and file size

### Downloading Backups

**Single Download:**
- Expand a site's backup list
- Click the **download icon (↓)** next to the backup
- File downloads automatically via secure temporary link

**Bulk Download (Latest):**
- Select multiple sites
- Click **"Télécharger dernière"** in toolbar
- Downloads the most recent backup from each site

### Deleting Backups

- Expand a site's backup list
- Click the **delete icon (🗑)** next to the backup
- Confirm the deletion
- Backup is permanently removed from the child site

### Activity Logs

- Scroll to the bottom of the dashboard
- View all recent operations (backups, downloads, deletions)
- Logs show timestamp, site name, and operation result
- Click **"Rafraîchir"** to update logs
- Click **"Effacer l'historique"** to clear all logs

---

## 🔧 Development

### Building from Source

```powershell
# Clone the repository
git clone https://github.com/kinou-p/mainwp-ai1wm-manager.git
cd mainwp-ai1wm-manager

# Build ZIP files
.\build.ps1

# Output files:
# - mainwp-ai1wm-manager.zip (Dashboard plugin)
# - mainwp-ai1wm-manager-child.zip (Child plugin)
```

### Project Structure

```
mainwp-ai1wm-manager/
├── mainwp-ai1wm-manager/           # Dashboard Plugin
│   ├── mainwp-ai1wm-manager.php    # Main plugin file
│   ├── class-github-updater.php    # Auto-update handler
│   ├── assets/
│   │   ├── css/
│   │   │   └── dashboard.css       # Styles
│   │   └── js/
│   │       └── dashboard.js        # JavaScript logic
│   ├── includes/
│   │   ├── class-ajax-handlers.php # AJAX endpoints
│   │   └── class-logger.php        # Logging system
│   └── templates/
│       └── dashboard.php           # Main dashboard template
│
├── mainwp-ai1wm-manager-child/     # Child Site Plugin
│   ├── mainwp-ai1wm-manager-child.php  # Main plugin file
│   └── class-github-updater.php    # Auto-update handler
│
├── build.ps1                       # Build script
├── README.md                       # This file
├── GITHUB-RELEASES-GUIDE.md        # Release creation guide
└── LICENSE                         # GPL v2 License
```

### Creating a Release

See [GITHUB-RELEASES-GUIDE.md](GITHUB-RELEASES-GUIDE.md) for detailed instructions on creating GitHub releases with automatic WordPress updates.

---

## 🛡️ Security Features

- **Secure Downloads**: Temporary one-time tokens with 5-minute expiration
- **.htaccess Protection**: Automatic protection of backup directories
- **Path Traversal Prevention**: Strict file path validation
- **CSRF Protection**: Nonce verification on all AJAX requests
- **Permission Checks**: Requires `manage_options` capability
- **Sanitization**: All user inputs are sanitized and validated

---

## 🐛 Troubleshooting

### "Plugin enfant non détecté"
- **Cause**: Child plugin not installed on the site
- **Solution**: Install `mainwp-ai1wm-manager-child.zip` on that child site

### Backup Creation Fails
- **Cause**: AI1WM plugin not installed or inactive
- **Solution**: Install and activate All-in-One WP Migration on the child site

### Timeouts During Backup
- **Cause**: Backup takes longer than 2 minutes
- **Solution**: Wait and refresh - the backup continues in the background

### Download Link Expired
- **Cause**: Tried to use download link after 5 minutes
- **Solution**: Click download button again to generate a new link

### Updates Not Showing
- **Cause**: GitHub API cache or incorrect release format
- **Solution**: See [GITHUB-RELEASES-GUIDE.md](GITHUB-RELEASES-GUIDE.md) troubleshooting section

### Enable Debug Logging

Add to `wp-config.php`:
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

Check logs in `wp-content/debug.log`

---

## 📝 Changelog

### Version 1.1.3 (2026-02-11)

**Security:**
- Added secure download system with temporary tokens
- Automatic .htaccess protection for backup directories
- Enhanced path traversal protection

**Bug Fixes:**
- Fixed NOMAINWP error on most sites
- Fixed GitHub updater not checking for updates
- Improved backup creation async feedback

**Improvements:**
- Automatic retry with exponential backoff
- Proper timeouts (30s list, 120s backup)
- Bulk operations with concurrency limit
- Smart caching for GitHub API calls

[See full changelog](CHANGELOG.md)

---

## 🤝 Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/AmazingFeature`)
3. Commit your changes (`git commit -m 'Add some AmazingFeature'`)
4. Push to the branch (`git push origin feature/AmazingFeature`)
5. Open a Pull Request

### Development Guidelines

- Follow WordPress Coding Standards
- Test on multiple PHP versions (7.4, 8.0, 8.1, 8.2)
- Ensure compatibility with MainWP Dashboard 4.0+
- Add inline documentation for new functions
- Update README.md if adding new features

---

## 📄 License

This project is licensed under the **GNU General Public License v2.0** - see the [LICENSE](LICENSE) file for details.

```
MainWP AI1WM Backup Manager
Copyright (C) 2024-2026 Alexandre Pommier

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License along
with this program; if not, write to the Free Software Foundation, Inc.,
51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
```

---

## 👨‍💻 Author

**Alexandre Pommier**
- Website: [alexandre-pommier.com](https://alexandre-pommier.com)
- GitHub: [@kinou-p](https://github.com/kinou-p)

---

## 🙏 Acknowledgments

- [MainWP](https://mainwp.com/) - For the amazing WordPress management dashboard
- [All-in-One WP Migration](https://servmask.com/) - For the excellent backup solution
- WordPress Community - For continuous support and inspiration

---

## 💬 Support

- **Issues**: [GitHub Issues](https://github.com/kinou-p/mainwp-ai1wm-manager/issues)
- **Documentation**: [GitHub Wiki](https://github.com/kinou-p/mainwp-ai1wm-manager/wiki)
- **Releases**: [GitHub Releases](https://github.com/kinou-p/mainwp-ai1wm-manager/releases)

---

## ⭐ Show Your Support

If this plugin helped you manage your backups more efficiently, please consider:
- ⭐ Starring this repository
- 🐛 Reporting bugs or requesting features
- 🔀 Contributing code improvements
- 📢 Sharing with other MainWP users

---

**Made with ❤️ for the MainWP community**
