@php
  $viewer = auth('member')->user();
  $followUrl = route('social.follow', $mutual->slug);
  $place = collect([$mutual->city, $mutual->region, $mutual->country?->name])->filter()->implode(', ');
  $catLabel = \App\Models\Social\MutualProfile::CATEGORIES[$profile->category ?? 'mutuelle'] ?? 'Mutuelle de santé';
  $isPage = $isPage ?? false; $isOwner = $isOwner ?? false;
@endphp
<x-layouts.social :title="$mutual->name" active="organisations">
  <x-slot:head>
    <meta property="og:title" content="{{ $mutual->name }} · MX-CONNECT">
    <meta property="og:description" content="{{ $profile->tagline ?: $catLabel . ($place ? ' — ' . $place : '') }}">
  </x-slot:head>

  <section class="mx-card" style="overflow:hidden">
    <div @class(['mx-cover', 'has-img' => $profile->cover_path]) @if($profile->cover_path) style="background-image:url('{{ $profile->coverUrl() }}')" @endif></div>
    <div class="mx-profilehead">
      <div class="mx-profilehead__top">
        <x-mx.avatar :src="$profile->logoUrl()" :name="$mutual->name" org size="lg" />
        <div class="mx-profilehead__actions">
          @if ($viewer)
            <button class="mx-btn mx-btn--primary" data-follow="{{ $followUrl }}" @class(['is-on' => $following])>{{ $following ? 'Suivi' : 'Suivre' }}</button>
          @else
            <a class="mx-btn mx-btn--primary" href="{{ route('social.login') }}">Suivre</a>
          @endif
          @if ($isOwner)
            <a class="mx-btn mx-btn--ghost" href="{{ route('social.pages.edit', $mutual->slug) }}"><x-mx.icon name="edit" :size="16"/> Modifier la page</a>
          @else
            <a class="mx-btn mx-btn--ghost" href="{{ route('social.mutual', [$mutual->slug, 'onglet' => 'contact']) }}"><x-mx.icon name="mail" :size="16"/> Contacter</a>
          @endif
          @if ($offer)<a class="mx-btn mx-btn--ghost" href="{{ route('social.mutual', [$mutual->slug, 'onglet' => 'offres']) }}"><x-mx.icon name="gift" :size="16"/> Offres</a>@endif
          @if ($canPublish ?? false)
            <a class="mx-btn mx-btn--ghost" href="{{ route('social.manage.projects', $mutual->slug) }}"><x-mx.icon name="target" :size="16"/> Projets et offres</a>
          @endif
          <details class="mx-menu">
            <summary class="mx-btn mx-btn--ghost" aria-label="Plus d'options"><x-mx.icon name="more" :size="16"/></summary>
            <div class="mx-menu__list">
              <button type="button" data-copy="{{ route('social.mutual', $mutual->slug) }}"><x-mx.icon name="link" :size="18"/> Copier le lien</button>
              @if ($viewer)
                <form method="POST" action="{{ route('social.mute', $mutual->slug) }}">@csrf<button><x-mx.icon name="eye-off" :size="18"/> {{ $muted ? 'Réafficher dans mon fil' : 'Masquer de mon fil' }}</button></form>
                @unless (($canPublish ?? false) || $profile->is_demo)
                  <a href="{{ route('social.request.create', ['type' => 'engagement', 'page' => $mutual->slug]) }}"><x-mx.icon name="hand" :size="18"/> Je m’engage auprès de cette organisation</a>
                  <a href="{{ route('social.request.create', ['type' => 'support', 'page' => $mutual->slug]) }}"><x-mx.icon name="handshake" :size="18"/> Soutenir ou devenir partenaire</a>
                @endunless
                @unless ($canPublish ?? false)
                  <button type="button" class="is-danger" data-report="{{ route('social.mutual.report', $mutual->slug) }}"><x-mx.icon name="flag" :size="18"/> Signaler cette page</button>
                @endunless
              @endif
            </div>
          </details>
        </div>
      </div>
      <div>
        <h1 style="font-size:24px;display:flex;align-items:center;gap:8px;flex-wrap:wrap">{{ $mutual->name }}
          @if ($mutual->isVerified())<span class="mx-verified" title="Organisation vérifiée par l’administration du réseau MX-CONNECT"><x-mx.icon name="verified" :size="22"/></span>@endif
          @if ($profile->is_demo)<span class="mx-demo-ribbon" title="Organisation fictive de démonstration">Démo</span>@endif
        </h1>
        <p class="mx-small mx-muted">{{ $catLabel }}@if($isPage) · Page créée par un membre @endif @if($place) · <x-mx.icon name="pin" :size="14"/> {{ $place }}@endif</p>
        @if ($profile->tagline)<p style="margin-top:8px">{{ $profile->tagline }}</p>@endif
      </div>
      <div class="mx-stats">
        <span><b data-followers="{{ $followUrl }}">{{ number_format($stats['followers'], 0, ',', ' ') }}</b> abonné(s)</span>
        <span><b>{{ number_format($stats['posts'], 0, ',', ' ') }}</b> publication(s)</span>
        <span><b>{{ $stats['events'] }}</b> événement(s)</span>
      </div>
      <p class="mx-xs mx-muted"><x-mx.icon name="shield" :size="13"/> @if($isPage) S’abonner à cette page permet de voir ses actualités dans votre fil. @else Suivre une mutuelle permet de voir ses actualités. Ce n'est pas une adhésion. @endif</p>
    </div>
    <nav class="mx-tabs" aria-label="Sections de la page">
      @foreach ($tabs as $k => $v)
        <a href="{{ route('social.mutual', [$mutual->slug, 'onglet' => $k === 'publications' ? null : $k]) }}" @class(['is-active' => $tab === $k])>{{ $v }}</a>
      @endforeach
    </nav>
  </section>

  @if ($tab === 'a-propos')
      <section class="mx-card mx-card__pad mx-stack">
        <h2 class="mx-card__title">À propos</h2>
        @if ($profile->description)<p>{!! nl2br(e($profile->description)) !!}</p>@endif
        @if ($profile->mission)<h3 style="font-size:15px">Mission</h3><p>{!! nl2br(e($profile->mission)) !!}</p>@endif
        @if (! $profile->description && ! $profile->mission)<p class="mx-muted mx-small">Cette mutuelle n'a pas encore complété sa présentation.</p>@endif
        @if (! empty($profile->services))
          <h3 style="font-size:15px">Services</h3>
          <div class="mx-chips" style="flex-wrap:wrap">@foreach ($profile->services as $s)<span class="mx-chip">{{ $s }}</span>@endforeach</div>
        @endif
        <dl class="mx-kv">
          <dt>Catégorie</dt><dd>{{ $catLabel }}</dd>
          @if ($place)<dt>Localisation</dt><dd>{{ $place }}</dd>@endif
          @if ($profile->coverage_zone)<dt>Zone couverte</dt><dd>{{ $profile->coverage_zone }}</dd>@endif
          <dt>Statut réseau</dt><dd>{{ $isPage ? 'Page créée par un membre (non vérifiée)' : ($mutual->isVerified() ? 'Agréée sur MX-CONNECT' : 'En cours de vérification') }}</dd>
          @if ($mutual->approved_at)<dt>Membre depuis</dt><dd>{{ $mutual->approved_at->translatedFormat('F Y') }}</dd>@endif
        </dl>
      </section>
  @elseif ($tab === 'offres')
      <section class="mx-card mx-card__pad mx-stack">
        <h2 class="mx-card__title">Offres de couverture</h2>
        @if ($profile->is_demo)<p class="mx-alert mx-alert--warn">Organisation fictive de démonstration : l’adhésion en ligne n’est pas possible.</p>@endif
        @if ($offer)
          @php $cur = $offer->currency; @endphp
          <dl class="mx-kv">
            @if ($offer->monthly_contribution_min_minor !== null)
              <dt>Cotisation mensuelle</dt>
              <dd><b>{{ $cur ? $cur->format($offer->monthly_contribution_min_minor) : $offer->monthly_contribution_min_minor }}</b>@if ($offer->monthly_contribution_max_minor > $offer->monthly_contribution_min_minor) – {{ $cur ? $cur->format($offer->monthly_contribution_max_minor) : $offer->monthly_contribution_max_minor }}@endif</dd>
            @endif
            @if ($offer->membership_fee_min_minor !== null)<dt>Droit d'adhésion</dt><dd>{{ $cur ? $cur->format($offer->membership_fee_min_minor) : $offer->membership_fee_min_minor }}</dd>@endif
            @if ($offer->coverage_min !== null)<dt>Taux de prise en charge</dt><dd>{{ rtrim(rtrim(number_format($offer->coverage_min, 2, ',', ''), '0'), ',') }} % – {{ rtrim(rtrim(number_format($offer->coverage_max, 2, ',', ''), '0'), ',') }} %</dd>@endif
            <dt>Garanties</dt><dd>{{ $offer->guarantees_count }}</dd>
          </dl>
          @if ($offer->highlights)<ul class="mx-small" style="padding-left:18px">@foreach ((array) $offer->highlights as $h)<li>{{ is_array($h) ? implode(' — ', $h) : $h }}</li>@endforeach</ul>@endif
          <p class="mx-xs mx-muted">Informations publiées par la mutuelle{{ $offer->synced_at ? ', mises à jour le ' . $offer->synced_at->format('d/m/Y') : '' }}. Le détail des garanties et les conditions sont à confirmer auprès de la mutuelle.</p>
          <div class="mx-row mx-row--wrap">
            @if (! $profile->is_demo)<a class="mx-btn mx-btn--primary" href="{{ route('social.adhesion.create', $mutual->slug) }}">Demander l’adhésion</a>@endif
            <a class="mx-btn mx-btn--ghost" href="{{ route('comparator') }}">Comparer les offres</a>
          </div>
        @else
          <p class="mx-muted mx-small">Cette mutuelle n'a pas encore publié d'offre en ligne. Vous pouvez la contacter pour connaître ses formules.</p>
          <p class="mx-row mx-row--wrap">
            @if (! $profile->is_demo)<a class="mx-btn mx-btn--primary" href="{{ route('social.adhesion.create', $mutual->slug) }}">Demander l’adhésion</a>@endif
            <a class="mx-btn mx-btn--ghost" href="{{ route('social.mutual', [$mutual->slug, 'onglet' => 'contact']) }}">Poser une question</a>
          </p>
        @endif
      </section>
  @elseif ($tab === 'projets')
      <section class="mx-stack">
        <div class="mx-row mx-between mx-row--wrap" style="gap:8px;padding:0 4px">
          <h2 class="mx-card__title">Projets de {{ $mutual->name }}</h2>
          @if ($canPublish ?? false)<a class="mx-btn mx-btn--primary mx-btn--sm" href="{{ route('social.manage.project.create', $mutual->slug) }}"><x-mx.icon name="plus" :size="16"/> Nouveau projet</a>@endif
        </div>
        @forelse ($projects as $p)
          @include('social.projects._card', ['p' => $p->setRelation('mutual', $mutual)])
        @empty
          @include('social.partials.empty', ['icon' => 'target', 'title' => 'Aucun projet publié', 'text' => $mutual->name . ' n’a pas encore présenté de projet.'])
        @endforelse
        {{ $projects->links('social.partials.pagination') }}
      </section>
  @elseif ($tab === 'opportunites')
      <section class="mx-stack">
        <div class="mx-row mx-between mx-row--wrap" style="gap:8px;padding:0 4px">
          <h2 class="mx-card__title">Emplois et opportunités</h2>
          @if ($canPublish ?? false)<a class="mx-btn mx-btn--primary mx-btn--sm" href="{{ route('social.manage.opportunity.create', $mutual->slug) }}"><x-mx.icon name="plus" :size="16"/> Nouvelle offre</a>@endif
        </div>
        @forelse ($opportunities as $o)
          @include('social.opportunities._card', ['o' => $o->setRelation('mutual', $mutual)])
        @empty
          @include('social.partials.empty', ['icon' => 'briefcase', 'title' => 'Aucune offre ouverte', 'text' => $mutual->name . ' n’a pas d’offre ouverte pour le moment.', 'action' => ['Toutes les opportunités du réseau', route('social.opportunities')]])
        @endforelse
        {{ $opportunities->links('social.partials.pagination') }}
      </section>
  @elseif ($tab === 'photos')
      <section class="mx-card mx-card__pad mx-stack">
        <h2 class="mx-card__title">Photos de {{ $mutual->name }}</h2>
        @if ($photos->isEmpty())
          <p class="mx-small mx-muted">Aucune photo publiée pour le moment.</p>
        @else
          <div class="mx-album">
            @foreach ($photos as $ph)
              <a href="{{ route('social.post', $ph->post_id) }}" aria-label="Voir la publication de cette photo"><img src="{{ $ph->thumbUrl() }}" alt="Photo publiée par {{ $mutual->name }}" loading="lazy"></a>
            @endforeach
          </div>
          {{ $photos->links('social.partials.pagination') }}
        @endif
      </section>
  @elseif ($tab === 'contact')
      <section class="mx-card mx-card__pad mx-stack">
        <h2 class="mx-card__title">Contacter {{ $mutual->name }}</h2>
        @if ($profile->phone || $profile->email || $profile->website || $profile->address)
          <dl class="mx-kv">
            @if ($profile->phone)<dt>Téléphone</dt><dd><a href="tel:{{ preg_replace('/\s+/', '', $profile->phone) }}">{{ $profile->phone }}</a></dd>@endif
            @if ($profile->email)<dt>E-mail</dt><dd><a href="mailto:{{ $profile->email }}">{{ $profile->email }}</a></dd>@endif
            @if ($profile->website)<dt>Site web</dt><dd><a href="{{ $profile->website }}" target="_blank" rel="noopener nofollow">{{ preg_replace('#^https?://#', '', $profile->website) }}</a></dd>@endif
            @if ($profile->address)<dt>Adresse</dt><dd>{{ $profile->address }}</dd>@endif
          </dl>
        @endif
        @if ($isPage)
          @if (! ($profile->phone || $profile->email || $profile->website || $profile->address))<p class="mx-small mx-muted">Cette page n’a pas encore indiqué de coordonnées.</p>@endif
          <p class="mx-xs mx-muted">Page créée par un membre du réseau : utilisez les coordonnées ci-dessus, ou commentez ses publications.</p>
        @elseif ($viewer)
        <form method="POST" action="{{ route('social.messages.start', $mutual->slug) }}" class="mx-stack">
          @csrf
          <p class="mx-small mx-muted">Votre message est privé : seule {{ $mutual->name }} le lit, et sa réponse arrive dans vos <a href="{{ route('social.messages') }}">Messages</a>.</p>
          <div class="mx-field"><label class="mx-label" for="pm-subject">Objet</label><input class="mx-input" id="pm-subject" name="subject" required maxlength="180" value="{{ old('subject') }}" placeholder="Ex. : Question sur les garanties"></div>
          <div class="mx-field"><label class="mx-label" for="pm-body">Message</label><textarea class="mx-textarea" id="pm-body" name="body" required minlength="5" maxlength="3000">{{ old('body') }}</textarea>
            <p class="mx-help">N'indiquez aucune information médicale.</p>@error('body')<p class="mx-error">{{ $message }}</p>@enderror</div>
          <div class="mx-row mx-row--wrap"><button class="mx-btn mx-btn--primary"><x-mx.icon name="send" :size="16"/> Envoyer le message</button>
            @if (! $profile->is_demo)<a class="mx-btn mx-btn--ghost" href="{{ route('social.adhesion.create', $mutual->slug) }}">Demander l’adhésion</a>@endif</div>
        </form>
        @else
        <form method="POST" action="{{ route('social.contact', $mutual->slug) }}" class="mx-stack">
          @csrf
          @unless ($viewer)
            <div class="mx-field"><label class="mx-label" for="ct-name">Votre nom</label><input class="mx-input" id="ct-name" name="name" value="{{ old('name') }}" required maxlength="180">@error('name')<p class="mx-error">{{ $message }}</p>@enderror</div>
          @endunless
          <div class="mx-grid-2">
            <div class="mx-field"><label class="mx-label" for="ct-email">E-mail</label><input class="mx-input" id="ct-email" type="email" name="email" value="{{ old('email', $viewer?->email) }}" maxlength="180">@error('email')<p class="mx-error">{{ $message }}</p>@enderror</div>
            <div class="mx-field"><label class="mx-label" for="ct-phone">Téléphone</label><input class="mx-input" id="ct-phone" name="phone" value="{{ old('phone', $viewer?->phone) }}" maxlength="40">@error('phone')<p class="mx-error">{{ $message }}</p>@enderror</div>
          </div>
          <div class="mx-field"><label class="mx-label" for="ct-subject">Objet</label>
            <select class="mx-select" id="ct-subject" name="subject">
              @foreach (\App\Models\Social\ContactRequest::SUBJECTS as $k => $v)<option value="{{ $k }}" @selected(old('subject', request('sujet')) === $k)>{{ $v }}</option>@endforeach
            </select></div>
          <div class="mx-field"><label class="mx-label" for="ct-msg">Message</label>
            <textarea class="mx-textarea" id="ct-msg" name="message" required minlength="10" maxlength="2000">{{ old('message') }}</textarea>
            <p class="mx-help">N'indiquez aucune information médicale (maladie, traitement, résultats d'examens).</p>
            @error('message')<p class="mx-error">{{ $message }}</p>@enderror</div>
          <label class="mx-check"><input type="checkbox" name="consent" value="1" required> <span>J'accepte que mes coordonnées soient transmises à {{ $mutual->name }} pour qu'elle me réponde.</span></label>
          @error('consent')<p class="mx-error">{{ $message }}</p>@enderror
          <div><button class="mx-btn mx-btn--primary"><x-mx.icon name="send" :size="16"/> Envoyer</button></div>
          <p class="mx-xs mx-muted"><a href="{{ route('social.login') }}">Connectez-vous</a> pour échanger par messagerie privée ou demander l’adhésion en ligne.</p>
        </form>
        @endif
      </section>
  @else
      @if ($tab === 'publications' && ($canPublish ?? false))
        @include('social.partials.composer', ['composerAs' => $mutual->slug, 'composerPage' => $mutual])
      @endif
      @if ($tab === 'publications')
        <div class="mx-chips">
          <a href="{{ route('social.mutual', $mutual->slug) }}" @class(['mx-chip', 'is-active' => ! request('type')])>Tout</a>
          @foreach (['annonce', 'campagne', 'offre', 'document'] as $t)
            <a href="{{ route('social.mutual', [$mutual->slug, 'type' => $t]) }}" @class(['mx-chip', 'is-active' => request('type') === $t])>{{ \App\Models\Social\Post::TYPES[$t] }}</a>
          @endforeach
        </div>
      @endif
      @if ($posts->isEmpty())
        @include('social.partials.empty', ['icon' => $tab === 'evenements' ? 'calendar' : 'list', 'title' => 'Rien pour le moment', 'text' => $mutual->name . ' n’a encore rien publié dans cette rubrique.'])
      @else
        <div class="mx-stack">@include('social.partials.feed')</div>
      @endif
  @endif

  <x-slot:right>
    <section class="mx-card mx-card__pad mx-stack" style="gap:8px">
      <h2 class="mx-card__title">En bref</h2>
      <p class="mx-small">{{ \Illuminate\Support\Str::limit($profile->description ?: ($profile->tagline ?: 'Présentation à venir.'), 220) }}</p>
      @if ($profile->coverage_zone)<p class="mx-small"><x-mx.icon name="pin" :size="14"/> {{ $profile->coverage_zone }}</p>@endif
      @if ($profile->phone)<p class="mx-small"><x-mx.icon name="phone" :size="14"/> {{ $profile->phone }}</p>@endif
      @if ($profile->website)<p class="mx-small"><x-mx.icon name="globe" :size="14"/> <a href="{{ $profile->website }}" target="_blank" rel="noopener nofollow">{{ preg_replace('#^https?://#', '', $profile->website) }}</a></p>@endif
    </section>
    @php $mGroups = \App\Models\Social\Group::where('mutual_id', $mutual->id)->where('status', 'active')->orderByDesc('members_count')->limit(5)->get(); @endphp
    @if ($mGroups->isNotEmpty())
      <section class="mx-card">
        <div class="mx-card__head"><h2 class="mx-card__title">Groupes animés</h2></div>
        <div class="mx-mini">@foreach ($mGroups as $g)<a class="mx-mini__item" href="{{ route('social.group', $g) }}" style="color:inherit"><span class="mx-avatar mx-avatar--sm" style="background:var(--mx-blue-soft);color:var(--mx-blue)"><x-mx.icon name="users" :size="16"/></span><span class="mx-grow"><b class="mx-truncate">{{ $g->name }}</b><span class="mx-xs mx-muted">{{ $g->isPrivate() ? 'Privé' : 'Public' }} · {{ $g->members_count }} membre(s)</span></span></a>@endforeach</div>
      </section>
    @endif
    @include('social.partials.sidebar')
  </x-slot:right>
</x-layouts.social>
