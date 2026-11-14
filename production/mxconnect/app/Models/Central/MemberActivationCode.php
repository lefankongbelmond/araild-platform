<?php

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Model;

/** Code d'activation du portail adhérent (2026-11-20) : seule son empreinte sha256 est stockée. */
class MemberActivationCode extends Model
{
    protected $connection = 'central';
    protected $guarded = [];
    protected $casts = ['expires_at' => 'datetime', 'used_at' => 'datetime'];

    /** Alphabet sans caractères ambigus (0/O, 1/I/L). */
    public const ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    public static function hash(string $code): string
    {
        return hash('sha256', preg_replace('/[^A-Z0-9]/', '', mb_strtoupper($code)));
    }
}
