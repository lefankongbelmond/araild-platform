<?php

namespace App\Support;

class Hashtags
{
    public const PATTERN = '/(?<![\p{L}\p{N}_&])#([\p{L}\p{N}_]{2,50})/u';

    /** @return list<string> normalized (lowercase, no "#"), unique, in order of appearance */
    public static function extract(?string $text): array
    {
        if ($text === null || $text === '') {
            return [];
        }

        preg_match_all(self::PATTERN, $text, $matches);

        return array_values(array_unique(array_map(fn ($t) => mb_strtolower($t), $matches[1])));
    }
}
