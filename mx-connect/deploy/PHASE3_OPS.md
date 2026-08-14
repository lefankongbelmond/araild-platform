# MX-CONNECT — Phase 3 operations runbook

Operational backbone added in Phase 3 (Modules 15–17). This complements
`nginx.conf`, `supervisor.conf` (Reverb), `crontab.txt` and `deploy.sh`.

## Services expected in production
- **PHP-FPM + Nginx** — wildcard `*.mx-connect.com` (see `nginx.conf`).
- **Redis** — cache, queue and session backend. Install `predis/predis` or the
  `phpredis` extension. If Redis is unavailable, set `QUEUE_CONNECTION=database`,
  `CACHE_STORE=database`, `SESSION_DRIVER=database` (tables already migrated).
- **MySQL/MariaDB** — one central DB (`c1central`) + one DB per tenant.

## Queue workers
Copy `supervisor-worker.conf` to `/etc/supervisor/conf.d/`, then
`supervisorctl reread && supervisorctl update`. Scale `numprocs` with volume.
Queued work today: contribution reminders (notification), and headroom for
report/import jobs. After each deploy run `php artisan queue:restart`.

## Scheduler (cron)
`crontab.txt` runs `php artisan schedule:run` every minute. Scheduled tasks:
- `contributions:sweep-overdue` — 01:00 daily (arrears).
- `contributions:remind` — 08:00 daily (queued reminders).
- `mxconnect:backup` — 02:30 daily (central + tenant DB dumps, logged to DR).

## Backups & DR
`mxconnect:backup` dumps every database to `storage/app/backups/<timestamp>/`
and records the run in the DR register (`/reseau/pra`) with duration as the RTO
actual. Ship the dump directory off-box (S3/rsync) from `deploy.sh` or a wrapper.
Rehearse restores and log them from the DR screen; the verdict flags any drill
that missed RTO/RPO.

## Observability
- Structured JSON logs (`config/logging.php`, `structured` channel) with a
  per-request `request_id` (echoed on the `X-Request-Id` response header),
  tenant and user, via the `RequestContext` middleware — ship to an aggregator.
- Slow queries beyond `MXCONNECT_SLOW_QUERY_MS` (default 500 ms) are logged as
  `slow_query` warnings with the request context attached.
- `GET /health` returns app/DB/cache status (200/503) for load balancers.
- `php artisan mxconnect:tenants-health` and the network **Health** dashboard
  report per-tenant reachability and volumes.

## Security
`SecurityHeaders` sets CSP/HSTS/X-Frame-Options/etc. on every response; HTTPS is
forced in production. Tighten the CSP (drop `unsafe-inline`) once front-end
assets are bundled instead of using the Tailwind CDN.

## N+1 audit
Set `MXCONNECT_STRICT_LAZY=true` in a staging/CI run to make lazy loads throw,
surfacing any missing eager-load before it reaches production.
