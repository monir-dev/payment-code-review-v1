<?php

use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';

if (method_exists(Dotenv::class, 'bootEnv')) {
    (new Dotenv())->bootEnv(dirname(__DIR__).'/.env');
}

if ($_SERVER['APP_DEBUG']) {
    umask(0000);
}

// Set up test database
if (isset($_SERVER['APP_ENV']) && $_SERVER['APP_ENV'] === 'test') {
    try {
        $kernel = new \App\Kernel('test', false);
        $kernel->boot();
        $container = $kernel->getContainer();
        
        // Get doctrine
        $entityManager = $container->get('doctrine.orm.entity_manager');
        $schemaTool = new \Doctrine\ORM\Tools\SchemaTool($entityManager);
        $metadata = $entityManager->getMetadataFactory()->getAllMetadata();
        
        // Drop and create schema
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);
        
        $kernel->shutdown();
    } catch (\Throwable $e) {
        // Ignore database setup errors for now
        error_log("Test database setup failed: " . $e->getMessage());
    }
}
