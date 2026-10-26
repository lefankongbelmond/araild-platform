<x-layouts.social title="Compte annonceur" active="annonceur">
  <section class="mx-card mx-card__pad mx-stack">
    <h1 style="font-size:22px">Compte annonceur</h1>
    <p class="mx-small mx-muted">Sponsorisez une publication (la vôtre ou celle d’une page que vous gérez) pour la faire apparaître dans le fil d’un plus grand nombre de membres, avec la mention « Sponsorisé ».</p>
  </section>

  @if (! $advertiser)
    <form class="mx-card mx-card__pad mx-stack" method="POST" action="{{ route('social.ads.save') }}">
      @csrf
      <input type="hidden" name="retour" value="{{ request('retour') }}">
      <h2 class="mx-card__title">Ouvrir mon compte annonceur</h2>
      <div class="mx-grid-2">
        <div class="mx-field"><label class="mx-label" for="ad-name">Nom affiché *</label><input class="mx-input" id="ad-name" name="display_name" required maxlength="160" value="{{ old('display_name', auth('member')->user()->name) }}">@error('display_name')<p class="mx-error">{{ $message }}</p>@enderror</div>
        <div class="mx-field"><label class="mx-label" for="ad-org">Organisation</label><input class="mx-input" id="ad-org" name="organisation" maxlength="160" value="{{ old('organisation') }}"></div>
        <div class="mx-field"><label class="mx-label" for="ad-phone">Téléphone *</label><input class="mx-input" id="ad-phone" name="phone" required maxlength="40" value="{{ old('phone', auth('member')->user()->phone) }}">@error('phone')<p class="mx-error">{{ $message }}</p>@enderror</div>
        <div class="mx-field"><label class="mx-label" for="ad-mail">E-mail</label><input class="mx-input" id="ad-mail" type="email" name="email" maxlength="180" value="{{ old('email', auth('member')->user()->email) }}"></div>
        <div class="mx-field"><label class="mx-label" for="ad-city">Ville</label><input class="mx-input" id="ad-city" name="city" maxlength="120" value="{{ old('city', auth('member')->user()->city) }}"></div>
      </div>
      <label class="mx-check"><input type="checkbox" name="accept" value="1" required> <span>J’accepte les règles de la publicité sur MX-CONNECT : aucune promesse de guérison, aucun médicament sans autorisation, aucune donnée médicale personnelle. Les publications sponsorisées restent soumises à la modération.</span></label>
      @error('accept')<p class="mx-error">{{ $message }}</p>@enderror
      <div><button class="mx-btn mx-btn--primary">Ouvrir mon compte annonceur</button></div>
    </form>
  @else
    <section class="mx-card mx-card__pad mx-stack">
      <div class="mx-row mx-between mx-row--wrap" style="gap:8px">
        <div><b>{{ $advertiser->display_name }}</b>@if($advertiser->organisation) · {{ $advertiser->organisation }}@endif<div class="mx-xs mx-muted">{{ $advertiser->phone }}@if($advertiser->email) · {{ $advertiser->email }}@endif</div></div>
        @if ($advertiser->status !== 'active')<span class="mx-badge" style="color:var(--mx-danger)">Compte suspendu</span>@endif
      </div>
      <p class="mx-small"><b>Comment sponsoriser ?</b> Ouvrez le menu « ⋯ » d’une de vos publications (ou d’une publication de votre page) puis « Sponsoriser cette publication ».</p>
      <div class="mx-alert mx-alert--info"><b>Paiement :</b> {{ $instructions }}<br><span class="mx-xs">MX-CONNECT n’encaisse aucun paiement en ligne : la campagne est activée par l’administration du réseau après vérification de votre paiement.</span></div>
    </section>

    <section class="mx-card">
      <div class="mx-card__head"><h2 class="mx-card__title">Mes campagnes ({{ $campaigns->count() }})</h2></div>
      @forelse ($campaigns as $c)
        <article class="mx-card__pad mx-stack" style="gap:8px;border-top:1px solid var(--mx-border)">
          <div class="mx-row mx-between mx-row--wrap" style="gap:8px">
            <b>SP-{{ $c->id }} · <a href="{{ route('social.post', $c->post_id) }}">{{ \Illuminate\Support\Str::limit($c->post?->body ?: 'Publication', 60) }}</a></b>
            <span @class(['mx-badge', 'mx-badge--blue' => $c->status === 'active'])>{{ $c->statusLabel() }}</span>
          </div>
          <div class="mx-xs mx-muted">Du {{ $c->starts_on->format('d/m/Y') }} au {{ $c->ends_on->format('d/m/Y') }} · Budget {{ $c->money($c->budget_minor) }} · {{ number_format($c->max_impressions, 0, ',', ' ') }} affichages prévus
            @if($c->target_city) · Ville : {{ $c->target_city }}@endif @if($c->target_interest) · Intérêt : {{ \App\Http\Controllers\Social\AccountController::INTERESTS[$c->target_interest] ?? $c->target_interest }}@endif</div>
          <div class="mx-stats" style="padding:0">
            <span><b>{{ number_format($c->impressions, 0, ',', ' ') }}</b> affichages</span>
            <span><b>{{ number_format($c->clicks, 0, ',', ' ') }}</b> clics</span>
            <span><b>{{ $c->money($c->spentMinor()) }}</b> consommés</span>
          </div>
          @if ($c->admin_note)<p class="mx-xs"><b>Note de l’administration :</b> {{ $c->admin_note }}</p>@endif
          @if ($c->status === 'pending_payment')
            <form method="POST" action="{{ route('social.sponsor.payment', $c->id) }}" class="mx-row mx-row--wrap" style="gap:8px;align-items:flex-end">
              @csrf
              <div class="mx-field mx-grow" style="min-width:200px"><label class="mx-label" for="pay-{{ $c->id }}">Référence de votre paiement</label><input class="mx-input" id="pay-{{ $c->id }}" name="payment_reference" maxlength="120" value="{{ $c->payment_reference }}" placeholder="Ex. identifiant de transaction Mobile Money"></div>
              <button class="mx-btn mx-btn--primary mx-btn--sm">{{ $c->payment_reference ? 'Mettre à jour' : 'Envoyer la référence' }}</button>
            </form>
            @if ($c->payment_reference)<p class="mx-xs mx-muted">Référence transmise : en attente de vérification par l’administration.</p>@endif
          @endif
          <div class="mx-row" style="gap:6px">
            @foreach (['pause' => ['active', 'Mettre en pause'], 'reprendre' => ['paused', 'Reprendre'], 'annuler' => ['pending_payment', 'Annuler la demande']] as $act => [$st, $lbl])
              @if ($c->status === $st)<form method="POST" action="{{ route('social.sponsor.action', $c->id) }}">@csrf<input type="hidden" name="action" value="{{ $act }}"><button class="mx-btn mx-btn--ghost mx-btn--sm">{{ $lbl }}</button></form>@endif
            @endforeach
          </div>
        </article>
      @empty
        <p class="mx-card__pad mx-small mx-muted">Aucune campagne pour l’instant.</p>
      @endforelse
    </section>
  @endif
</x-layouts.social>
