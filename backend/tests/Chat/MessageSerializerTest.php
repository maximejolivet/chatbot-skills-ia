<?php

declare(strict_types=1);

namespace App\Tests\Chat;

use App\Chat\MessageSerializer;
use App\Entity\Message;
use App\Enum\MessageFeedback;
use App\Enum\MessageRole;
use PHPUnit\Framework\TestCase;

final class MessageSerializerTest extends TestCase
{
    public function testSerializeIncludesSourcesHiddenFlag(): void
    {
        $message = new Message()
            ->setRole(MessageRole::Assistant)
            ->setContent('Bonjour')
            ->setMetadata(['sources' => [['document_id' => 1]]]);

        $serialized = MessageSerializer::serialize($message);

        self::assertTrue($serialized['metadata']['sources_hidden']);
        self::assertSame([['document_id' => 1]], $serialized['metadata']['sources']);
    }

    public function testSerializeDefaultsFeedbackToNull(): void
    {
        $message = new Message()->setRole(MessageRole::User)->setContent('Salut');

        $serialized = MessageSerializer::serialize($message);

        self::assertArrayHasKey('feedback', $serialized);
        self::assertNull($serialized['feedback']);
    }

    public function testSerializeExposesFeedbackValue(): void
    {
        $message = new Message()
            ->setRole(MessageRole::Assistant)
            ->setContent('Bonjour')
            ->setFeedback(MessageFeedback::Positive);

        $serialized = MessageSerializer::serialize($message);

        self::assertSame('positive', $serialized['feedback']);
    }
}
