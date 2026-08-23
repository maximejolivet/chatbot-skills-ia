<?php

declare(strict_types=1);

namespace App\Tests\RateLimiter;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\RateLimiter\RateLimiterFactory;

/**
 * Exercises the real `limiter.chat_message` service (config/packages/
 * rate_limiter.yaml) directly -- not through QuickSendController/
 * ConversationMessagesController, which would also trigger a real LLM call
 * per accepted request. This only needs the container, no DB/Ollama/Qdrant.
 */
final class ChatMessageRateLimiterTest extends KernelTestCase
{
    public function testAllowsUpToTheConfiguredLimitThenRejects(): void
    {
        self::bootKernel();
        /** @var RateLimiterFactory $factory */
        $factory = self::getContainer()->get('limiter.chat_message');

        $limiter = $factory->create(__METHOD__ . uniqid('', true));

        for ($i = 0; $i < 20; ++$i) {
            self::assertTrue($limiter->consume()->isAccepted(), "Request {$i} should be accepted");
        }

        self::assertFalse($limiter->consume()->isAccepted(), 'The 21st request should be rejected');
    }
}
