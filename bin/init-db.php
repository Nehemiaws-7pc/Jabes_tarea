<?php
declare(strict_types=1);
require __DIR__ . '/../src/bootstrap.php';
try {
    $db = Database::connect();
    $db->exec(file_get_contents(__DIR__ . '/../sql/schema.sql'));
    echo "Esquema académico inicializado (sin datos ni tasas de ejemplo).\n";
} catch (Throwable $error) {
    fwrite(STDERR, "No se pudo inicializar MySQL. Revisa la configuración y disponibilidad.\n");
    exit(1);
}

