# Isaac & Corine Wedding Website

Wedding website for Isaac & Corine — June 20, 2026.
Live at **https://isaacandcorine.com/**

## Tech Stack

| Layer | Technology |
|-------|-----------|
| Frontend | HTML, CSS, vanilla JS |
| Backend | PHP 8.x on Namecheap shared hosting |
| Database | MySQL (Namecheap cPanel) |
| Email | PHPMailer 7.0.2 over SMTP |
| Deploy | GitHub Actions → rsync over SSH |

## Project Structure

```
├── index.html              # Main single-page website
├── discover.html            # Standalone Discover Lebanon page (legacy)
├── style.css                # All styles (~1600 lines)
├── script.js                # All interactivity (~720 lines)
├── .htaccess                # HTTPS, security headers, caching
│
├── assets/
│   ├── intro-letter.mp4     # Envelope opening video
│   ├── hero-birds.mp4       # Hero background video
│   ├── song.m4a             # Background music
│   ├── ball-dance.webp      # Schedule & footer image
│   ├── confetti-CrGrT4ka.gif
│   ├── flowers/             # Decorative watercolor flowers
│   ├── butterflies/         # Decorative butterflies
│   └── destinations/        # Discover Lebanon images
│
├── api/
│   ├── config.example.php   # Configuration template (committed)
│   ├── config.php           # Actual config with secrets (GITIGNORED)
│   ├── Database.php         # MySQL PDO helper class
│   ├── guest-lookup.php     # Guest name lookup endpoint
│   ├── token.php            # CSRF token generator
│   ├── token.example.php    # Token generator template
│   ├── send-rsvp.php        # RSVP submission handler
│   ├── send-rsvp.example.php# RSVP handler template
│   ├── seed-test-guests.php # CLI script to insert test guests
│   ├── .htaccess            # API-level security rules
│   ├── rate_limits/         # Auto-created rate limit data (GITIGNORED)
│   └── PHPMailer-7.0.2/     # PHPMailer library (GITIGNORED)
│
└── .github/workflows/
    └── deploy.yml           # Production deploy (main → Namecheap)
```

## Sections

1. **Intro Overlay** — Full-screen envelope video, tap to open (starts music)
2. **Hero** — Looping birds video with "Isaac & Corine" and date
3. **Invitation** — Formal wedding invitation from both families
4. **Countdown** — Live countdown with canvas fireworks when it reaches zero
5. **Location** — Two venue cards with embedded Google Maps
6. **Schedule** — Timeline from 5:30 PM walk to 1:00 AM after party
7. **Travel & Stay** — Hotels, restaurants (accordion categories), tips
8. **Discover Lebanon** — Mosaic of 11 destinations (in-page toggle)
9. **Gifts** — Wedding registry with reveal button
10. **RSVP** — Guest lookup by name → shows invitation card with +1 → confirm

## MySQL Database

### Multi-Tenant Design

The database uses a **tenant model** where each wedding website has its own `tenant_id`. All rows include this field, so multiple wedding sites can share the same database.

### Table: `guests`

| Column | Type | Description |
|--------|------|-------------|
| `id` | INT UNSIGNED AUTO_INCREMENT | Primary key |
| `tenant_id` | VARCHAR(100) | Website identifier (e.g., `"isaac-and-corine"`) |
| `name` | VARCHAR(255) | Guest's full name (display version) |
| `name_lower` | VARCHAR(255) | Lowercase version of name (used for lookup) |
| `plus_one` | TINYINT(1) | Whether the guest has a +1 (0 or 1) |
| `plus_one_name` | VARCHAR(255) | Name of the +1 guest (nullable) |
| `rsvp_status` | ENUM | `"pending"`, `"attending"`, or `"declined"` |
| `rsvp_date` | DATETIME | When RSVP was submitted (nullable) |
| `created_at` | DATETIME | Auto-set on insert |
| `updated_at` | DATETIME | Auto-updated on change |

### Schema

The full schema is in `schema.sql`. Run it in phpMyAdmin or via CLI:

```bash
mysql -u USER -p DB_NAME < schema.sql
```

### Adding Guests

Via phpMyAdmin or MySQL CLI:

```sql
INSERT INTO guests (tenant_id, name, name_lower, plus_one, plus_one_name)
VALUES
  ('isaac-and-corine', 'John Smith', 'john smith', 1, 'Jane Smith'),
  ('isaac-and-corine', 'Marie Dupont', 'marie dupont', 0, NULL);
```

### For Future Wedding Websites

To add a new wedding site, simply use a different `tenant_id`:

```sql
INSERT INTO guests (tenant_id, name, name_lower, plus_one)
VALUES ('another-wedding', 'Guest Name', 'guest name', 0);
```

All the API code filters by `tenant_id` automatically — no cross-site data leaks.

## Setup Guide

### 1. MySQL Database

1. In Namecheap cPanel, go to **Databases → Manage My Databases**
2. Create a new database (Namecheap will prefix your cPanel username, e.g., `cpuser_wedding`)
3. Create a MySQL user with a strong password
4. Assign the user to the database with **ALL PRIVILEGES**
5. Open **phpMyAdmin**, select the database, and import `schema.sql`
6. Insert your guest list (see "Adding Guests" above)

### 2. GitHub Secrets (for deployment)

Set these in **Settings → Secrets and variables → Actions**:

| Secret | Description |
|--------|-------------|
| `SSH_PRIVATE_KEY` | SSH private key for Namecheap |
| `SSH_HOST` | Server hostname |
| `SSH_USER` | SSH username |
| `DEPLOY_PATH` | Remote path (e.g., `/home/user/public_html/`) |
| `CONFIG_PHP` | Entire contents of `api/config.php` (credentials) |

The deployment workflow automatically:
- Deploys `token.php`, `send-rsvp.php`, and `guest-lookup.php` via rsync
- Downloads and installs PHPMailer 7.0.2 (only the 3 required source files)
- Creates `api/config.php` on the server from the `CONFIG_PHP` secret via scp
- Preserves `api/rate_limits/` across deploys (excluded from rsync `--delete`)

### 3. Adding Guests

You can seed test guests via CLI (requires `config.php` with valid MySQL credentials):

```bash
php api/seed-test-guests.php
```

Or add guests through phpMyAdmin (see "Adding Guests" above).

### 4. Deploy

Push to `main` to deploy to production. Guest data lives in MySQL on the server and is never affected by deployments.

## Security

- **CSRF tokens**: Signed HMAC tokens with 10-minute TTL
- **Rate limiting**: File-based, per-IP (10 req/min for lookups, 5 req/min for RSVP)
- **Input sanitization**: All user inputs stripped/validated before use
- **HTTPS enforced** via `.htaccess`
- **Security headers**: `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`, `HSTS`
- **API protection**: `.htaccess` blocks direct access to config, libraries, and data files
- **No secrets in code**: All credentials live in `config.php` (gitignored)
- **PHP errors**: Logged to server, never displayed to users

## Background Texture

The site uses an SVG `feTurbulence` fractal noise filter to create a subtle **painted plaster/concrete wall texture** across the entire page. This gives the background an organic, slightly rough surface feel — like real paint on a concrete or stucco wall — rather than a flat digital color.

- Applied globally via `body::before` (covers all sections)
- Separately applied to `#main-nav::after` and `.section-footer::after` to ensure coverage on elements with their own stacking contexts
- Uses `pointer-events: none` so it never blocks interaction
- Tunable via `opacity` (currently `0.35`) and `baseFrequency` for grain coarseness
- Text, buttons, and interactive elements render cleanly above the texture via `isolation: isolate` on body and `z-index: -1` on the texture layer

## Color Palette

| Color | Hex | Usage |
|-------|-----|-------|
| Cream | `#efdfd5` | Background |
| Dark Sage | `#587042` | Primary text, headings, buttons |
| Sage | `#a9b494` | Accents, borders, muted text |

## Fonts

- **Cormorant Garamond** — Display headings
- **Great Vibes** — Script/cursive (couple names)
- **Lora** — Body text
