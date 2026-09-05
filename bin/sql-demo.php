<?php
declare(strict_types=1);
require __DIR__ . '/../src/bootstrap.php';
try {
    $db = Database::connect();
    $sql = preg_replace('/^--.*$/m', '', file_get_contents(__DIR__ . '/../sql/demo.sql'));
    foreach (explode(';', $sql) as $query) {
        if (trim($query) === '') {
            continue;
        }
        echo trim($query) . ";\n";
        echo json_encode($db->query($query)->fetchAll(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n\n";
    }
} catch (Throwable $error) {
    fwrite(STDERR, "No se pudieron consultar los registros de MySQL.\n");
    exit(1);
}

