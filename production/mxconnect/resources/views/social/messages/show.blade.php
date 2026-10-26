<x-layouts.social :title="$c->subject" active="messages">
  <a class="mx-small" href="{{ route('social.messages') }}"><x-mx.icon name="arrow-left" :size="14"/> Messages</a>
  <section class="mx-card">
    <div class="mx-card__head"><div class="mx-row" style="gap:10px"><x-mx.avatar :src="$c->mutual?->profile?->logoUrl()" :name="$c->mutual?->name ?? '?'" org size="sm" />
      <div><b>{{ $c->mutual?->name }}</b><div class="mx-xs mx-muted">{{ $c->subject }}@if($c->adhesion) · <a href="{{ route('social.adhesion', $c->adhesion) }}">dossier {{ $c->adhesion->reference }}</a>@endif</div></div></div></div>
    <div class="mx-thread">
      @foreach ($c->messages as $m)
        <div class="mx-bubble {{ $m->sender === 'account' ? 'mx-bubble--me' : 'mx-bubble--them' }}">{{ $m->body }}<small>{{ $m->sender === 'account' ? 'Vous' : $c->mutual?->name }} · {{ $m->created_at->format('d/m/Y H:i') }}</small></div>
      @endforeach
      <span id="fin"></span>
    </div>
    <form method="POST" action="{{ route('social.messages.reply', $c) }}" class="mx-row" style="padding:0 16px 16px">
      @csrf
      <label class="mx-sr" for="r-body">Votre réponse</label>
      <textarea class="mx-textarea" id="r-body" name="body" required maxlength="3000" rows="2" placeholder="Votre message…" style="min-height:44px"></textarea>
      <button class="mx-btn mx-btn--primary" aria-label="Envoyer"><x-mx.icon name="send" :size="16"/></button>
    </form>
    <p class="mx-xs mx-muted" style="padding:0 16px 14px">N’envoyez pas d’information médicale par message : la mutuelle vous indiquera le canal adapté si nécessaire.</p>
  </section>
</x-layouts.social>
