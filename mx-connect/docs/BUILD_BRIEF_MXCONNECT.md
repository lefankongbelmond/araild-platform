# MX-CONNECT — BUILD BRIEF (optimised master prompt)

> This is the refined, execution-ready version of the master prompt. It keeps your intent, removes ambiguity, adds explicit acceptance criteria and a definition of done, and — importantly — corrects one architectural gap: **nothing country-specific may be hard-coded**, so countries, currencies, payment providers and locales become **configuration tables**, not enums.

---

## 1. Role of the build agent
Senior Software Architect + Senior PHP/Laravel Developer + MySQL Database Architect + DevSecOps Engineer + Community Health Mutual (mutuelle de santé) domain expert. Deliver a **production-ready**, LWS-VPS-deployable platform — not a prototype, static demo, fake dashboard, or throwaway skeleton.

## 2. Product principle (non-negotiable)
MX-CONNECT is a **multi-country, multi-mutual, multi-tenant SaaS**. Cameroon is only the first deployment country; the 4 pilot mutuals are only seed data. **No country, currency, provider, language, mutual, contribution rule or benefit package may be hard-coded.** The Super Admin creates unlimited mutuals from the admin UI, subject to configurable platform limits.

**Architectural consequence (the correction):** replace hard-coded enums (`mtn_momo|orange_money`, `fr|en`, free-text region) with reference tables:
- `countries` (ISO code, name, default currency, default locale, active)
- `currencies` (ISO 4217 code, symbol, minor-unit exponent — FCFA = 0, USD = 2)
- `payment_providers` (code, display name, driver class, per-provider JSON config schema, per-country availability)
- `locales` (code, name, active) — drives i18n; French is the default *value*, not a hard-coded assumption

Each mutual references these by foreign key. Adding a country/provider/currency is **data**, never code.

## 3. Mandatory stack
- **Backend: 100% PHP, Laravel (current stable), PHP 8.3+.** No Node.js backend. (Reverb, a PHP WebSocket server, is the only realtime component — phase 5 only.)
- **Frontend: server-rendered** — Blade + Tailwind + Alpine (only where needed) + Livewire (only where it clearly improves UX). No separate React/Vue/Next app.
- **DB: MySQL 8+.** Migrations, foreign keys, indexes, unique constraints, transactions, soft deletes where appropriate, normalized relational structure. JSON only for provider config, extensible settings, API payloads, metadata — never as a substitute for a proper relational table.
- **Multi-tenancy: `stancl/tenancy`, database-per-tenant.** One central DB + one DB per mutual.
- **Supporting packages:** `spatie/laravel-permission` (RBAC), `laravel/fortify` + `laravel/sanctum` (auth + MFA + API tokens), `owen-it/laravel-auditing` (audit trail), `spatie/laravel-backup` (encrypted off-server backups).

## 4. Production target
LWS VPS: Ubuntu/Debian, Nginx, PHP-FPM, MySQL 8+, Redis, Supervisor, Cron, HTTPS, Git, Composer. Kept as a conventional PHP/Laravel deploy.

## 5. Functional scope (by module, tied to the phased schema)
Phase 1 (pilot core): tenancy + auth/MFA + RBAC + mutual onboarding + configuration (countries/currencies/providers/locales) + parameters (antennas, guarantees with versioning, act types, medicines, ceilings) + members (with duplicate detection) + subscriptions (maker-checker) + contributions (schedule, arrears, reminders, receipts) + **direct Mobile Money payment + reconciliation ledger** + digital QR card + rights verification + care claims/invoices/reimbursements + anti-fraud alerts + treasury + SYSCOHADA accounting + member PWA + minimal public directory + migration imports + audit + i18n.
Phase 2: actuarial & solvency dashboards, structured risk register, incidents, complaints, data-quality score, real DR test.
Phase 3: scale 4→10→25→50 mutuals (provisioning automation, performance).
Phase 4: enriched directory, comparator, network consolidation (aggregates only), SaaS billing.
Phase 5: moderated social feed (Reverb realtime).

## 6. Cross-cutting rules (must be enforced in code, not just documented)
- **Isolation:** database-per-tenant; a member of mutual A physically cannot exist in mutual B's DB. Automated isolation test = GO/NO-GO, 0 leaks.
- **Payment = facilitation only:** money flows Adhérent → aggregator → **mutual's own merchant account**; MX-CONNECT only initiates, identifies, is notified (idempotent signed webhook), reconciles, traces. **Never holds funds.** Ledger is append-only; corrections are counter-entries, never edits/deletes.
- **Separation of duties (maker-checker):** the user who captured an operation cannot validate it (enforced by Policy).
- **Traceability:** soft deletes only on business data; append-only audit log with actor, timestamp, before/after.
- **Data minimisation:** RBAC + per-resource Policies; QR card exposes no medical data.
- **Security by design from the skeleton:** HTTPS/HSTS, encrypted casts on sensitive columns, forced MFA for sensitive roles, rate limiting, CSRF, escaped output.

## 7. Definition of done (per module)
Migrations + FK/indexes/constraints · Eloquent models with relationships, casts, enums · form-request validation · policies (incl. maker-checker where relevant) · controller/Livewire + Blade views · feature tests (happy path + at least one isolation/authorization test) · audited · i18n keys (no hard-coded UI strings) · seed/factory for demo data.

## 8. Acceptance (pilot)
The operational cycle runs end to end for 4 seeded mutuals with strict isolation; automated isolation test passes 0-leak; a full payment round-trips (debit → mutual credit → webhook → reconciliation); backup restore is actually tested; PV signed by the 4 pilots. No production go-live until every GO/NO-GO proof exists.

## 9. Deliverables
Source (versioned) · central + tenant migrations · seeders/factories · services (provisioning, ledger, rights, duplicate detection, anti-fraud) · policies · Blade UI · tests (incl. isolation) · API docs · install + deploy docs (Nginx/PHP-FPM/MySQL/Redis/Supervisor/Cron/SSL) · backup + DR procedures · security policy · role matrix · indicator dictionary.

## 10. Working method
Build **phase by phase, module by module**, each module meeting the Definition of Done before the next. Never ship a half-wired module to appear complete.
