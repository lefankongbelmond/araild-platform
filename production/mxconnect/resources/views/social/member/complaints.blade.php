<x-layouts.social title="{{ __('Réclamations et assistance') }}" active="mon-adhesion">
  @include('social.member._head')
  @php $C = \App\Models\Tenant\Complaint::CATEGORIES; $S = \App\Models\Tenant\Complaint::STATUS_LABELS; @endphp
  <section class="mx-card mx-card__pad">
    <h2 class="mx-card__title" style="font-size:16px">{{ __('Écrire à ma mutuelle') }}</h2>
    <form method="POST" action="{{ route('social.member.complaints.store', $mutual->slug) }}" class="mx-stack" style="gap:10px;margin-top:8px">@csrf
      <label class="mx-field"><span class="mx-label">{{ __('Type de demande') }}</span><select class="mx-input" name="category">@foreach ($C as $k => $t)<option value="{{ $k }}" @selected(old('category') === $k)>{{ $t }}</option>@endforeach</select></label>
      <label class="mx-field"><span class="mx-label">{{ __('Objet') }}</span><input class="mx-input" name="subject" required maxlength="200" value="{{ old('subject') }}"></label>
      <label class="mx-field"><span class="mx-label">{{ __('Votre message') }}</span><textarea class="mx-input" name="description" required rows="4" maxlength="3000">{{ old('description') }}</textarea></label>
      <p class="mx-xs mx-muted">{{ __('N’écrivez pas de détail médical : la mutuelle vous recontactera si nécessaire.') }}</p>
      <div><button class="mx-btn mx-btn--primary">{{ __('Envoyer') }}</button></div>
    </form>
  </section>
  <h2 style="font-size:16px;margin:16px 0 8px">{{ __('Mes demandes') }}</h2>
  @forelse ($items as $c)
    <a class="mx-card mx-card__pad" href="{{ route('social.member.complaint', [$mutual->slug, $c->id]) }}" style="display:block;color:inherit;margin-bottom:8px">
      <div class="mx-between" style="gap:8px;flex-wrap:wrap"><b>{{ $c->subject }}</b><span class="mx-badge mx-badge--{{ in_array($c->status, ['resolved', 'closed'], true) ? 'green' : 'warn' }}">{{ __($S[$c->status] ?? $c->status) }}</span></div>
      <p class="mx-xs mx-muted">{{ $c->ref }} · {{ __($C[$c->category] ?? __('Réclamation')) }} · {{ $c->received_on?->format('d/m/Y') }}</p>
    </a>
  @empty
    <p class="mx-small mx-muted">{{ __('Aucune demande.') }}</p>
  @endforelse
</x-layouts.social>
