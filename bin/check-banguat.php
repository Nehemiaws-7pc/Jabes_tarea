<?php
declare(strict_types=1);
require __DIR__ . '/../src/bootstrap.php';
try {
    echo json_encode([
        'operation' => 'TipoCambioDia', 'endpoint' => BanguatClient::ENDPOINT,
        'data' => (new BanguatClient())->fetchToday(),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage() . "\n");
    exit(1);
}

