@php $central = rtrim(config('app.url'), '/'); @endphp
<x-layouts.network title="Sponsorisations">
  <h1 class="text-2xl font-semibold tracking-tight">Publications sponsorisées</h1>
  <p class="mt-1 text-sm text-ink/60">Vérifiez le paiement (référence indiquée par l'annonceur) avant d'activer une campagne. MX-CONNECT n'encaisse rien en ligne : l'activation vaut confirmation que le paiement a été reçu.</p>
  @if (session('success'))<div class="mt-4 rounded-md bg-pine/10 px-4 py-2 text-sm text-pine">{{ session('success') }}</div>@endif
  @error('note')<div class="mt-4 rounded-md bg-clay/10 px-4 py-2 text-sm text-clay">{{ $message }}</div>@enderror
  <div class="mt-6 flex flex-wrap gap-2 text-sm">
    @foreach (\App\Models\Social\Sponsorship::STATUSES as $k => $l)
      <a href="{{ route('network.social.sponsorships', ['statut' => $k]) }}" class="rounded-md px-3 py-1.5 {{ $status === $k ? 'bg-pine text-white' : 'bg-white border border-ink/10 text-ink/70' }}">{{ $l }} ({{ $counts[$k] ?? 0 }})</a>
    @endforeach
  </div>
  <div class="mt-4 space-y-3">
    @forelse ($items as $s)
      <div class="rounded-lg border border-ink/10 bg-white p-4 text-sm">
        <div class="flex flex-wrap justify-between gap-2">
          <b>SP-{{ $s->id }} · {{ $s->advertiser?->display_name }}@if($s->advertiser?->organisation) ({{ $s->advertiser->organisation }})@endif</b>
          <span class="text-xs text-ink/50">Demandée le {{ $s->created_at->format('d/m/Y H:i') }} · {{ $s->advertiser?->phone }}</span>
        </div>
        <div class="mt-2 grid grid-cols-1 gap-1 text-ink/75 sm:grid-cols-2">
          <span>Budget : <b>{{ $s->money($s->budget_minor) }}</b> ({{ number_format($s->max_impressions, 0, ',', ' ') }} affichages à {{ $s->money($s->cpm_minor) }} / 1 000)</span>
          <span>Période : {{ $s->starts_on->format('d/m/Y') }} → {{ $s->ends_on->format('d/m/Y') }}</span>
          <span>Ciblage : {{ $s->target_city ?: 'toutes villes' }} · {{ $s->target_interest ? (\App\Http\Controllers\Social\AccountController::INTERESTS[$s->target_interest] ?? $s->target_interest) : 'tous intérêts' }}</span>
          <span>Référence de paiement : <b>{{ $s->payment_reference ?: 'non indiquée' }}</b></span>
          <span>Résultats : {{ number_format($s->impressions, 0, ',', ' ') }} affichages · {{ number_format($s->clicks, 0, ',', ' ') }} clics · {{ $s->money($s->spentMinor()) }} consommés</span>
        </div>
        <blockquote class="mt-3 border-l-4 border-ink/10 pl-3 whitespace-pre-line text-ink/80">{{ \Illuminate\Support\Str::limit($s->post?->body ?: '(publication sans texte)', 400) }}</blockquote>
        <div class="mt-3 flex flex-wrap items-center gap-2 text-xs">
          <a href="{{ $central }}/publications/{{ $s->post_id }}" target="_blank" rel="noopener" class="text-pine hover:underline">Voir la publication ↗</a>
          @foreach (['activer' => ['pending_payment', 'rejected'], 'pause' => ['active'], 'reprendre' => ['paused'], 'refuser' => ['pending_payment', 'active', 'paused']] as $act => $from)
            @if (in_array($s->status, $from, true))
              <form method="POST" action="{{ route('network.social.sponsorships.decide', $s) }}" class="flex items-center gap-1" @if($act === 'activer') onsubmit="return confirm('Confirmez-vous avoir reçu le paiement de cette campagne ?')" @endif>@csrf
                <input type="hidden" name="action" value="{{ $act }}">
                @if ($act === 'refuser')<input name="note" maxlength="255" placeholder="Motif du refus" class="rounded border border-ink/15 px-2 py-1">@endif
                <button class="rounded px-3 py-1.5 {{ $act === 'activer' ? 'bg-pine text-white' : ($act === 'refuser' ? 'bg-clay text-white' : 'border border-ink/15') }}">{{ ['activer' => 'Paiement reçu : activer', 'pause' => 'Mettre en pause', 'reprendre' => 'Reprendre', 'refuser' => 'Refuser'][$act] }}</button></form>
            @endif
          @endforeach
        </div>
        @if ($s->admin_note)<p class="mt-2 text-xs text-ink/55">Note : {{ $s->admin_note }}</p>@endif
      </div>
    @empty
      <p class="rounded-lg border border-ink/10 bg-white p-6 text-center text-sm text-ink/50">Aucune campagne dans cet état.</p>
    @endforelse
  </div>
  <div class="mt-4">{{ $items->links() }}</div>
</x-layouts.network>
