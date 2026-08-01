# Auto Caller

[![License: GPL v3](https://img.shields.io/badge/License-GPLv3-blue.svg)](https://www.gnu.org/licenses/gpl-3.0)

[ ورژن فارسی | Persian ](https://github.com/milad-mma/VOIP-Auto-Caller/blob/main/readme2.md)

Automated voice-call (auto-dialer) system for Asterisk-based PBX platforms (Issabel / Elastix). Upload a list of numbers and an audio file, and the system calls each number automatically and plays the message — with IVR-style digit capture (press 1/2 to transfer to a live extension), per-call reporting, and a REST-style API for scheduling calls programmatically.

**Requirement:** a SIP trunk (or internal extensions) capable of outbound dialing. Analog-to-digital gateway trunks are not supported reliably.

## Install

Run on your Issabel/Elastix server via SSH:

```bash
wget https://raw.githubusercontent.com/milad-mma/VOIP-Auto-Caller/main/install.sh && chmod +x install.sh && sudo ./install.sh
```

You'll be prompted for your MySQL root password. The installer creates the `callblaster` database/user, deploys the app to `/var/www/html/autocaller`, registers the Asterisk dialplan context, and configures Apache.

The same script also offers a clean **uninstall** option (option 2 in the menu) that removes the database, files, dialplan entries, and restores Apache config.

## Usage

Open (HTTP, not HTTPS): `http://[server-ip]/autocaller`

![Panel screenshot](https://raw.githubusercontent.com/milad-mma/VOIP-Auto-Caller/main/ISSABEL-Auto-Dialler.jpg)

| Field | Description |
|---|---|
| Wait Time | Seconds to wait for the callee to answer |
| Interval | Seconds between calls (tune to your trunk's concurrent-call capacity) |
| Caller ID | Local caller ID label |
| Prefix | Prefix applied to outbound numbers |
| Press 1–9 | Transfers the call to a chosen extension if the callee presses that digit (9 independently configurable routes) |
| Upload Numbers | Import a number list (with per-row voice file) from Excel. ⚠️ Format the phone column as **Number, no decimals** (not scientific/general) and include the leading `0` (e.g. `09123456789`) — the configured outbound Prefix is added automatically. Also, the `audio` column value must match an uploaded voice file's name **exactly** (no typos, no extension). |
| History | Per-call outcome log, exportable to Excel |
| Manage Voice | Upload WAV/MP3 (8kHz, 16-bit, mono recommended) |
| API | Trigger/schedule calls externally: `http://[server-ip]/autocaller/api.php?action=democall&phone=[number]&file=[audio.wav]&action=call` |

## Project structure

```
autocaller/
├── assets/
│   ├── css/       theme + local stylesheets (no external CDN dependency)
│   ├── js/        first-party JS
│   ├── fonts/     locally hosted webfonts
│   ├── img/       icons, screenshots, brand assets
│   └── vendor/    third-party libraries (Bootstrap, AdminLTE, DataTables, etc.)
├── admin/         admin/user-management panel
├── controller/    core PHP application logic
├── cron/          scheduled-job management UI
└── install.sh     installer/uninstaller
```

## Features

- Self-contained PHP application, no external framework dependency
- Fully offline-capable front end (no CDN/Google Fonts calls at runtime)
- Scheduled and on-demand calling via API
- IVR-style call routing (press-digit transfer)
- Live call monitoring and historical success/failure reporting
- Multiple concurrent calls, each with an independently assigned voice file
- Active/inactive number tracking across campaigns

---
[imapro.ir](https://imapro.ir)
