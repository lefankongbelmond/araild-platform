# MX-CONNECT

Multi-country, multi-mutual, multi-tenant SaaS for community health mutuals.
100% PHP / Laravel 11 · MySQL 8 · Redis · database-per-tenant (`stancl/tenancy`) · LWS VPS.

> **What this package is.** A real, buildable, **installable** Laravel 11 application — full skeleton (`artisan`, `public/index.php`, `config/*`) plus the load-bearing spine the rest of the platform bolts onto: multi-tenancy wiring, the multi-country configuration layer, the full central + tenant database schema, core domain models, the payment ledger (facilitation model), tenant provisioning, rights verification, duplicate detection, RBAC + seeding, a modern public marketing site, the isolation test, and the LWS deploy config.
>
> **What it is not (yet).** It is not the finished platform. Controllers, Blade/Livewire screens, form requests, policies and full test coverage for every module are built **phase by phase, module by module** (see roadmap).

## Why nothing is hard-coded for Cameroon
Countries, currencies, payment providers and locales are **database tables** (`countries`, `currencies`, `payment_providers`, `locales`), seeded as *data*. Cameroon and MTN/Orange are the first rows, not code. A new country/provider/currency is an insert — never a code change. Each mutual references them by foreign key.

## Install (on your machine or the VPS)
This is a complete, installable Laravel 11 project — `artisan`, `public/index.php` and every core `config/*.php` file are already in place. On a machine with PHP 8.3 + Composer:

```bash
composer install
cp .env.example .env && php artisan key:generate
# (a pre-filled .env is generated separately for the LWS VPS — see deploy/DEPLOIEMENT_LWS.md)

# 2. Create the central DB (c1central) and set DB_* in .env.
php artisan migrate            # central schema (config layer, mutuals, ledger, providers, audit)
php artisan db:seed            # countries/currencies/locales/providers + first super admin
php artisan storage:link

# 3. Publish stancl + spatie + fortify config, then create a tenant to test:
php artisan vendor:publish --tag=tenancy-config
php artisan tinker
>>> app(\App\Services\MutualProvisioningService::class)->create([
...   'name'=>'Mutuelle Pilote 1','slug'=>'pilote1',
...   'country_id'=>1,'currency_id'=>1,'locale_id'=>1,
... ]);
# stancl creates mxconnect_mut_01, runs database/migrations/tenant/*, seeds TenantParameterSeeder.
```

## Deploy (LWS VPS)
`deploy/deploy.sh` provisions Ubuntu + Nginx + PHP-FPM + MySQL + Redis + Supervisor + Cron, installs the app, migrates/seeds central, and wires services. Nginx uses a **wildcard** `*.mx-connect.com` so one block serves every tenant subdomain. Rotate the seeded super-admin password immediately.

## What's inside
```
app/Models/Central/   Mutual (tenant), Country, Currency, Locale, PaymentProvider,
                      MutualPaymentConfig (encrypted), PaymentTransaction (ledger)
app/Models/Tenant/    Member, Dependent, MemberGroup, Guarantee, GuaranteeVersion,
                      Ceiling, Subscription, ContributionSchedule
app/Services/         MutualProvisioning, PaymentLedger, RightsVerification, DuplicateDetection
app/Enums/            SubscriptionStatus, PaymentStatus, MemberStatus, AlertLevel
config/               mxconnect.php (limits/roles/MFA), tenancy.php
database/migrations/          central: config layer, tenancy+identity, ledger+providers+audit
database/migrations/tenant/   parameters+guarantees, members+subscriptions, contributions+claims+finance
database/seeders/     ConfigReference (data), Network (super admin), TenantParameter (roles/SYSCOHADA)
routes/               web.php (central), tenant.php (subdomain, isolation middleware)
tests/Feature/        TenantIsolationTest (GO/NO-GO, 0-leak)
deploy/               nginx.conf, supervisor.conf, crontab.txt, deploy.sh
docs/                 BUILD_BRIEF_MX-CONNECT.md (the optimised master prompt)
```

## Build roadmap (module by module, each meeting Definition of Done)
Definition of Done = migration ✔ · model+relations+casts ✔ · form-request validation ✔ · policy (maker-checker where relevant) ✔ · controller/Livewire + Blade ✔ · feature test (incl. isolation/authz) ✔ · audited ✔ · i18n keys ✔ · factory/seed ✔.

**Phase 1 — pilot core (build order):**
1. Auth + MFA (Fortify) + RBAC wiring (network + tenant guards) + `mfa` middleware.
2. Mutual onboarding (super admin): create/approve mutual → provisioning → subdomain live.
3. Configuration screens: countries/currencies/providers/locales admin (data-driven).
4. Parameters: antennas, guarantees + versioning, act types, medicines, ceilings.
5. Members + dependents + groups (with duplicate detection).
6. Subscriptions (maker-checker) → schedule generation → digital QR card.
7. Contributions: arrears sweep, reminders, receipts.
8. Payment: provider driver + initiation + idempotent webhook + reconciliation job.
9. Care claims → prestations → provider invoices (tiers payant) → reimbursements + anti-fraud alerts.
10. Treasury + SYSCOHADA accounting.
11. Member PWA (Sanctum) + minimal public directory + migration imports + audit views + i18n.

**Phases 2–5:** actuarial/solvency dashboards, risk register, incidents, complaints, DQ score, DR test → scale 4→50 → directory/comparator/consolidation/SaaS billing → moderated social feed (Reverb).

I can build any of these modules out in full next — say which one and I'll deliver its migration, model, validation, policy, controller/Livewire, Blade, and tests.

---

## Delivered so far (module by module)
- **Foundation spine** — tenancy, multi-country config layer, full schema, ledger, provisioning, services, deploy config.
- **Module 1 — Auth + MFA + RBAC** ✔ network guard + tenant guard, TOTP MFA forced for sensitive roles (`mfa` middleware), spatie roles on both central and tenant, login + MFA screens, feature tests.
- **Module 2 — Mutual onboarding** ✔ super-admin creates a mutual → tenancy pipeline provisions an isolated DB, runs tenant migrations, seeds roles/SYSCOHADA → subdomain attached; approve/suspend with policy; validation, Blade UI, feature tests, i18n (FR/EN).

**Next in priority order:** Module 3 — Configuration screens (countries/currencies/providers/locales admin) → Module 4 — Parameters (antennas, guarantees + versioning, act types, medicines, ceilings) → Module 5 — Members (+ duplicate detection).
- **Module 3 — Network configuration** ✔ super-admin CRUD for currencies, locales, countries (with payment-provider availability) and payment providers; data-driven (adding a country is data, not code); config changes audited; gate `manage-config` / `view-config`; feature tests.
- **Module 4 — Mutual parameters** ✔ antennas, act types, medicines, and **guarantees with versioning** (`GuaranteeVersioningService`: editing terms closes the current version and opens a successor; past claims stay evaluated on their own version; locked versions immutable); tenant layout + views; gate `manage-parameters`; versioning + applicability tests.

**Next in priority order:** Module 5 — Members (+ duplicate detection) → Module 6 — Subscriptions (maker-checker) + schedule generation + digital QR card → Module 7 — Contributions (arrears, reminders, receipts).
- **Module 5 — Members** ✔ enrolment with **duplicate detection** (strong match on phone/encrypted-ID blocks; weak match on name+DOB warns and requires agent confirmation), search, dependents add/remove, group/antenna assignment, active/suspended lifecycle; member-code generation; index/create/show views; audited; feature + unit tests.

**Next in priority order:** Module 6 — Subscriptions (maker-checker) + schedule generation + digital QR card → Module 7 — Contributions (arrears sweep, reminders, receipts) → Module 8 — Payment (provider driver + idempotent webhook + reconciliation job).
- **Module 6 — Subscriptions (maker-checker) + digital card** ✔ capture by an agent (`captured`), validation by a *different* controller (separation of duties enforced — a capturer cannot validate their own); on validation the contribution schedule is generated (`ScheduleGenerationService`: 12 monthly / 4 quarterly / 1 annual, idempotent) and a **digital QR card** is issued (`DigitalCardService`: HMAC-signed token, no medical data; provider scan → rights verdict only). Tests: maker-checker, schedule counts, idempotency, signed-token verification.
- **Module 7 — Contributions** ✔ arrears overview (to-pay / overdue / paid tabs), nightly `contributions:sweep-overdue` (per-tenant, flags only past-due unpaid lines), `contributions:remind` at offsets −3/0/+7 via `ContributionReminder` notification, cash/group payment capture, numbered receipts (`ReceiptService`, stored per-tenant). Tests: arrears sweep.
- **Module 8 — Payment (facilitation)** ✔ `PaymentDriver` contract + `SandboxDriver`; Mobile Money initiation toward the mutual's *own* merchant account (MX-CONNECT never holds funds); **central webhook** `PaymentWebhookController` (per-mutual signature verification, idempotent ledger update) → tenant-aware `ReconcilePayment` job (marks schedule paid, records treasury inflow, generates receipt). Reconciliation is idempotent and happens in exactly one place. Tests: webhook idempotency, failed-payment handling.

**Scheduling:** `routes/console.php` registers the two daily commands (sweep 01:00, reminders 08:00), run by `php artisan schedule:run` via cron (see `deploy/crontab.txt`).

**Next in priority order:** Module 9 — Care claims / prestations (recours → prestations → provider invoices [tiers payant] → reimbursements + anti-fraud alerts) → Module 10 — Treasury + SYSCOHADA accounting → Module 11 — Member PWA (Sanctum) + public directory + migration imports + audit views.
- **Module 9 — Care claims chain** ✔ open a claim (recours) for a member/dependent → capture prestations (each runs the 8-point rights check to block ineligible care, an **assessment** computing the mutual/beneficiary split capped by per-act & annual ceilings, and **anti-fraud screening** raising alerts) → maker-checker validation (a benefits_manager captures, a controller validates) → settlement by **reimbursement** (pay the member) or **tiers payant** (provider invoice), each recording a treasury outflow with payer≠capturer enforced. Central care-provider registry added. Anti-fraud alert queue (critical/watch) with clear action. Tests: assessment split + per-act & annual ceiling caps, fraud rules (duplicate/temporal/financial), prestation maker-checker.

**Next in priority order:** Module 10 — Treasury dashboard + SYSCOHADA accounting (journal, accounts, daily close) → Module 11 — Member PWA (Sanctum) + public directory + migration imports + audit views.
- **Module 10 — Treasury + SYSCOHADA accounting** ✔ treasury dashboard (balance, inflow/outflow, balance-by-mode, 6-month chart, theoretical cash), movements list + manual adjustment (blocked on a closed day); SYSCOHADA chart of accounts seeded per tenant from config; **journal** with double-entry lines; `AccountingService` bridges each treasury movement into a balanced entry (debit/credit resolved from a source→account mapping) — idempotent, stamps the movement as posted; **daily cash close** (`DailyCloseService`, "arrêté de caisse") computing theoretical cash (opening + cash in − cash out), recording the counted amount and the discrepancy, then freezing the day. Tests: bridge accounts + balance + idempotency, theoretical-cash computation, discrepancy, closed-day guard.
- **Module 11 — Member PWA + public directory + migration imports + audit trail** ✔ **Critical infra fix:** added the owen-it `audits` table (central + tenant) — without it every `Auditable` model would have crashed at runtime — plus the tenant `personal_access_tokens` table and Sanctum config. **Member PWA** (`/espace-membre`): installable shell (manifest + service worker, offline shell cache) driving a Sanctum-secured member API — login (phone + password against the central public account, must be linked to a member of this mutual), `me`, digital card token, contribution schedules, claims, and Mobile Money payment of one's own schedule. Tokens are tenant-scoped (a member signs in per mutual subdomain). **Public directory** (`/annuaire`, unauthenticated, central): approved mutuals with country filter. **Migration import** (`MemberImportService`): CSV upload with a downloadable template, per-row validation, duplicate detection (strong duplicate skipped, weak duplicate imported but flagged), and a full per-row report. **Audit viewer** (tenant, role-gated): reads the `audits` trail with a model-type filter. CSRF exemptions added for `api/member/*` and `webhooks/paiement/*` (the latter also closing a latent Module 8 gap). Tests: CSV import (create / validate / dedupe).
- **Module 12 — Actuarial & solvency dashboard + data-quality score** ✔ (Phase 2). `ActuarialService`: loss ratio S/P (validated claims' mutual part ÷ collected contributions), technical result, reserves (net treasury balance), prudential required reserve (average monthly claims × configurable months), solvency ratio, a loss-ratio traffic light, and a monthly premiums-vs-claims series. Dashboard with year selector, KPI cards, inline chart and a solvency panel. `DataQualityService`: weighted completeness/consistency checks over member & subscription data (phone, ID, antenna, birth date, active-has-subscription, subscription-has-schedule), producing a 0–100 score with a per-check breakdown; checks with no applicable rows are skipped so an empty mutual isn't unfairly scored. Both dashboards role-gated. Tests: loss-ratio & technical result, validated-only incurred claims, loss-ratio banding, solvency ratio; data-quality perfect score and weighted penalty.
- **Module 13 — Governance registers: risks, incidents, complaints** ✔ (Phase 2). **Risk register**: likelihood × impact scoring (1..25) banded low/medium/high/critical by a configurable matrix (`RiskScoringService`), category, owner, treatment, review date, and an open→mitigating→closed workflow; the register is sorted by inherent score. **Incident log**: category/severity, occurred & detected dates, open→investigating→resolved→closed workflow with a guard requiring a resolution before resolving/closing. **Complaint register**: optional member link, channel, an SLA due date computed from receipt (`complaint_sla_days`), overdue detection, and a resolution-required workflow. All three are auditable, role-gated, with inline-create index + detail views. Tests: risk score & banding across the matrix, complaint SLA overdue logic (including that resolved complaints are never overdue).
- **Module 14 — Disaster-recovery drill log** ✔ (Phase 2, network-level). A central register of DR exercises (backup/restore, failover, full DR, tabletop) with scope, date, operator, RTO/RPO targets vs actuals, stated outcome and findings. `DrAssessmentService` evaluates each objective (met only when an actual is recorded and ≤ target) and derives a verdict — clean / degraded / failed / incomplete — so a drill marked "success" that blew its RTO surfaces as degraded, and a target with no measured actual surfaces as incomplete. Super-admin only; index (inline create) + detail, with a new network header nav (Mutuelles · Config · PRA). Tests: objective-met boundary, and the four verdict paths.
- **Module 15 — Operations backbone** ✔ (Phase 3, part 1). Queue/cache/session configs (Redis-first, env-driven) and the central `jobs` / `job_batches` / `failed_jobs` / `cache` tables; the contribution reminder is now a queued notification so a sweep across thousands of members doesn't block. Named rate limiters (`AppServiceProvider`): `member-api` (60/min per account or IP), `webhooks` (120/min per mutual), `login` (10/min per IP), applied as `throttle:` middleware on the member API group and the payment webhook. A `/health` probe returning JSON status for app + central DB + cache (200/503). A tenant-aware cache helper (`TenantCache`, keys namespaced per mutual) wired into the data-quality dashboard. Operator tooling: `TenantHealthService` + a `mxconnect:tenants-health` command and a network **tenant-health dashboard** (reachability, member & active-subscription counts, last activity per tenant DB) with a new network nav entry. Tests: tenant cache scoping/remember/forget, health endpoint shape, rate-limiter registration, reminder-is-queued.
- **Module 16 — Security & performance hardening + backup automation** ✔ (Phase 3, part 2). **Security headers** (`SecurityHeaders`, global): X-Content-Type-Options, X-Frame-Options DENY, Referrer-Policy, Permissions-Policy, a moderate configurable CSP (allows the Tailwind CDN + inline config the views use), and HSTS on secure requests; production forces HTTPS URLs. **Model strictness**: silently-discarded-attribute and missing-attribute guards on outside production, with aggressive lazy-loading prevention available as an opt-in N+1 audit (`MXCONNECT_STRICT_LAZY`). **Performance indexes** on the hot actuarial/treasury/arrears paths (prestations status+care_date, treasury moved_on and direction+mode, contribution_payments paid_on). **Backup automation** (`BackupService` + `mxconnect:backup`, scheduled 02:30): dumps central + every tenant database and closes the loop with the DR log by recording each run as a DrTest (duration as RTO actual, outcome derived from per-DB success); the dumper is injectable so the mechanism is unit-tested without a live mysqldump. Tests: security headers present, backup success/failure outcomes recorded to the DR log.
- **Module 17 — Observability & operations finalization** ✔ (Phase 3, part 3). Structured JSON logging (`config/logging.php`, `structured` channel) with a `RequestContext` middleware that assigns/propagates a per-request `X-Request-Id` (honouring an upstream value), attaches tenant/user/ip to the shared log context, and echoes the id on the response — so every log line is correlatable. Slow queries beyond a configurable threshold are logged as `slow_query` warnings with that context. A production ops runbook (`deploy/PHASE3_OPS.md`) plus a queue-worker supervisor unit (`deploy/supervisor-worker.conf`) covering Redis, workers, scheduler, backups/DR, observability, security and the N+1 audit switch. Tests: request-id is set and an inbound id is honoured.
- **Module 18 — Cross-mutual consolidation** ✔ (Phase 4). `ConsolidationService` iterates every approved mutual, gathers membership and financial figures inside each tenant DB (reusing `ActuarialService`), and aggregates them: head-counts summed network-wide, money aggregated **per currency** (mutuals may operate in different currencies, so raw minor units are never mixed across them) with a network loss ratio recomputed on the pooled figures. Operator dashboard with year selector, on-demand cache refresh (the snapshot touches all tenant DBs so it's cached 10 min), network KPI cards, per-currency financial totals, and a per-mutual breakdown that dims unreachable tenants. The `aggregate()` step is pure and unit-tested (global counts, per-currency pooling with no cross-currency mixing, network loss ratio, null-premium guard).
- **Module 19 — Public offer comparator** ✔ (Phase 4). Each mutual publishes a denormalised public offer summary into a central `mutual_offers` table (`OfferPublicationService` + `mxconnect:sync-offers`, scheduled): it reads each mutual's CURRENT guarantee versions inside the tenant DB and summarises them — guarantee count, coverage min/max, membership-fee range, and a monthly-equivalent contribution range (base contributions normalised across monthly/quarterly/annual periodicities so mutuals are comparable) plus guarantee highlights. The public `/comparateur` page (unauthenticated, central, country filter) reads only `mutual_offers` — fast, no tenant access — and renders a side-by-side comparison table plus offer cards; cross-linked with the public directory. The summarisation is pure and unit-tested (monthly normalisation, min/max/coverage/highlights, empty-offer guard).
- **Module 20 — SaaS billing** ✔ (Phase 4). How the platform charges each mutual. Central `billing_plans` (price, currency, monthly/annual interval, optional member ceiling, features), one `billing_subscriptions` row per mutual, and `billing_invoices` per period. `BillingService` places a mutual on a plan, issues one invoice per period (idempotent — never double-invoices the same `period_start`), advances the period each cycle, sweeps overdue invoices (and reflects `past_due` on the subscription), and clears `past_due` when the outstanding invoice is paid. Invoice numbers are `INV-YYYYMM-XXXXX` (collision-checked). Command `mxconnect:billing-run` (scheduled 04:00) runs the cycle; a `BillingPlanSeeder` ships three starter tiers. Super-admin screens: plan catalogue (create/toggle) and a billing overview (assign a plan, generate an invoice, mark paid). Tests: invoice amount/number, per-period idempotence, period advancement + overdue detection, and past_due→active on payment. Central FKs to the string tenant id were declared as `string('mutual_id')` to match the UUID primary key.
- **Module 21 — Member social feed (real-time, Reverb)** ✔ (Phase 5). The members of a mutual get a live feed. Tenant tables `feed_posts` / `feed_comments` / `feed_post_likes` (one like per member per post, counters kept on the post). `FeedService` publishes posts, toggles likes idempotently, adds comments, and lists the feed newest-first with the caller's like state — and broadcasts each new post via the `FeedPostCreated` event on the **private, tenant-scoped** channel `private-tenant.{tenantId}.feed`. Broadcasting authorisation runs through a tenant-aware, Sanctum-guarded endpoint (`POST /api/member/broadcasting/auth`) and `routes/channels.php` only lets an account linked to a member of that exact mutual subscribe — a member of one mutual can never see another's feed. Member API: list/post/like/comment. The PWA feed page (`espace-membre/fil`) loads Echo + pusher-js from CDN, connects with the member's token, shows a connecting/live/offline pill, and prepends posts in real time. Reverb is configured (`config/reverb.php`, `config/broadcasting.php`), wired into `bootstrap/app.php`, added to `composer.json`, with deploy notes (`deploy/PHASE5_REALTIME.md`) and a Supervisor entry. Tests: post creation broadcasts, like toggle idempotence + distinct-member counting, comment counter, newest-first ordering with caller like-state, and the exact private channel name/payload.

**Phases 1–5 are complete.** MX-CONNECT now spans the full per-mutual product (membership → contributions → claims → treasury → SYSCOHADA accounting → member PWA), governance & actuarial oversight, the production operations backbone (queues, security, backups/DR, observability), the network layer (cross-mutual consolidation, public comparator, SaaS billing), and a real-time member social feed. Reverb was introduced only here, in Phase 5, as planned.

## How to run it (first real execution)
Everything so far is validated **structurally** (syntax, brace/bracket balance, Blade directive balance, route-name resolution, i18n key coverage in FR + EN, independent re-computation of service math). It has **not** been executed here — no `composer`, no `php -l`, no test run is possible in this environment. On your machine:
1. `composer install`
2. Configure `.env` (central + tenant DB, Redis or set queue/cache/session to `database`, and the `REVERB_*` + `BROADCAST_CONNECTION=reverb` block for the feed).
3. `php artisan migrate --seed` (central), then provision a mutual to get a tenant DB.
4. `php artisan test` — the ~31 Pest feature files (tagged `tenant` / `central`) run for the first time here.
5. For the feed: `php artisan reverb:start` (or via Supervisor) and open `espace-membre/fil` on a tenant domain.

## Déploiement (www.mx-connect.com)
Guide pas à pas VPS LWS : `deploy/DEPLOIEMENT_LWS.md`. Modèle d'environnement de production : `deploy/.env.production.example` (inclut le bloc Reverb pour le fil temps réel). Nginx sert le domaine central + le wildcard tenant et relaie les WebSockets Reverb ; Supervisor lance les workers de file et le serveur Reverb ; le certificat TLS wildcard `*.mx-connect.com` s'obtient via un challenge DNS-01.
