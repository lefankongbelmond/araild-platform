<?php

namespace App\Services;

use App\Models\Tenant\Member;
use Illuminate\Support\Facades\Config;

/**
 * Digital membership card (CDC §31).
 *
 * The QR encodes a SIGNED token — never medical data. A provider scans it, the
 * app verifies the signature (blocks forged cards) then runs the rights check
 * and shows only the verdict + minimal identity.
 *
 * Token format: "{mutualId}.{memberId}.{sig}" where sig is an HMAC over the first
 * two segments keyed by the app secret, so a token cannot be forged or transplanted
 * to another mutual.
 */
class DigitalCardService
{
    public function issueToken(Member $member): string
    {
        $mutualId = (string) tenant('id');
        $memberId = (string) $member->id;
        $sig = $this->sign($mutualId, $memberId);

        return "{$mutualId}.{$memberId}.{$sig}";
    }

    /** @return array{valid:bool, member_id:?int} */
    public function verifyToken(string $token): array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return ['valid' => false, 'member_id' => null];
        }
        [$mutualId, $memberId, $sig] = $parts;

        // Must belong to the current tenant AND carry a valid signature.
        if ($mutualId !== (string) tenant('id')) {
            return ['valid' => false, 'member_id' => null];
        }
        if (! hash_equals($this->sign($mutualId, $memberId), $sig)) {
            return ['valid' => false, 'member_id' => null];
        }

        return ['valid' => true, 'member_id' => (int) $memberId];
    }

    private function sign(string $mutualId, string $memberId): string
    {
        $key = Config::get('app.key');
        // Strip Laravel's "base64:" prefix if present.
        if (str_starts_with((string) $key, 'base64:')) {
            $key = base64_decode(substr($key, 7));
        }
        return substr(hash_hmac('sha256', "{$mutualId}|{$memberId}", (string) $key), 0, 32);
    }
}
