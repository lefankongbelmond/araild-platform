<?php

namespace App\Services;

use App\Models\Tenant\Guarantee;
use App\Models\Tenant\GuaranteeVersion;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Guarantee versioning (CDC §11).
 *
 * Editing a guarantee's terms NEVER mutates the running version — that would
 * retroactively change past claims. Instead we CLOSE the current version
 * (valid_to = today) and OPEN a new one (valid_from = tomorrow). A version that
 * has already been used by a claim is `locked` and may never be edited.
 */
class GuaranteeVersioningService
{
    /** Create a brand-new guarantee with its first version. */
    public function createWithFirstVersion(array $guarantee, array $version): Guarantee
    {
        return DB::transaction(function () use ($guarantee, $version) {
            $g = Guarantee::create($guarantee);
            $g->versions()->create($version + [
                'version_no' => 1,
                'valid_from' => $version['valid_from'] ?? now()->toDateString(),
                'valid_to'   => null,
            ]);
            return $g;
        });
    }

    /**
     * Roll a guarantee to a new version. Closes the current open version the day
     * before the new one takes effect, then opens the successor.
     */
    public function newVersion(Guarantee $guarantee, array $params, ?string $effectiveFrom = null): GuaranteeVersion
    {
        $effectiveFrom = $effectiveFrom ?: now()->addDay()->toDateString();

        return DB::transaction(function () use ($guarantee, $params, $effectiveFrom) {
            /** @var GuaranteeVersion|null $current */
            $current = $guarantee->versions()->whereNull('valid_to')->latest('version_no')->first();

            if ($current) {
                $closeOn = Carbon::parse($effectiveFrom)->subDay()->toDateString();
                $current->update(['valid_to' => $closeOn]);
            }

            return $guarantee->versions()->create($params + [
                'version_no' => ($current->version_no ?? 0) + 1,
                'valid_from' => $effectiveFrom,
                'valid_to'   => null,
            ]);
        });
    }

    /** Guard: a locked version (already used by a claim) is immutable. */
    public function assertEditable(GuaranteeVersion $version): void
    {
        if ($version->locked) {
            throw new \RuntimeException('This guarantee version is locked and cannot be edited.');
        }
    }
}
