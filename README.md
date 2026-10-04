# SPLABAB EDU

SPLABAB EDU is the staff training and notice app for SPLABAB stores. This repository contains only the EDU app code needed for development and deployment.

- Current version: SPLABAB EDU v1.1.4
- Production URL: https://splabab.co.kr/edu/
- Deployment target: `/srv/splababcokr/edu`

## File Structure

```text
.
├── admin/              # Admin dashboard page
├── assets/             # EDU logo and PWA icon assets
├── admin.js            # Admin dashboard client logic
├── api.php             # JSON API routes
├── app.js              # Employee-facing app logic
├── index.php           # Employee-facing app entry point
├── lib.php             # SQLite schema, auth, stores, uploads, helpers
├── login.php           # Admin login page
├── manifest.json       # PWA manifest
├── styles.css          # App styles
└── sw.js               # Service worker
```

## Local Notes

Run this app from a PHP-capable web server with the project mounted at `/edu`, because the frontend uses `/edu/...` absolute paths.

Admin accounts are configured outside Git with the `EDU_ADMIN_ACCOUNTS` environment variable. It should be a JSON array:

```json
[
  { "password": "0000", "name": "Admin Name", "role": "owner" }
]
```

Use real passwords only in the server environment. Do not commit them.

The app creates runtime data automatically under `data/`:

- `data/edu.sqlite`
- `data/uploads/`

Those runtime files are intentionally not committed. Production database files, uploaded media, backups, logs, temporary files, and secrets should stay on the server.

## Deployment Notes

Deploy the repository contents to:

```text
/srv/splababcokr/edu
```

Make sure PHP can write to `data/` on the server. If `data/` does not exist, the app will try to create it on first use.
