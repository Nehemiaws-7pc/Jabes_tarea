<?php
declare(strict_types=1);
require __DIR__ . '/../src/bootstrap.php';

$count = 0;
function check(bool $result, string $description): void
{
    global $count;
    if (!$result) { throw new RuntimeException("FALLO: $description"); }
    $count++;
    echo "OK: $description\n";
}
function rejects(callable $run, string $description): void
{
    try { $run(); } catch (InvalidArgumentException | RuntimeException) { check(true, $description); return; }
    check(false, $description);
}
$totals = Money::totals('12.35', '7.800000'); // Fixture sintético, nunca se carga a la aplicación.
check($totals['tax_usd'] === '1.48', 'IVA redondeado al centavo');
check($totals['total_usd'] === '13.83', 'Total USD exacto');
check($totals['total_gtq'] === '107.87', 'Conversión GTQ exacta');
check(Money::totals('0.05', '1.000000')['tax_usd'] === '0.01', 'Redondeo de fracción de centavo');
check(Money::totals('0.10', '7.650000')['total_gtq'] === '0.84', 'Conversión con redondeo');
rejects(fn() => Money::price('-1'), 'Precio negativo rechazado');
rejects(fn() => Money::price('1.001'), 'Más de dos decimales rechazados');
rejects(fn() => Money::price('1e2'), 'Notación exponencial rechazada');
rejects(fn() => Money::totals('100000000.00', '7.000000'), 'Límite de total');
rejects(fn() => Validation::id(1.5, 'Cantidad'), 'Cantidad fraccionaria rechazada');
$fixture = (object) ['TipoCambioDiaResult' => (object) ['CambioDolar' => (object) [
    'VarDolar' => (object) ['fecha' => '01/01/2020', 'referencia' => 7.65],
]]];
check(BanguatClient::parse($fixture)['rate'] === '7.650000', 'Parseo de estructura SOAP');
$fixture->TipoCambioDiaResult->CambioDolar->VarDolar->fecha = '31/02/2020';
rejects(fn() => BanguatClient::parse($fixture), 'Fecha imposible rechazada');
rejects(fn() => BanguatClient::parse((object) []), 'Respuesta SOAP incompleta rechazada');
$fixture->TipoCambioDiaResult->CambioDolar->VarDolar = [
    (object) ['fecha' => '01/01/2020', 'referencia' => 7.65],
    (object) ['fecha' => '01/01/2020', 'referencia' => 7.66],
];
rejects(fn() => BanguatClient::parse($fixture), 'Referencias ambiguas rechazadas');
echo "$count pruebas correctas.\n";

