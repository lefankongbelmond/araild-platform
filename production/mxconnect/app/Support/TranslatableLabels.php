<?php

namespace App\Support;

/**
 * Libellés affichés depuis des listes PHP (constantes) et traduits à l'affichage par __()
 * (multilingue M2, 2026-11-28). Ce registre permet au test de complétude de vérifier que chacun
 * existe dans lang/fr.json et dans chaque langue livrée. Toute nouvelle liste affichée et
 * traduite doit être ajoutée ici.
 */
class TranslatableLabels
{
    /** Listes du réseau social et du portail adhérent (M2). */
    public const LISTS = [
        [\App\Support\JsTranslations::class, 'SOCIAL'],   // textes du JavaScript du réseau (M2b)
        // Back-office des mutuelles (M3a, 2026-11-30)
        [\App\Support\TenantNav::class, 'ROLE_LABELS'], [\App\Support\TenantNav::class, 'ROLE_DESCRIPTIONS'],
        [\App\Support\CatalogLabels::class, 'STATUSES'], [\App\Models\Tenant\ProviderConvention::class, 'TYPES'],
        [\App\Models\Tenant\CoverageRule::class, 'BENEFICIARY_CATEGORIES'], [\App\Models\Tenant\Preauthorization::class, 'REVIEWS'],
        [\App\Models\Tenant\CoverageLimit::class, 'SCOPES'], [\App\Models\Tenant\CoverageLimit::class, 'HOLDERS'],
        [\App\Models\Tenant\ServiceCategory::class, 'FAMILIES'], [\App\Models\Tenant\Ceiling::class, 'PERIODS'],
        [\App\Models\Tenant\CoverageException::class, 'STATUSES'], [\App\Models\Tenant\CoverageException::class, 'KINDS'],
        [\App\Support\SocialDomains::class, 'ALL'],
        [\App\Models\Social\Post::class, 'TYPES'], [\App\Models\Social\Post::class, 'MEMBER_TYPES'],
        [\App\Models\Social\Post::class, 'ACTIVITY_CATEGORIES'], [\App\Models\Social\Post::class, 'REACTIONS'],
        [\App\Models\Social\AdhesionRequest::class, 'STATUSES'], [\App\Models\Social\AdhesionRequest::class, 'RELATIONS'],
        [\App\Models\Social\AdhesionRequest::class, 'DOC_KINDS'],
        [\App\Models\Social\BetaFeedback::class, 'CATEGORIES'], [\App\Models\Social\ContactRequest::class, 'SUBJECTS'],
        [\App\Models\Social\LegalDocument::class, 'TYPES'], [\App\Models\Social\LegalDocument::class, 'ACTIONS'],
        [\App\Models\Social\LegalAcceptance::class, 'ACTIONS'],
        [\App\Models\Social\MutualProfile::class, 'CATEGORIES'],
        [\App\Models\Social\Opportunity::class, 'CATEGORIES'], [\App\Models\Social\Opportunity::class, 'CONTRACTS'],
        [\App\Models\Social\Opportunity::class, 'WORKPLACES'], [\App\Models\Social\Opportunity::class, 'AVAILABILITIES'],
        [\App\Models\Social\Opportunity::class, 'APPLY_MODES'],
        [\App\Models\Social\PageAdmin::class, 'ROLES'], [\App\Models\Social\Poll::class, 'DURATIONS'],
        [\App\Models\Social\Project::class, 'PHASES'], [\App\Models\Social\Project::class, 'STATUSES'],
        [\App\Models\Social\Report::class, 'REASONS'],
        [\App\Models\Social\SocialRequest::class, 'KINDS'], [\App\Models\Social\SocialRequest::class, 'ENGAGEMENT_TYPES'],
        [\App\Models\Social\SocialRequest::class, 'SUPPORT_TYPES'], [\App\Models\Social\SocialRequest::class, 'STATUSES'],
        [\App\Models\Social\Sponsorship::class, 'STATUSES'], [\App\Models\Social\Sponsorship::class, 'CTA'],
        [\App\Http\Controllers\Social\AccountController::class, 'INTERESTS'],
        [\App\Http\Controllers\Social\DiscoverController::class, 'TABS'],
        [\App\Models\Tenant\Complaint::class, 'CATEGORIES'], [\App\Models\Tenant\Complaint::class, 'STATUS_LABELS'],
        [\App\Models\Tenant\MemberChangeRequest::class, 'KINDS'], [\App\Models\Tenant\MemberChangeRequest::class, 'STATUSES'],
        [\App\Services\Tenant\MemberSelfService::class, 'PRESTATION_STATUS'], [\App\Services\Tenant\MemberSelfService::class, 'SCHEDULE_STATUS'],
    ];

    /** Tous les textes (chaînes) de ces listes, à plat et sans doublon. */
    public static function all(): array
    {
        $out = [];
        foreach (self::LISTS as [$class, $const]) {
            $name = $class . '::' . $const;
            if (! defined($name)) {
                continue;
            }
            $values = constant($name);
            array_walk_recursive($values, function ($s) use (&$out) {
                if (is_string($s) && preg_match('/\p{L}/u', $s) && ! in_array($s, ['accept', 'acknowledge'], true)) {
                    $out[$s] = true;
                }
            });
        }

        // Libellés portés par des clés de tableau ou des méthodes (menu, cartes des paramètres, matrice des droits).
        foreach (self::extra() as $s) {
            $out[$s] = true;
        }

        return array_keys($out);
    }

    /** Menu du back-office, cartes « Paramètres », rubriques de la page « Rôles et droits ». */
    private static function extra(): array
    {
        $out = [];
        foreach (TenantNav::groups() as $group => $items) {
            $out[] = $group;
            foreach ($items as [$label]) {
                if (! str_starts_with($label, 'mxconnect.')) {
                    $out[] = $label;
                }
            }
        }
        foreach (TenantSettings::SECTIONS as $section => $cards) {
            $out[] = $section;
            foreach ($cards as [$title, $desc]) {
                $out[] = $title;
                $out[] = $desc;
            }
        }
        foreach (TenantAccess::SUMMARY as $section => $actions) {
            $out[] = $section;
            foreach ($actions as $label) {
                $out[] = $label;
            }
        }

        return $out;
    }
}
