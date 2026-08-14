<?php

namespace App\Services;

use App\Models\Tenant\Member;
use Illuminate\Support\Str;

/**
 * Anti-duplicate on member creation/edit (CDC §10.2), inside the current tenant DB.
 *
 * STRONG match (block): same phone OR same national id document.
 *   - phone is a plain column -> matched in SQL.
 *   - id_document is ENCRYPTED (per-record IV) -> cannot be matched by a SQL WHERE,
 *     so candidates are decrypted and compared in PHP.
 * WEAK match (warn, agent confirms): same birth date AND a normalised name match.
 *
 * Criteria are configurable per mutual; the defaults below cover the common case.
 */
class DuplicateDetectionService
{
    /**
     * @return array{block:bool, warn:bool, strong:array<int>, weak:array<int>}
     */
    public function check(
        string $firstName,
        string $lastName,
        string $birthDate,
        ?string $phone,
        ?string $idDocument,
        ?int $ignoreId = null
    ): array {
        $strong = [];

        // --- Strong: phone (SQL) ---
        if ($phone = $this->normalisePhone($phone)) {
            $strong = array_merge($strong, Member::query()
                ->where('phone', $phone)
                ->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))
                ->pluck('id')->all());
        }

        // --- Strong: id document (encrypted -> compare in PHP) ---
        if ($idDocument = $this->normaliseId($idDocument)) {
            $candidates = Member::query()
                ->whereNotNull('id_document')
                ->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))
                ->get(['id', 'id_document']);
            foreach ($candidates as $c) {
                if ($this->normaliseId($c->id_document) === $idDocument) {
                    $strong[] = $c->id;
                }
            }
        }

        $strong = array_values(array_unique($strong));
        if ($strong) {
            return ['block' => true, 'warn' => false, 'strong' => $strong, 'weak' => []];
        }

        // --- Weak: same birth date + normalised name ---
        $weak = [];
        $needle = $this->normaliseName($lastName . ' ' . $firstName);
        $sameDob = Member::query()
            ->whereDate('birth_date', $birthDate)
            ->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))
            ->get(['id', 'first_name', 'last_name']);
        foreach ($sameDob as $m) {
            if ($this->normaliseName($m->last_name . ' ' . $m->first_name) === $needle) {
                $weak[] = $m->id;
            }
        }

        return ['block' => false, 'warn' => (bool) $weak, 'strong' => [], 'weak' => $weak];
    }

    private function normalisePhone(?string $v): ?string
    {
        if (! $v) return null;
        $v = preg_replace('/[^\d+]/', '', $v);
        return $v ?: null;
    }

    private function normaliseId(?string $v): ?string
    {
        if (! $v) return null;
        $v = strtoupper(preg_replace('/\s+/', '', $v));
        return $v ?: null;
    }

    private function normaliseName(?string $v): string
    {
        $v = Str::of((string) $v)->lower()->ascii()->squish();  // lower, strip accents, collapse spaces
        return (string) $v;
    }
}
