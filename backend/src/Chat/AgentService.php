<?php

namespace App\Chat;

use App\Entity\AiAgent;
use App\Entity\Collection;
use App\Repository\AiAgentRepository;
use Doctrine\Common\Collections\Collection as DoctrineCollection;

final class AgentService
{
    public function __construct(private readonly AiAgentRepository $agentRepository)
    {
    }

    public function getAgent(int $agentId): ?AiAgent
    {
        return $this->agentRepository->getActive($agentId);
    }

    public function getAgentSystemPrompt(int $agentId): string
    {
        $agent = $this->getAgent($agentId);

        return $agent && '' !== $agent->getSystemPrompt()
            ? $agent->getSystemPrompt()
            : ChatOrchestrationService::DEFAULT_SYSTEM_PROMPT;
    }

    /**
     * @return DoctrineCollection<int, \App\Entity\Workflow>
     */
    public function getAgentWorkflows(int $agentId): DoctrineCollection|array
    {
        $agent = $this->getAgent($agentId);

        return $agent ? $agent->getActiveWorkflows() : [];
    }

    public function getAgentCollection(int $agentId): ?Collection
    {
        $agent = $this->getAgent($agentId);

        return $agent?->getCollection();
    }
}
