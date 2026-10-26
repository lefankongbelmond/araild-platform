@php $field = 'mt-1 w-full rounded-md border border-ink/15 bg-white px-3 py-2 text-sm'; $editing = $group->exists; @endphp
<x-layouts.tenant :title="$editing ? 'Modifier le groupe' : 'Nouveau groupe'">
  <a href="{{ route('social-groups.index') }}" class="text-sm text-pine hover:underline">← Groupes</a>
  <h1 class="mt-2 text-2xl font-semibold tracking-tight">{{ $editing ? 'Modifier le groupe' : 'Nouveau groupe' }}</h1>
  @if ($errors->any())<div class="mt-4 rounded-md border border-clay/30 bg-clay/5 px-4 py-3 text-sm text-clay">{{ $errors->first() }}</div>@endif
  <form method="POST" enctype="multipart/form-data" action="{{ $editing ? route('social-groups.update', $group->id) : route('social-groups.store') }}" class="mt-6 max-w-2xl space-y-4 rounded-lg border border-ink/10 bg-white p-5 text-sm">
    @csrf @if ($editing) @method('PUT') @endif
    <label class="block font-medium">Nom du groupe<input name="name" required maxlength="120" value="{{ old('name', $group->name) }}" class="{{ $field }}" placeholder="Ex. : Mamans en bonne santé"></label>
    <label class="block font-medium">Thème
      <select name="topic" class="{{ $field }}"><option value="">—</option>@foreach (\App\Models\Social\Post::ACTIVITY_CATEGORIES as $k => $v)<option value="{{ $k }}" @selected(old('topic', $group->topic) === $k)>{{ $v }}</option>@endforeach</select></label>
    <label class="block font-medium">Description<textarea name="description" rows="4" maxlength="3000" class="{{ $field }}">{{ old('description', $group->description) }}</textarea></label>
    <label class="block font-medium">Règles du groupe<textarea name="rules" rows="4" maxlength="3000" class="{{ $field }}" placeholder="Respect, bienveillance, aucune information médicale personnelle…">{{ old('rules', $group->rules) }}</textarea></label>
    <fieldset><legend class="font-medium">Type</legend>
      <label class="mt-1 flex items-center gap-2"><input type="radio" name="visibility" value="public" @checked(old('visibility', $group->visibility) === 'public')> Public — tout le monde lit, adhésion libre</label>
      <label class="mt-1 flex items-center gap-2"><input type="radio" name="visibility" value="private" @checked(old('visibility', $group->visibility) === 'private')> Privé — seuls les membres lisent, vous approuvez chaque demande</label>
    </fieldset>
    <label class="block font-medium">Image de couverture (facultatif)<input type="file" name="cover" accept="image/jpeg,image/png,image/webp,image/gif" class="mt-1 block text-xs"></label>
    <button class="rounded-md bg-pine px-5 py-2 font-medium text-white hover:bg-navy">{{ $editing ? 'Enregistrer' : 'Créer le groupe' }}</button>
  </form>
</x-layouts.tenant>
