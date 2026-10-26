<?php

namespace App\Services\Social;

use App\Models\Central\PublicAccount;
use App\Models\Social\Poll;
use App\Models\Social\PollOption;
use App\Models\Social\PollVote;
use App\Models\Social\Post;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Sondages du fil : 2 à 4 choix, durée limitée, un vote par personne (modifiable
 * tant que le sondage est ouvert). Les résultats sont des totaux : personne ne voit
 * qui a voté quoi.
 */
class PollService
{
    public static function rules(): array
    {
        return [
            'poll_options'   => ['nullable', 'array', 'max:4'],
            'poll_options.*' => ['nullable', 'string', 'max:120'],
            'poll_days'      => ['nullable', 'integer', 'in:' . implode(',', array_keys(Poll::DURATIONS))],
        ];
    }

    /** Choix réellement saisis (vides ignorés, doublons retirés). */
    public static function options(array $data): array
    {
        return collect($data['poll_options'] ?? [])->map(fn ($o) => trim((string) $o))->filter()
            ->unique(fn ($o) => mb_strtolower($o))->values()->all();
    }

    /** Crée le sondage d'une publication si au moins 2 choix sont saisis ; 1 seul choix = erreur. */
    public function attach(Post $post, array $data): ?Poll
    {
        $options = self::options($data);
        if (! $options) {
            return null;
        }
        if (count($options) < 2) {
            throw ValidationException::withMessages(['poll_options' => 'Un sondage demande au moins deux choix différents.']);
        }
        $days = (int) ($data['poll_days'] ?? 7) ?: 7;

        return DB::connection('central')->transaction(function () use ($post, $options, $days) {
            $poll = Poll::create(['post_id' => $post->id, 'closes_at' => now()->addDays($days)]);
            foreach ($options as $i => $label) {
                PollOption::create(['poll_id' => $poll->id, 'label' => $label, 'position' => $i]);
            }

            return $poll;
        });
    }

    /** Vote ou change de vote. Retourne le sondage à jour. */
    public function vote(Poll $poll, PublicAccount $account, int $optionId): Poll
    {
        abort_unless($poll->isOpen(), 422, 'Ce sondage est terminé.');
        $option = PollOption::where('poll_id', $poll->id)->findOrFail($optionId);

        DB::connection('central')->transaction(function () use ($poll, $account, $option) {
            $existing = PollVote::where('poll_id', $poll->id)->where('public_account_id', $account->id)->lockForUpdate()->first();
            if ($existing && (int) $existing->option_id === $option->id) {
                return;
            }
            if ($existing) {
                PollOption::whereKey($existing->option_id)->where('votes_count', '>', 0)->decrement('votes_count');
                $existing->update(['option_id' => $option->id]);
            } else {
                PollVote::create(['poll_id' => $poll->id, 'option_id' => $option->id, 'public_account_id' => $account->id]);
                Poll::whereKey($poll->id)->increment('votes_count');
            }
            PollOption::whereKey($option->id)->increment('votes_count');
        });

        return $poll->fresh('options');
    }
}
