<?php

namespace App\Http\Controllers;

use App\Mail\SocialMail;
use App\Models\Central\BillingPlan;
use App\Models\Central\DemoRequest;
use App\Models\Central\Domain;
use App\Models\Central\Mutual;
use App\Models\Central\PublicAccount;
use App\Models\Social\Post;
use App\Services\Social\FeedService;
use App\Services\Social\MailService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Page d'accueil de la plateforme MX-CONNECT (domaine central) : présentation,
 * fonctionnalités, tarifs, actualités du réseau, demande de démonstration.
 * Les chiffres affichés sont RÉELS (calculés depuis la base) ; tout visuel
 * d'exemple est explicitement signalé comme tel.
 */
class HomeController extends Controller
{
    public function index()
    {
        if (auth('member')->check()) {
            return redirect()->route('social.home');
        }

        $stats = Cache::remember('home:stats', 600, function () {
            $mutuals = Mutual::operational()->where('status', '!=', 'suspended');

            return [
                'mutuals'   => (clone $mutuals)->count(),
                'countries' => (clone $mutuals)->distinct()->count('country_id'),
                'accounts'  => PublicAccount::where('is_demo', false)->count(),
                'posts'     => Post::publicFeed()->where('is_demo', false)->count(),
            ];
        });

        return view('home.platform', [
            'stats'  => $stats,
            'plans'  => BillingPlan::where('active', true)->with('currency')->orderBy('price_minor')->get(),
            'latest' => Post::publicFeed()->with(FeedService::WITH)->orderByDesc('published_at')->limit(3)->get(),
            'ranges' => DemoRequest::RANGES,
        ]);
    }

    /** Demande de démonstration (mutuelle, réseau, partenaire). */
    public function demo(Request $request, MailService $mail)
    {
        $data = $request->validate([
            'name'          => ['required', 'string', 'max:180'],
            'organisation'  => ['required', 'string', 'max:180'],
            'role'          => ['nullable', 'string', 'max:120'],
            'email'         => ['required', 'email:rfc', 'max:180'],
            'phone'         => ['nullable', 'string', 'max:40'],
            'country'       => ['nullable', 'string', 'max:120'],
            'members_range' => ['nullable', 'in:' . implode(',', array_keys(DemoRequest::RANGES))],
            'message'       => ['nullable', 'string', 'max:2000'],
            'consent'       => ['accepted'],
            'website'       => ['nullable', 'max:0'],          // piège à robots (champ invisible)
        ], ['consent.accepted' => 'Merci d’accepter d’être recontacté.']);

        $demo = DemoRequest::create(collect($data)->except(['consent', 'website'])->all());

        $mail->send(config('mxconnect.contact_email'), new SocialMail(
            '[MX-CONNECT] Nouvelle demande de démonstration', 'Nouvelle demande de démonstration',
            ["Organisation : {$demo->organisation}", "Contact : {$demo->name}" . ($demo->role ? " ({$demo->role})" : ''),
             "E-mail : {$demo->email}" . ($demo->phone ? " · Tél. : {$demo->phone}" : ''),
             $demo->message ? 'Message : ' . Str::limit($demo->message, 600) : 'Pas de message.'],
            'Voir les demandes', rtrim((string) config('app.url'), '/') . '/reseau/demandes-demo',
            'Alerte envoyée à l’adresse de contact de la plateforme.',
        ));

        return redirect()->to(route('home') . '#contact')
            ->with('demo_ok', 'Merci ! Votre demande a bien été enregistrée. Notre équipe vous recontacte rapidement.');
    }

    /** Page « Se connecter » : chaque profil vers son espace. */
    public function login()
    {
        return view('home.login', ['base' => config('mxconnect.tenant_base_domain')]);
    }

    /** Personnel d'une mutuelle : redirection vers l'adresse de sa mutuelle. */
    public function mutualLogin(Request $request)
    {
        $slug = Str::lower(trim((string) $request->validate(['mutuelle' => ['required', 'string', 'max:120']])['mutuelle']));
        $slug = preg_replace('#^https?://#', '', $slug);
        $slug = explode('.', $slug)[0];

        $mutual = Mutual::where('slug', $slug)->where('status', '!=', 'suspended')->first();
        $domain = $mutual ? Domain::where('mutual_id', $mutual->id)->value('domain') : null;
        if (! $domain) {
            return back()->withInput()->withErrors(['mutuelle' => 'Aucune mutuelle trouvée avec cet identifiant. Vérifiez l’adresse communiquée par votre mutuelle.']);
        }
        $scheme = parse_url((string) config('app.url'), PHP_URL_SCHEME) ?: 'https';

        return redirect()->away("{$scheme}://{$domain}/connexion");
    }
}
