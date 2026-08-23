<?php

declare(strict_types=1);

namespace App\Enum;

enum DocumentFileType: string
{
    case Pdf = 'pdf';
    case Txt = 'txt';
    case Docx = 'docx';
    case Md = 'md';
    case Html = 'html';
    case Json = 'json';
}
