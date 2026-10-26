<x-layouts.social title="Sponsoriser une publication" active="annonceur">
  <form class="mx-card mx-card__pad mx-stack" method="POST" action="{{ route('social.sponsor.store', $post) }}" data-sponsor-form
        data-cpm="{{ $cpm }}" data-minor="{{ $currency->minor_unit }}" data-symbol="{{ $currency->symbol }}">
    @csrf
    <h1 style="font-size:22px">Sponsoriser une publication</h1>
    <div class="mx-alert mx-alert--info">Tarif : <b>{{ $currency->format($cpm) }}</b> pour 1 000 affichages · budget minimum {{ $currency->format($minBudget) }}. La publication porte la mention « Sponsorisé » ; une même personne la voit au plus 3 fois par jour.</div>
    <div class="mx-grid-2">
      <div class="mx-field"><label class="mx-label" for="sp-budget">Budget total ({{ $currency->code }}) *</label>
        <input class="mx-input" id="sp-budget" name="budget" inputmode="decimal" required value="{{ old('budget', $minBudget / (10 ** $currency->minor_unit)) }}" data-budget>
        <p class="mx-help" data-estimate></p>@error('budget')<p class="mx-error">{{ $message }}</p>@enderror</div>
      <div class="mx-field"><label class="mx-label" for="sp-days">Durée (jours) *</label><input class="mx-input" id="sp-days" type="number" name="days" min="1" max="60" required value="{{ old('days', 7) }}">@error('days')<p class="mx-error">{{ $message }}</p>@enderror</div>
      <div class="mx-field"><label class="mx-label" for="sp-start">Début *</label><input class="mx-input" id="sp-start" type="date" name="starts_on" required value="{{ old('starts_on', now()->toDateString()) }}" min="{{ now()->toDateString() }}">@error('starts_on')<p class="mx-error">{{ $message }}</p>@enderror</div>
      <div class="mx-field"><label class="mx-label" for="sp-cta">Bouton</label>
        <select class="mx-select" id="sp-cta" name="cta">@foreach (\App\Models\Social\Sponsorship::CTA as $k => $v)@if($k === 'savoir' || $post->mutual_id)<option value="{{ $k }}" @selected(old('cta') === $k)>{{ $v }}</option>@endif @endforeach</select></div>
      <div class="mx-field"><label class="mx-label" for="sp-city">Ville ciblée (facultatif)</label><input class="mx-input" id="sp-city" name="target_city" maxlength="120" value="{{ old('target_city') }}" placeholder="Toutes les villes"></div>
      <div class="mx-field"><label class="mx-label" for="sp-int">Centre d’intérêt ciblé (facultatif)</label>
        <select class="mx-select" id="sp-int" name="target_interest"><option value="">Tous les membres</option>@foreach ($interests as $k => $v)<option value="{{ $k }}" @selected(old('target_interest') === $k)>{{ $v }}</option>@endforeach</select></div>
    </div>
    <p class="mx-xs mx-muted">Un ciblage par ville ou par intérêt ne touche que les membres connectés qui l’ont renseigné dans leur profil : l’audience est plus petite.</p>
    <div>
      <p class="mx-label">Publication sponsorisée</p>
      <div class="mx-shared" style="margin:6px 0 0">@include('social.partials.post-embed', ['p' => $post])</div>
    </div>
    <div class="mx-alert mx-alert--warn"><b>Paiement :</b> {{ $instructions }}<br><span class="mx-xs">Aucun paiement n’est encaissé en ligne : après votre demande, indiquez la référence de votre paiement dans « Compte annonceur ». La campagne démarre après vérification par l’administration du réseau.</span></div>
    <div class="mx-row mx-row--wrap"><button class="mx-btn mx-btn--primary"><x-mx.icon name="megaphone" :size="16"/> Envoyer la demande</button><a class="mx-btn mx-btn--ghost" href="{{ route('social.post', $post) }}">Annuler</a></div>
  </form>
  <x-slot:scripts>
    <script>
      (function () {
        var f = document.querySelector('[data-sponsor-form]'), b = f.querySelector('[data-budget]'), out = f.querySelector('[data-estimate]');
        var cpm = +f.dataset.cpm, minor = +f.dataset.minor;
        function upd() {
          var v = parseFloat((b.value || '').replace(/\s/g, '').replace(',', '.')) || 0;
          var n = cpm > 0 ? Math.floor(v * Math.pow(10, minor) * 1000 / cpm) : 0;
          out.textContent = n ? '≈ ' + n.toLocaleString('fr-FR') + ' affichages' : '';
        }
        b.addEventListener('input', upd); upd();
      })();
    </script>
  </x-slot:scripts>
</x-layouts.social>
