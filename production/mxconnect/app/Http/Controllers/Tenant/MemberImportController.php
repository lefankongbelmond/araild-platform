<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Services\MemberImportService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Member migration import (CDC §27). Restricted to enrollment/admin roles.
 * The operator downloads a template, fills it, uploads it, and gets a per-row report.
 */
class MemberImportController extends Controller
{
    public function form(Request $request)
    {
        abort_unless($request->user()->hasAnyRole(['enrollment_agent', 'mutual_admin']), 403);
        return view('tenant.members.import', ['report' => session('import_report')]);
    }

    public function import(Request $request, MemberImportService $service)
    {
        abort_unless($request->user()->hasAnyRole(['enrollment_agent', 'mutual_admin']), 403);

        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
        ]);

        $report = $service->import($request->file('file')->getRealPath());

        return redirect()->route('members.import.form')->with('import_report', $report);
    }

    /** Downloadable CSV template with the expected header. */
    public function template(): StreamedResponse
    {
        $header = implode(',', MemberImportService::COLUMNS);
        $example = 'Awa,Njoya,1990-05-12,F,690000000,,Yaoundé';

        return response()->streamDownload(function () use ($header, $example) {
            echo $header . "\n" . $example . "\n";
        }, 'modele_import_membres.csv', ['Content-Type' => 'text/csv']);
    }
}
