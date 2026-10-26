@php($editing = $project !== null)
<x-layouts.social :title="$editing ? __('mxconnect.social.project.edit_title') : __('mxconnect.social.project.create_title', ['name' => $organization->name])">
    <div class="mx-auto max-w-3xl">
        <a href="{{ $editing ? route('projects.show', $project) : route('organizations.show', [$organization, 'onglet' => 'projets']) }}" class="inline-flex items-center gap-1 text-sm font-medium text-ink/50 hover:text-navy">&larr; {{ __('mxconnect.social.common.back') }}</a>
        <h1 class="mt-2 text-2xl font-extrabold tracking-tight text-navy">{{ $editing ? __('mxconnect.social.project.edit_title') : __('mxconnect.social.project.create_title', ['name' => $organization->name]) }}</h1>

        <form method="POST" enctype="multipart/form-data"
              action="{{ $editing ? route('projects.update', $project) : route('projects.store', $organization) }}"
              class="card mt-6 space-y-5 p-5 sm:p-6">
            @csrf
            @if ($editing) @method('PUT') @endif

            <label class="block text-sm font-semibold text-navy">{{ __('mxconnect.social.project.fields.title') }}
                <input name="title" required maxlength="200" value="{{ old('title', $project?->title) }}" class="field mt-1 font-normal">
                @error('title')<span class="mt-1 block text-xs font-normal text-clay">{{ $message }}</span>@enderror
            </label>
            <label class="block text-sm font-semibold text-navy">{{ __('mxconnect.social.project.fields.summary') }}
                <input name="summary" maxlength="300" value="{{ old('summary', $project?->summary) }}" class="field mt-1 font-normal">
            </label>
            <label class="block text-sm font-semibold text-navy">{{ __('mxconnect.social.project.fields.description') }}
                <textarea name="description" rows="4" maxlength="5000" class="field mt-1 font-normal">{{ old('description', $project?->description) }}</textarea>
            </label>
            <label class="block text-sm font-semibold text-navy">{{ __('mxconnect.social.project.fields.objectives') }}
                <textarea name="objectives" rows="3" maxlength="5000" class="field mt-1 font-normal">{{ old('objectives', $project?->objectives) }}</textarea>
            </label>

            <div class="grid gap-4 sm:grid-cols-2">
                <label class="block text-sm font-semibold text-navy">{{ __('mxconnect.social.project.fields.zone') }}
                    <input name="zone" maxlength="200" value="{{ old('zone', $project?->zone) }}" class="field mt-1 font-normal">
                </label>
                <label class="block text-sm font-semibold text-navy">{{ __('mxconnect.social.project.fields.beneficiaries') }}
                    <input name="beneficiaries_summary" maxlength="255" value="{{ old('beneficiaries_summary', $project?->beneficiaries_summary) }}" class="field mt-1 font-normal">
                </label>
                <label class="block text-sm font-semibold text-navy">{{ __('mxconnect.social.project.fields.status') }}
                    <select name="status" class="field mt-1 font-normal">
                        @foreach (\App\Enums\ProjectStatus::cases() as $s)
                            <option value="{{ $s->value }}" @selected(old('status', $project?->status->value ?? 'en_cours') === $s->value)>{{ __('mxconnect.social.project_status.'.$s->value) }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="block text-sm font-semibold text-navy">{{ __('mxconnect.social.project.fields.progress') }}
                    <input type="number" name="progress_percent" min="0" max="100" required value="{{ old('progress_percent', $project?->progress_percent ?? 0) }}" class="field mt-1 font-normal">
                    @error('progress_percent')<span class="mt-1 block text-xs font-normal text-clay">{{ $message }}</span>@enderror
                </label>
                <label class="block text-sm font-semibold text-navy">{{ __('mxconnect.social.project.fields.start') }}
                    <input type="date" name="start_date" value="{{ old('start_date', $project?->start_date?->format('Y-m-d')) }}" class="field mt-1 font-normal">
                </label>
                <label class="block text-sm font-semibold text-navy">{{ __('mxconnect.social.project.fields.end') }}
                    <input type="date" name="end_date" value="{{ old('end_date', $project?->end_date?->format('Y-m-d')) }}" class="field mt-1 font-normal">
                    @error('end_date')<span class="mt-1 block text-xs font-normal text-clay">{{ $message }}</span>@enderror
                </label>
            </div>

            <label class="block text-sm font-semibold text-navy">{{ __('mxconnect.social.project.fields.partners') }}
                <textarea name="partners" rows="3" maxlength="2000" class="field mt-1 font-normal">{{ old('partners', implode("\n", $project?->partners ?? [])) }}</textarea>
            </label>
            <label class="block text-sm font-semibold text-navy">{{ __('mxconnect.social.project.fields.cover') }}
                <input type="file" name="cover" accept="image/jpeg,image/png,image/webp" class="mt-1 block w-full text-sm font-normal file:mr-3 file:rounded-lg file:border-0 file:bg-brand-light file:px-3 file:py-2 file:font-semibold file:text-brand-dark">
                @error('cover')<span class="mt-1 block text-xs font-normal text-clay">{{ $message }}</span>@enderror
            </label>

            <div class="flex justify-end border-t border-ink/10 pt-5">
                <button class="btn-primary">{{ __('mxconnect.social.project.submit') }}</button>
            </div>
        </form>
    </div>
</x-layouts.social>
