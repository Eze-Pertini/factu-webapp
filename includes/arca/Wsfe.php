<?php
// ================================================
// includes/arca/Wsfe.php — Facturación Electrónica
// Web Service de Facturación Electrónica
// ================================================

class Wsfe {

    const URL_PROD = 'https://servicios1.afip.gov.ar/wsfev1/service.asmx?WSDL';
    const URL_TEST = 'https://wswhomo.afip.gov.ar/wsfev1/service.asmx?WSDL';

    private SoapClient $client;
    private string     $token;
    private string     $sign;
    private int        $cuit;

    public function __construct(
        string $token,
        string $sign,
        int    $cuit,
        bool   $produccion = true
    ) {
        $this->token = $token;
        $this->sign  = $sign;
        $this->cuit  = $cuit;

        $url = $produccion ? self::URL_PROD : self::URL_TEST;

        $this->client = new SoapClient($url, [
            'soap_version'   => SOAP_1_2,
            'trace'          => false,
            'exceptions'     => true,
            'stream_context' => stream_context_create([
                'ssl' => [
                    'verify_peer'      => false,
                    'verify_peer_name' => false,
                ]
            ])
        ]);
    }

    // ── Auth para cada request ──
    private function auth(): array {
        return [
            'Token' => $this->token,
            'Sign'  => $this->sign,
            'Cuit'  => $this->cuit,
        ];
    }

    // ── Obtener último número de comprobante ──
    public function ultimoComprobante(int $puntoVenta, int $tipoComprobante): int {
        $result = $this->client->FECompUltimoAutorizado([
            'Auth'    => $this->auth(),
            'PtoVta'  => $puntoVenta,
            'CbteTipo'=> $tipoComprobante,
        ]);

        $resp = $result->FECompUltimoAutorizadoResult ?? null;

        if (!$resp) {
            throw new Exception('WSFE no devolvió respuesta válida.');
        }

        if (isset($resp->Errors->Err)) {
            $err = $resp->Errors->Err;
            throw new Exception("Error WSFE [{$err->Code}]: {$err->Msg}");
        }

        return (int)($resp->CbteNro ?? 0);
    }

    // ── Emitir comprobante y obtener CAE ──
    public function emitirComprobante(array $datos): array {
        $request = [
            'Auth' => $this->auth(),
            'FeCAEReq' => [
                'FeCabReq' => [
                    'CantReg'  => 1,
                    'PtoVta'   => $datos['punto_venta'],
                    'CbteTipo' => $datos['tipo_cbte'],
                ],
                'FeDetReq' => [
                    'FECAEDetRequest' => [
                        'Concepto'               => $datos['concepto'],
                        'DocTipo'                => $datos['doc_tipo']         ?? 99,
                        'DocNro'                 => $datos['doc_nro']          ?? 0,
                        'CbteDesde'              => $datos['cbte_numero'],
                        'CbteHasta'              => $datos['cbte_numero'],
                        'CbteFch'                => $datos['fecha_cbte'],
                        'ImpTotal'               => $datos['importe_total'],
                        'ImpTotConc'             => 0,
                        'ImpNeto'                => $datos['importe_total'],
                        'ImpOpEx'                => 0,
                        'ImpIVA'                 => 0,
                        'ImpTrib'                => 0,
                        'MonId'                  => 'PES',
                        'MonCotiz'               => 1,
                        'CondicionIVAReceptorId' => $datos['condicion_iva_recp'] ?? 5, // 5 = Consumidor Final (RG 5616)
                    ]
                ]
            ]
        ];

        // Agregar fechas de servicio si corresponde (concepto 2=Servicios o 3=Ambos)
        if ($datos['concepto'] === 2 || $datos['concepto'] === 3) {
            $request['FeCAEReq']['FeDetReq']['FECAEDetRequest']['FchServDesde'] = $datos['fecha_serv_desde'];
            $request['FeCAEReq']['FeDetReq']['FECAEDetRequest']['FchServHasta'] = $datos['fecha_serv_hasta'];
            $request['FeCAEReq']['FeDetReq']['FECAEDetRequest']['FchVtoPago']   = $datos['fecha_vto_pago'];
        }

        $result = $this->client->FECAESolicitar($request);
        $resp   = $result->FECAESolicitarResult ?? null;

        if (!$resp) {
            throw new Exception('WSFE no devolvió respuesta válida al emitir.');
        }

        // Verificar errores generales
        if (isset($resp->Errors->Err)) {
            $err = $resp->Errors->Err;
            throw new Exception("Error WSFE [{$err->Code}]: {$err->Msg}");
        }

        $detalle = $resp->FeDetResp->FECAEDetResponse ?? null;

        if (!$detalle) {
            throw new Exception('WSFE no devolvió detalle del comprobante.');
        }

        // Verificar observaciones (errores del comprobante)
        if (isset($detalle->Observaciones->Obs)) {
            $obs = $detalle->Observaciones->Obs;
            $msg = is_array($obs) ? $obs[0]->Msg : $obs->Msg;
            throw new Exception("Observación WSFE: {$msg}");
        }

        $resultado = (string)($detalle->Resultado ?? '');

        if ($resultado !== 'A') {
            throw new Exception("WSFE rechazó el comprobante. Resultado: {$resultado}");
        }

        return [
            'cae'            => (string)($detalle->CAE        ?? ''),
            'cae_vencimiento'=> (string)($detalle->CAEFchVto  ?? ''),
            'numero'         => (int)   ($detalle->CbteDesde  ?? 0),
            'resultado'      => $resultado,
        ];
    }
}
