<?php

namespace App\Http\Controllers\Social;

use App\Models\Social\Post;
use App\Services\Social\PollService;
use Illuminate\Http\Request;

/** Vote à un sondage (un vote par compte, modifiable tant que le sondage est ouvert). */
class PollController extends SocialController
{
    public function vote(Request $request, Post $post, PollService $polls)
    {
        abort_unless($post->poll && Post::query()->visibleTo($this->viewer())->whereKey($post->id)->exists(), 404);
        $data = $request->validate(['option' => ['required', 'integer']], ['option.required' => 'Choisissez une réponse.']);
        $polls->vote($post->poll, $this->viewer(), (int) $data['option']);

        return redirect()->to(url()->previous() . '#sondage-' . $post->id)->with('ok', 'Votre vote est enregistré.');
    }
}
