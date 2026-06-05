# Bubbles

A community-driven social platform where users create and join **bubbles** (communities), share posts, react with likes and comments, chat privately, and manage friendships — built with Laravel 12, Vue 3, and Inertia.js.

---

## Table of Contents

1. [Features](#features)
2. [Tech Stack](#tech-stack)
3. [Prerequisites](#prerequisites)
4. [Installation](#installation)
5. [Environment Configuration](#environment-configuration)
6. [Email Setup (Mailtrap)](#email-setup-mailtrap)
7. [Cloudinary Setup](#cloudinary-setup)
8. [Google OAuth Setup](#google-oauth-setup)
9. [Running the Application](#running-the-application)
10. [First Use](#first-use)
11. [Admin Panel](#admin-panel)
12. [Development Workflow](#development-workflow)
13. [Testing](#testing)
14. [Troubleshooting](#troubleshooting)
15. [Production Build](#production-build)
16. [Roadmap](#roadmap)

---

## Features

| Feature | Description |
|---|---|
| **Feed** | Chronological post feed from joined communities and friends |
| **Bubbles (Communities)** | Create and join topic-based communities with custom images and banners |
| **Posts** | Text + image/video posts on profiles or inside communities; edit and delete your own |
| **Reactions** | Like or react to posts and comments; view who reacted |
| **Comments & Replies** | Threaded comments with nested replies on posts |
| **Notifications** | In-app notification badge with real-time polling |
| **Private Messaging** | One-to-one and group conversations; send, edit, and delete messages; custom chat backgrounds |
| **Group Chats** | Create group conversations, manage members and roles, transfer ownership |
| **Friends** | Send, accept, and reject friend requests |
| **Block Users** | Block any user to prevent contact |
| **Search** | Search users and communities in real time |
| **Profiles** | Public user profiles with avatar, banner, bio, and post history |
| **Reports** | Report posts, community posts, and users for admin review |
| **Community Moderation** | Community owners can ban/mute members and manage moderator roles |
| **Punishments** | Admins can issue warnings, mutes, or bans with acknowledgement flow |
| **Announcements** | Admins can post platform-wide announcements |
| **Admin Panel** | Manage users, posts, communities, reports, and audit logs |
| **Audit Logs** | Full log of sensitive actions (logins, profile changes, moderation events) |
| **Google OAuth** | Register and log in with a Google account (no password required) |
| **Theme** | Light and dark mode; preference saved per account |
| **Email Verification** | Enforced email verification for standard accounts; Google accounts are pre-verified |
| **Security Headers** | CSP, HSTS, X-Frame-Options, and other security headers on all responses |

---

## Tech Stack

| Layer | Technology | Version |
|---|---|---|
| Backend | Laravel | 12 |
| Language | PHP | 8.2+ |
| Frontend | Vue 3 (Composition API) | 3.4+ |
| Bridge | Inertia.js | v2 |
| Styling | Tailwind CSS | v3 |
| Build Tool | Vite | 7 |
| Database | MySQL / MariaDB | 8+ / 10.6+ |
| Auth | Laravel Breeze (session) + Socialite | — |
| Media CDN | Cloudinary | v3 |
| Email (dev) | Mailtrap | — |

---

## Prerequisites

Before you start, make sure you have the following installed on your machine.

### PHP 8.2+

**Windows (XAMPP):** Download and install [XAMPP](https://www.apachefriends.org/). It includes PHP, MySQL, and Apache.

Verify your PHP version:

```bash
php -v
```

Required PHP extensions (all included in XAMPP by default): `pdo_mysql`, `mbstring`, `xml`, `curl`, `gd`.

### Composer 2

Composer is PHP's package manager.

Download from [getcomposer.org](https://getcomposer.org/download/) and follow the installer.

Verify:

```bash
composer -V
```

### Node.js 18+ and npm

Download from [nodejs.org](https://nodejs.org/) (choose the LTS version).

Verify:

```bash
node -v
npm -v
```

### MySQL

If you installed XAMPP, MySQL is already included. Start it from the XAMPP Control Panel.

### External Accounts

You will need free accounts on:

- **[Cloudinary](https://cloudinary.com)** — for image and video storage
- **[Mailtrap](https://mailtrap.io)** — for email testing in development
- **[Google Cloud Console](https://console.cloud.google.com)** — for Google OAuth (optional; only needed if you want Google login)

---

## Installation

### 1. Clone the repository

```bash
git clone <repo-url> bubbles
cd bubbles
```

### 2. Install PHP dependencies

This downloads all Laravel packages defined in `composer.json` into the `vendor/` folder.

```bash
composer install
```

### 3. Install Node dependencies

This downloads all frontend packages defined in `package.json` into `node_modules/`.

```bash
npm install
```

### 4. Set up environment file

Copy the example environment file and generate a unique application key:

```bash
cp .env.example .env
php artisan key:generate
```

The app key is used by Laravel to encrypt sessions, cookies, and other sensitive values. Never share it.

### 5. Create the database

Open MySQL (via phpMyAdmin, XAMPP, or the CLI) and create the database:

```sql
CREATE DATABASE bubbles CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Or from the terminal:

```bash
mysql -u root -e "CREATE DATABASE bubbles CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

### 6. Fill in the `.env` file

Open `.env` and configure at minimum the database and external services (see [Environment Configuration](#environment-configuration) below).

### 7. Run migrations

This creates all the database tables:

```bash
php artisan migrate
```

You should see output listing each migration as it runs. If you see errors, double-check your `DB_*` values in `.env`.

### 8. Build frontend assets

> **Important:** Run `composer install` (step 2) **before** this step. The build depends on Ziggy, which is generated by Composer.

```bash
npm run build
```

This compiles all Vue components and assets into `public/build/`.

### 9. Start the development server

```bash
composer run dev
```

Open your browser at [http://localhost:8000](http://localhost:8000).

---

### Quick Setup (all steps at once)

If you just want to get running fast, this single command does steps 2–8 automatically:

```bash
composer run setup
```

Then start the server with `composer run dev`.

---

## Environment Configuration

Open `.env` and fill in the values below. Lines starting with `#` are comments and are ignored.

### App

```env
APP_NAME=Bubbles
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost
```

- `APP_ENV=local` — enables development helpers and detailed error pages
- `APP_DEBUG=true` — shows full stack traces on errors (set to `false` in production)

### Database

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=bubbles
DB_USERNAME=root
DB_PASSWORD=
```

XAMPP's default MySQL has no password for the root user, so `DB_PASSWORD` can be left empty.

### Queue

```env
QUEUE_CONNECTION=sync
```

`sync` means jobs (like sending emails) run immediately in the same request. Use `database` in production with a queue worker running.

### Mail

See [Email Setup (Mailtrap)](#email-setup-mailtrap) for the full setup. The relevant variables are:

```env
MAIL_MAILER=smtp
MAIL_HOST=sandbox.smtp.mailtrap.io
MAIL_PORT=2525
MAIL_USERNAME=<your_mailtrap_username>
MAIL_PASSWORD=<your_mailtrap_password>
MAIL_FROM_ADDRESS="bubblessupport@gmail.com"
MAIL_FROM_NAME="${APP_NAME}"
```

### Cloudinary

See [Cloudinary Setup](#cloudinary-setup) for the full setup. The relevant variables are:

```env
CLOUDINARY_URL=cloudinary://<api_key>:<api_secret>@<cloud_name>
CLOUDINARY_CLOUD_NAME=<cloud_name>
CLOUDINARY_API_KEY=<api_key>
CLOUDINARY_API_SECRET=<api_secret>
```

### Google OAuth

See [Google OAuth Setup](#google-oauth-setup) for the full setup. The relevant variables are:

```env
GOOGLE_CLIENT_ID=<your_client_id>
GOOGLE_CLIENT_SECRET=<your_client_secret>
GOOGLE_REDIRECT_URI=http://localhost:8000/auth/google/callback
```

Google OAuth is optional — the platform works fully without it.

### After changing `.env`

Always clear the config cache after editing `.env`:

```bash
php artisan config:clear
```

---

## Email Setup (Mailtrap)

Bubbles uses email verification — every new standard account must verify their email before accessing the platform. In development, emails are intercepted by Mailtrap instead of actually being delivered.

> **Note:** Google OAuth accounts are automatically verified — no email step is required for them.

### Step 1 — Create a Mailtrap account

Go to [mailtrap.io](https://mailtrap.io) and sign up for free.

### Step 2 — Get your SMTP credentials

1. In the Mailtrap dashboard, go to **Email Testing** → **Inboxes**.
2. Click on your inbox (create one if you don't have one yet).
3. Click **SMTP Settings**.
4. You will see credentials like:

```
Host:     sandbox.smtp.mailtrap.io
Port:     2525
Username: a1b2c3d4e5f6
Password: xxxxxxxxxxxx
```

### Step 3 — Add credentials to `.env`

```env
MAIL_MAILER=smtp
MAIL_HOST=sandbox.smtp.mailtrap.io
MAIL_PORT=2525
MAIL_USERNAME=a1b2c3d4e5f6
MAIL_PASSWORD=xxxxxxxxxxxx
MAIL_FROM_ADDRESS="bubblessupport@gmail.com"
MAIL_FROM_NAME="${APP_NAME}"
```

> **Note:** If `MAIL_USERNAME` or `MAIL_PASSWORD` contain spaces, wrap the value in double quotes.

### Step 4 — Clear cache and test

```bash
php artisan config:clear
```

Register a new account at `/register` and check your Mailtrap inbox. The verification email should appear within seconds.

### How email verification works

1. User registers → verification email is sent automatically
2. User is redirected to `/bubbles` → blocked by the `verified` middleware → redirected to `/verify-email`
3. User opens Mailtrap → clicks the verification link in the email
4. Laravel verifies the signed URL → marks the email as verified → redirects to `/bubbles`
5. Full platform access is granted

Users can also request a new verification email from the `/verify-email` page if the original expired.

---

## Cloudinary Setup

All media uploads (avatars, profile banners, post images, and post videos) are stored on Cloudinary. Without valid credentials, uploads will fail.

### Step 1 — Create a Cloudinary account

Go to [cloudinary.com](https://cloudinary.com) and sign up for free. The free tier is generous enough for development and small projects.

### Step 2 — Get your credentials

After logging in, go to your **Dashboard**. You will find:

- **Cloud Name** — a unique identifier for your account (e.g. `dus2rcg8j`)
- **API Key** — a numeric key (e.g. `468683853198913`)
- **API Secret** — a secret string (e.g. `CzMo1ESz...`)

### Step 3 — Add credentials to `.env`

```env
CLOUDINARY_URL=cloudinary://<api_key>:<api_secret>@<cloud_name>
CLOUDINARY_CLOUD_NAME=<cloud_name>
CLOUDINARY_API_KEY=<api_key>
CLOUDINARY_API_SECRET=<api_secret>
```

Replace `<api_key>`, `<api_secret>`, and `<cloud_name>` with your actual values.

### Step 4 — Clear cache

```bash
php artisan config:clear
```

### What Cloudinary stores

| Upload type | Cloudinary folder | Transformations applied |
|---|---|---|
| User avatar | `bubbles/avatars/` | 300×300 cropped to face |
| Profile banner | `bubbles/banners/` | 1200×400 cropped |
| Community image | `bubbles/communities/` | Resized and auto-quality |
| Community banner | `bubbles/banners/` | 1200×400 cropped |
| Post image | `bubbles/posts/` | Auto-format and quality |
| Post video | `bubbles/videos/` | Auto-format |

---

## Google OAuth Setup

Bubbles supports login and registration via Google. Google accounts are automatically verified — no email confirmation step is required.

### Step 1 — Create a Google Cloud project

1. Go to [console.cloud.google.com](https://console.cloud.google.com)
2. Create a new project (or select an existing one)
3. In the left menu, go to **APIs & Services** → **OAuth consent screen**
4. Choose **External**, fill in the app name and email, and save

### Step 2 — Create OAuth credentials

1. Go to **APIs & Services** → **Credentials**
2. Click **Create Credentials** → **OAuth client ID**
3. Choose **Web application**
4. Under **Authorised redirect URIs**, add:
   - `http://localhost:8000/auth/google/callback` (development)
   - `https://yourdomain.com/auth/google/callback` (production)
5. Click **Create** — you will receive a **Client ID** and **Client Secret**

### Step 3 — Add credentials to `.env`

```env
GOOGLE_CLIENT_ID=<your_client_id>
GOOGLE_CLIENT_SECRET=<your_client_secret>
GOOGLE_REDIRECT_URI=http://localhost:8000/auth/google/callback
```

For production, change `GOOGLE_REDIRECT_URI` to your live domain.

### Step 4 — Clear cache

```bash
php artisan config:clear
```

### How Google OAuth works

- If the Google email matches an existing account, that account is linked to Google and the user is logged in
- If no account exists, a new one is created automatically (with a generated username and random avatar colour)
- If the Google account provides a profile photo, it is used as the avatar
- Google-only accounts have no password — they can set one later from the **Settings** page

---

## Running the Application

### Development (recommended)

This single command starts all required services concurrently:

```bash
composer run dev
```

It runs:
- `php artisan serve` — the Laravel HTTP server at `http://localhost:8000`
- `php artisan queue:listen` — processes queued jobs (only needed when `QUEUE_CONNECTION=database`)
- `php artisan pail` — real-time log viewer in the terminal
- `npm run dev` — Vite dev server with Hot Module Replacement (HMR)

Press `Ctrl+C` to stop all services.

### Running services individually

If you prefer to run each service in a separate terminal:

```bash
# Terminal 1 — Laravel server
php artisan serve

# Terminal 2 — Vite (frontend HMR)
npm run dev

# Terminal 3 — Queue worker (only if QUEUE_CONNECTION=database)
php artisan queue:listen --tries=1 --timeout=0

# Terminal 4 — Log viewer (optional)
php artisan pail --timeout=0
```

---

## First Use

Once the app is running at [http://localhost:8000](http://localhost:8000):

### Registering an account

**Via email:**
1. Go to `/register`
2. Fill in your name, username, email, and password
3. Submit the form — a verification email is sent to your Mailtrap inbox
4. Open Mailtrap, find the email, and click **Verify Email Address**
5. You are now logged in and have full access to the platform

**Via Google:**
1. Go to `/login` or `/register`
2. Click **Continue with Google**
3. Authorise the app in the Google popup
4. Your account is created and verified automatically — no email step required

### Navigating the platform

| Page | URL | What you can do |
|---|---|---|
| Feed | `/bubbles` | View posts from communities you joined and friends |
| Communities | `/bubbles` | Browse, create, and join bubbles |
| Community | `/c/{id}` | View community posts, join/leave, create posts |
| Profile | `/u/{username}` | View any user's public profile |
| Settings | `/profile` | Edit name, bio, avatar, banner, theme, and password |
| Conversations | `/conversations` | Send and read private messages and group chats |
| Friends | `/friends` | Manage friend requests and friendships |
| Notifications | `/notifications` | View all notifications |
| Search | `/search?q=...` | Find users and communities |

### Creating your first bubble

1. Go to `/bubbles` (the main feed page)
2. Click **Create Bubble** (or the `+` button)
3. Give it a name, color, and optional image
4. Your bubble is now public — other users can find and join it

### Making a post

**On your profile:**
1. Go to `/u/{your-username}` or click your avatar
2. Click the post composer
3. Write your text and optionally attach an image or video
4. Submit

**Inside a community:**
1. Go to the community page `/c/{id}`
2. Use the post composer at the top
3. Submit — the post appears in the community feed

---

## Admin Panel

The admin panel is available at `/admin` and is only accessible to users with the `admin` role.

### Granting admin access

To make a user an admin, run this in the terminal:

```bash
php artisan tinker
```

Then inside tinker:

```php
\App\Models\User::where('email', 'your@email.com')->update(['role' => 'admin']);
```

Exit tinker with `exit` or `Ctrl+D`.

### Admin features

| Section | URL | Actions |
|---|---|---|
| Dashboard | `/admin` | Overview and stats |
| Users | `/admin/users` | View all users, change roles, delete accounts |
| Posts | `/admin/posts` | View, restore, and force-delete posts |
| Community Posts | `/admin/community-posts` | View, restore, and force-delete community posts |
| Communities | `/admin/communities` | View and delete communities |
| Reports | `/admin/reports` | Review, resolve, or dismiss reported content |
| Punishments | `/admin/punishments` | Issue warnings, mutes, or bans; revoke punishments |
| Announcements | `/admin/announcements` | Post and manage platform-wide announcements |
| Audit Logs | `/admin/audit-logs` | View all sensitive actions across the platform |

### User roles

| Role | Access |
|---|---|
| `user` | Default — full access to platform features |
| `moderator` | (Reserved for future use) |
| `admin` | Full access including the admin panel |
| `suspended` | Blocked from logging in |

---

## Development Workflow

### Code style

Format PHP with Laravel Pint:

```bash
./vendor/bin/pint
```

Format and lint JavaScript/Vue:

```bash
npm run format   # Prettier
npm run lint     # ESLint
```

### Useful artisan commands

```bash
# Clear all caches
php artisan config:clear && php artisan route:clear && php artisan view:clear

# Reset the database and re-run all migrations
php artisan migrate:fresh

# Reset database and run seeders
php artisan migrate:fresh --seed

# Open the interactive PHP shell
php artisan tinker

# View all registered routes
php artisan route:list

# View application logs in real time
php artisan pail --timeout=0
```

### Database

All migrations are in `database/migrations/`. To add a new table or column, create a new migration:

```bash
php artisan make:migration add_column_to_table
```

Never edit existing migration files — create new ones instead.

---

## Testing

Run the full test suite:

```bash
composer run test
```

Or directly with artisan:

```bash
php artisan test
```

Run a specific test file:

```bash
php artisan test tests/Feature/Auth/RegistrationTest.php
```

Run tests with coverage (requires Xdebug or PCOV):

```bash
php artisan test --coverage
```

Tests use `RefreshDatabase`, which wraps each test in a transaction and rolls it back — the database is never permanently modified by tests.

---

## Troubleshooting

### HTTP 500 on every page

**Cause:** Invalid `.env` file syntax (e.g. unquoted value with spaces).

**Fix:** Check `.env` for values with spaces and wrap them in double quotes:

```env
MAIL_USERNAME="My Name"   # correct
MAIL_USERNAME=My Name     # breaks .env parsing
```

Then clear the cache:

```bash
php artisan config:clear
```

---

### Email not sending — "Invalid credentials"

**Cause:** `MAIL_USERNAME` or `MAIL_PASSWORD` in `.env` are wrong.

**Fix:** Go to Mailtrap → **Email Testing** → **Inboxes** → click your inbox → **SMTP Settings** → copy the exact `Username` and `Password` strings into `.env`. They are random alphanumeric strings, not your Mailtrap account email or password.

---

### Google login shows an error or redirects to login with an error message

**Cause:** Missing or incorrect Google OAuth credentials, or the redirect URI is not registered in Google Cloud Console.

**Fix:**
1. Check `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, and `GOOGLE_REDIRECT_URI` in `.env`
2. Confirm the redirect URI matches exactly what is listed in your Google Cloud Console credentials
3. Run `php artisan config:clear`

---

### Existing accounts can't access the platform after enabling email verification

**Cause:** Accounts created before email verification was enabled have `email_verified_at = null`.

**Fix:** Mark all existing accounts as verified:

```bash
php artisan tinker
```

```php
\App\Models\User::whereNull('email_verified_at')->update(['email_verified_at' => now()]);
```

---

### `npm run build` fails with module-not-found error

**Cause:** `vendor/` folder is missing (Composer was not run first).

**Fix:**

```bash
composer install
npm run build
```

---

### Images/videos not uploading

**Cause:** Cloudinary credentials are missing or incorrect.

**Fix:** Double-check all four `CLOUDINARY_*` values in `.env` against your Cloudinary dashboard, then:

```bash
php artisan config:clear
```

---

### Port 8000 already in use

**Fix:** Start on a different port:

```bash
php artisan serve --port=8080
```

---

## Production Build

### 1. Set environment variables

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://yourdomain.com
QUEUE_CONNECTION=database
GOOGLE_REDIRECT_URI=https://yourdomain.com/auth/google/callback
```

### 2. Build frontend assets

```bash
npm run build
```

### 3. Optimise Laravel

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan optimize
```

### 4. Run migrations

```bash
php artisan migrate --force
```

### 5. Run the queue worker

In production, `QUEUE_CONNECTION=database` requires a persistent queue worker. Run it as a system service (e.g. via Supervisor):

```bash
php artisan queue:work --tries=3 --timeout=60
```

---

## Roadmap

- [ ] WebSocket-based real-time messaging (replace polling)
- [ ] Post scheduling
- [ ] Hashtags and tag-based discovery
- [ ] Email notifications for activity outside the app
- [ ] Mobile-responsive improvements
- [ ] Story / ephemeral posts

---

## License

MIT
