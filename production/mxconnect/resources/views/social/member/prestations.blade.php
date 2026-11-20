<x-layouts.social title="{{ __('Mes prestations') }}" active="mon-adhesion">
  @include('social.member._head')
  @php $S = \App\Services\Tenant\MemberSelfService::PRESTATION_STATUS; $fmt = fn ($v) => $currency->format((int) $v); @endphp
  <form method="GET" class="mx-row" style="gap:8px;margin-bottom:10px">
    <select class="mx-input" name="annee" style="max-width:160px" aria-label="{{ __('Année') }}"><option value="">{{ __('Toutes les années') }}</option>@for ($y = now()->year; $y >= now()->year - 4; $y--)<option value="{{ $y }}" @selected($year === $y)>{{ $y }}</option>@endfor</select>
    <button class="mx-btn mx-btn--soft mx-btn--sm">{{ __('Filtrer') }}</button>
  </form>
  @forelse ($x['items'] as $p)
    @php $who = $p->careClaim?->dependent; @endphp
    <a class="mx-card mx-card__pad" href="{{ route('social.member.prestation', [$mutual->slug, $p->id]) }}" style="display:block;color:inherit;margin-bottom:10px">
      <div class="mx-between" style="gap:8px;flex-wrap:wrap"><b>{{ $p->actType?->label }}</b><span class="mx-badge mx-badge--{{ $p->status === 'validated' ? 'green' : ($p->status === 'rejected' ? 'danger' : 'warn') }}">{{ __($S[$p->status] ?? $p->status) }}</span></div>
      <p class="mx-xs mx-muted">{{ $p->care_date?->format('d/m/Y') }} · {{ $x['providers'][$p->provider_id] ?? 'Établissement' }} · {{ $who ? $who->first_name : 'vous' }} · réf. PC-{{ $p->id }}</p>
      <p class="mx-small" style="margin:4px 0 0">Total {{ $fmt($p->total_minor) }} · <b>mutuelle {{ $fmt($p->mutual_part_minor) }}</b> · votre part {{ $fmt($p->beneficiary_part_minor) }}</p>
    </a>
  @empty
    @include('social.partials.empty', ['icon' => 'file', 'title' => 'Aucune prestation', 'text' => 'Vos soins pris en charge par la mutuelle apparaîtront ici.'])
  @endforelse
  {{ $x['items']->links() }}
</x-layouts.social>
