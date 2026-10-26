<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Tenant\Concerns\MutualContext;
use App\Models\Central\MemberLink;
use App\Models\Tenant\Antenna;
use App\Models\Tenant\Guarantee;
use App\Models\Tenant\Member;
use App\Models\Tenant\MemberNotification;
use App\Services\Social\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Notifications aux membres, individuelles ou en masse.
 *  - Application MX-CONNECT (et e-mail si le membre l'accepte) : membres dont le
 *    compte du réseau est relié à leur fiche d'adhérent.
 *  - WhatsApp : lien d'envoi individuel (ouvre l'application de l'agent).
 *  - SMS : export CSV pour l'outil d'envoi de la mutuelle (aucun envoi simulé).
 */
class MemberNotificationController extends Controller
{
    use MutualContext;

    public const ROLES = ['mutual_admin', 'community_manager', 'enrollment_agent'];

    public function index(Request $request)
    {
        $this->requireRoles($request, self::ROLES);
        $linked = MemberLink::where('mutual_id', tenant('id'))->count();

        return view('tenant.notifications.index', [
            'history'    => MemberNotification::latest()->paginate(15),
            'antennas'   => Antenna::orderBy('name')->get(),
            'guarantees' => Guarantee::orderBy('name')->get(),
            'stats'      => [
                'active'  => Member::where('status', 'active')->count(),
                'linked'  => $linked,
                'phones'  => Member::where('status', 'active')->whereNotNull('phone')->where('phone', '!=', '')->count(),
                'sent'    => (int) MemberNotification::sum('recipients'),
            ],
            'audiences'  => MemberNotification::AUDIENCES,
            'prefill'    => $request->only(['audience', 'membre']),
        ]);
    }

    public function store(Request $request, NotificationService $notifications)
    {
        $this->requireRoles($request, self::ROLES);
        $data = $request->validate([
            'audience'    => ['required', 'in:' . implode(',', array_keys(MemberNotification::AUDIENCES))],
            'member_code' => ['required_if:audience,member', 'nullable', 'string', 'max:40'],
            'antenna_id'  => ['required_if:audience,antenna', 'nullable', 'integer'],
            'guarantee_id'=> ['required_if:audience,guarantee', 'nullable', 'integer'],
            'title'       => ['required', 'string', 'max:150'],
            'body'        => ['required', 'string', 'max:1000'],
        ], ['member_code.required_if' => 'Indiquez le code de l’adhérent.', 'antenna_id.required_if' => 'Choisissez l’antenne.', 'guarantee_id.required_if' => 'Choisissez la garantie.'],
            ['title' => 'titre', 'body' => 'message']);

        [$query, $label, $ref] = $this->audience($data);
        $members = $query->get(['id', 'phone', 'first_name', 'last_name', 'member_code']);
        if ($members->isEmpty()) {
            return back()->withInput()->with('error', 'Aucun adhérent ne correspond à ce public.');
        }
        $links = MemberLink::where('mutual_id', tenant('id'))->whereIn('member_id', $members->pluck('id'))->pluck('public_account_id', 'member_id');
        $mutual = \App\Models\Central\Mutual::find(tenant('id'));
        $url = $mutual?->slug ? '/mutuelles/' . $mutual->slug : null;

        $campaign = DB::transaction(function () use ($data, $members, $links, $label, $ref, $request) {
            $c = MemberNotification::create([
                'title' => $data['title'], 'body' => $data['body'], 'audience' => $data['audience'],
                'audience_ref' => $ref, 'audience_label' => $label, 'recipients' => $members->count(),
                'in_app' => $links->count(), 'with_phone' => $members->filter(fn ($m) => trim((string) $m->phone) !== '')->count(),
                'sent_by' => $request->user()->id,
            ]);
            foreach ($members->chunk(500) as $chunk) {
                $c->recipientsRows()->insert($chunk->map(fn ($m) => [
                    'member_notification_id' => $c->id, 'member_id' => $m->id, 'in_app' => isset($links[$m->id]),
                    'created_at' => now(), 'updated_at' => now(),
                ])->values()->all());
            }

            return $c;
        });
        // Dépôt dans l'application (hors transaction : base centrale).
        foreach ($links as $accountId) {
            $notifications->notify((int) $accountId, 'mutuelle', (tenant('name') ?: 'Votre mutuelle') . ' : ' . $data['title'], $data['body'], $url);
        }

        return redirect()->route('member-notifications.show', $campaign)->with('success',
            'Notification enregistrée pour ' . $members->count() . ' adhérent(s) : ' . $links->count() . ' reçue(s) dans l’application MX-CONNECT. '
            . 'Utilisez WhatsApp ou l’export SMS pour les autres.');
    }

    public function show(Request $request, MemberNotification $notification)
    {
        $this->requireRoles($request, self::ROLES);
        $rows = $notification->recipientsRows()->with('member:id,member_code,first_name,last_name,phone')->paginate(50);

        return view('tenant.notifications.show', ['n' => $notification, 'rows' => $rows]);
    }

    /** Export CSV (téléphone, nom, message) pour un outil d'envoi de SMS. */
    public function export(Request $request, MemberNotification $notification)
    {
        $this->requireRoles($request, self::ROLES);
        $file = 'notification-' . $notification->id . '-sms.csv';
        $text = $notification->title . ' : ' . $notification->body;

        return response()->streamDownload(function () use ($notification, $text) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['telephone', 'code_adherent', 'nom', 'message'], ';');
            $notification->recipientsRows()->with('member:id,member_code,first_name,last_name,phone')->chunk(500, function ($rows) use ($out, $text) {
                foreach ($rows as $r) {
                    if ($r->member && trim((string) $r->member->phone) !== '') {
                        fputcsv($out, [$r->member->phone, $r->member->member_code, $r->member->last_name . ' ' . $r->member->first_name, $text], ';');
                    }
                }
            });
            fclose($out);
        }, $file, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** @return array{0:\Illuminate\Database\Eloquent\Builder,1:string,2:?string} */
    private function audience(array $d): array
    {
        $active = Member::query()->where('status', 'active');

        return match ($d['audience']) {
            'member'    => (function () use ($d) {
                $m = Member::where('member_code', trim((string) $d['member_code']))->first();
                abort_unless($m, 422, 'Aucun adhérent avec ce code.');

                return [Member::whereKey($m->id), $m->last_name . ' ' . $m->first_name . ' (' . $m->member_code . ')', (string) $m->id];
            })(),
            'antenna'   => [$active->where('antenna_id', (int) $d['antenna_id']), 'Antenne : ' . (Antenna::find($d['antenna_id'])?->name ?? '?'), (string) $d['antenna_id']],
            'guarantee' => [$active->whereHas('subscriptions', fn ($s) => $s->where('status', 'validated')->whereHas('guaranteeVersion', fn ($v) => $v->where('guarantee_id', (int) $d['guarantee_id']))),
                'Garantie : ' . (Guarantee::find($d['guarantee_id'])?->name ?? '?'), (string) $d['guarantee_id']],
            'overdue'   => [$active->whereHas('subscriptions', fn ($s) => $s->whereHas('schedules', fn ($q) => $q->where('status', 'overdue'))), 'Adhérents en retard de cotisation', null],
            default     => [$active, 'Tous les adhérents actifs', null],
        };
    }
}
