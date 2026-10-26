@php
  $viewer = auth('member')->user();
  $inGroupComposer = isset($composerAction);
  // Pages gérées par ce membre : « Publier en tant que ».
  $myPages = ($viewer && ! $inGroupComposer)
      ? \App\Services\Social\PageService::managedBy($viewer)->with('profile')->orderBy('name')->get()
      : collect();
  $asDefault = old('as', $composerAs ?? 'me');
  $composerPage = $composerPage ?? null;   // zone de publication d'une page : on publie au nom de la page
@endphp
@if ($viewer)
<section class="mx-card mx-card__pad mx-composer-card" id="composer" aria-label="Créer une publication">
  <form method="POST" action="{{ $composerAction ?? route('social.post.store') }}" enctype="multipart/form-data" data-composer
        @unless($inGroupComposer) data-video-start="{{ route('social.video.start') }}" @endunless>
    @csrf
    <div @class(['mx-composer', 'is-open' => old('body') || $errors->any()]) data-composer-box>
      @if ($composerPage)
        <span class="mx-composer__me" title="Publication au nom de {{ $composerPage->name }}"><x-mx.avatar :src="$composerPage->profile?->logoUrl()" :name="$composerPage->name" org /></span>
      @else
        <a href="{{ route('social.user', $viewer) }}" class="mx-composer__me" aria-label="Mon profil"><x-mx.avatar :src="$viewer->avatarUrl()" :name="$viewer->name" /></a>
      @endif
      <div class="mx-grow" style="min-width:0">
        <label class="mx-sr" for="composer-body">Votre publication</label>
        <textarea id="composer-body" class="mx-textarea" name="body" maxlength="5000" rows="1" placeholder="{{ $composerPage ? 'Publier au nom de ' . $composerPage->name . '…' : 'Quoi de neuf, ' . $viewer->first_name . ' ?' }}" data-composer-text data-mentions data-emoji>{{ old('body') }}</textarea>
      </div>
      <label class="mx-composer__photo" title="Ajouter des photos (6 maximum)">
        <span class="mx-composer__photoico"><x-mx.icon name="image" :size="22"/></span>
        <span>Photo</span>
        <input type="file" name="images[]" accept="image/jpeg,image/png,image/webp,image/gif" multiple hidden data-images>
      </label>
      @unless ($inGroupComposer)
        <label class="mx-composer__photo mx-composer__video" title="Ajouter une vidéo ou un réel">
          <span class="mx-composer__photoico"><x-mx.icon name="video" :size="22"/></span>
          <span>Vidéo</span>
          <input type="file" accept="video/mp4,video/quicktime,video/webm,video/*" hidden data-video>
        </label>
      @endunless
    </div>
    <div class="mx-preview" data-preview></div>

    @unless ($inGroupComposer)
      <div class="mx-videobox" data-video-box hidden>
        <video playsinline muted controls preload="metadata" data-video-preview></video>
        <div class="mx-stack" style="gap:8px;min-width:0">
          <b class="mx-small" data-video-name></b>
          <div class="mx-progress" data-video-progress role="progressbar" aria-label="Envoi de la vidéo" aria-valuemin="0" aria-valuemax="100"><span></span></div>
          <span class="mx-xs mx-muted" data-video-status>Préparation…</span>
          <label class="mx-check mx-small"><input type="checkbox" name="reel" value="1" data-reel> <span><b>Publier comme réel</b> — vidéo verticale de {{ config('mxconnect.social.reel_max_seconds', 90) }} s maximum, visible dans « Réels »</span></label>
          <div><button type="button" class="mx-btn mx-btn--ghost mx-btn--sm" data-video-remove><x-mx.icon name="trash" :size="14"/> Retirer la vidéo</button></div>
        </div>
        <input type="hidden" name="video_upload" data-video-id>
        <input type="hidden" name="video_duration" data-video-duration>
        <input type="hidden" name="video_width" data-video-width>
        <input type="hidden" name="video_height" data-video-height>
      </div>
    @endunless

    <div class="mx-stack" style="gap:4px" data-composer-errors>
      @foreach (['body', 'type', 'images', 'images.*', 'video', 'as', 'documents', 'documents.*', 'documents_public', 'poll_options', 'poll_options.*'] as $field)
        @error($field)<p class="mx-error">{{ $message }}</p>@enderror
      @endforeach
    </div>
    <div class="mx-progress" hidden data-progress role="progressbar" aria-label="Envoi de la publication" aria-valuemin="0" aria-valuemax="100"><span></span></div>
    <div class="mx-composer__more">
      @unless ($inGroupComposer)
        <div class="mx-composer__extras">
          <details class="mx-extra" @if(old('poll_options.0')) open @endif>
            <summary class="mx-tool"><x-mx.icon name="chart" :size="16"/> Sondage</summary>
            <div class="mx-extra__body">
              <p class="mx-xs mx-muted">Votre texte sert de question. 2 à 4 choix ; les résultats sont anonymes.</p>
              @for ($i = 0; $i < 4; $i++)
                <label class="mx-sr" for="poll-{{ $i }}">Choix {{ $i + 1 }}</label>
                <input class="mx-input" id="poll-{{ $i }}" name="poll_options[]" maxlength="120" value="{{ old('poll_options.' . $i) }}" placeholder="Choix {{ $i + 1 }}{{ $i < 2 ? '' : ' (facultatif)' }}">
              @endfor
              <label class="mx-small" for="poll-days">Durée</label>
              <select class="mx-select" id="poll-days" name="poll_days">@foreach (\App\Models\Social\Poll::DURATIONS as $d => $l)<option value="{{ $d }}" @selected((int) old('poll_days', 7) === $d)>{{ $l }}</option>@endforeach</select>
            </div>
          </details>
          @if ($composerPage || $myPages->isNotEmpty())
            <details class="mx-extra" @if($errors->has('documents') || $errors->has('documents.*') || $errors->has('documents_public')) open @endif>
              <summary class="mx-tool"><x-mx.icon name="file" :size="16"/> Document PDF</summary>
              <div class="mx-extra__body">
                <p class="mx-xs mx-muted">Réservé aux publications d’une page{{ $composerPage ? '' : ' (choisissez « Page : … » dans « Publier en tant que »)' }}. PDF uniquement, 3 maximum, {{ round(\App\Services\Social\DocumentService::maxKb() / 1024) }} Mo chacun.</p>
                <input type="file" name="documents[]" accept="application/pdf" multiple aria-label="Documents PDF">
                <label class="mx-check mx-small"><input type="checkbox" name="documents_public" value="1"> <span>Ces documents sont publics et ne contiennent aucune donnée médicale ou personnelle.</span></label>
              </div>
            </details>
          @endif
        </div>
      @endunless

      <div class="mx-composer__tools">
        <div class="mx-row mx-row--wrap" style="gap:6px">
          <button type="button" class="mx-tool mx-tool--icon" data-emoji-btn aria-label="Ajouter un emoji" title="Emoji"><x-mx.icon name="smile" :size="18"/></button>
          <button type="button" class="mx-tool mx-tool--icon" data-mention-btn aria-label="Mentionner quelqu’un" title="Mentionner (@)"><x-mx.icon name="at" :size="18"/></button>
          @if ($composerPage)
            <input type="hidden" name="as" value="{{ $composerPage->slug }}">
            <span class="mx-visibility" title="Cette publication sera signée par la page"><x-mx.icon name="flag-page" :size="14"/> <span>{{ \Illuminate\Support\Str::limit($composerPage->name, 28) }}</span></span>
          @elseif ($myPages->isNotEmpty())
            <label class="mx-sr" for="composer-as">Publier en tant que</label>
            <select id="composer-as" name="as" class="mx-tool" title="Publier en tant que">
              <option value="me" @selected($asDefault === 'me')>{{ $viewer->name }}</option>
              @foreach ($myPages as $pg)<option value="{{ $pg->slug }}" @selected($asDefault === $pg->slug)>Page : {{ $pg->name }}</option>@endforeach
            </select>
          @endif
          @unless ($inGroupComposer)
            <label class="mx-sr" for="composer-type">Type de publication</label>
            <select id="composer-type" name="type" class="mx-tool" title="Type de publication">
              @foreach (\App\Models\Social\Post::MEMBER_TYPES as $k => $v)<option value="{{ $k }}" @selected(old('type', 'texte') === $k)>{{ $v }}</option>@endforeach
            </select>
          @endunless
          <input type="hidden" name="visibility" value="public">
          <span class="mx-visibility" title="Visible par tous les visiteurs du réseau"><x-mx.icon name="globe" :size="14"/> <span>{{ $inGroupComposer ? 'Groupe' : 'Public' }}</span></span>
        </div>
        <button class="mx-btn mx-btn--primary" data-publish><x-mx.icon name="send" :size="16"/> Publier</button>
      </div>
      <p class="mx-composer__help"><x-mx.icon name="shield" :size="14"/> <span>Ne publiez pas d'informations médicales personnelles (les vôtres ou celles d'autrui). Tapez @ pour mentionner un membre ou une page. <span class="mx-draftnote" data-draftnote hidden>Brouillon enregistré sur cet appareil.</span></span></p>
    </div>
  </form>
</section>
@endif
