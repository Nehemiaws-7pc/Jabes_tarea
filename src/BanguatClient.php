<?php
declare(strict_types=1);

final class BanguatClient
{
    public const WSDL = 'https://www.banguat.gob.gt/variables/ws/TipoCambio.asmx?WSDL';
    public const ENDPOINT = 'https://www.banguat.gob.gt/variables/ws/TipoCambio.asmx';

    public function fetchToday(): array
    {
        try {
            $client = new SoapClient(self::WSDL, [
                'soap_version' => SOAP_1_1,
                'exceptions' => true,
                'trace' => false,
                'cache_wsdl' => WSDL_CACHE_NONE,
                'connection_timeout' => 8,
                // El WSDL puede anunciar HTTP: forzar el transporte HTTPS.
                'location' => self::ENDPOINT,
                'stream_context' => stream_context_create([
                    'http' => ['timeout' => 15],
                    'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
                ]),
            ]);
            return self::parse($client->__soapCall('TipoCambioDia', []));
        } catch (SoapFault $error) {
            throw new RuntimeException('No se pudo consultar Banguat por SOAP/HTTPS. Comprueba la conexión o intenta más tarde.', 0, $error);
        }
    }

    public static function parse(object $response): array
    {
        $value = $response->TipoCambioDiaResult->CambioDolar->VarDolar ?? null;
        if (is_array($value)) {
            if (count($value) !== 1) {
                throw new RuntimeException('Banguat devolvió más de una referencia; no se seleccionó una tasa automáticamente.');
            }
            $value = $value[0];
        }
        $rawDate = $value->fecha ?? '';
        $rawRate = $value->referencia ?? null;
        if (!is_string($rawDate) || !is_numeric($rawRate)
            || !is_finite((float) $rawRate) || $rawRate <= 0 || $rawRate > 9999) {
            throw new RuntimeException('Banguat devolvió una fecha o tasa inválida.');
        }
        $date = DateTimeImmutable::createFromFormat('!d/m/Y', trim($rawDate));
        if (!$date || $date->format('d/m/Y') !== trim($rawDate) || $date->format('Y-m-d') > date('Y-m-d')) {
            throw new RuntimeException('La fecha de referencia recibida de Banguat no es válida.');
        }
        return [
            'reference_date' => $date->format('Y-m-d'),
            // SOAP define referencia como float; se normaliza una sola vez.
            'rate' => number_format((float) $rawRate, 6, '.', ''),
            'fetched_at' => date('Y-m-d H:i:s'),
        ];
    }
}

