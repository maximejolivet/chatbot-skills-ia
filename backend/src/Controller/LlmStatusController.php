<?php

namespace App\Controller;

use App\AiProvider\ProviderSelectionService;
use App\Enum\AiProviderUsage;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AsController;
use Symfony\Component\HttpFoundation\JsonResponse;

#[AsController]
final readonly class LlmStatusController
{
    public function __construct(
        private ProviderSelectionService $providerSelectionService,
        private LoggerInterface $logger,
    ) {}

    public function __invoke(): JsonResponse
    {
        try {
            return new JsonResponse($this->providerSelectionService->checkLlmStatus(AiProviderUsage::Chat));
        } catch (\Throwable $e) {
            $this->logger->error('Error checking LLM status: {error}', ['error' => $e->getMessage()]);

            return new JsonResponse(['error' => 'Failed to check LLM status', 'status' => 'error'], 500);
        }
    }
}
