<?php

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../src/Kernel.php';

use Symfony\Component\Dotenv\Dotenv;

use App\Kernel;

(new Dotenv())->bootEnv(__DIR__ . '/../.env');

$kernel = new Kernel($_SERVER['APP_ENV'] ?? 'dev', (bool) ($_SERVER['APP_DEBUG'] ?? true));
$kernel->boot();

$container = $kernel->getContainer();
$storage = $container->get('doctrine.migrations.metadata_storage');

$ref = new ReflectionClass($storage);
$getExpectedTable = $ref->getMethod('getExpectedTable');
$getExpectedTable->setAccessible(true);
$expected = $getExpectedTable->invoke($storage);

$needsUpdate = $ref->getMethod('needsUpdate');
$needsUpdate->setAccessible(true);
$diff = $needsUpdate->invoke($storage, $expected);

if ($diff === null) {
    echo "NO_DIFF\n";
    exit(0);
}

echo "DIFF\n";
var_dump(
    $diff->addedColumns,
    $diff->changedColumns,
    $diff->removedColumns,
    $diff->addedIndexes,
    $diff->changedIndexes,
    $diff->removedIndexes
);
