<?php

namespace App\Http\Controllers\Social;

use App\Http\Controllers\Controller;
use App\Models\Central\Currency;
use App\Models\Central\Mutual;
use App\Models\Tenant\Complaint;
use App\Models\Tenant\ComplaintMessage;
use App\Models\Tenant\Member;
use App\Services\AccountingRefusal;
use App\Services\DigitalCardService;
use App\Services\Social\MemberPortalService;
use App\Services\Tenant\MemberSelfService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * PORTAIL ADHÉRENT (/compte/mon-adhesion) dans le compte MX-CONNECT, aussi dans l'application Android
 * (2026-11-20). Chaque page : 1) contrôle du lien compte ↔ adhérent dans CETTE mutuelle (sinon 404),
 * 2) lecture et rendu DANS la base de la mutuelle ($mutual->run), filtrés sur cet adhérent.
 * Le paiement en ligne n'est PAS proposé (aucun agrégateur choisi : décision du fondateur).
 */
class MemberPortalController extends Controller
{
    public function __construct(private MemberPortalService $portal, private MemberSelfService $self) {}

    public function index(Request $request)
    {
        $links = $this->portal->links($request->user('member'));

        return view('social.member.index', ['links' => $links]);
    }

    public function activate(Request $request)
    {
        $d = $request->validate([
            'code'       => ['required', 'string', 'max:20'],
            'birth_date' => ['required', 'date', 'before:today'],
        ], ['code.required' => 'Saisissez le code remis par votre mutuelle.', 'birth_date.required' => 'Indiquez la date de naissance de l’adhérent.']);
        try {
            $link = $this->portal->activate($request->user('member'), $d['code'], \Illuminate\Support\Carbon::parse($d['birth_date'])->toDateString());
        } catch (AccountingRefusal $e) {
            return back()->withInput($request->except('code'))->withErrors(['code' => $e->getMessage()]);
        }

        return redirect()->route('social.member.dashboard', $link->mutual->slug)->with('success', 'Votre adhésion est maintenant accessible dans votre compte.');
    }

    public function dashboard(Request $request, string $mutual)
    {
        return $this->page($request, $mutual, 'dashboard', fn (Member $m) => ['d' => $this->self->dashboard($m)]);
    }

    public function profile(Request $request, string $mutual)
    {
        return $this->page($request, $mutual, 'profile', fn (Member $m) => [
            'requests' => \App\Models\Tenant\MemberChangeRequest::where('member_id', $m->id)->latest('id')->limit(20)->get(),
            'subscription' => $m->subscriptions()->with('guaranteeVersion.guarantee')->where('status', 'validated')->latest('effective_date')->first(),
        ]);
    }

    public function contact(Request $request, string $mutual)
    {
        $d = $request->validate([
            'phone'   => ['nullable', 'string', 'max:20', 'regex:/^[0-9 +().-]{6,20}$/'],
            'address' => ['nullable', 'string', 'max:255'],
            'note'    => ['nullable', 'string', 'max:500'],
        ], ['phone.regex' => 'Numéro de téléphone invalide.']);
        if (blank($d['phone'] ?? null) && blank($d['address'] ?? null)) {
            return back()->withErrors(['phone' => 'Indiquez au moins un changement.']);
        }

        return $this->act($request, $mutual, function (Member $m) use ($request, $d) {
            $this->self->requestChange($m, $request->user('member')->id, 'contact', array_filter($d, fn ($v) => filled($v)));

            return 'Demande envoyée : votre mutuelle la vérifiera avant de mettre votre fiche à jour.';
        });
    }

    /** Attestation d'adhésion imprimable (Imprimer → Enregistrer en PDF). */
    public function attestation(Request $request, string $mutual)
    {
        [$mut, $link] = $this->portal->resolve($request->user('member'), $mutual);

        return $mut->run(function () use ($link, $mut) {
            $m = Member::with('dependents')->findOrFail($link->member_id);
            $d = $this->self->dashboard($m);

            return response()->view('social.member.attestation', ['m' => $m, 'd' => $d, 'mutual' => $mut, 'currency' => $this->currency($mut)]);
        });
    }

    /** Carte de membre numérique (QR signé, sans donnée médicale), imprimable. */
    public function card(Request $request, string $mutual, DigitalCardService $cards)
    {
        [$mut, $link] = $this->portal->resolve($request->user('member'), $mutual);

        return $mut->run(function () use ($link, $mut, $cards) {
            $m = Member::findOrFail($link->member_id);
            $sub = $m->subscriptions()->with('guaranteeVersion.guarantee')->where('status', 'validated')->latest('effective_date')->first();
            $domain = $mut->domains()->value('domain');
            $scheme = parse_url((string) config('app.url'), PHP_URL_SCHEME) ?: 'https';
            $port = parse_url((string) config('app.url'), PHP_URL_PORT);
            $token = $cards->issueToken($m);
            $verify = "{$scheme}://{$domain}" . ($port ? ':' . $port : '') . '/carte/verification?t=' . urlencode($token);

            return response()->view('social.member.card', ['m' => $m, 'sub' => $sub, 'token' => $token, 'verifyUrl' => $verify, 'mutual' => $mut]);
        });
    }

    public function dependents(Request $request, string $mutual)
    {
        return $this->page($request, $mutual, 'dependents', fn (Member $m) => ['x' => $this->self->dependents($m)]);
    }

    public function dependentRequest(Request $request, string $mutual)
    {
        $kind = $request->input('kind');
        abort_unless(in_array($kind, ['dependent_add', 'dependent_update', 'dependent_remove'], true), 422);
        $rules = ['kind' => ['required'], 'dependent_id' => [$kind === 'dependent_add' ? 'nullable' : 'required', 'integer'], 'note' => ['nullable', 'string', 'max:500']];
        if ($kind !== 'dependent_remove') {
            $rules += [
                'first_name' => [$kind === 'dependent_add' ? 'required' : 'nullable', 'string', 'max:120'],
                'last_name' => [$kind === 'dependent_add' ? 'required' : 'nullable', 'string', 'max:120'],
                'birth_date' => [$kind === 'dependent_add' ? 'required' : 'nullable', 'date', 'before_or_equal:today'],
                'sex' => [$kind === 'dependent_add' ? 'required' : 'nullable', Rule::in(['M', 'F'])],
                'relationship' => [$kind === 'dependent_add' ? 'required' : 'nullable', Rule::in(['spouse', 'child', 'adopted_child', 'ascendant', 'other'])],
                'phone' => ['nullable', 'string', 'max:20'],
                'is_student' => ['nullable', 'boolean'],
            ];
        } else {
            $rules['note'] = ['required', 'string', 'min:5', 'max:500'];
        }
        $d = $request->validate($rules, ['note.required' => 'Indiquez le motif du retrait.', 'first_name.required' => 'Indiquez le prénom.', 'last_name.required' => 'Indiquez le nom.', 'birth_date.required' => 'Indiquez la date de naissance.']);
        $payload = array_filter(collect($d)->except(['kind', 'dependent_id'])->all(), fn ($v) => $v !== null && $v !== '');

        return $this->act($request, $mutual, function (Member $m) use ($request, $kind, $payload, $d) {
            $this->self->requestChange($m, $request->user('member')->id, $kind, $payload, isset($d['dependent_id']) ? (int) $d['dependent_id'] : null);

            return $kind === 'dependent_add'
                ? 'Demande envoyée. Le bénéficiaire ne sera couvert qu’après validation par la mutuelle, à partir de sa date d’intégration.'
                : 'Demande envoyée : la mutuelle la traitera.';
        }, 'beneficiaires');
    }

    public function guarantees(Request $request, string $mutual)
    {
        return $this->page($request, $mutual, 'guarantees', fn (Member $m) => ['g' => $this->self->guarantees($m)]);
    }

    public function providers(Request $request, string $mutual)
    {
        $f = $request->validate(['type' => ['nullable', Rule::in(['hospital', 'clinic', 'health_center', 'pharmacy', 'laboratory', 'other'])],
            'ville' => ['nullable', 'string', 'max:120'], 'q' => ['nullable', 'string', 'max:80']]);

        return $this->page($request, $mutual, 'providers', fn (Member $m) => ['x' => $this->self->providers($m, $f), 'f' => $f]);
    }

    public function prestations(Request $request, string $mutual)
    {
        $year = $request->integer('annee') ?: null;

        return $this->page($request, $mutual, 'prestations', fn (Member $m) => ['x' => $this->self->prestations($m, $year), 'year' => $year]);
    }

    public function prestation(Request $request, string $mutual, int $prestation)
    {
        return $this->page($request, $mutual, 'prestation', fn (Member $m) => ['x' => $this->self->prestation($m, $prestation)]);
    }

    public function contributions(Request $request, string $mutual)
    {
        return $this->page($request, $mutual, 'contributions', fn (Member $m) => ['x' => $this->self->contributions($m)]);
    }

    public function receipt(Request $request, string $mutual, int $payment)
    {
        [$mut, $link] = $this->portal->resolve($request->user('member'), $mutual);

        return $mut->run(function () use ($link, $mut, $payment) {
            $m = Member::findOrFail($link->member_id);

            return response()->view('social.member.receipt', ['m' => $m, 'x' => $this->self->receipt($m, $payment), 'mutual' => $mut, 'currency' => $this->currency($mut)]);
        });
    }

    public function complaints(Request $request, string $mutual)
    {
        return $this->page($request, $mutual, 'complaints', fn (Member $m) => ['items' => $this->self->complaints($m)]);
    }

    public function complaint(Request $request, string $mutual, int $complaint)
    {
        return $this->page($request, $mutual, 'complaint', fn (Member $m) => [
            'c' => Complaint::with('messages')->where('member_id', $m->id)->whereKey($complaint)->firstOrFail(),
        ]);
    }

    public function complaintStore(Request $request, string $mutual)
    {
        $d = $request->validate([
            'category'    => ['required', Rule::in(array_keys(Complaint::CATEGORIES))],
            'subject'     => ['required', 'string', 'min:5', 'max:200'],
            'description' => ['required', 'string', 'min:10', 'max:3000'],
        ], ['subject.required' => 'Indiquez l’objet de votre demande.', 'description.required' => 'Décrivez votre demande.']);

        return $this->act($request, $mutual, function (Member $m) use ($request, $d) {
            $sla = (int) config('mxconnect.governance.complaint_sla_days', 15);
            $c = Complaint::create([
                'ref' => 'RCL-' . now()->format('y') . '-' . strtoupper(Str::random(5)), 'member_id' => $m->id, 'channel' => 'online',
                'category' => $d['category'], 'public_account_id' => $request->user('member')->id,
                'subject' => trim($d['subject']), 'description' => trim($d['description']),
                'status' => 'received', 'received_on' => now()->toDateString(), 'due_on' => now()->addDays($sla)->toDateString(),
            ]);

            return "Demande {$c->ref} envoyée. Vous serez prévenu de la réponse.";
        }, 'reclamations');
    }

    public function complaintReply(Request $request, string $mutual, int $complaint)
    {
        $d = $request->validate(['body' => ['required', 'string', 'min:2', 'max:3000']]);

        return $this->act($request, $mutual, function (Member $m) use ($complaint, $d) {
            $c = Complaint::where('member_id', $m->id)->whereKey($complaint)->firstOrFail();
            abort_if($c->status === 'closed', 422, 'Cette demande est clôturée.');
            ComplaintMessage::create(['complaint_id' => $c->id, 'author' => 'member', 'body' => trim($d['body'])]);
            if ($c->status === 'resolved') {
                $c->update(['status' => 'in_progress', 'resolved_on' => null]);
            }

            return 'Message envoyé.';
        }, 'reclamations/' . $complaint);
    }

    // ------------------------------------------------------------------ outils

    /** Page du portail : rendue DANS la mutuelle, pour l'adhérent relié à ce compte. */
    private function page(Request $request, string $slug, string $view, \Closure $data)
    {
        [$mut, $link] = $this->portal->resolve($request->user('member'), $slug);
        $links = $this->portal->links($request->user('member'));

        return $mut->run(function () use ($link, $mut, $view, $data, $links) {
            $m = Member::with('dependents')->find($link->member_id);
            abort_unless($m, 404, 'Votre fiche adhérent n’existe plus dans cette mutuelle : contactez-la.');

            return response()->view('social.member.' . $view, ['m' => $m, 'mutual' => $mut, 'links' => $links, 'tab' => $view,
                'currency' => $this->currency($mut)] + $data($m));
        });
    }

    /** Action du portail (demande, message) dans la mutuelle, puis retour au portail. */
    private function act(Request $request, string $slug, \Closure $do, string $path = '')
    {
        [$mut, $link] = $this->portal->resolve($request->user('member'), $slug);
        $msg = $mut->run(function () use ($link, $do) {
            $m = Member::find($link->member_id);
            abort_unless($m, 404);

            return $do($m);
        });

        return redirect('/compte/mon-adhesion/' . $mut->slug . ($path ? '/' . $path : ''))->with('success', $msg);
    }

    private function currency(Mutual $mut): Currency
    {
        return Currency::on('central')->find($mut->currency_id) ?? Currency::on('central')->where('code', 'XAF')->firstOrFail();
    }
}
