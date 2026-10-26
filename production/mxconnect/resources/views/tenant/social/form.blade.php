@php
  $field = 'mt-1 w-full rounded-md border border-ink/15 bg-white px-3 py-2 text-sm focus:border-pine focus:outline-none';
  $central = rtrim(config('app.url'), '/');
  $editing = $post->exists;
  $types = collect(\App\Http\Controllers\Tenant\SocialPresenceController::POST_TYPES)->mapWithKeys(fn ($t) => [$t => \App\Models\Social\Post::TYPES[$t]]);
@endphp
<x-layouts.tenant :title="$editing ? 'Modifier la publication' : 'Nouvelle publication'">
  <h1 class="text-2xl font-semibold tracking-tight">{{ $editing ? 'Modifier la publication' : 'Nouvelle publication' }}</h1>
  @if ($post->status === 'hidden')<div class="mt-4 rounded-md border border-clay/30 bg-clay/5 px-4 py-3 text-sm text-clay">Cette publication a été masquée par la modération du réseau. Vous pouvez la corriger ; contactez l'administration du réseau pour la réafficher.</div>@endif
  @if ($errors->any())<div class="mt-4 rounded-md border border-clay/30 bg-clay/5 px-4 py-3 text-sm text-clay"><ul class="list-disc pl-5">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

  <form method="POST" enctype="multipart/form-data" action="{{ $editing ? route('social-presence.update', $post->id) : route('social-presence.store') }}" class="mt-6 max-w-3xl space-y-4 rounded-lg border border-ink/10 bg-white p-5">
    @csrf @if ($editing) @method('PUT') @endif
    <div class="grid gap-4 sm:grid-cols-2">
      <label class="block text-sm font-medium">Type
        <select name="type" class="{{ $field }}" id="sp-type">@foreach ($types as $k => $v)<option value="{{ $k }}" @selected(old('type', $post->type) === $k)>{{ $v }}</option>@endforeach</select></label>
      <label class="block text-sm font-medium">Thème (facultatif)
        <select name="category" class="{{ $field }}"><option value="">—</option>@foreach (\App\Models\Social\Post::ACTIVITY_CATEGORIES as $k => $v)<option value="{{ $k }}" @selected(old('category', $post->category) === $k)>{{ $v }}</option>@endforeach</select></label>
    </div>
    <label class="block text-sm font-medium">Texte<textarea name="body" rows="7" maxlength="5000" class="{{ $field }}">{{ old('body', $post->body) }}</textarea></label>
    <div class="grid gap-4 sm:grid-cols-2" data-for="evenement">
      <label class="block text-sm font-medium">Date et heure de l'événement<input type="datetime-local" name="event_starts_at" value="{{ old('event_starts_at', $post->event_starts_at?->format('Y-m-d\TH:i')) }}" class="{{ $field }}"></label>
      <label class="block text-sm font-medium">Lieu<input name="event_location" maxlength="180" value="{{ old('event_location', $post->event_location) }}" class="{{ $field }}"></label>
    </div>
    <label class="block text-sm font-medium">Lien (facultatif, obligatoire pour le type « Lien »)<input type="url" name="link_url" maxlength="500" value="{{ old('link_url', $post->link_url) }}" class="{{ $field }}" placeholder="https://"></label>

    <div class="text-sm">
      <div class="font-medium">Images (JPEG, PNG, WebP ou GIF — 6 maximum, 6 Mo chacune, converties en WebP)</div>
      @if ($editing && $post->media->isNotEmpty())
        <div class="mt-2 flex flex-wrap gap-3">
          @foreach ($post->media as $m)
            <label class="text-xs"><img src="{{ $central . $m->thumbUrl() }}" alt="" class="h-20 w-20 rounded object-cover"><span class="mt-1 flex items-center gap-1"><input type="checkbox" name="remove_media[]" value="{{ $m->id }}"> Retirer</span></label>
          @endforeach
        </div>
      @endif
      <input type="file" name="images[]" multiple accept="image/jpeg,image/png,image/webp,image/gif" class="mt-2 block text-xs">
      <p class="mt-1 text-xs text-ink/45">Les métadonnées des photos (position GPS, appareil) sont supprimées automatiquement. N'utilisez une photo de personne qu'avec son accord.</p>
    </div>

    <div class="grid gap-4 sm:grid-cols-2">
      <label class="block text-sm font-medium">Visibilité
        <select name="visibility" class="{{ $field }}"><option value="public" @selected(old('visibility', $post->visibility) === 'public')>Public — tout le monde</option><option value="followers" @selected(old('visibility', $post->visibility) === 'followers')>Abonnés uniquement</option></select></label>
      <label class="block text-sm font-medium">Statut
        <select name="status" class="{{ $field }}"><option value="published" @selected(in_array(old('status', $post->status), ['published', 'hidden'], true))>Publier maintenant</option><option value="draft" @selected(in_array(old('status', $post->status), ['draft', 'archived'], true))>Brouillon</option></select></label>
    </div>
    <label class="flex items-start gap-2 rounded-md bg-mist px-3 py-2 text-sm"><input type="checkbox" name="medical_ack" value="1" class="mt-1" required> <span>Je confirme que cette publication ne contient aucune donnée médicale ou personnelle d'adhérent (nom de malade, diagnostic, dossier, numéro d'adhérent…).</span></label>
    <div class="flex gap-3">
      <button class="rounded-md bg-pine px-5 py-2 text-sm font-medium text-white hover:bg-navy">{{ $editing ? 'Enregistrer' : 'Publier' }}</button>
      <a href="{{ route('social-presence.index') }}" class="px-3 py-2 text-sm text-ink/60 hover:underline">Annuler</a>
    </div>
  </form>
</x-layouts.tenant>
