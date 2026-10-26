{{-- Bandeau horizontal du fil : créer une publication + organisations (suivies d'abord). --}}
@php $viewer = auth('member')->user(); $strip = app(\App\Services\Social\FeedService::class)->strip($viewer, 10); @endphp
@if ($strip->isNotEmpty() || $viewer)
<section class="mx-strip" aria-label="Organisations du réseau">
  <div class="mx-strip__track">
    @if ($viewer)
      <a class="mx-strip__card mx-strip__card--create" href="#composer" data-open-composer>
        <span class="mx-strip__me">@if($viewer->avatarUrl())<img src="{{ $viewer->avatarUrl() }}" alt="" loading="lazy">@else<span>{{ mb_strtoupper(mb_substr($viewer->first_name, 0, 1) . mb_substr($viewer->last_name, 0, 1)) }}</span>@endif</span>
        <span class="mx-strip__plus"><x-mx.icon name="plus" :size="20"/></span>
        <span class="mx-strip__label">Créer une publication</span>
      </a>
    @endif
    @foreach ($strip as $m)
      @php $p = $m->profile; @endphp
      <a class="mx-strip__card" href="{{ route('social.mutual', $m->slug) }}" @if($p?->cover_path) style="background-image:url('{{ $p->coverUrl() }}')" @endif
         aria-label="{{ $m->name }}{{ $m->recent_posts ? ' — ' . $m->recent_posts . ' publication(s) cette semaine' : '' }}">
        <span class="mx-strip__logo">@if($p?->logo_path)<img src="{{ $p->logoUrl() }}" alt="" loading="lazy">@else<span>{{ mb_strtoupper(mb_substr($m->name, 0, 2)) }}</span>@endif</span>
        @if ($m->recent_posts)<span class="mx-strip__count" title="Publications des 7 derniers jours">{{ $m->recent_posts > 9 ? '9+' : $m->recent_posts }}</span>@endif
        @if ($p?->is_demo)<span class="mx-strip__demo">Démo</span>@endif
        <span class="mx-strip__name">{{ $m->name }}</span>
      </a>
    @endforeach
    <a class="mx-strip__card mx-strip__card--more" href="{{ route('social.mutuals') }}"><x-mx.icon name="building" :size="26"/><span class="mx-strip__label">Toutes les organisations</span></a>
  </div>
</section>
@endif
