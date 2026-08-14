# MX-CONNECT — Phase 5 real-time (Reverb) notes

The member social feed broadcasts new posts over WebSockets using **Laravel Reverb**.

## Services
- Run the Reverb server: `php artisan reverb:start` (keep alive under Supervisor —
  see `deploy/supervisor.conf`). It listens on `REVERB_SERVER_PORT` (default 8080);
  put Nginx in front to terminate TLS and proxy `wss://` to it.
- Broadcasting driver: set `BROADCAST_CONNECTION=reverb` and the `REVERB_APP_ID`,
  `REVERB_APP_KEY`, `REVERB_APP_SECRET`, `REVERB_HOST`, `REVERB_PORT`,
  `REVERB_SCHEME` env vars. The client reads the public host/key from the page.

## Multi-tenancy
Broadcasting authorisation is handled by a **tenant-scoped** endpoint,
`POST /api/member/broadcasting/auth`, inside the member API group so tenancy is
initialised by domain and the caller is authenticated with their Sanctum token.
The feed channel is `private-tenant.{tenantId}.feed`; `routes/channels.php` only
authorises an account linked to a member of that exact tenant, so a member of one
mutual can never subscribe to another mutual's feed.

## Client
`resources/views/tenant/pwa/feed.blade.php` loads `pusher-js` + `laravel-echo`
from CDN (consistent with the CDN-based PWA), connects with the member's bearer
token, and listens for `.feed.post.created` to prepend new posts live. A status
pill shows connecting/live/offline.

## Scaling
For more than one Reverb process, enable `REVERB_SCALING_ENABLED=true` (Redis
pub/sub) so events fan out across processes. Single-process is fine for the pilot.

## Nginx
Add a location block proxying the Reverb port with `Upgrade`/`Connection` headers
for WebSocket upgrades (see the commented example in `deploy/nginx.conf`).
