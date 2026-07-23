# Connecta Apps

A full-stack web application starter built with **Laravel 13**, **React 19**, **Inertia.js 3.0**, and **shadcn/ui**.

## Tech Stack

| Layer | Technology |
|---|---|
| Backend | Laravel 13, PHP 8.3+ |
| Frontend | React 19, TypeScript, Inertia.js 3.0 |
| UI | shadcn/ui (New York), Radix UI, Tailwind CSS 4, Lucide icons |
| Build | Vite 8 |
| Database | SQLite (default) |
| Auth | Laravel Fortify (Passkeys/WebAuthn, 2FA) |

## Features

- **Authentication** — Login, registration, email verification, password reset
- **Two-Factor Auth** — TOTP-based 2FA with recovery codes
- **Passkeys** — WebAuthn passwordless authentication
- **User Profile** — Edit name/email, update password, delete account
- **Appearance** — Light/dark mode toggle with persistent preference
- **Dashboard** — Authenticated home page

## Getting Started

```bash
# Install dependencies
composer install
npm install

# Set up environment
cp .env.example .env
php artisan key:generate

# Run migrations
php artisan migrate

# Start dev servers
npm run dev
php artisan serve
```

## Routes

| Route | Page | Auth |
|---|---|---|
| `/` | Landing page | No |
| `/dashboard` | Dashboard | Yes |
| `/settings/profile` | Edit profile | Yes |
| `/settings/security` | Password, 2FA, Passkeys | Yes |
| `/settings/appearance` | Theme toggle | Yes |

## License

[MIT](LICENSE)
