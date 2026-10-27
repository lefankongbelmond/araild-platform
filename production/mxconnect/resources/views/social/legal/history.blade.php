<x-layouts.social title="Mes acceptations" active="parametres">
  <section class="mx-card mx-card__pad mx-stack">
    <h1 style="font-size:22px">Documents et acceptations</h1>
    <p class="mx-small mx-muted">Versions en vigueur des documents de MX-CONNECT et historique de vos acceptations (date, version, canal). Rien n’est effacé de cet historique, sauf si vous supprimez votre compte.</p>
    @if (count($pending))<a class="mx-btn mx-btn--primary" href="{{ route('social.legal.pending') }}">Valider les documents en attente ({{ count($pending) }})</a>@endif
    <ul class="mx-legal__list">
      @foreach ($documents as $d)<li><a href="{{ $d->url() }}">{{ $d->label() }}</a> — version {{ $d->version }} du {{ $d->published_at?->format('d/m/Y') }}</li>@endforeach
    </ul>
    <h2 style="font-size:17px">Historique</h2>
    @forelse ($rows as $r)
      <p class="mx-small" style="margin:0">{{ \Illuminate\Support\Carbon::parse($r->created_at)->format('d/m/Y H:i') }} — {{ $r->document?->label() ?? $r->type }} {{ $r->version }} :
        {{ \App\Models\Social\LegalAcceptance::ACTIONS[$r->action] ?? $r->action }} ({{ $r->channel === 'android' ? 'application Android' : 'site web' }})</p>
    @empty
      <p class="mx-small mx-muted">Aucune acceptation enregistrée pour l’instant. Les comptes créés avant le suivi des versions ont accepté les règles affichées à l’inscription.</p>
    @endforelse
  </section>
  <x-slot:head><link rel="stylesheet" href="/css/mx-app.css?v={{ config('mxconnect.asset_version') }}"></x-slot:head>
</x-layouts.social>
