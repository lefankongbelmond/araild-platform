<?php

namespace App\Services;

use App\Models\Tenant\Member;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * Bulk member import for mutuals migrating from paper or a legacy tool (CDC §27).
 *
 * Parses a CSV, validates each row, and runs duplicate detection before creating.
 * Policy:
 *   - a STRONG duplicate (same phone / ID document) is skipped with an error;
 *   - a WEAK duplicate (same name + birth date) is still imported but flagged,
 *     since a migration legitimately contains many look-alike records;
 *   - invalid rows are skipped with the validation messages.
 * Nothing is committed for a row that fails — the import is row-independent and a
 * full per-row report is returned for the operator to review.
 */
class MemberImportService
{
    /** Expected header, in order. Extra columns are ignored. */
    public const COLUMNS = ['first_name', 'last_name', 'birth_date', 'sex', 'phone', 'id_document', 'address'];

    public function __construct(private DuplicateDetectionService $duplicates) {}

    /**
     * @return array{created:int, skipped:int, rows:array<int,array{line:int,status:string,message:string,code:?string}>}
     */
    public function import(string $path): array
    {
        $handle = fopen($path, 'r');
        if ($handle === false) {
            throw new \RuntimeException('Unable to open the uploaded file.');
        }

        $created = 0; $skipped = 0; $rows = [];
        $line = 0; $header = null;

        while (($raw = fgetcsv($handle, 0, ',')) !== false) {
            $line++;

            // First non-empty row is the header.
            if ($header === null) {
                $header = array_map(fn ($h) => Str::of($h)->trim()->lower()->replace(' ', '_')->value(), $raw);
                continue;
            }
            if (count(array_filter($raw, fn ($v) => trim((string) $v) !== '')) === 0) {
                continue; // blank line
            }

            $row = $this->associate($header, $raw);
            $result = $this->importRow($row, $line);

            $rows[] = $result;
            $result['status'] === 'created' ? $created++ : $skipped++;
        }

        fclose($handle);

        return ['created' => $created, 'skipped' => $skipped, 'rows' => $rows];
    }

    /** @return array{line:int,status:string,message:string,code:?string} */
    private function importRow(array $row, int $line): array
    {
        $validator = Validator::make($row, [
            'first_name'  => ['required', 'string', 'max:120'],
            'last_name'   => ['required', 'string', 'max:120'],
            'birth_date'  => ['required', 'date', 'before:today'],
            'sex'         => ['required', 'in:M,F'],
            'phone'       => ['nullable', 'string', 'max:20'],
            'id_document' => ['nullable', 'string', 'max:60'],
            'address'     => ['nullable', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            return $this->row($line, 'invalid', $validator->errors()->first());
        }

        $data = $validator->validated();

        $check = $this->duplicates->check(
            $data['first_name'], $data['last_name'], $data['birth_date'],
            $data['phone'] ?? null, $data['id_document'] ?? null,
        );

        if ($check['block']) {
            return $this->row($line, 'duplicate', __('mxconnect.import.strong_dup'));
        }

        $member = Member::create([
            'member_code' => $this->generateCode(),
            'first_name'  => $data['first_name'],
            'last_name'   => $data['last_name'],
            'birth_date'  => Carbon::parse($data['birth_date'])->toDateString(),
            'sex'         => $data['sex'],
            'phone'       => $data['phone'] ?? null,
            'id_document' => $data['id_document'] ?? null,
            'address'     => $data['address'] ?? null,
            'status'      => 'active',
            'joined_at'   => now()->toDateString(),
        ]);

        $status = $check['warn'] ? 'created_warn' : 'created';
        $message = $check['warn'] ? __('mxconnect.import.weak_dup') : __('mxconnect.import.ok');

        return $this->row($line, $status, $message, $member->member_code);
    }

    private function associate(array $header, array $raw): array
    {
        $row = [];
        foreach (self::COLUMNS as $col) {
            $idx = array_search($col, $header, true);
            $value = $idx !== false && isset($raw[$idx]) ? trim((string) $raw[$idx]) : null;
            $row[$col] = $value === '' ? null : $value;
        }
        // Normalise sex to M/F.
        if ($row['sex'] !== null) {
            $row['sex'] = strtoupper(substr($row['sex'], 0, 1));
        }
        return $row;
    }

    private function generateCode(): string
    {
        do {
            $code = 'ADH-' . now()->format('y') . '-' . strtoupper(Str::random(5));
        } while (Member::where('member_code', $code)->exists());

        return $code;
    }

    private function row(int $line, string $status, string $message, ?string $code = null): array
    {
        // 'created_warn' counts as created for the caller's created/skipped tally.
        $normalised = $status === 'created_warn' ? 'created' : $status;

        return ['line' => $line, 'status' => $normalised, 'display' => $status, 'message' => $message, 'code' => $code];
    }
}
