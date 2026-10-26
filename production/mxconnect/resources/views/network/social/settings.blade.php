<x-layouts.network title="Réglages du réseau social">
  <h1 class="text-2xl font-semibold tracking-tight">Réglages du réseau social</h1>
  @if (session('success'))<div class="mt-4 rounded-md bg-pine/10 px-4 py-2 text-sm text-pine">{{ session('success') }}</div>@endif
  <form method="POST" action="{{ route('network.social.settings.save') }}" class="mt-6 grid gap-6 lg:grid-cols-2">
    @csrf
    <section class="rounded-lg border border-ink/10 bg-white p-5 text-sm">
      <h2 class="text-base font-semibold">Protection contre la surcharge</h2>
      <p class="mt-1 text-ink/60">Le mode allégé réduit le travail du serveur quand le réseau est très fréquenté : moins de publications par page, pas d'aperçu des liens, envoi de vidéos suspendu, pas de lecture automatique, pages publiques mises en cache 30 s pour les visiteurs. Tout reste accessible.</p>
      <label class="mt-4 flex items-start gap-2"><input type="checkbox" name="light_mode" value="1" @checked($light)> <span><b>Activer le mode allégé maintenant</b></span></label>
      <label class="mt-2 flex items-start gap-2"><input type="checkbox" name="light_auto" value="1" @checked($lightAuto)> <span>Activation automatique pendant 10 minutes si 40 % des pages répondent en plus de 2,5 s</span></label>
      @if ($autoUntil)
        <p class="mt-3 rounded bg-amber-50 px-3 py-2 text-amber-800">Mode allégé automatique en cours jusqu'à {{ \Illuminate\Support\Carbon::createFromTimestamp($autoUntil)->format('H:i') }}.
          <label class="ml-2 inline-flex items-center gap-1"><input type="checkbox" name="reset_auto" value="1"> l'arrêter</label></p>
      @endif
    </section>
    <section class="rounded-lg border border-ink/10 bg-white p-5 text-sm">
      <h2 class="text-base font-semibold">Publications sponsorisées</h2>
      <label class="mt-3 flex items-start gap-2"><input type="checkbox" name="sponsor_enabled" value="1" @checked($sponsorEnabled)> <span>Autoriser la sponsorisation et la diffusion des campagnes</span></label>
      <div class="mt-3 grid gap-3 sm:grid-cols-3">
        <label class="block font-medium">Devise<select name="currency_id" class="mt-1 block w-full rounded-md border border-ink/15 px-2 py-2">@foreach ($currencies as $c)<option value="{{ $c->id }}" @selected($currency?->id === $c->id)>{{ $c->code }}</option>@endforeach</select></label>
        <label class="block font-medium">Prix pour 1 000 affichages<input name="cpm" value="{{ $cpm }}" class="mt-1 block w-full rounded-md border border-ink/15 px-2 py-2" inputmode="decimal"></label>
        <label class="block font-medium">Budget minimum<input name="min_budget" value="{{ $minBudget }}" class="mt-1 block w-full rounded-md border border-ink/15 px-2 py-2" inputmode="decimal"></label>
      </div>
      <label class="mt-3 block font-medium">Instructions de paiement affichées aux annonceurs
        <textarea name="instructions" maxlength="1000" rows="4" class="mt-1 block w-full rounded-md border border-ink/15 px-2 py-2" placeholder="Ex. : Envoyez le montant par Orange Money au 6XX XX XX XX (nom du titulaire), en indiquant le numéro SP-… de votre campagne.">{{ $instructions }}</textarea></label>
      <p class="mt-1 text-xs text-ink/50">Aucun paiement n'est encaissé par la plateforme : vous activez chaque campagne après avoir vérifié le paiement.</p>
    </section>
    <div><button class="rounded-md bg-pine px-5 py-2.5 text-sm font-medium text-white hover:bg-navy">Enregistrer</button></div>
  </form>
</x-layouts.network>
