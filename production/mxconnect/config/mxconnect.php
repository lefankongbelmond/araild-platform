<?php

/**
 * MX-CONNECT platform configuration.
 *
 * Everything country-specific (countries, currencies, payment providers, locales)
 * lives in DATABASE TABLES, not here. This file holds only platform-wide,
 * non-country conventions and configurable limits.
 */
return [

    // Opt-in N+1 audit: throw on lazy loads in dev/CI when true.
    'strict_lazy_loading' => env('MXCONNECT_STRICT_LAZY', false),
    // Slow-query warning threshold (ms) for the structured log.
    'slow_query_ms' => (int) env('MXCONNECT_SLOW_QUERY_MS', 500),

    // Domaine racine des sous-domaines mutuelles : <slug>.<tenant_base_domain>
    'tenant_base_domain' => env('TENANT_BASE_DOMAIN', 'mx-connect.com'),

    // Mode de création des bases mutuelles :
    //   auto   : MX-CONNECT crée la base (VPS, utilisateur MySQL avec droit CREATE)
    //   manual : la base est créée à l'avance dans le panneau d'hébergement
    //            (hébergement mutualisé) ; on la saisit à la création de la mutuelle.
    'tenant_db_mode' => env('TENANT_DB_MODE', 'auto'),

    // Racine web publique si elle n'est pas <appli>/public (mutualisé : htdocs/).
    'public_path' => env('MXCONNECT_PUBLIC_PATH'),

    // Adresse qui reçoit les demandes de démonstration (page d'accueil).
    'contact_email' => env('MXCONNECT_CONTACT_EMAIL'),

    // Version des fichiers CSS/JS (force le rechargement après une mise à jour).
    'asset_version' => '2026.11.01',

    // Réseau social : médias téléversés
    'social' => [
        'max_upload_kb'   => (int) env('MXCONNECT_SOCIAL_MAX_KB', 6144),   // 6 Mo par image
        'document_max_kb' => (int) env('MXCONNECT_DOCUMENT_MAX_KB', 10240), // 10 Mo par document PDF public
        'max_images'      => (int) env('MXCONNECT_SOCIAL_MAX_IMAGES', 6),
        'image_max_side'  => 1600,
        'thumb_max_side'  => 640,
        'jpeg_quality'    => 82,   // repli si le serveur ne sait pas écrire le WebP
        'webp_quality'    => 80,   // toutes les images publiées sont converties en WebP
        'per_page'        => 12,
        'adhesion_doc_kb' => (int) env('MXCONNECT_ADHESION_DOC_KB', 5120),     // 5 Mo par pièce
        'adhesion_max_docs' => 4,
        // Vidéos et réels (envoyés par morceaux : indépendants de upload_max_filesize)
        'video_max_mb'      => (int) env('MXCONNECT_VIDEO_MAX_MB', 100),
        'video_chunk_kb'    => 1536,
        'reel_max_seconds'  => 90,
        'video_max_seconds' => (int) env('MXCONNECT_VIDEO_MAX_SECONDS', 900),
        // Pages créées par les membres
        'max_pages_per_account' => (int) env('MXCONNECT_MAX_PAGES', 5),
        // Aperçu des liens publiés (lecture du titre et de l'image du site)
        'link_preview'      => (bool) env('MXCONNECT_LINK_PREVIEW', true),
        'link_preview_test_hosts' => array_filter(explode(',', (string) env('MXCONNECT_LINK_PREVIEW_TEST_HOSTS', ''))),
    ],

    // Nombre de sauvegardes locales conservées (storage/app/backups).
    'backup_keep' => (int) env('MXCONNECT_BACKUP_KEEP', 7),

    // Jeton de la page d'installation web (/_installation). Vide = page désactivée.
    'install_token' => env('INSTALL_TOKEN'),

    // Jeton de l'URL de tâches planifiées (/_cron). Vide = URL désactivée.
    'cron_token' => env('CRON_TOKEN'),

    // 0 = unlimited (subject to super-admin policy). Otherwise a hard cap on active mutuals.
    'max_mutuals' => (int) env('MXCONNECT_MAX_MUTUALS', 0),

    // Roles at the network level (central DB).
    'network_roles' => ['super_admin', 'security_admin', 'moderator'],

    // Roles inside each mutual (tenant DB).
    'mutual_roles' => [
        'mutual_admin',
        'enrollment_agent',
        'controller_validator',
        'benefits_manager',
        'treasurer_accountant',
        'community_manager',
        'provider',
        'auditor',          // Auditeur / contrôle interne : lecture seule, sans données de santé (2026-10-31)
    ],

    // Maker-checker: role pairs that must NEVER be held by the same user.
    'incompatible_role_pairs' => [
        ['enrollment_agent', 'controller_validator'],
        ['benefits_manager', 'controller_validator'],
        ['controller_validator', 'treasurer_accountant'],   // qui valide ne paie pas (2026-10-31)
        ['benefits_manager', 'treasurer_accountant'],       // qui saisit les soins ne paie pas (2026-10-31)
    ],

    // Rôles qui ne se cumulent avec AUCUN autre : l'auditeur reste indépendant,
    // le prestataire est une personne extérieure à la mutuelle (2026-10-31).
    'exclusive_roles' => ['auditor', 'provider'],

    // Roles for which MFA is mandatory (enforced by EnsureMfaEnabled middleware).
    // Depuis le 2026-10-31 : TOUT le personnel des mutuelles et les prestataires.
    'mfa_required_roles' => [
        'super_admin', 'security_admin',
        'mutual_admin', 'treasurer_accountant', 'controller_validator', 'benefits_manager',
        'enrollment_agent', 'community_manager', 'provider', 'auditor',
    ],

    // Reminder cadence (days relative to due date) for contribution reminders.
    'contribution_reminder_offsets' => [-3, 0, 7],

    // Payment transaction auto-expiry (minutes) for 'initiated' transactions with no webhook.
    'payment_initiation_ttl_minutes' => 30,

    // --- SYSCOHADA accounting (Module 10) ---
    'accounting' => [
        // Minimal chart of accounts seeded per tenant (number => [label, class]).
        'chart' => [
            '521'  => ['Banque', 5],
            '571'  => ['Caisse', 5],
            '585'  => ['Mobile Money (virements internes)', 5],
            '7011' => ['Droits d’adhésion', 7],
            '7561' => ['Cotisations des membres', 7],
            '7580' => ['Autres produits', 7],
            '6011' => ['Prestations remboursées aux membres', 6],
            '6041' => ['Factures prestataires (tiers payant)', 6],
            '6580' => ['Autres charges', 6],
        ],
        // Treasury account per payment mode.
        'mode_account' => [
            'cash'         => '571',
            'bank'         => '521',
            'mobile_money' => '585',
        ],
        // Income (inflow) / expense (outflow) account per movement source.
        'source_income'  => [
            'contribution'   => '7561',
            'membership_fee' => '7011',
            'other'          => '7580',
        ],
        'source_expense' => [
            'reimbursement'    => '6011',
            'provider_invoice' => '6041',
            'other'            => '6580',
        ],
    ],


    // --- Phase 2: actuarial / solvency (Module 12) ---
    'actuarial' => [
        // Months of average claims a mutual should hold as reserve (prudential).
        'reserve_months' => 3,
        // Loss-ratio (claims / premiums) thresholds for the traffic light.
        'loss_ratio' => [
            'healthy' => 0.75,   // < 0.75  -> green
            'watch'   => 1.00,   // 0.75..1 -> amber ; > 1 -> red (paying out more than collected)
        ],
        // Solvency ratio (reserves / required reserve) minimum before it's a concern.
        'solvency_floor' => 1.0,
    ],

    // Data-quality checks (Module 12) — weight per check, score is the weighted pass rate.
    'data_quality' => [
        'weights' => [
            'member_phone'        => 2,
            'member_id_document'  => 1,
            'member_antenna'      => 1,
            'member_birth_date'   => 2,
            'active_has_subscription' => 3,
            'subscription_has_schedule' => 2,
        ],
    ],


    // --- Phase 2 / Module 13: governance registers ---
    'governance' => [
        // Risk severity bands by score (likelihood 1-5 × impact 1-5 => 1..25).
        'risk_bands' => [
            'low'      => 4,    // score <= 4
            'medium'   => 9,    // 5..9
            'high'     => 15,   // 10..15
            // > 15 => critical
        ],
        // Complaint resolution SLA in days (used to compute the due date).
        'complaint_sla_days' => 15,
    ],


    // --- Phase 2 / Module 14: disaster-recovery drills ---
    'dr' => [
        'default_rto_minutes' => 240,   // recovery time objective (4h)
        'default_rpo_minutes' => 60,    // recovery point objective (1h)
    ],


    // --- Phase 3 / Module 16: security headers ---
    'security' => [
        // Moderate CSP: allows the Tailwind CDN + cdnjs and the inline config the
        // Blade views use. Tighten (drop 'unsafe-inline') once assets are bundled.
        // blob: = aperçus locaux des photos et vidéos choisies avant l'envoi (jamais de contenu distant).
        'csp' => "default-src 'self'; img-src 'self' data: blob: https:; media-src 'self' blob:; "
            . "script-src 'self' 'unsafe-inline' https://cdn.tailwindcss.com https://cdnjs.cloudflare.com; "
            . "style-src 'self' 'unsafe-inline' https://cdn.tailwindcss.com; "
            . "connect-src 'self'; font-src 'self' data:; frame-ancestors 'none'; base-uri 'self'",
    ],

];
