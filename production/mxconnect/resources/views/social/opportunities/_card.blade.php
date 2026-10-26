{{-- Carte d'opportunité (listes). $o = opportunité, chargée avec mutual.profile. --}}
@php $open = $o->isOpen(); @endphp
<a class="mx-card mx-listcard" href="{{ route('social.opportunity', $o) }}">
  <x-mx.avatar :src="$o->mutual?->profile?->logoUrl()" :name="$o->mutual?->name ?? '?'" org />
  <span class="mx-listcard__body">
    <span class="mx-tags">
      <span class="mx-badge mx-badge--blue">{{ $o->categoryLabel() }}</span>
      @if ($o->contract_type)<span class="mx-badge">{{ \App\Models\Social\Opportunity::CONTRACTS[$o->contract_type] ?? $o->contract_type }}</span>@endif
      @unless ($open)<span class="mx-status mx-status--closed">{{ $o->availabilityLabel() }}</span>@endunless
      @if ($o->is_demo || $o->mutual?->profile?->is_demo)<span class="mx-demo-ribbon">Démo</span>@endif
      @if ($o->status !== 'published')<span class="mx-badge mx-badge--warn">{{ $o->status === 'draft' ? 'Brouillon' : 'Masquée par la modération' }}</span>@endif
    </span>
    <span class="mx-listcard__title">{{ $o->title }}</span>
    <span class="mx-meta">
      <span><x-mx.icon name="building" :size="14"/> {{ $o->mutual?->name }}@if($o->mutual?->isVerified()) <x-mx.icon name="verified" :size="13"/>@endif</span>
      @if ($o->place())<span><x-mx.icon name="pin" :size="14"/> {{ $o->place() }}</span>@endif
      @if ($o->workplace)<span>{{ \App\Models\Social\Opportunity::WORKPLACES[$o->workplace] }}</span>@endif
      @if ($o->deadline_on)<span><x-mx.icon name="calendar" :size="14"/> Avant le {{ $o->deadline_on->format('d/m/Y') }}</span>@endif
    </span>
  </span>
</a>
