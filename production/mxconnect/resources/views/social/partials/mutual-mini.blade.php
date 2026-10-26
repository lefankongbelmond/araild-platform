@php $p = $m->profile; $following = isset($followMap) ? isset($followMap[$m->id]) : false; @endphp
<div class="mx-mini__item">
  <a href="{{ route('social.mutual', $m->slug) }}"><x-mx.avatar :src="$p?->logoUrl()" :name="$m->name" org /></a>
  <div class="mx-grow">
    <a href="{{ route('social.mutual', $m->slug) }}" style="color:inherit"><b class="mx-truncate">{{ $m->name }} @if($m->isVerified())<span class="mx-verified" title="Organisation vérifiée par l’administration du réseau"><x-mx.icon name="verified" :size="15"/></span>@endif</b></a>
    <span class="mx-xs mx-muted">{{ collect([$m->city, $m->region])->filter()->implode(', ') ?: ($m->country?->name) }} · {{ number_format($p->followers_count ?? 0, 0, ',', ' ') }} abonné(s)</span>
  </div>
  @auth('member')
    <button class="mx-btn mx-btn--soft mx-btn--sm" data-follow="{{ route('social.follow', $m->slug) }}" @class(['is-on' => $following])>{{ $following ? 'Suivi' : 'Suivre' }}</button>
  @endauth
</div>
