<p align="center">
  <img src="public/static/logo.svg" alt="Sendrify Logo" width="60">
</p>

<h1 align="center">Sendrify</h1>

<p align="center">
  <strong>Secure PDF Sharing with Analytics</strong><br>
  Share PDFs with password protection, engagement tracking, and real-time analytics.
</p>

<p align="center">
  <a href="#features">Features</a> •
  <a href="#quick-start">Quick Start</a> •
  <a href="#self-hosting">Self-Hosting</a> •
  <a href="#demo">Demo</a> •
  <a href="#license">License</a>
</p>

---

## Features

- **Secure PDF Sharing** – Password-protect documents for recipients and senders
- **Real-Time Analytics** – Track views, time spent per page, scroll depth, and engagement
- **Self-Hostable** – Deploy on your own server with a single MySQL database
- **Multi-Language** – Built-in support for English and German (DE/EN)
- **Demo Mode** – Built-in demo mode with sample data for easy evaluation
- **Lightweight** – No frameworks, no build step – pure PHP with Pico CSS

## Requirements

- PHP 8.0+
- MySQL 5.7+ / MariaDB 10.3+
- Apache with `mod_rewrite` (or equivalent URL rewriting)

## Quick Start

1. **Clone the repository**
   ```bash
   git clone https://github.com/your-username/sendrify.git
   cd sendrify
   ```

2. **Create the database**
   ```bash
   mysql -u root -p -e "CREATE DATABASE sendrify CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
   mysql -u root -p sendrify < schema.sql
   ```

3. **Configure the environment**
   ```bash
   cp .env.example .env
   # Edit .env with your database credentials
   ```

4. **Set up the web server**

   Point your web server's document root to the `public/` directory.

5. **Open in browser** and register your first account.

## Self-Hosting

### Apache

```apache
<VirtualHost *:80>
    ServerName sendrify.yourdomain.com
    DocumentRoot /var/www/sendrify/public

    <Directory /var/www/sendrify/public>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

### Docker

The fastest way to get Sendrify running:

```bash
git clone https://github.com/your-username/sendrify.git
cd sendrify
docker compose up -d
```

Sendrify is now available at **http://localhost:8080**. Register your first account and start sharing PDFs.

#### Environment Variables

| Variable | Default | Description |
|---|---|---|
| `DB_HOST` | `db` | MySQL/MariaDB hostname |
| `DB_NAME` | `sendrify` | Database name |
| `DB_USER` | `sendrify` | Database user |
| `DB_PASS` | `sendrify_secret` | Database password |
| `DB_ROOT_PASS` | `root_secret` | MariaDB root password |
| `DEMO_MODE` | `false` | Enable demo mode with sample data |
| `MAIL_FROM_ADDRESS` | auto-detected | Sender address for emails |

#### Demo Mode

To start with demo data and pre-configured accounts:

```bash
DEMO_MODE=true docker compose up -d
```

Login with `demo@sendrify.local` / `demo123`.

#### Production Tips

For production, override the default passwords:

```bash
DB_PASS=your_secure_password DB_ROOT_PASS=your_root_password docker compose up -d
```

Or create a `.env` file next to `docker-compose.yml`:

```env
DB_PASS=your_secure_password
DB_ROOT_PASS=your_root_password
```

## Demo Mode

To enable demo mode, set `DEMO_MODE=true` in your environment or config. Then run:

```bash
php setup_demo.php
```

This creates demo accounts and sample PDF files for testing.

## Project Structure

```
sendrify/
├── config/          # Configuration (database, i18n, utilities)
├── lang/            # Language files (en.php, de.php)
├── public/          # Web root (all public-facing files)
│   ├── account/     # Auth pages (login, register, forgot password)
│   ├── partials/    # Shared header/footer
│   └── static/      # CSS, logo, PDF.js viewer
├── storage/         # Runtime storage (uploads, logs)
├── schema.sql       # Database schema
├── setup_demo.php   # Demo data setup script
└── cleanup_cron.php # Cron job for cleaning expired files
```

## Third-Party Libraries

- [PDF.js](https://mozilla.github.io/pdf.js/) v4.6.82 – PDF rendering (Apache 2.0)
- [Pico CSS](https://picocss.com/) – Minimal CSS framework (MIT)

## Contributing

Contributions are welcome! Please open an issue first to discuss what you'd like to change.

## License

This project is licensed under the [Mozilla Public License 2.0](LICENSE).

```
This Source Code Form is subject to the terms of the Mozilla Public
License, v. 2.0. If a copy of the MPL was not distributed with this
file, You can obtain one at http://mozilla.org/MPL/2.0/.
```
