@php($editing = $organization !== null)
<x-layouts.social :title="$editing ? __('mxconnect.social.org.edit_title') : __('mxconnect.social.org.create_title')">
    <div class="mx-auto max-w-3xl">
        <h1 class="text-2xl font-extrabold tracking-tight text-navy">{{ $editing ? __('mxconnect.social.org.edit_title') : __('mxconnect.social.org.create_title') }}</h1>
        @unless ($editing)<p class="mt-1 text-sm text-ink/60">{{ __('mxconnect.social.org.create_sub') }}</p>@endunless

        <form method="POST" enctype="multipart/form-data"
              action="{{ $editing ? route('organizations.update', $organization) : route('organizations.store') }}"
              class="card mt-6 space-y-6 p-5 sm:p-6">
            @csrf
            @if ($editing) @method('PUT') @endif

            <div class="grid gap-4 sm:grid-cols-2">
                <label class="block text-sm font-semibold text-navy sm:col-span-2">{{ __('mxconnect.social.org.fields.name') }}
                    <input name="name" required maxlength="180" value="{{ old('name', $organization?->name) }}" class="field mt-1 font-normal">
                    @error('name')<span class="mt-1 block text-xs font-normal text-clay">{{ $message }}</span>@enderror
                </label>
                <label class="block text-sm font-semibold text-navy">{{ __('mxconnect.social.org.fields.type') }}
                    <select name="type" class="field mt-1 font-normal">
                        @foreach (\App\Enums\OrganizationType::cases() as $t)
                            <option value="{{ $t->value }}" @selected(old('type', $organization?->type->value ?? 'association') === $t->value)>{{ __('mxconnect.social.org_types.'.$t->value) }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="block text-sm font-semibold text-navy">{{ __('mxconnect.social.org.fields.tagline') }}
                    <input name="tagline" maxlength="200" value="{{ old('tagline', $organization?->tagline) }}" class="field mt-1 font-normal">
                </label>
                <label class="block text-sm font-semibold text-navy sm:col-span-2">{{ __('mxconnect.social.org.fields.description') }}
                    <textarea name="description" rows="5" maxlength="5000" class="field mt-1 font-normal">{{ old('description', $organization?->description) }}</textarea>
                </label>
            </div>

            <fieldset>
                <legend class="text-sm font-semibold text-navy">{{ __('mxconnect.social.org.fields.domains') }}</legend>
                @php($selected = old('domains', $organization?->domains ?? []))
                <div class="mt-2 flex flex-wrap gap-2">
                    @foreach (config('mxconnect.social.domains') as $d)
                        <label class="inline-flex cursor-pointer items-center gap-2 rounded-xl border border-ink/15 bg-white px-3 py-2 text-sm has-[:checked]:border-brand has-[:checked]:bg-brand-light">
                            <input type="checkbox" name="domains[]" value="{{ $d }}" @checked(in_array($d, $selected, true)) class="rounded border-ink/30 text-brand focus:ring-brand">
                            {{ __('mxconnect.social.domains.'.$d) }}
                        </label>
                    @endforeach
                </div>
            </fieldset>

            <div class="grid gap-4 sm:grid-cols-3">
                <label class="block text-sm font-semibold text-navy">{{ __('mxconnect.social.org.fields.country') }}
                    <select name="country_id" class="field mt-1 font-normal">
                        <option value=""></option>
                        @foreach ($countries as $c)<option value="{{ $c->id }}" @selected(old('country_id', $organization?->country_id) == $c->id)>{{ $c->name }}</option>@endforeach
                    </select>
                </label>
                <label class="block text-sm font-semibold text-navy">{{ __('mxconnect.social.org.fields.region') }}
                    <input name="region" maxlength="120" value="{{ old('region', $organization?->region) }}" class="field mt-1 font-normal">
                </label>
                <label class="block text-sm font-semibold text-navy">{{ __('mxconnect.social.org.fields.city') }}
                    <input name="city" maxlength="120" value="{{ old('city', $organization?->city) }}" class="field mt-1 font-normal">
                </label>
            </div>

            <div>
                <p class="text-xs text-ink/50">{{ __('mxconnect.social.org.fields.public_help') }}</p>
                <div class="mt-2 grid gap-4 sm:grid-cols-3">
                    <label class="block text-sm font-semibold text-navy">{{ __('mxconnect.social.org.fields.email') }}
                        <input type="email" name="email" maxlength="180" value="{{ old('email', $organization?->email) }}" class="field mt-1 font-normal">
                        @error('email')<span class="mt-1 block text-xs font-normal text-clay">{{ $message }}</span>@enderror
                    </label>
                    <label class="block text-sm font-semibold text-navy">{{ __('mxconnect.social.org.fields.phone') }}
                        <input name="phone" maxlength="30" value="{{ old('phone', $organization?->phone) }}" class="field mt-1 font-normal">
                    </label>
                    <label class="block text-sm font-semibold text-navy">{{ __('mxconnect.social.org.fields.website') }}
                        <input type="url" name="website" maxlength="255" placeholder="https://" value="{{ old('website', $organization?->website) }}" class="field mt-1 font-normal">
                        @error('website')<span class="mt-1 block text-xs font-normal text-clay">{{ $message }}</span>@enderror
                    </label>
                </div>
            </div>

            <fieldset>
                <legend class="text-sm font-semibold text-navy">{{ __('mxconnect.social.org.fields.socials') }}</legend>
                <div class="mt-2 grid gap-3 sm:grid-cols-2">
                    @foreach (['facebook', 'linkedin', 'youtube', 'x', 'instagram'] as $network)
                        <label class="flex items-center gap-2 text-sm capitalize text-ink/60">
                            <span class="w-20 shrink-0">{{ $network }}</span>
                            <input type="url" name="socials[{{ $network }}]" maxlength="255" placeholder="https://" value="{{ old('socials.'.$network, $organization?->socials[$network] ?? '') }}" class="field">
                        </label>
                        @error('socials.'.$network)<span class="text-xs text-clay sm:col-span-2">{{ $message }}</span>@enderror
                    @endforeach
                </div>
            </fieldset>

            <fieldset>
                <legend class="text-sm font-semibold text-navy">{{ __('mxconnect.social.org.fields.stats') }}</legend>
                <p class="mt-1 text-xs text-ink/50">{{ __('mxconnect.social.org.fields.stats_help') }}</p>
                @php($stats = collect($organization?->stats ?? [])->map(fn ($v, $k) => ['label' => $k, 'value' => $v])->values()->all())
                <div class="mt-2 grid gap-2">
                    @for ($i = 0; $i < 4; $i++)
                        <div class="grid grid-cols-2 gap-2">
                            <input name="stats[{{ $i }}][label]" maxlength="60" placeholder="{{ __('mxconnect.social.org.fields.stat_label') }}" value="{{ old("stats.$i.label", $stats[$i]['label'] ?? '') }}" class="field">
                            <input name="stats[{{ $i }}][value]" maxlength="30" placeholder="{{ __('mxconnect.social.org.fields.stat_value') }}" value="{{ old("stats.$i.value", $stats[$i]['value'] ?? '') }}" class="field">
                        </div>
                    @endfor
                </div>
            </fieldset>

            <div class="grid gap-4 sm:grid-cols-2">
                <label class="block text-sm font-semibold text-navy">{{ __('mxconnect.social.org.fields.logo') }}
                    <input type="file" name="logo" accept="image/jpeg,image/png,image/webp" class="mt-1 block w-full text-sm font-normal file:mr-3 file:rounded-lg file:border-0 file:bg-brand-light file:px-3 file:py-2 file:font-semibold file:text-brand-dark">
                    @error('logo')<span class="mt-1 block text-xs font-normal text-clay">{{ $message }}</span>@enderror
                </label>
                <label class="block text-sm font-semibold text-navy">{{ __('mxconnect.social.org.fields.cover') }}
                    <input type="file" name="cover" accept="image/jpeg,image/png,image/webp" class="mt-1 block w-full text-sm font-normal file:mr-3 file:rounded-lg file:border-0 file:bg-brand-light file:px-3 file:py-2 file:font-semibold file:text-brand-dark">
                    @error('cover')<span class="mt-1 block text-xs font-normal text-clay">{{ $message }}</span>@enderror
                </label>
            </div>

            <div class="flex justify-end gap-3 border-t border-ink/10 pt-5">
                <a href="{{ $editing ? route('organizations.show', $organization) : route('my.organizations') }}" class="btn-secondary">{{ __('mxconnect.social.common.cancel') }}</a>
                <button class="btn-primary">{{ $editing ? __('mxconnect.social.org.submit_update') : __('mxconnect.social.org.submit_create') }}</button>
            </div>
        </form>
    </div>
</x-layouts.social>
