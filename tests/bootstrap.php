<?php

use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';

if (method_exists(Dotenv::class, 'bootEnv')) {
    (new Dotenv())->bootEnv(dirname(__DIR__).'/.env');
}

if ($_SERVER['APP_DEBUG']) {
    umask(0000);
}

// Set up test database using schema creation (compatible with SQLite)
if (isset($_SERVER['APP_ENV']) && $_SERVER['APP_ENV'] === 'test') {
    setupTestDatabase();
}

function setupTestDatabase(): void {
    static $schemaCreated = false;

    // Only create schema once per test session
    if ($schemaCreated) {
        return;
    }

    try {
        $kernel = new \App\Kernel('test', false);
        $kernel->boot();
        $container = $kernel->getContainer();

        /** @var \Doctrine\ORM\EntityManagerInterface $entityManager */
        $entityManager = $container->get('doctrine.orm.entity_manager');

        // Get schema tool and metadata (fresh metadata will be loaded)
        $schemaTool = new \Doctrine\ORM\Tools\SchemaTool($entityManager);

        // Force fresh metadata by creating a new metadata factory instance
        $metadataFactory = $entityManager->getMetadataFactory();
        $metadata = $metadataFactory->getAllMetadata();

        // Debug: Log metadata for Subscription entity specifically
        foreach ($metadata as $meta) {
            if ($meta->getName() === 'App\Entity\Subscription') {
                $allFields = array_merge($meta->getFieldNames(), array_keys($meta->getAssociationMappings()));
                error_log('Subscription entity fields detected: ' . implode(', ', $allFields));

                // Specifically check for original_transaction_id
                if ($meta->hasField('original_transaction_id')) {
                    error_log('SUCCESS: original_transaction_id field is in metadata');
                } else {
                    error_log('ERROR: original_transaction_id field is NOT in metadata');
                }
                break;
            }
        }

        if (empty($metadata)) {
            throw new RuntimeException('No entity metadata found. Check if entities are properly configured.');
        }

        // Drop existing schema and create fresh schema for testing
        try {
            $schemaTool->dropSchema($metadata);
        } catch (\Exception $e) {
            // Ignore drop errors if tables don't exist
        }

        $schemaTool->createSchema($metadata);

        // Verify the schema was created correctly by checking column existence
        $connection = $entityManager->getConnection();
        try {
            $subscriptionColumns = $connection->fetchAllAssociative("PRAGMA table_info(subscriptions)");
            $columnNames = array_column($subscriptionColumns, 'name');
            if (!in_array('original_transaction_id', $columnNames)) {
                error_log('WARNING: original_transaction_id column not found in subscriptions table. Available columns: ' . implode(', ', $columnNames));
            } else {
                error_log('SUCCESS: original_transaction_id column found in subscriptions table');
            }
        } catch (\Exception $e) {
            error_log('Could not verify schema: ' . $e->getMessage());
        }

        // Log success for debugging
        $entityNames = array_map(fn($meta) => $meta->getName(), $metadata);
        error_log(sprintf('Test database schema created successfully with %d entities: %s', count($metadata), implode(', ', $entityNames)));

        $kernel->shutdown();
        $schemaCreated = true;

    } catch (\Throwable $e) {
        error_log("CRITICAL: Test database setup failed: " . $e->getMessage());

        // Re-throw the exception to fail fast if database setup fails
        throw new RuntimeException('Test database setup failed: ' . $e->getMessage(), 0, $e);
    }
}
