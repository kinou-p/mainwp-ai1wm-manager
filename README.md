# MainWP AI1WM Manager

This repository contains a MainWP Dashboard extension and a Child Site plugin for managing All-in-One WP Migration backups.

[https://github.com/kinou-p/mainwp-ai1wm-manager](https://github.com/kinou-p/mainwp-ai1wm-manager)


## Project Structure

- **`mainwp-ai1wm-manager/`**: The MainWP Dashboard extension plugin.
- **`mainwp-ai1wm-manager-child/`**: The Child Site plugin to be installed on child sites.
- **`build.ps1`**: PowerShell script to build the plugins into ZIP files.

## specific functionality

- **Dashboard**: View and manage AI1WM backups across all child sites.
- **Child Plugin**: Exposes AI1WM backups to the MainWP Dashboard.

## How to Build

Run the included PowerShell script to create the installable ZIP files:

```powershell
.\build.ps1
```

This will generate:
- `mainwp-ai1wm-manager.zip`
- `mainwp-ai1wm-manager-child.zip`
