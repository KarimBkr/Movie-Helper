# Queue Configuration - Movie Helper

## Configuration (C-06)

### Queue Settings
- **Queue Name:** `ai`
- **Max Workers:** 2 (parallel, production)
- **Job:** `AnalyzeSequenceJob`
- **Tries:** 2
- **Backoff:** 30 seconds

### Local Development

Start queue worker:
```bash
php artisan queue:work --queue=ai --sleep=2 --tries=2 --timeout=120
```

### Production

Use supervisor or similar daemon manager to run:
```bash
php artisan queue:work --queue=ai --sleep=2 --tries=2 --timeout=120 --max-processes=2
```

## AnalyzeSequenceJob

```php
public int $tries = 2;
public int $backoff = 30;
// Retry after 30 seconds on failure
// Max 2 attempts total
```

## Monitoring

- Failed jobs in `failed_jobs` table
- Retry failed items via `/api/projects/{id}/analysis/{jobId}/retry-failed`
- Max 2 workers to avoid Claude rate limiting
