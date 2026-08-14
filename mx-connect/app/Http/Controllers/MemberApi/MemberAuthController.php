<?php

namespace App\Http\Controllers\MemberApi;

use App\Http\Controllers\Controller;
use App\Models\Central\PublicAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Member PWA auth (Sanctum). A public account signs in with phone + password and
 * receives a token scoped to this mutual. The account must be linked to a member
 * in this mutual, else access is refused.
 */
class MemberAuthController extends Controller
{
    use ResolvesMember;

    public function login(Request $request)
    {
        $data = $request->validate([
            'phone'    => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $account = PublicAccount::where('phone', $data['phone'])->first();
        if (! $account || ! Hash::check($data['password'], $account->password)) {
            throw ValidationException::withMessages(['phone' => __('mxconnect.pwa.bad_credentials')]);
        }

        // Must belong to this mutual.
        $member = $this->currentMember($account);
        if (! $member) {
            throw ValidationException::withMessages(['phone' => __('mxconnect.pwa.not_a_member')]);
        }

        $token = $account->createToken('pwa-' . tenant('id'))->plainTextToken;

        return response()->json([
            'token'  => $token,
            'member' => [
                'code'       => $member->member_code,
                'first_name' => $member->first_name,
                'last_name'  => $member->last_name,
            ],
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()?->currentAccessToken()?->delete();
        return response()->json(['ok' => true]);
    }
}
