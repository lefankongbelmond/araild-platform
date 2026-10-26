<x-layouts.tenant :title="$c->subject">
  <a href="{{ route('conversations.index') }}" class="text-sm text-pine hover:underline">← Messagerie</a>
  <div class="mt-2 flex flex-wrap items-start justify-between gap-3">
    <div><h1 class="text-xl font-semibold tracking-tight">{{ $c->subject }}</h1>
      <p class="text-sm text-ink/60">Avec {{ $c->account?->name }}@if($c->adhesion && \App\Support\TenantAccess::can('online-adhesions.show')) · <a class="text-pine underline" href="{{ route('online-adhesions.show', $c->adhesion->reference) }}">dossier {{ $c->adhesion->reference }}</a>@endif</p></div>
    <form method="POST" action="{{ route('conversations.toggle', $c->id) }}">@csrf<button class="rounded-md border border-ink/15 bg-white px-3 py-1.5 text-sm">{{ $c->status === 'open' ? 'Clore' : 'Rouvrir' }}</button></form>
  </div>
  <div class="mt-6 max-w-3xl space-y-3">
    @foreach ($c->messages as $m)
      <div class="flex {{ $m->sender === 'mutual' ? 'justify-end' : 'justify-start' }}">
        <div class="max-w-[80%] whitespace-pre-line rounded-2xl px-4 py-2 text-sm {{ $m->sender === 'mutual' ? 'bg-pine text-white' : 'border border-ink/10 bg-white' }}">{{ $m->body }}
          <div class="mt-1 text-[11px] opacity-70">{{ $m->sender === 'mutual' ? 'La mutuelle' : $c->account?->name }} · {{ $m->created_at->format('d/m/Y H:i') }}</div></div>
      </div>
    @endforeach
    <span id="fin"></span>
    <form method="POST" action="{{ route('conversations.reply', $c->id) }}" class="flex gap-2 pt-2">
      @csrf
      <textarea name="body" rows="2" required maxlength="3000" class="flex-1 rounded-md border border-ink/15 px-3 py-2 text-sm" placeholder="Votre réponse au nom de la mutuelle…"></textarea>
      <button class="rounded-md bg-pine px-4 py-2 text-sm font-medium text-white hover:bg-navy">Envoyer</button>
    </form>
    <p class="text-xs text-ink/45">Le membre reçoit une notification. Ne demandez pas d’information médicale par ce canal.</p>
  </div>
</x-layouts.tenant>
