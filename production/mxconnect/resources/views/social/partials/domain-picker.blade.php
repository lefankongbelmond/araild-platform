{{-- Choix des domaines d'intervention (cases à cocher en pastilles). $selected = codes déjà choisis. --}}
@php $sel = old('domains', $selected ?? []) ?: []; @endphp
<fieldset class="mx-field">
  <legend class="mx-label">{{ __('Domaines d’intervention') }}</legend>
  <div class="mx-domainpick">
    @foreach (\App\Support\SocialDomains::ALL as $code => $label)
      <label><input type="checkbox" name="domains[]" value="{{ $code }}" @checked(in_array($code, (array) $sel, true))><span><x-mx.icon :name="'d-' . $code" :size="16"/> {{ __($label) }}</span></label>
    @endforeach
  </div>
  <p class="mx-help">{{ __('Ils permettent de vous trouver dans « Découvrir » et par domaine.') }}</p>
</fieldset>
