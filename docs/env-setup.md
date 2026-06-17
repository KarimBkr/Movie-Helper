# Environment Setup - Movie Helper

## Laravel Environment Variables

```
# .env (backend/.env)
APP_NAME=MovieHelper
APP_ENV=local
APP_KEY=base64:YOUR_BASE64_ENCODED_KEY
APP_DEBUG=true
APP_URL=http://localhost:8000

# Database - Supabase PostgreSQL
DB_CONNECTION=pgsql
DB_HOST=your-project.supabase.co
DB_PORT=5432
DB_DATABASE=postgres
DB_USERNAME=postgres
DB_PASSWORD=your_supabase_password

# Supabase Configuration
SUPABASE_URL=https://your-project.supabase.co
SUPABASE_SERVICE_ROLE_KEY=your_service_role_key_here
SUPABASE_JWT_SECRET=your_jwt_secret_here
SUPABASE_ANON_KEY=your_anon_key_here

# Claude API (MUST NOT be in frontend .env)
ANTHROPIC_API_KEY=sk-ant-your-claude-api-key-here

# Storage - Supabase
SUPABASE_STORAGE_BUCKET_FDX=fdx-files
SUPABASE_STORAGE_BUCKET_EXPORTS=exports

# Queue Configuration
QUEUE_CONNECTION=database
QUEUE_DRIVER=database

# Mail (optional for V1, no email invitations yet)
MAIL_DRIVER=smtp
MAIL_HOST=smtp.mailtrap.io
MAIL_PORT=465
MAIL_USERNAME=your_mailtrap_username
MAIL_PASSWORD=your_mailtrap_password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@moviehelper.local

# Session & Cache
SESSION_DRIVER=file
CACHE_DRIVER=file

# Logging
LOG_CHANNEL=stack

# Analytics (optional, not in V1)
SENTRY_DSN=
```

## Next.js Environment Variables

```
# frontend/.env.local (NEVER commit)
NEXT_PUBLIC_SUPABASE_URL=https://your-project.supabase.co
NEXT_PUBLIC_SUPABASE_ANON_KEY=your_anon_key_here

# Backend API
NEXT_PUBLIC_API_URL=http://localhost:8000/api
```

### ⚠️ Security Rules

- **NEVER in frontend:**
  - `SUPABASE_SERVICE_ROLE_KEY`
  - `ANTHROPIC_API_KEY`
  - `SUPABASE_JWT_SECRET`
  - `DB_PASSWORD`

- **Stored on Laravel only**, retrieved from environment:
  - All sensitive credentials

- **Passed by Next.js to Laravel on every request:**
  - Supabase access token in `Authorization: Bearer <token>` header

## Local Development Setup

### 1. Backend (Laravel)
```bash
cd backend
cp .env.example .env
# Edit .env with Supabase credentials
php artisan key:generate
php artisan migrate
php artisan serve
```

### 2. Frontend (Next.js)
```bash
cd frontend
cp .env.local.example .env.local
# Edit .env.local with public vars only
npm run dev
```

### 3. Queue Worker (Laravel)
```bash
cd backend
php artisan queue:work --queue=ai --sleep=2 --tries=2 --timeout=120
```

## Production Checklist

- [ ] All secrets in hosting provider secrets manager
- [ ] No `.env` files committed to git
- [ ] RLS policies enabled on all Supabase tables
- [ ] Queue max workers set to 2 in production
- [ ] Claude API key rotated regularly
- [ ] Database backups configured
- [ ] API rate limiting enabled
