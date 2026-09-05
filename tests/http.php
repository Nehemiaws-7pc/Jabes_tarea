<?php
declare(strict_types=1);
require __DIR__ . '/../src/bootstrap.php';
if (getenv('DB_NAME') !== 'ventas_test') {
    fwrite(STDERR, "Estas pruebas solo se ejecutan en ventas_test.\n");
    exit(1);
}
require __DIR__ . '/unit.php';
$cookie = '';
$csrf = '';
function request(string $resource, ?array $body = null, bool $sendCsrf = true): array
{
    global $cookie, $csrf;
    $headers = ['Content-Type: application/json'];
    if ($cookie !== '') $headers[] = 'Cookie: ' . $cookie;
    if ($sendCsrf && $csrf !== '') $headers[] = 'X-CSRF-Token: ' . $csrf;
    $context = stream_context_create(['http' => [
        'method' => $body === null ? 'GET' : 'POST', 'header' => implode("\r\n", $headers),
        'content' => $body === null ? '' : json_encode($body), 'ignore_errors' => true, 'timeout' => 45,
    ]]);
    $raw = file_get_contents('http://localhost/api.php?resource=' . $resource, false, $context);
    foreach ($http_response_header as $header) {
        if (preg_match('/^Set-Cookie: ([^;]+)/i', $header, $matches)) $cookie = $matches[1];
    }
    preg_match('/HTTP\/\S+ (\d+)/', $http_response_header[0], $matches);
    return ['status' => (int) $matches[1], 'body' => json_decode($raw, true, 512, JSON_THROW_ON_ERROR)];
}
$session = request('session');
$csrf = $session['body']['csrf'];
check(request('health')['status'] === 200, 'HTTP conecta con MySQL');
check(request('products', [], false)['status'] === 403, 'Escritura sin CSRF rechazada');
check(request('products', ['code' => 'x', 'name' => 'x', 'price_usd' => '-1'])['status'] === 422, 'Error de validación HTTP');
check(request('rates')['body']['status'] === 'saved', 'Leer tasa no afirma consulta actual');
$rate = request('rates', []);
check($rate['status'] === 502 && $rate['body']['status'] === 'unavailable', 'Fallo SOAP devuelve error HTTP 502');
check($rate['body']['saved_rate'] !== null, 'Fallo SOAP ofrece tasa guardada identificada');
$db = Database::connect();
$customer = $db->query('SELECT id FROM customers ORDER BY id DESC LIMIT 1')->fetchColumn();
$product = $db->query('SELECT id FROM products ORDER BY id DESC LIMIT 1')->fetchColumn();
$payload = ['request_key' => bin2hex(random_bytes(18)), 'customer_id' => (int) $customer,
    'rate_id' => (int) $rate['body']['saved_rate']['id'], 'rate_usage' => 'saved', 'payment_method' => 'Tarjeta',
    'items' => [['product_id' => (int) $product, 'quantity' => 1]]];
$result = request('sales', $payload);
check($result['status'] === 201, 'Venta HTTP con aceptación explícita de tasa guardada');
$id = $result['body']['data']['id'];
check(request('sales', $payload)['body']['data']['id'] === $id, 'Reintento HTTP conserva el mismo comprobante');
check(count(request('sales&id=' . $id)['body']['data']['details']) === 1, 'Consulta HTTP recupera detalles');
check(request('no-existe')['status'] === 404, 'Ruta inexistente devuelve 404');
echo "Pruebas HTTP correctas. No se imprimen cookies ni tokens.\n";

