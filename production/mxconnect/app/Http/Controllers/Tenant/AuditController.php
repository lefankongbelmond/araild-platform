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
        abort_unless($request->user()->hasAnyRole(['mutual_admin', 'controller_validator', 'security_admin']), 403);

        $type = $request->get('type');

        $query = DB::table('audits')->orderByDesc('created_at');
        if ($type) {
            $query->where('auditable_type', 'like', "%{$type}%");
        }

        $audits = $query->paginate(40)->withQueryString();

        // Distinct model types present, for the filter.
        $types = DB::table('audits')->select('auditable_type')->distinct()->pluck('auditable_type')
            ->map(fn ($t) => class_basename($t))->unique()->values();

        return view('tenant.audit.index', compact('audits', 'types', 'type'));
    }
}
