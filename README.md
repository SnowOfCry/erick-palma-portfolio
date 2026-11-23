# Erick Palma — Portfolio

Bilingual (English / Spanish) portfolio website for **Erick Palma — Web Developer & Business Solutions**, with a password-protected admin panel to manage projects and photos.

No database and no framework: plain PHP 8 + JSON files, so it runs on any shared hosting (e.g. Hostinger).

## Features

**Public site (`index.php`)**
- Hero, services, projects, process, about and contact sections
- EN / ES language toggle (auto-detects Spanish browsers, remembers the choice)
- Project cards with screenshot, description, problem/result, tools and links (live site, demo, video)
- "Featured" projects show as full-width cards
- Contact form via [Web3Forms](https://web3forms.com) (free)
- Responsive, mobile-first

**Admin panel (`admin.php`)**
- Add, edit, delete and reorder projects
- Drag-and-drop photo upload with preview
- English + Spanish fields for every text
- Settings: profile photo, email, phone, WhatsApp, LinkedIn, Web3Forms key, password

**Security**
- Password hashed with `password_hash()`; created on first visit to `admin.php`
- CSRF tokens on every form, `HttpOnly` + `SameSite=Strict` session cookie
- Login lockout (10 min after 5 failed attempts)
- Uploads validated as real images, saved with random names; scripts blocked in `uploads/`
- `data/` folder blocked from the web via `.htaccess`

## Structure

```
index.php          Public portfolio page
admin.php          Admin panel
lib.php            Shared helpers (JSON storage, uploads, escaping)
data/projects.json Projects (seed data)
data/.htaccess     Blocks web access to data files
uploads/.htaccess  Blocks scripts in the uploads folder
```

Created on the server at runtime (git-ignored): `data/auth.json`, `data/settings.json`, `data/login.json`, `uploads/*`.

## Deploy (Hostinger)

1. Upload the files to your site folder (e.g. `public_html` or a subdomain folder).
2. Make sure `data/` and `uploads/` are writable by PHP (755 is usually enough).
3. **Immediately** open `https://your-site/admin.php` and create your admin password.
4. Add photos, links and contact info from the admin panel.

## Run locally

```bash
php -S localhost:8000
# open http://localhost:8000 and http://localhost:8000/admin.php
```
