# Bubbles

A community-driven social platform where users create and join **bubbles** (communities), share posts, react with likes and comments, chat privately, and manage their profiles — all in a modern, real-time-capable web app.

---

## Features

- **Feed** — chronological post feed from followed communities and friends
- **Bubbles (Communities)** — create, join, and manage topic-based communities with custom images and banners
- **Posts** — create text + image/video posts on profiles or inside communities; edit and delete your own
- **Likes & Comments** — like and comment on profile posts and community posts
- **Notifications** — in-app notification badge with real-time polling; notifications for likes, comments, and messages
- **Private Messaging** — one-to-one conversations with message send, edit, and delete
- **Friends** — send, accept, and reject friend requests
- **Search** — search users and communities
- **Profiles** — public user profiles with avatar, banner, and post history
- **Reports** — report posts for admin review
- **Admin Panel** — manage users, posts, communities, and reports

---

## Stack

| Layer | Technology |
|---|---|
| Backend | Laravel 12 (PHP 8.2+) |
| Frontend | Vue 3 (Composition API) |
| Bridge | Inertia.js v2 |
| Styling | Tailwind CSS v3 |
| Build | Vite 7 |
| Image/Video CDN | Cloudinary |
| Database | MySQL |
| Queue | Laravel Database Queue |
| Auth | Laravel Breeze (session) |

---

## Prerequisites

- PHP 8.2 or higher (with extensions: `pdo_mysql`, `mbstring`, `xml`, `curl`, `gd`)
- Composer 2
- Node.js 18+ and npm
- MySQL 8 (or MariaDB 10.6+)
- A free [Cloudinary](https://cloudinary.com) account (for image and video uploads)

---

## Local Installation

### 1. Clone the repository

```bash
git clone <repo-url> bubbles
cd bubbles
```

### 2. Install PHP dependencies

```bash
composer install
```

### 3. Install Node dependencies

```bash
npm install
```

### 4. Set up environment

```bash
cp .env.example .env
php artisan key:generate
```

Then fill in the required `.env` values (see section below).

### 5. Create the database

Create a MySQL database named `bubbles` (or whatever you set in `DB_DATABASE`):

```sql
CREATE DATABASE bubbles CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

### 6. Run migrations

```bash
php artisan migrate
```

### 7. Build frontend assets

> **Important:** `npm run build` depends on Ziggy, which is installed by Composer into `vendor/`.
> Always run `composer install` (step 2) **before** `npm run build`, or the build will fail with a
> module-not-found error.

```bash
npm run build
```

### 8. Start the dev server

```bash
composer run dev
```

This runs Laravel, the queue worker, the log viewer (Pail), and Vite concurrently.

---

## Environment Configuration

Copy `.env.example` to `.env` and set at minimum:

```env
APP_NAME=Bubbles
APP_URL=http://localhost

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=bubbles
DB_USERNAME=root
DB_PASSWORD=

QUEUE_CONNECTION=database

# Cloudinary — required for image and video uploads
CLOUDINARY_URL=cloudinary://<api_key>:<api_secret>@<cloud_name>
CLOUDINARY_CLOUD_NAME=<cloud_name>
CLOUDINARY_API_KEY=<api_key>
CLOUDINARY_API_SECRET=<api_secret>
```

All other variables (mail, Redis, AWS) are optional for local development and default to safe no-op values.

---

## Running the Application

### Development (all services at once)

```bash
composer run dev
```

Starts:
- `php artisan serve` — Laravel HTTP server
- `php artisan queue:listen` — processes queued jobs (notifications, etc.)
- `php artisan pail` — real-time log viewer
- `npm run dev` — Vite HMR dev server

### Individual services

```bash
# Backend only
php artisan serve

# Queue worker
php artisan queue:listen --tries=1 --timeout=0

# Real-time logs
php artisan pail --timeout=0

# Vite dev server
npm run dev
```

---

## Running Tests

```bash
composer run test
```

Or directly:

```bash
php artisan test
```

---

## Production Build

Build and optimise all frontend assets:

```bash
npm run build
```

Optimise Laravel for production:

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan optimize
```

Make sure `APP_ENV=production` and `APP_DEBUG=false` are set in `.env`.

---

## Cloudinary Notes

Bubbles uses Cloudinary for all media uploads (avatars, banners, post images, and videos). Without valid Cloudinary credentials, image and video uploads will fail silently or throw errors.

1. Create a free account at [cloudinary.com](https://cloudinary.com).
2. Copy your **Cloud Name**, **API Key**, and **API Secret** from the Cloudinary dashboard.
3. Paste them into the four `CLOUDINARY_*` variables in `.env`.

The integration uses the [`cloudinary-labs/cloudinary-laravel`](https://github.com/cloudinary-labs/cloudinary-laravel) package.

---

## Quick Setup Script

The `composer setup` script performs a full bootstrap from scratch:

```bash
composer run setup
```

This runs: `composer install` → copy `.env` → `key:generate` → `migrate` → `npm install` → `npm run build`.

---

## Roadmap

- [ ] WebSocket-based real-time messaging (replace polling)
- [ ] Post scheduling
- [ ] Hashtags and tag-based discovery
- [ ] Email notifications
- [ ] Mobile-responsive improvements
- [ ] Story / ephemeral posts

---

## License

MIT
