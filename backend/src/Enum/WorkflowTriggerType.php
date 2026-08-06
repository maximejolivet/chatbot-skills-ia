<?php

namespace App\Enum;

enum WorkflowTriggerType: string
{
    case Manual = 'manual';
    case Api = 'api';
    case AgentTool = 'agent_tool';
}
