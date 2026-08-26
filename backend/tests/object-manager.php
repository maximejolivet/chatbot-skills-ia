<?php

declare(strict_types=1);

use App\Kernel;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__) . '/vendor/autoload.php';

if (method_exists(Dotenv::class, 'bootEnv')) {
    (new Dotenv())->bootEnv(dirname(__DIR__) . '/.env');
}

$env = $_SERVER['APP_ENV'] ?? 'dev';
$kernel = new Kernel(\is_string($env) ? $env : 'dev', (bool) ($_SERVER['APP_DEBUG'] ?? true));
$kernel->boot();

$doctrine = $kernel->getContainer()->get('doctrine');
if (!$doctrine instanceof ManagerRegistry) {
    throw new LogicException('Expected the "doctrine" service to be a ManagerRegistry.');
}

return $doctrine->getManager();
