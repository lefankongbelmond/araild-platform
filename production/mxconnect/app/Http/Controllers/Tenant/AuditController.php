<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Audit trail viewer (CDC §25). Reads the owen-it `audits` table in the tenant DB.
 * Restricted to admin/controller/security roles — the audit log is sensitive.
 */
class AuditController extends Controller
{
    public function index(Request $request)
    {
        \App\Support\TenantAccess::authorize();

        $type = $request->get('type');

        $query = DB::table('audits')->orderByDesc('created_at');
        if ($type) {
            $query->where('auditable_type', 'like', "%{$type}%");
        }

        $audits = $query->paginate(40)->withQueryString();

        // Distinct model types present, for the filter.
        $types = DB::table('audits')->select('auditable_type')->distinct()->pluck('auditable_type')
            ->map(fn ($t) => class_basename($t))->unique()->values();

        // Accès aux données de santé et opérations sensibles tracées dans audit_logs (2026-11-22).
        $access = collect();
        if (\Illuminate\Support\Facades\Schema::hasTable('audit_logs')) {
            $access = DB::table('audit_logs')->whereIn('action', [\App\Support\HealthAccessLog::ACTION, 'test_data_reset', 'identity_updated'])->orderByDesc('occurred_at')->limit(50)->get();
            $names = \App\Models\Tenant\User::whereIn('id', $access->pluck('user_id')->filter()->unique())->get()
                ->mapWithKeys(fn ($u) => [$u->id => trim($u->first_name . ' ' . $u->last_name)]);
            $access = $access->map(fn ($a) => (object) ((array) $a + ['who' => $names[$a->user_id] ?? null]));
        }

        return view('tenant.audit.index', compact('audits', 'types', 'type', 'access'));
    }
}
