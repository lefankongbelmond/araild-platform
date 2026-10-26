@php($engage = $kind === 'engagement')
<x-layouts.social :title="$engage ? __('mxconnect.social.actions.engage') : __('mxconnect.social.actions.support')">
    <div class="mx-auto max-w-2xl">
        <a href="{{ route('organizations.show', $organization) }}" class="inline-flex items-center gap-1 text-sm font-medium text-ink/50 hover:text-navy">&larr; {{ $organization->name }}</a>

        <div class="card mt-3 overflow-hidden">
            <div class="flex items-center gap-4 p-5 sm:p-6 {{ $engage ? 'bg-brand-light' : 'bg-sun-light' }}">
                <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-white {{ $engage ? 'text-brand' : 'text-sun' }}">
                    <x-social.icon :name="$engage ? 'hand' : 'gift'" class="h-6 w-6" />
                </span>
                <div>
                    <h1 class="text-xl font-extrabold tracking-tight text-navy">
                        {{ $engage ? __('mxconnect.social.requests.engage_title', ['name' => $organization->name]) : __('mxconnect.social.requests.support_title', ['name' => $organization->name]) }}
                    </h1>
                    <p class="mt-1 text-sm text-ink/70">{{ $engage ? __('mxconnect.social.requests.engage_sub') : __('mxconnect.social.requests.support_sub') }}</p>
                </div>
            </div>

            <div class="p-5 sm:p-6">
                @if ($isManager)
                    <p class="rounded-xl bg-paper px-4 py-3 text-sm text-ink/70">{{ __('mxconnect.social.requests.own_org') }}</p>
                @else
                    @if ($post || $project)
                        <div class="mb-5 space-y-2 rounded-xl border border-ink/10 p-3 text-sm">
                            @if ($post)
                                <p><span class="text-xs font-semibold uppercase tracking-wide text-ink/40">{{ __('mxconnect.social.requests.about_post') }}</span><br>
                                <a href="{{ route('posts.show', $post) }}" class="text-navy hover:underline">{{ \Illuminate\Support\Str::limit((string) $post->body, 140) ?: __('mxconnect.social.post_types.'.$post->type->value) }}</a></p>
                            @endif
                            @if ($project)
                                <p><span class="text-xs font-semibold uppercase tracking-wide text-ink/40">{{ __('mxconnect.social.requests.about_project') }}</span><br>
                                <a href="{{ route('projects.show', $project) }}" class="font-semibold text-navy hover:underline">{{ $project->title }}</a></p>
                            @endif
                        </div>
                    @endif

                    <form method="POST" action="{{ $engage ? route('organizations.engage', $organization) : route('organizations.support', $organization) }}" class="space-y-5">
                        @csrf
                        @if ($post)<input type="hidden" name="post_id" value="{{ $post->id }}">@endif
                        @if ($project)<input type="hidden" name="project_id" value="{{ $project->id }}">@endif

                        <fieldset>
                            <legend class="text-sm font-semibold text-navy">{{ $engage ? __('mxconnect.social.requests.contribution') : __('mxconnect.social.requests.support_type') }}</legend>
                            @php($field = $engage ? 'contribution_type' : 'support_type')
                            @php($options = $engage ? \App\Enums\ContributionType::cases() : \App\Enums\SupportType::cases())
                            <div class="mt-2 grid gap-2 sm:grid-cols-2">
                                @foreach ($options as $option)
                                    <label class="flex cursor-pointer items-center gap-3 rounded-xl border border-ink/15 px-3 py-2.5 text-sm has-[:checked]:border-brand has-[:checked]:bg-brand-light">
                                        <input type="radio" name="{{ $field }}" value="{{ $option->value }}" required @checked(old($field, $loop->first ? $option->value : null) === $option->value) class="border-ink/30 text-brand focus:ring-brand">
                                        {{ __('mxconnect.social.'.($engage ? 'contribution_types' : 'support_types').'.'.$option->value) }}
                                    </label>
                                @endforeach
                            </div>
                            @error($field)<p class="mt-1 text-xs text-clay">{{ $message }}</p>@enderror
                        </fieldset>

                        <label class="block text-sm font-semibold text-navy">{{ __('mxconnect.social.requests.message') }}
                            <textarea name="message" rows="5" maxlength="2000" @unless ($engage) required @endunless class="field mt-1 font-normal"
                                      placeholder="{{ $engage ? __('mxconnect.social.requests.message_engage_ph') : __('mxconnect.social.requests.message_support_ph') }}">{{ old('message') }}</textarea>
                            @error('message')<span class="mt-1 block text-xs font-normal text-clay">{{ $message }}</span>@enderror
                        </label>

                        @unless ($engage)
                            <div class="grid gap-4 sm:grid-cols-2">
                                <label class="block text-sm font-semibold text-navy">{{ __('mxconnect.social.requests.contact_org') }}
                                    <input name="contact_organization" maxlength="180" value="{{ old('contact_organization') }}" class="field mt-1 font-normal">
                                </label>
                                <label class="block text-sm font-semibold text-navy">{{ __('mxconnect.social.requests.contact_email') }}
                                    <input type="email" name="contact_email" maxlength="180" value="{{ old('contact_email') }}" class="field mt-1 font-normal">
                                    @error('contact_email')<span class="mt-1 block text-xs font-normal text-clay">{{ $message }}</span>@enderror
                                </label>
                            </div>
                        @endunless

                        <p class="flex items-start gap-2 rounded-xl bg-paper px-3 py-2.5 text-xs text-ink/60"><x-social.icon name="protection_sociale" class="h-4 w-4 shrink-0 text-brand" />{{ __('mxconnect.social.requests.privacy') }}</p>

                        <div class="flex justify-end gap-3">
                            <a href="{{ $post ? route('posts.show', $post) : ($project ? route('projects.show', $project) : route('organizations.show', $organization)) }}" class="btn-secondary">{{ __('mxconnect.social.common.cancel') }}</a>
                            <button class="btn-primary">{{ $engage ? __('mxconnect.social.requests.submit_engage') : __('mxconnect.social.requests.submit_support') }}</button>
                        </div>
                    </form>
                @endif
            </div>
        </div>
    </div>
</x-layouts.social>
