<x-layouts.social :title="$post->organization?->name ?? $post->author?->displayName()">
    <a href="{{ route('feed') }}" class="mb-4 inline-flex items-center gap-1 text-sm font-medium text-ink/50 hover:text-navy">&larr; {{ __('mxconnect.social.feed.title') }}</a>

    <x-social.post-card :post="$post" :viewer="$viewer" :liked="in_array($post->id, $likedIds)" :roles="$roles" />

    <section id="commentaires" class="card mt-5 p-4 sm:p-5">
        <h2 class="text-base font-bold text-navy">{{ __('mxconnect.social.feed.comments') }} <span class="font-normal text-ink/40">({{ $post->comments_count }})</span></h2>

        @if ($viewer)
            <form method="POST" action="{{ route('posts.comments.store', $post) }}" class="mt-4 flex gap-3">
                @csrf
                <x-social.avatar :account="$viewer" size="h-9 w-9" />
                <div class="flex-1">
                    <textarea name="body" rows="2" maxlength="2000" required class="field" placeholder="{{ __('mxconnect.social.feed.comment_placeholder') }}">{{ old('parent_id') ? '' : old('body') }}</textarea>
                    @if (! old('parent_id'))
                        @error('body')<p class="mt-1 text-xs text-clay">{{ $message }}</p>@enderror
                    @endif
                    <button class="btn-primary mt-2">{{ __('mxconnect.social.actions.comment') }}</button>
                </div>
            </form>
        @else
            <p class="mt-3 text-sm text-ink/60"><a href="{{ route('login') }}" class="font-semibold text-pine hover:underline">{{ __('mxconnect.social.feed.login_to_comment') }}</a></p>
        @endif

        <div class="mt-6 space-y-5">
            @forelse ($comments as $comment)
                @php($canRemove = $viewer && ((int) $comment->public_account_id === (int) $viewer->id || in_array($roles[$post->organization_id] ?? null, ['owner', 'admin'], true)))
                <div class="flex gap-3">
                    <x-social.avatar :account="$comment->author" size="h-9 w-9" />
                    <div class="min-w-0 flex-1">
                        <div class="rounded-2xl bg-paper px-4 py-3">
                            <p class="text-sm font-semibold text-navy">{{ $comment->author?->displayName() }} <span class="font-normal text-ink/40">· {{ $comment->created_at->diffForHumans() }}</span></p>
                            <div class="mt-1 text-sm text-ink/80">{{ \App\Support\PostFormatter::render($comment->body) }}</div>
                        </div>
                        <div class="mt-1 flex gap-4 px-2 text-xs text-ink/50">
                            @if ($viewer)
                                <details>
                                    <summary class="cursor-pointer font-semibold hover:text-navy">{{ __('mxconnect.social.feed.reply') }}</summary>
                                    <form method="POST" action="{{ route('posts.comments.store', $post) }}" class="mt-2 flex gap-2">
                                        @csrf
                                        <input type="hidden" name="parent_id" value="{{ $comment->id }}">
                                        <input name="body" required maxlength="2000" class="field py-2" placeholder="{{ __('mxconnect.social.feed.comment_placeholder') }}">
                                        <button class="btn-primary py-2">{{ __('mxconnect.social.common.send') }}</button>
                                    </form>
                                </details>
                            @endif
                            @if ($canRemove)
                                <form method="POST" action="{{ route('comments.destroy', $comment) }}" onsubmit="return confirm(@js(__('mxconnect.social.actions.confirm_delete')))">
                                    @csrf @method('DELETE')
                                    <button class="font-semibold text-clay hover:underline">{{ __('mxconnect.social.actions.delete') }}</button>
                                </form>
                            @endif
                        </div>

                        @foreach ($comment->replies as $reply)
                            @php($canRemoveReply = $viewer && ((int) $reply->public_account_id === (int) $viewer->id || in_array($roles[$post->organization_id] ?? null, ['owner', 'admin'], true)))
                            <div class="mt-3 flex gap-3">
                                <x-social.avatar :account="$reply->author" size="h-8 w-8" text="text-xs" />
                                <div class="min-w-0 flex-1">
                                    <div class="rounded-2xl bg-paper px-4 py-2.5">
                                        <p class="text-sm font-semibold text-navy">{{ $reply->author?->displayName() }} <span class="font-normal text-ink/40">· {{ $reply->created_at->diffForHumans() }}</span></p>
                                        <div class="mt-1 text-sm text-ink/80">{{ \App\Support\PostFormatter::render($reply->body) }}</div>
                                    </div>
                                    @if ($canRemoveReply)
                                        <form method="POST" action="{{ route('comments.destroy', $reply) }}" class="mt-1 px-2 text-xs" onsubmit="return confirm(@js(__('mxconnect.social.actions.confirm_delete')))">
                                            @csrf @method('DELETE')
                                            <button class="font-semibold text-clay hover:underline">{{ __('mxconnect.social.actions.delete') }}</button>
                                        </form>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @empty
                <p class="text-sm text-ink/50">{{ __('mxconnect.social.feed.no_comments') }}</p>
            @endforelse
        </div>
    </section>
</x-layouts.social>
