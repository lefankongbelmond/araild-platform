@php
  $arrow = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 5l7 7-7 7"/></svg>';
  $fmt = fn ($n) => number_format($n, 0, ',', ' ');
  $realStats = array_filter([
      ['building', $stats['mutuals'], $stats['mutuals'] > 1 ? 'Mutuelles sur la plateforme' : 'Mutuelle sur la plateforme'],
      ['globe', $stats['countries'], $stats['countries'] > 1 ? 'Pays' : 'Pays'],
      ['users', $stats['accounts'], 'Membres inscrits au réseau'],
      ['megaphone', $stats['posts'], 'Publications partagées'],
  ], fn ($s) => $s[1] > 0);
  $modules = [
      ['user', 'g', 'Gestion des adhérents', 'Inscription, profils, dépendants et historique'],
      ['chart', 'b', 'Cotisations & paiements', 'Suivi, relances et reçus en temps réel'],
      ['heart-pulse', 'g', 'Prestations de santé', 'Demandes, plafonds et prise en charge'],
      ['activity', 'b', 'Analyses & rapports', 'Tableaux de bord, solvabilité et indicateurs clés'],
      ['megaphone', 'g', 'Communication', 'Réseau social, annonces et messagerie intégrée'],
      ['shield', 'b', 'Sécurité avancée', 'Rôles, double authentification et journal d’audit'],
  ];
@endphp
<!DOCTYPE html>
<html lang="fr">
<head>
  @include('home.partials.head')
  <title>MX-CONNECT — La plateforme digitale des mutuelles de santé</title>
  <meta name="description" content="MX-CONNECT : gestion des adhérents, cotisations, prestations, réseau social et adhésion en ligne pour les mutuelles de santé. Connecter aujourd’hui pour protéger demain.">
  <meta property="og:title" content="MX-CONNECT — Connecter aujourd’hui pour protéger demain">
  <meta property="og:image" content="{{ url('/brand/mx-logo-full.webp') }}">
</head>
<body class="h-body">
  @include('home.partials.header', ['current' => 'accueil'])

  <main id="contenu">
    {{-- ============ HÉROS ============ --}}
    <section class="h-hero">
      <div class="h-wrap h-hero__grid">
        <div>
          <span class="h-pill"><x-mx.icon name="shield" :size="16"/> La plateforme de référence des mutuelles</span>
          <h1>Connecter aujourd’hui<br>pour <em>protéger</em> demain</h1>
          <p class="h-lead">MX-CONNECT est la plateforme digitale tout-en-un qui permet aux mutuelles de santé, réseaux et partenaires de collaborer efficacement, gérer en toute sécurité et offrir le meilleur à leurs membres.</p>
          <div class="h-cta">
            <a class="h-btn h-btn--grad" href="#fonctionnalites">Découvrir la plateforme {!! $arrow !!}</a>
            <a class="h-btn h-btn--ghost" href="{{ route('social.home') }}">Voir le réseau social <x-mx.icon name="users" :size="18"/></a>
          </div>
          <div class="h-trust">
            <div class="h-trust__item"><span class="h-trust__ico"><x-mx.icon name="shield" :size="18"/></span><span><b>Sécurisé</b>Rôles, 2FA et audit</span></div>
            <div class="h-trust__item"><span class="h-trust__ico"><x-mx.icon name="building" :size="18"/></span><span><b>Données isolées</b>Une base par mutuelle</span></div>
            <div class="h-trust__item"><span class="h-trust__ico"><x-mx.icon name="heart-pulse" :size="18"/></span><span><b>Accompagnement</b>Paramétrage et formation</span></div>
          </div>
        </div>

        <div class="h-visual" aria-label="Aperçu de la plateforme (illustration)">
          <img class="h-visual__photo" src="/brand/hero-equipe.webp" alt="Deux professionnels de santé consultent la plateforme sur une tablette" width="1244" height="1140">

          <div class="h-card" style="top:70px">
            <span class="h-card__tag">Exemple</span>
            <small>Membres actifs</small><strong>24 560</strong>
            <div class="h-card__up">↑ 18,6 % <span style="color:#98A2B3;font-weight:500">vs mois dernier</span></div>
            <svg viewBox="0 0 120 34" aria-hidden="true"><defs><linearGradient id="spk" x1="0" x2="0" y1="0" y2="1"><stop offset="0" stop-color="#4F992F" stop-opacity=".25"/><stop offset="1" stop-color="#4F992F" stop-opacity="0"/></linearGradient></defs><path d="M2 30 L20 24 L34 27 L50 16 L64 20 L80 13 L96 17 L118 3 L118 34 L2 34Z" fill="url(#spk)"/><path d="M2 30 L20 24 L34 27 L50 16 L64 20 L80 13 L96 17 L118 3" stroke="#4F992F" stroke-width="2" fill="none"/></svg>
          </div>
          <div class="h-card h-card--dark" style="top:240px;right:-6px">
            <span class="h-card__tag">Exemple</span>
            <small>Cotisations ce mois</small><strong style="font-size:22px">125 780 500 <span style="font-size:11px">FCFA</span></strong>
            <svg viewBox="0 0 120 40" aria-hidden="true">@foreach ([14,22,10,28,18,30,24,34,20,26,38,30,36] as $i => $v)<rect x="{{ $i * 9 + 1 }}" y="{{ 40 - $v }}" width="5" height="{{ $v }}" rx="1.5" fill="{{ $i % 3 === 1 ? '#4F992F' : '#2D8CFF' }}"/>@endforeach</svg>
          </div>
          <div class="h-card h-card--3" style="top:408px;right:22px">
            <span class="h-card__tag">Exemple</span>
            <small>Demandes traitées</small><strong>98,7 %</strong>
            <div class="h-bar"><span></span></div>
          </div>
          <p class="h-visual__note">Illustration : chiffres d’exemple, non représentatifs.</p>
        </div>
      </div>

      <div class="h-wrap h-modules" style="margin-top:28px">
        <div class="h-modules__box" id="modules">
          @foreach ($modules as [$ico, $c, $t, $d])
            <div class="h-mod"><span class="h-mod__ico h-mod__ico--{{ $c }}"><x-mx.icon :name="$ico" :size="22"/></span><div><b>{{ $t }}</b><span>{{ $d }}</span></div></div>
          @endforeach
        </div>
      </div>
    </section>

    {{-- ============ COMMUNAUTÉ (chiffres réels) ============ --}}
    <section class="h-band" aria-labelledby="h-band-title">
      <div class="h-wrap">
        <div class="h-band__grid">
          <div class="h-band__intro">
            <span class="h-round"><x-mx.icon name="users" :size="34"/></span>
            <div><h2 id="h-band-title">Rejoignez une communauté qui grandit chaque jour</h2>
              <p>MX-CONNECT accompagne les mutuelles dans leur transformation digitale pour un impact durable.</p></div>
          </div>
          @forelse ($realStats as [$ico, $n, $label])
            <div class="h-stat"><span class="h-round"><x-mx.icon :name="$ico" :size="24"/></span><div><strong>{{ $fmt($n) }}</strong><span>{{ $label }}</span></div></div>
          @empty
            <div class="h-stat" style="grid-column:span 4"><div><strong style="font-size:22px">Le réseau démarre</strong><span>Les premières mutuelles rejoignent la plateforme : soyez parmi elles.</span></div></div>
          @endforelse
        </div>
        @if ($realStats)<p class="h-band__note">Chiffres réels, calculés à partir de la plateforme (hors données de démonstration).</p>@endif
      </div>
    </section>

    {{-- ============ À PROPOS ============ --}}
    <section class="h-section" id="apropos">
      <div class="h-wrap h-about">
        <div>
          <span class="h-eyebrow">À propos</span>
          <h2 class="h-title">Une plateforme pensée pour les mutuelles de santé africaines</h2>
          <p class="h-sub">MX-CONNECT réunit sur un même socle la gestion quotidienne de chaque mutuelle, le pilotage du réseau et un espace numérique pour les adhérents. Chaque mutuelle dispose de sa propre base de données : ses informations restent séparées de celles des autres.</p>
          <div class="h-grid-3" style="grid-template-columns:1fr 1fr;margin-top:30px">
            <div class="h-feature" style="padding:20px"><b style="color:#0B1A3A">Multi-pays, multi-devises</b><p>Pays, devises (FCFA par défaut) et langues paramétrables par le réseau.</p></div>
            <div class="h-feature" style="padding:20px"><b style="color:#0B1A3A">Accessible partout</b><p>Navigateur, téléphone et application installable pour les adhérents.</p></div>
          </div>
        </div>
        <div class="h-about__panel">
          <img src="/brand/mx-mark-128.webp" alt="">
          <p><b>Notre mission :</b> simplifier la gestion des mutuelles pour qu’elles se consacrent à l’essentiel — protéger la santé de leurs membres.</p>
          <p><b>Notre approche :</b> des outils fiables, des données protégées, et un réseau qui permet aux mutuelles de partager leurs actions de prévention avec la population.</p>
        </div>
      </div>
    </section>

    {{-- ============ FONCTIONNALITÉS ============ --}}
    <section class="h-section h-section--alt" id="fonctionnalites">
      <div class="h-wrap">
        <div class="h-center"><span class="h-eyebrow">Fonctionnalités</span>
          <h2 class="h-title">Tout ce dont une mutuelle a besoin, au même endroit</h2>
          <p class="h-sub">Des modules connectés entre eux, du premier contact avec un adhérent jusqu’au rapport annuel.</p></div>
        <div class="h-grid-3">
          @foreach ([
            ['user', 'g', 'Adhérents & souscriptions', 'Fiches adhérents, ayants droit, import en masse et contrôle des doublons.', ['Double validation (saisie / contrôle)', 'Carte d’adhérent digitale', 'Adhésion en ligne depuis le réseau']],
            ['chart', 'b', 'Cotisations & paiements', 'Échéanciers, relances automatiques et rapprochement des paiements.', ['Paiement mobile (selon prestataire)', 'Reçus et historique', 'Suivi des impayés']],
            ['shield', 'g', 'Prestations & garanties', 'Garanties versionnées, plafonds, actes et médicaments de référence.', ['Prise en charge et remboursements', 'Détection d’anomalies', 'Réclamations et délais']],
            ['activity', 'b', 'Pilotage & conformité', 'Comptabilité, trésorerie, solvabilité et cartographie des risques.', ['Tableaux de bord', 'Qualité des données', 'Journal d’audit complet']],
            ['megaphone', 'g', 'Réseau social des mutuelles', 'Page publique, publications, événements, groupes et messagerie.', ['Campagnes de prévention', 'Abonnés et communauté', 'Modération intégrée']],
            ['globe', 'b', 'Réseau & consolidation', 'Vue d’ensemble pour les faîtières : consolidation, facturation, santé des bases.', ['Agrément des mutuelles', 'Sauvegardes et plan de reprise', 'Comparateur public des offres']],
          ] as [$ico, $c, $t, $d, $items])
            <div class="h-feature">
              <span class="h-mod__ico h-mod__ico--{{ $c }}"><x-mx.icon :name="$ico" :size="22"/></span>
              <h3>{{ $t }}</h3><p>{{ $d }}</p>
              <ul>@foreach ($items as $i)<li>{{ $i }}</li>@endforeach</ul>
            </div>
          @endforeach
        </div>
      </div>
    </section>

    {{-- ============ SOLUTIONS ============ --}}
    <section class="h-section" id="solutions">
      <div class="h-wrap">
        <div class="h-center"><span class="h-eyebrow">Solutions</span><h2 class="h-title">Une solution pour chaque acteur</h2></div>
        <div class="h-grid-3">
          <div class="h-feature"><span class="h-mod__ico h-mod__ico--g"><x-mx.icon name="building" :size="22"/></span><h3>Mutuelles de santé</h3><p>Un espace de gestion sécurisé à votre adresse (ex. <em>votre-mutuelle.{{ config('mxconnect.tenant_base_domain') }}</em>), avec des rôles adaptés à chaque agent.</p><ul><li>Agents, contrôleurs, trésoriers, community manager</li><li>Adhésions reçues en ligne</li><li>Alertes et messagerie</li></ul></div>
          <div class="h-feature"><span class="h-mod__ico h-mod__ico--b"><x-mx.icon name="globe" :size="22"/></span><h3>Réseaux & faîtières</h3><p>Supervisez les mutuelles du réseau, agréez les nouvelles et consolidez les indicateurs.</p><ul><li>Consolidation multi-mutuelles</li><li>Facturation des abonnements</li><li>Modération du réseau social</li></ul></div>
          <div class="h-feature"><span class="h-mod__ico h-mod__ico--g"><x-mx.icon name="users" :size="22"/></span><h3>Adhérents & population</h3><p>Suivez les mutuelles, découvrez leurs actions de prévention et demandez votre adhésion en ligne.</p><ul><li>Espace adhérent et carte digitale</li><li>Réseau social et groupes</li><li>Notifications par e-mail</li></ul></div>
        </div>
        <div class="h-grid-4 h-steps" style="margin-top:56px">
          <div class="h-step"><h3>Demande de démo</h3><p>Présentation de la plateforme adaptée à votre organisation.</p></div>
          <div class="h-step"><h3>Paramétrage</h3><p>Garanties, cotisations, antennes, rôles et page publique.</p></div>
          <div class="h-step"><h3>Import des adhérents</h3><p>Reprise de vos fichiers existants, avec contrôle des doublons.</p></div>
          <div class="h-step"><h3>Mise en service</h3><p>Formation des équipes et accompagnement au démarrage.</p></div>
        </div>
      </div>
    </section>

    {{-- ============ TARIFS ============ --}}
    <section class="h-section h-section--alt" id="tarifs">
      <div class="h-wrap">
        <div class="h-center"><span class="h-eyebrow">Tarifs</span><h2 class="h-title">Un abonnement adapté à la taille de votre mutuelle</h2></div>
        @if ($plans->isNotEmpty())
          <div class="h-grid-3">
            @foreach ($plans as $plan)
              <div class="h-plan">
                <h3 style="font-size:20px;color:#0B1A3A">{{ $plan->name }}</h3>
                <div class="h-plan__price">{{ $plan->currency ? $plan->currency->format($plan->price_minor) : $fmt($plan->price_minor) }} <small>/ {{ $plan->interval === 'annual' ? 'an' : 'mois' }}</small></div>
                @if ($plan->max_members)<p class="h-sub" style="font-size:14px">Jusqu’à {{ $fmt($plan->max_members) }} adhérents</p>@endif
                <ul style="margin:16px 0 22px;padding:0;list-style:none">@foreach ((array) $plan->features as $f)<li style="margin-top:8px;font-size:14px">✓ {{ is_array($f) ? implode(' ', $f) : $f }}</li>@endforeach</ul>
                <a class="h-btn h-btn--grad" href="#contact">Choisir cette offre</a>
              </div>
            @endforeach
          </div>
        @endif
        <div class="h-quote">
          <div><h3>{{ $plans->isEmpty() ? 'Tarification sur devis' : 'Besoin d’une offre sur mesure ?' }}</h3>
            <p class="h-sub" style="margin-top:8px">Le tarif dépend du nombre d’adhérents et des modules activés. Demandez une démonstration : nous vous adressons une proposition détaillée.</p></div>
          <a class="h-btn h-btn--grad" href="#contact">Demander un devis {!! $arrow !!}</a>
        </div>
      </div>
    </section>

    {{-- ============ ACTUALITÉS (réelles) ============ --}}
    @if ($latest->isNotEmpty())
    <section class="h-section" id="actualites">
      <div class="h-wrap">
        <div style="display:flex;justify-content:space-between;align-items:end;gap:20px;flex-wrap:wrap">
          <div><span class="h-eyebrow">Actualités</span><h2 class="h-title">Les dernières nouvelles du réseau</h2></div>
          <a class="h-btn h-btn--ghost" href="{{ route('social.home') }}">Toutes les actualités {!! $arrow !!}</a>
        </div>
        <div class="h-grid-3">
          @foreach ($latest as $p)
            @php $img = $p->media->first()?->thumbUrl() ?? $p->mutual?->profile?->coverUrl(); @endphp
            <a class="h-news" href="{{ route('social.post', $p) }}">
              <div class="h-news__img" @if($img) style="background-image:url('{{ $img }}')" @endif></div>
              <div class="h-news__body">
                <div class="h-news__meta">{{ $p->authorName() }} · {{ $p->typeLabel() }} · {{ $p->published_at?->translatedFormat('j F Y') }}@if($p->is_demo || $p->mutual?->profile?->is_demo) · <b style="color:#B54708">Démo</b>@endif</div>
                <p>{{ \Illuminate\Support\Str::limit($p->body, 150) }}</p>
              </div>
            </a>
          @endforeach
        </div>
      </div>
    </section>
    @endif

    {{-- ============ CONTACT / DÉMO ============ --}}
    <section class="h-section h-section--alt" id="contact">
      <div class="h-wrap h-contact">
        <div>
          <span class="h-eyebrow">Contact</span>
          <h2 class="h-title">Demandez une démonstration</h2>
          <p class="h-sub">Mutuelle, réseau de mutuelles ou partenaire : décrivez-nous votre besoin, nous vous recontactons pour organiser une présentation.</p>
          <div class="h-contact__list">
            <div><span class="h-round"><x-mx.icon name="calendar" :size="20"/></span><span>Démonstration en ligne ou sur site</span></div>
            <div><span class="h-round"><x-mx.icon name="settings" :size="20"/></span><span>Paramétrage selon vos garanties et vos procédures</span></div>
            <div><span class="h-round"><x-mx.icon name="users" :size="20"/></span><span>Formation des équipes et accompagnement</span></div>
          </div>
        </div>
        <div class="h-form">
          @if (session('demo_ok'))
            <p class="h-ok" role="status">{{ session('demo_ok') }}</p>
          @else
          <form method="POST" action="{{ route('home.demo') }}">
            @csrf
            <div class="h-row">
              <div><label for="d-name">Nom et prénom</label><input id="d-name" name="name" value="{{ old('name') }}" required maxlength="180" autocomplete="name">@error('name')<p class="h-err">{{ $message }}</p>@enderror</div>
              <div><label for="d-org">Organisation</label><input id="d-org" name="organisation" value="{{ old('organisation') }}" required maxlength="180" autocomplete="organization">@error('organisation')<p class="h-err">{{ $message }}</p>@enderror</div>
            </div>
            <div class="h-row">
              <div><label for="d-email">E-mail professionnel</label><input id="d-email" type="email" name="email" value="{{ old('email') }}" required maxlength="180" autocomplete="email">@error('email')<p class="h-err">{{ $message }}</p>@enderror</div>
              <div><label for="d-phone">Téléphone</label><input id="d-phone" name="phone" value="{{ old('phone') }}" maxlength="40" autocomplete="tel"></div>
            </div>
            <div class="h-row">
              <div><label for="d-role">Fonction</label><input id="d-role" name="role" value="{{ old('role') }}" maxlength="120" placeholder="Ex. : Président, gestionnaire"></div>
              <div><label for="d-country">Pays</label><input id="d-country" name="country" value="{{ old('country') }}" maxlength="120" autocomplete="country-name"></div>
            </div>
            <label for="d-range">Nombre d’adhérents</label>
            <select id="d-range" name="members_range"><option value="">—</option>@foreach ($ranges as $k => $v)<option value="{{ $k }}" @selected(old('members_range') === $k)>{{ $v }}</option>@endforeach</select>
            <label for="d-msg">Votre besoin (facultatif)</label>
            <textarea id="d-msg" name="message" maxlength="2000">{{ old('message') }}</textarea>
            <div class="h-hp" aria-hidden="true"><label for="d-web">Ne pas remplir</label><input id="d-web" name="website" tabindex="-1" autocomplete="off"></div>
            <label class="h-check"><input type="checkbox" name="consent" value="1" required @checked(old('consent'))> <span>J’accepte d’être recontacté(e) par l’équipe MX-CONNECT au sujet de cette demande.</span></label>
            @error('consent')<p class="h-err">{{ $message }}</p>@enderror
            <button class="h-btn h-btn--grad" style="margin-top:20px;width:100%">Envoyer ma demande {!! $arrow !!}</button>
          </form>
          @endif
        </div>
      </div>
    </section>
  </main>

  @include('home.partials.footer')
</body>
</html>
