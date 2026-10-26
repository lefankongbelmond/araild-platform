{{-- Carte d'organisation (design Lot 2). $m = Mutual avec profile (et country). --}}
@php
  $pf = $m->profile;
  $cat = \App\Models\Social\MutualProfile::CATEGORIES[$pf?->category ?? 'mutuelle'] ?? 'Mutuelle de santé';
  $place = collect([$m->city, $m->region])->filter()->implode(', ');
@endphp
<a class="mx-card mx-ocard" href="{{ route('social.mutual', $m->slug) }}">
  <span class="mx-ocard__cover" @if($pf?->cover_path) style="background-image:url('{{ $pf->coverUrl() }}')" @endif></span>
  <span class="mx-ocard__logo"><x-mx.avatar :src="$pf?->logoUrl()" :name="$m->name" org size="lg" /></span>
  <span class="mx-ocard__body">
    <span class="mx-ocard__name">{{ $m->name }}@if($m->isVerified()) <span class="mx-verified" title="Organisation vérifiée par l’administration du réseau"><x-mx.icon name="verified" :size="16"/></span>@endif
      @if ($pf?->is_demo)<span class="mx-demo-ribbon">Démo</span>@endif</span>
    <span class="mx-xs mx-muted">{{ $cat }}@if($pf?->owner_account_id) · page de membre @endif</span>
    @if ($pf?->tagline)<span class="mx-small">{{ \Illuminate\Support\Str::limit($pf->tagline, 90) }}</span>@endif
    <span class="mx-meta">
      @if ($place)<span><x-mx.icon name="pin" :size="13"/> {{ $place }}</span>@endif
      <span><x-mx.icon name="users" :size="13"/> {{ number_format((int) $pf?->followers_count, 0, ',', ' ') }} abonné(s)</span>
      @isset($m->projects_count)@if($m->projects_count)<span><x-mx.icon name="folder" :size="13"/> {{ $m->projects_count }} projet(s)</span>@endif @endisset
    </span>
    @if (! empty($pf?->domains))<span class="mx-dtags">@foreach ($pf->domains as $d)@if(\App\Support\SocialDomains::label($d))<span class="mx-dtag">{{ \App\Support\SocialDomains::label($d) }}</span>@endif @endforeach</span>@endif
  </span>
</a>
