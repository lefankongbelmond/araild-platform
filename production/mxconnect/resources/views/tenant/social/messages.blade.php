@php $st = ['new' => ['Nouveau', 'clay'], 'read' => ['Lu', 'pine'], 'closed' => ['Traité', 'ink/50']]; @endphp
<x-layouts.tenant title="Messages reçus">
  <a href="{{ route('social-presence.index') }}" class="text-sm text-pine hover:underline">← Réseau social</a>
  <h1 class="mt-2 text-2xl font-semibold tracking-tight">Messages reçus</h1>
  <p class="mt-1 text-sm text-ink/60">Demandes envoyées depuis votre page publique. Données personnelles : à traiter uniquement pour répondre au demandeur.</p>
  <div class="mt-6 space-y-3">
    @forelse ($messages as $m)
      @php [$l, $c] = $st[$m->status] ?? [$m->status, 'ink/50']; @endphp
      <div class="rounded-lg border border-ink/10 bg-white p-4 text-sm">
        <div class="flex flex-wrap items-center justify-between gap-2">
          <div><b>{{ $m->name }}</b> · {{ \App\Models\Social\ContactRequest::SUBJECTS[$m->subject] ?? $m->subject }} <span class="ml-2 rounded-full border border-{{ $c }}/30 px-2 py-0.5 text-xs text-{{ $c }}">{{ $l }}</span></div>
          <span class="text-xs text-ink/45">{{ $m->created_at->format('d/m/Y H:i') }}</span>
        </div>
        <p class="mt-2 whitespace-pre-line">{{ $m->message }}</p>
        <div class="mt-3 flex flex-wrap items-center justify-between gap-2 text-xs">
          <span class="text-ink/60">@if($m->email)<a class="text-pine hover:underline" href="mailto:{{ $m->email }}">{{ $m->email }}</a>@endif @if($m->phone) · <a class="text-pine hover:underline" href="tel:{{ preg_replace('/\s+/', '', $m->phone) }}">{{ $m->phone }}</a>@endif</span>
          <span class="flex gap-2">
            @foreach (['read' => 'Marquer lu', 'closed' => 'Marquer traité'] as $k => $lbl)
              @if ($m->status !== $k)<form method="POST" action="{{ route('social-presence.messages.status', $m->id) }}">@csrf<input type="hidden" name="status" value="{{ $k }}"><button class="rounded border border-ink/15 px-2 py-1 hover:border-pine">{{ $lbl }}</button></form>@endif
            @endforeach
          </span>
        </div>
      </div>
    @empty
      <p class="rounded-lg border border-ink/10 bg-white px-4 py-10 text-center text-sm text-ink/45">Aucun message pour le moment.</p>
    @endforelse
  </div>
  <div class="mt-4">{{ $messages->links() }}</div>
</x-layouts.tenant>
