<x-layouts.social title="{{ __('Mes pages') }}" active="pages">
  <section class="mx-card mx-card__pad mx-stack">
    <div class="mx-row mx-row--wrap" style="justify-content:space-between;gap:10px">
      <div>
        <h1 style="font-size:22px">{{ __('Mes pages') }}</h1>
        <p class="mx-small mx-muted">{{ __('Créez la page de votre association, de votre établissement de santé ou de votre organisation : les membres peuvent la suivre et voir ses publications.') }}</p>
      </div>
      @if ($pages->count() < $max)
        <a class="mx-btn mx-btn--primary" href="{{ route('social.pages.create') }}"><x-mx.icon name="plus" :size="16"/> {{ __('Créer une page') }}</a>
      @endif
    </div>
  </section>

  @forelse ($pages as $m)
    <article class="mx-card mx-card__pad mx-row" style="gap:14px;align-items:center">
      <a href="{{ route('social.mutual', $m->slug) }}"><x-mx.avatar :src="$m->profile?->logoUrl()" :name="$m->name" org size="lg" /></a>
      <div class="mx-grow" style="min-width:0">
        <a href="{{ route('social.mutual', $m->slug) }}" style="color:inherit"><b style="font-size:17px">{{ $m->name }}</b></a>
        <p class="mx-small mx-muted">{{ __(\App\Models\Social\MutualProfile::CATEGORIES[$m->profile?->category] ?? __('Organisation')) }}@if($m->city) · {{ $m->city }}@endif</p>
        <p class="mx-xs mx-muted">{{ __(':count abonné(s)', ['count' => \App\Support\Locales::number((int) $m->profile?->followers_count)]) }} · {{ __(':count publication(s)', ['count' => \App\Support\Locales::number((int) $m->profile?->posts_count)]) }}
          @if ($m->status === 'suspended') · <b style="color:var(--mx-red,#c62828)">{{ __('Suspendue par la modération') }}</b>@endif</p>
      </div>
      <div class="mx-row mx-row--wrap" style="gap:6px">
        <a class="mx-btn mx-btn--ghost mx-btn--sm" href="{{ route('social.mutual', $m->slug) }}">{{ __('Voir') }}</a>
        @if ($m->profile?->owner_account_id && \App\Services\Social\PageService::canAdminister(auth('member')->user(), $m))
          <a class="mx-btn mx-btn--ghost mx-btn--sm" href="{{ route('social.pages.edit', $m->slug) }}"><x-mx.icon name="edit" :size="14"/> {{ __('Modifier') }}</a>
        @endif
        <a class="mx-btn mx-btn--ghost mx-btn--sm" href="{{ route('social.manage.projects', $m->slug) }}"><x-mx.icon name="target" :size="14"/> {{ __('Projets') }}</a>
        <a class="mx-btn mx-btn--ghost mx-btn--sm" href="{{ route('social.manage.opportunities', $m->slug) }}"><x-mx.icon name="briefcase" :size="14"/> {{ __('Opportunités') }}</a>
        @if (\App\Services\Social\PageService::canAdminister(auth('member')->user(), $m))
          @php $newReq = \App\Models\Social\SocialRequest::where('mutual_id', $m->id)->where('status', 'submitted')->count(); @endphp
          <a class="mx-btn mx-btn--ghost mx-btn--sm" href="{{ route('social.manage.requests', $m->slug) }}"><x-mx.icon name="handshake" :size="14"/> {{ __('Demandes') }} @if($newReq)<span class="mx-badge mx-badge--blue">{{ $newReq }}</span>@endif</a>
        @endif
      </div>
    </article>
  @empty
    @include('social.partials.empty', ['icon' => 'building', 'title' => 'Vous ne gérez aucune page', 'text' => 'Créez une page pour faire connaître votre organisation et publier en son nom.'])
  @endforelse

  <p class="mx-xs mx-muted" style="padding:0 6px">{{ __('Une page n’est pas une mutuelle gérée sur MX-CONNECT : elle ne reçoit pas de demandes d’adhésion. Le badge « vérifié » est réservé aux organisations validées par l’administration du réseau. :max pages maximum par compte.', ['max' => $max]) }}</p>
</x-layouts.social>
