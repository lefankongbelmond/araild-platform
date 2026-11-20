<x-layouts.social title="{{ $c->ref }}" active="mon-adhesion">
  @include('social.member._head')
  @php $C = \App\Models\Tenant\Complaint::CATEGORIES; $S = \App\Models\Tenant\Complaint::STATUS_LABELS; @endphp
  <a class="mx-small" href="{{ route('social.member.complaints', $mutual->slug) }}">{{ __('← Mes demandes') }}</a>
  <section class="mx-card mx-card__pad" style="margin-top:8px">
    <div class="mx-between" style="gap:8px;flex-wrap:wrap"><h1 style="font-size:18px;margin:0">{{ $c->subject }}</h1><span class="mx-badge mx-badge--{{ in_array($c->status, ['resolved', 'closed'], true) ? 'green' : 'warn' }}">{{ __($S[$c->status] ?? $c->status) }}</span></div>
    <p class="mx-xs mx-muted">{{ $c->ref }} · {{ __($C[$c->category] ?? __('Réclamation')) }} · {{ __('envoyée le :date', ['date' => $c->received_on?->format('d/m/Y')]) }}@if ($c->due_on) · {{ __('réponse prévue avant le :date', ['date' => $c->due_on->format('d/m/Y')]) }}@endif</p>
    <div class="mx-thread" style="margin-top:12px">
      <div class="mx-bubble mx-bubble--me">{{ $c->description }}</div>
      @foreach ($c->messages as $msg)
        <div class="mx-bubble mx-bubble--{{ $msg->author === 'member' ? 'me' : 'them' }}">{{ $msg->body }}<span class="mx-xs mx-muted" style="display:block">{{ $msg->author === 'member' ? __('Vous') : __('La mutuelle') }} · {{ $msg->created_at?->format('d/m/Y H:i') }}</span></div>
      @endforeach
      @if ($c->resolution)<div class="mx-bubble mx-bubble--them"><b>{{ __('Réponse de la mutuelle :') }}</b> {{ $c->resolution }}</div>@endif
    </div>
    @if ($c->status !== 'closed')
      <form method="POST" action="{{ route('social.member.complaint.reply', [$mutual->slug, $c->id]) }}" class="mx-stack" style="gap:8px;margin-top:12px">@csrf
        <textarea class="mx-input" name="body" rows="3" required maxlength="3000" placeholder="{{ __('Votre message') }}" aria-label="{{ __('Votre message') }}"></textarea>
        <div><button class="mx-btn mx-btn--primary mx-btn--sm">{{ __('Envoyer') }}</button></div>
      </form>
    @endif
  </section>
</x-layouts.social>
