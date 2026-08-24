<?php

namespace App\Controller;

use App\AiProvider\Client\ChatMessage;
use App\AiProvider\ProviderSelectionService;
use App\Entity\AiProviderConfig;
use App\Enum\AiProviderTestStatus;
use App\Enum\AiProviderUsage;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AsController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Live-test an AiProviderConfig and persist the result on the row.
 */
#[AsController]
final readonly class TestAiProviderConfigController
{
    public function __construct(
        private ProviderSelectionService $providerSelectionService,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
    ) {}

    // AiProviderConfig's resource-level `security: "is_granted('ROLE_ADMIN')")`
    // does NOT apply to this custom-controller operation -- same API
    // Platform limitation as ConversationMessagesController, see its
    // comment. Without this, any visitor could spend real provider credits
    // via the public widget's Nuxt proxy (always authenticated as admin at
    // the HTTP layer, regardless of visitor).
    #[IsGranted('ROLE_ADMIN')]
    public function __invoke(AiProviderConfig $data): JsonResponse
    {
        $result = [
            'success' => false,
            'status' => 'unknown',
            'message' => '',
            'response_preview' => '',
            'error' => null,
        ];

        try {
            if (AiProviderUsage::Chat === $data->getUsage()) {
                $client = $this->providerSelectionService->buildLlmClientFromConfig($data);
                $completion = $client->complete(
                    messages: [new ChatMessage(role: 'user', content: 'Réponds uniquement par le mot "OK".')],
                    maxTokens: 10,
                );
                $result['success'] = true;
                $result['status'] = 'success';
                $result['message'] = 'Test réussi : le modèle a répondu correctement';
                $result['response_preview'] = mb_substr($completion->message->content, 0, 200);
                $result['token_usage'] = $completion->usage;
            } else {
                $client = $this->providerSelectionService->buildEmbeddingClientFromConfig($data);
                $embedding = $client->embed('test');
                $dimension = count($embedding->vector);
                $result['success'] = true;
                $result['status'] = 'success';
                $result['message'] = sprintf('Test réussi : embedding généré (dimension: %d)', $dimension);
                $result['response_preview'] = sprintf('Vecteur de %d dimensions', $dimension);
                $result['token_usage'] = $embedding->usage;
            }
        } catch (\InvalidArgumentException $e) {
            $result['status'] = 'error';
            $result['message'] = $e->getMessage();
            $result['error'] = $e->getMessage();
        } catch (\Throwable $e) {
            $this->logger->error('Error testing AiProviderConfig {id}: {error}', [
                'id' => $data->getId(),
                'error' => $e->getMessage(),
            ]);
            $result['status'] = 'error';
            $result['message'] = 'Erreur inattendue : ' . $e->getMessage();
            $result['error'] = mb_substr($e->getMessage(), 0, 500);
        }

        $data->setLastTestStatus($result['success'] ? AiProviderTestStatus::Success : AiProviderTestStatus::Error);
        $data->setLastTestedAt(new \DateTimeImmutable());
        $this->entityManager->flush();

        return new JsonResponse($result);
    }
}
