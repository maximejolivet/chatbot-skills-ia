<?php

namespace App\Controller;

use App\Entity\Document;
use App\Enum\DocumentStatus;
use App\Message\IndexDocumentMessage;
use App\Repository\DocumentChunkRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AsController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Single canonical (re)processing entry point. Resets to 'pending' and
 * dispatches to the `async` transport (see IndexDocumentMessageHandler) --
 * this returns as soon as the message is queued, not once processing is
 * done; poll GET /api/documents/{id} for the final status.
 */
#[AsController]
final readonly class DocumentProcessController
{
    public function __construct(
        private DocumentChunkRepository $chunkRepository,
        private EntityManagerInterface $entityManager,
        private MessageBusInterface $messageBus,
    ) {}

    // See DocumentUploadController's comment -- Document's resource-level
    // security doesn't apply to custom-controller operations.
    #[IsGranted('ROLE_ADMIN')]
    public function __invoke(Document $data): JsonResponse
    {
        $this->chunkRepository->deleteForDocument($data->getId());
        $data->setStatus(DocumentStatus::Pending);
        $this->entityManager->flush();

        $this->messageBus->dispatch(new IndexDocumentMessage($data->getId()));

        return new JsonResponse(['status' => $data->getStatus()->value], 202);
    }
}
