{{-- Inviter un ami (2026-11-10) : partage du lien d'inscription. Aucun suivi ni identifiant dans le lien. --}}
@php
  $e = 'rawurlencode';
  $intro = \Illuminate\Support\Str::before($text, 'Inscription gratuite');
  $links = [
      ['whatsapp', 'WhatsApp', 'https://wa.me/?text=' . $e($text), 'mx-share--wa'],
      ['facebook', 'Facebook', 'https://www.facebook.com/sharer/sharer.php?u=' . $e($link), 'mx-share--fb'],
      ['telegram', 'Telegram', 'https://t.me/share/url?url=' . $e($link) . '&text=' . $e(trim($intro)), 'mx-share--tg'],
      ['x-logo', 'X (Twitter)', 'https://twitter.com/intent/tweet?text=' . $e(trim($intro)) . '&url=' . $e($link), 'mx-share--x'],
      ['linkedin', 'LinkedIn', 'https://www.linkedin.com/sharing/share-offsite/?url=' . $e($link), 'mx-share--li'],
      ['sms', 'SMS', 'sms:?body=' . $e($text), 'mx-share--sms'],
      ['mail', 'E-mail', 'mailto:?subject=' . $e('Rejoins-moi sur MX-CONNECT') . '&body=' . $e($text), 'mx-share--mail'],
  ];
@endphp
<x-layouts.social title="Inviter un ami" active="inviter">
  <section class="mx-card mx-card__pad mx-stack">
    <div>
      <h1 style="font-size:22px;margin:0">Inviter un ami</h1>
      <p class="mx-small mx-muted" style="margin-top:4px">Partagez MX-CONNECT avec vos proches, collègues et partenaires. L’inscription est gratuite.</p>
    </div>

    <button type="button" class="mx-btn mx-btn--primary mx-btn--lg mx-btn--block" id="mx-native-share" hidden>
      <x-mx.icon name="share-out" :size="18"/> Partager…
    </button>

    <div class="mx-share-grid" role="list" aria-label="Partager l’invitation">
      @foreach ($links as [$icon, $label, $url, $cls])
        <a role="listitem" class="mx-share {{ $cls }}" href="{{ $url }}" @if(str_starts_with($url, 'https'))target="_blank" rel="noopener noreferrer"@endif>
          <span class="mx-share__ic"><x-mx.icon :name="$icon" :size="22"/></span><span>{{ $label }}</span>
        </a>
      @endforeach
      <button type="button" role="listitem" class="mx-share mx-share--copy" data-copy="{{ $link }}">
        <span class="mx-share__ic"><x-mx.icon name="link" :size="22"/></span><span>Copier le lien</span>
      </button>
    </div>

    <div class="mx-field">
      <label class="mx-label" for="inv-text">Message envoyé</label>
      <textarea class="mx-textarea" id="inv-text" readonly rows="4" style="min-height:0">{{ $text }}</textarea>
      <button type="button" class="mx-btn mx-btn--ghost mx-btn--sm" style="margin-top:8px" data-copy="{{ $text }}"><x-mx.icon name="document" :size="16"/> Copier le message</button>
    </div>
    <p class="mx-xs mx-muted">Le lien mène à la page d’inscription du réseau. Il ne contient aucune information sur vous et MX-CONNECT n’enregistre pas à qui vous l’envoyez.</p>
  </section>
  <script>(function(){var b=document.getElementById('mx-native-share');if(!b||!navigator.share)return;b.hidden=false;
    b.addEventListener('click',function(){navigator.share({title:'MX-CONNECT',text:@json(trim($intro)),url:@json($link)}).catch(function(){});});})();</script>
</x-layouts.social>
