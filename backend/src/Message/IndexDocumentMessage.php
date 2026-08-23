<?php

declare(strict_types=1);

namespace App\Message;

/**
 * Chunk and vectorize an already-uploaded Document in the background (see
 * IndexDocumentMessageHandler). Carries only the id -- the entity itself
 * isn't serializable across the transport, and may have changed by the time
 * a worker picks this up.
 */
final readonly class IndexDocumentMessage
{
    public function __construct(
        public int $documentId,
    ) {}
}
