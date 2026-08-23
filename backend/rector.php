<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Set\ValueObject\LevelSetList;
use Rector\Set\ValueObject\SetList;

// No SymfonySetList/DoctrineSetList here: rector/rector-symfony and
// rector/rector-doctrine's latest releases still require rector/rector ^0.x-1.x
// core, incompatible with this project's rector/rector ^2.6 -- see
// docs/BACKLOG.md. PHP-level modernization (this config's actual point per
// the php-modernization skill) doesn't need them.
return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ])
    ->withSkip([
        __DIR__ . '/src/Kernel.php',
    ])
    ->withSets([
        LevelSetList::UP_TO_PHP_84,
        SetList::CODE_QUALITY,
        SetList::DEAD_CODE,
        SetList::TYPE_DECLARATION,
    ]);
