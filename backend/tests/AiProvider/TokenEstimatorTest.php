<?php

declare(strict_types=1);

namespace App\Tests\AiProvider;

use App\AiProvider\Client\TokenEstimator;
use PHPUnit\Framework\TestCase;

final class TokenEstimatorTest extends TestCase
{
    public function testEmptyStringIsZeroTokens(): void
    {
        self::assertSame(0, TokenEstimator::estimate(''));
    }

    public function testShortNonEmptyStringIsAtLeastOneToken(): void
    {
        self::assertSame(1, TokenEstimator::estimate('hi'));
    }

    public function testEstimatesRoughlyFourCharsPerToken(): void
    {
        self::assertSame(5, TokenEstimator::estimate(str_repeat('a', 20)));
    }

    public function testCountsMultibyteCharactersNotBytes(): void
    {
        // 8 multibyte chars ("café" x2, é is 2 bytes in UTF-8) -> 2 tokens by
        // char count; a byte-counting implementation would estimate higher.
        self::assertSame(2, TokenEstimator::estimate(str_repeat('café', 2)));
    }
}
