<?php

namespace App\Http\Controllers\Social;

use App\Models\Social\SocialNotification;

class NotificationController extends SocialController
{
    public function index()
    {
        $items = SocialNotification::where('public_account_id', $this->viewer()->id)->latest('id')->paginate(30);

        return view('social.notifications', compact('items'));
    }

    /** Ouvre une notification : la marque lue puis redirige (lien interne uniquement). */
    public function open(int $notification)
    {
        $n = SocialNotification::where('public_account_id', $this->viewer()->id)->findOrFail($notification);
        $n->read_at ??= now();
        $n->save();
        $url = (string) $n->url;
        $safe = str_starts_with($url, '/') && ! str_starts_with($url, '//') ? $url : route('social.notifications');

        return redirect()->to($safe);
    }

    public function readAll()
    {
        SocialNotification::where('public_account_id', $this->viewer()->id)->whereNull('read_at')->update(['read_at' => now()]);

        return back()->with('ok', 'Toutes les notifications sont marquées comme lues.');
    }
}
