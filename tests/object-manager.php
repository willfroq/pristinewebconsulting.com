<?php

declare(strict_types=1);

use App\Kernel;
use Symfony\Component\Dotenv\Dotenv;

require_once dirname(__DIR__).'/vendor/autoload.php';

(new Dotenv())->bootEnv(dirname(__DIR__).'/.env');
$kernel = new Kernel('dev', true);
$kernel->boot();

return $kernel->getContainer()->get('doctrine.orm.entity_manager');
