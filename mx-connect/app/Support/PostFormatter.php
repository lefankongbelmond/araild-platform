<?php

namespace App\Support;

use Illuminate\Support\HtmlString;

/**
 * Renders user-written text for display. Everything is HTML-escaped FIRST; only
 * then are links and hashtags turned into anchors (in a single pass, so a "#" inside
 * a URL is never re-linked), so user input can never inject markup.
 */
class PostFormatter
{
    public static function render(?string $text): HtmlString
    {
        $escaped = e((string) $text);

        $pattern = '~(?<url>\bhttps?://[^\s<]+)|(?<![\p{L}\p{N}_&])#(?<tag>[\p{L}\p{N}_]{2,50})~u';

        $html = preg_replace_callback($pattern, function (array $m) {
            if (($m['url'] ?? '') !== '') {
                return '<a href="'.$m['url'].'" class="text-pine underline break-all" rel="nofollow noopener noreferrer" target="_blank">'.$m['url'].'</a>';
            }

            $href = e(route('feed', ['tag' => mb_strtolower($m['tag'])]));

            return '<a href="'.$href.'" class="font-medium text-pine hover:underline">#'.$m['tag'].'</a>';
        }, $escaped);

        return new HtmlString(nl2br($html, false));
    }
}
