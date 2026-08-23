<?php

declare(strict_types=1);

namespace App\Enum;

enum MessageFeedback: string
{
    case Positive = 'positive';
    case Negative = 'negative';
}
