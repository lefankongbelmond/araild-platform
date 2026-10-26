<x-layouts.network title="Demandes de démonstration">
  <h1 class="text-2xl font-semibold tracking-tight">Demandes de démonstration</h1>
  <p class="mt-1 text-sm text-ink/60">Envoyées depuis la page d’accueil de la plateforme.</p>
  <div class="mt-6 space-y-3">
    @forelse ($items as $d)
      <div class="rounded-lg border border-ink/10 bg-white p-4 text-sm">
        <div class="flex flex-wrap items-center justify-between gap-2">
          <div><b>{{ $d->organisation }}</b> · {{ $d->name }}@if($d->role) ({{ $d->role }})@endif <span class="ml-2 rounded-full bg-mist px-2 py-0.5 text-xs text-pine">{{ \App\Models\Central\DemoRequest::STATUSES[$d->status] ?? $d->status }}</span></div>
          <span class="text-xs text-ink/45">{{ $d->created_at->format('d/m/Y H:i') }}</span>
        </div>
        <p class="mt-1 text-ink/70"><a class="text-pine hover:underline" href="mailto:{{ $d->email }}">{{ $d->email }}</a>@if($d->phone) · {{ $d->phone }}@endif @if($d->country) · {{ $d->country }}@endif @if($d->members_range) · {{ \App\Models\Central\DemoRequest::RANGES[$d->members_range] ?? $d->members_range }} adhérents @endif</p>
        @if ($d->message)<p class="mt-2 whitespace-pre-line">{{ $d->message }}</p>@endif
        <div class="mt-3 flex gap-2 text-xs">
          @foreach (['contacted' => 'Marquer contactée', 'closed' => 'Clôturer'] as $k => $l)
            @if ($d->status !== $k)<form method="POST" action="{{ route('network.demos.status', $d) }}">@csrf<input type="hidden" name="status" value="{{ $k }}"><button class="rounded border border-ink/15 px-2 py-1 hover:border-pine">{{ $l }}</button></form>@endif
          @endforeach
        </div>
      </div>
    @empty
      <p class="rounded-lg border border-ink/10 bg-white px-4 py-10 text-center text-sm text-ink/45">Aucune demande pour le moment.</p>
    @endforelse
  </div>
  <div class="mt-4">{{ $items->links() }}</div>
</x-layouts.network>
