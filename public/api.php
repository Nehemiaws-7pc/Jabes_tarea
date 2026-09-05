<?php
declare(strict_types=1);
require __DIR__ . '/../src/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

function respond(array $body, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    exit;
}

$resource = $_GET['resource'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];
try {
    if ($resource === 'health' && $method === 'GET') {
        Database::connect()->query('SELECT 1');
        respond(['ok' => true]);
    }
    session_start();
    $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
    if ($resource === 'session' && $method === 'GET') {
        respond(['csrf' => $_SESSION['csrf'], 'tax_percent' => Money::TAX_PERCENT]);
    }
    if (!in_array($method, ['GET', 'POST'], true)) {
        header('Allow: GET, POST');
        respond(['error' => 'Método no permitido.'], 405);
    }
    $input = [];
    if ($method === 'POST') {
        if (!hash_equals($_SESSION['csrf'], $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) {
            respond(['error' => 'La sesión cambió. Recarga la página antes de guardar.'], 403);
        }
        if ((int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 65536) {
            respond(['error' => 'Solicitud demasiado grande.'], 413);
        }
        $input = json_decode(file_get_contents('php://input'), true, 32, JSON_THROW_ON_ERROR);
        if (!is_array($input)) {
            throw new InvalidArgumentException('Se requiere un objeto JSON.');
        }
    }
    $db = Database::connect();
    $catalog = new CatalogRepository($db);
    if ($resource === 'customers') {
        respond($method === 'GET' ? ['data' => $catalog->customers()] : ['id' => $catalog->createCustomer($input)], $method === 'GET' ? 200 : 201);
    }
    if ($resource === 'products') {
        respond($method === 'GET' ? ['data' => $catalog->products()] : ['id' => $catalog->createProduct($input)], $method === 'GET' ? 200 : 201);
    }
    if ($resource === 'rates') {
        $rates = new RateRepository($db);
        if ($method === 'GET') {
            respond(['data' => $rates->latest(), 'status' => 'saved']);
        }
        unset($_SESSION['live_rate']);
        try {
            $rate = (new BanguatClient())->fetchToday();
        } catch (RuntimeException $error) {
            respond(['error' => $error->getMessage(), 'status' => 'unavailable', 'saved_rate' => $rates->latest()], 502);
        }
        $rate = $rates->save($rate);
        $_SESSION['live_rate'] = ['id' => (int) $rate['id'], 'expires_at' => time() + 600, 'date' => date('Y-m-d')];
        respond(['data' => $rate, 'status' => 'current', 'valid_for_seconds' => 600]);
    }
    if ($resource === 'sales') {
        $sales = new SaleRepository($db);
        if ($method === 'POST') {
            respond(['data' => (new SaleService($db))->create($input, $_SESSION['live_rate'] ?? [])], 201);
        }
        if (isset($_GET['id'])) {
            $sale = $sales->find(Validation::id($_GET['id']));
            respond($sale ? ['data' => $sale] : ['error' => 'Venta no encontrada.'], $sale ? 200 : 404);
        }
        respond(['data' => $sales->listing()]);
    }
    respond(['error' => 'Recurso no encontrado.'], 404);
} catch (InvalidArgumentException | JsonException $error) {
    respond(['error' => $error instanceof JsonException ? 'JSON inválido.' : $error->getMessage()], 422);
} catch (PDOException $error) {
    if (($error->errorInfo[1] ?? null) === 1062) {
        respond(['error' => 'El NIT/identificación o código ya está registrado.'], 409);
    }
    error_log('Error de base de datos, SQLSTATE: ' . $error->getCode());
    respond(['error' => 'No se pudo completar la operación en MySQL. Comprueba la conexión y el esquema.'], 503);
} catch (Throwable $error) {
    error_log('Error de aplicación: ' . get_class($error));
    respond(['error' => 'Ocurrió un error interno. Revisa los registros de la aplicación.'], 500);
}

