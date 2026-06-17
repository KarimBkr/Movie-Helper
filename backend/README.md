# Movie Helper Backend

Laravel API server.

## Structure

```
app/
  Http/
    Controllers/
    Middleware/
    Requests/
  Services/             # Business logic
    Projects/
    Exports/
  DTO/                  # Data Transfer Objects
  Support/
  AI/                   # Claude integration
database/
  migrations/
  seeders/
tests/
  Feature/
  Unit/
routes/
  api.php
storage/
  testing/              # Test FDX files
```

## Setup

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan serve
```

## Queue Worker (Development)

```bash
php artisan queue:work --queue=ai --sleep=2 --tries=2 --timeout=120
```

## Environment

See `docs/env-setup.md` for all required variables.
