<?php

declare(strict_types=1);

namespace App\Enum;

enum WorkflowStepType: string
{
    case ApiCall = 'api_call';
    case Email = 'email';
    case Notification = 'notification';
    case DataTransform = 'data_transform';
    case Condition = 'condition';
    case Delay = 'delay';
    case Webhook = 'webhook';
    case SetConversation = 'set_conversation';
}
