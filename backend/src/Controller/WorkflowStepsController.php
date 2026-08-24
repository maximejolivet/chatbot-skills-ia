<?php

namespace App\Controller;

use App\Entity\Workflow;
use App\Entity\WorkflowStep;
use App\Enum\WorkflowStepType;
use App\Repository\WorkflowStepRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AsController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AsController]
final readonly class WorkflowStepsController
{
    public function __construct(
        private WorkflowStepRepository $workflowStepRepository,
        private EntityManagerInterface $entityManager,
    ) {}

    // Workflow's resource-level `security: "is_granted('ROLE_ADMIN')")` does
    // NOT apply to this custom-controller operation -- same API Platform
    // limitation as ConversationMessagesController, see its comment. Without
    // this, the operation was reachable by anyone (the Nuxt proxy always
    // authenticates as admin at the HTTP layer regardless of visitor).
    #[IsGranted('ROLE_ADMIN')]
    public function __invoke(Workflow $data, Request $request): JsonResponse
    {
        if ($request->isMethod('POST')) {
            return $this->create($data, $request);
        }

        return new JsonResponse(array_map($this->serialize(...), $this->workflowStepRepository->findActiveOrdered($data->getId())));
    }

    private function create(Workflow $workflow, Request $request): JsonResponse
    {
        $body = json_decode($request->getContent(), true) ?? [];

        $name = trim((string) ($body['name'] ?? ''));
        if ('' === $name) {
            throw new BadRequestHttpException('Missing name.');
        }

        $stepType = WorkflowStepType::tryFrom($body['step_type'] ?? '');
        if (!$stepType) {
            throw new BadRequestHttpException(sprintf('Invalid step_type. Allowed: %s', implode(', ', array_map(static fn(WorkflowStepType $t) => $t->value, WorkflowStepType::cases()))));
        }

        if (!isset($body['order']) || !is_numeric($body['order'])) {
            throw new BadRequestHttpException('Missing or invalid order.');
        }

        $step = new WorkflowStep()
            ->setName($name)
            ->setStepType($stepType)
            ->setOrder((int) $body['order'])
            ->setConfiguration(is_array($body['configuration'] ?? null) ? $body['configuration'] : [])
            ->setIsActive($body['is_active'] ?? true);
        $workflow->addStep($step);

        $this->entityManager->persist($step);
        $this->entityManager->flush();

        return new JsonResponse($this->serialize($step), 201);
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(WorkflowStep $step): array
    {
        return [
            'id' => $step->getId(),
            'name' => $step->getName(),
            'step_type' => $step->getStepType()->value,
            'order' => $step->getOrder(),
            'configuration' => $step->getConfiguration(),
            'is_active' => $step->isActive(),
            'created_at' => $step->getCreatedAt()->format(\DATE_ATOM),
        ];
    }
}
