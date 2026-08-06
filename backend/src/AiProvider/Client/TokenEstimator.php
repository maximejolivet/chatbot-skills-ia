<?php

namespace App\AiProvider\Client;

/**
 * Rudimentary token estimate for providers/paths without real usage data.
 */
final class TokenEstimator
{
    public static function estimate(string $text): int
    {
        if ('' === $text) {
            return 0;
        }

        return max(1, intdiv(mb_strlen($text), 4));
    }
}
