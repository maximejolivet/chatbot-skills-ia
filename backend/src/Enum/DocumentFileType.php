<?php

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
