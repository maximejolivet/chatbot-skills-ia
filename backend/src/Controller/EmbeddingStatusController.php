<?php

namespace App\Controller;

use App\AiProvider\ProviderSelectionService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AsController;
use Symfony\Component\HttpFoundation\JsonResponse;

#[AsController]
final class EmbeddingStatusController
{
    public function __construct(
        private readonly ProviderSelectionService $providerSelectionService,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(): JsonResponse
    {
        try {
            return new JsonResponse($this->providerSelectionService->checkEmbeddingStatus());
        } catch (\Throwable $e) {
            $this->logger->error('Error checking embedding status: {error}', ['error' => $e->getMessage()]);

            return new JsonResponse(['error' => 'Failed to check embedding status', 'status' => 'error'], 500);
        }
    }
}
