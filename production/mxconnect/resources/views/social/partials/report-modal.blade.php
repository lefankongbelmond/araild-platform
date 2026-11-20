@auth('member')
<dialog class="mx-modal" id="mx-report">
  <form method="POST" data-report-form>
    @csrf
    <div class="mx-row mx-between"><h2>{{ __('Signaler un contenu') }}</h2><button type="button" class="mx-iconbtn" data-close aria-label="{{ __('Fermer') }}"><x-mx.icon name="x" :size="18"/></button></div>
    <p class="mx-small mx-muted">{{ __('Votre signalement est transmis à l\'équipe de modération de MX-CONNECT. Il reste confidentiel.') }}</p>
    <div class="mx-field"><label class="mx-label" for="rp-reason">{{ __('Motif') }}</label>
      <select class="mx-select" id="rp-reason" name="reason" required>
        @foreach (\App\Models\Social\Report::REASONS as $k => $v)<option value="{{ $k }}">{{ __($v) }}</option>@endforeach
      </select></div>
    <div class="mx-field"><label class="mx-label" for="rp-details">{{ __('Précisions (facultatif)') }}</label>
      <textarea class="mx-textarea" id="rp-details" name="details" maxlength="1000"></textarea></div>
    <div class="mx-row" style="justify-content:flex-end"><button type="button" class="mx-btn mx-btn--ghost" data-close>{{ __('Annuler') }}</button><button class="mx-btn mx-btn--primary">{{ __('Envoyer le signalement') }}</button></div>
  </form>
</dialog>
@endauth
