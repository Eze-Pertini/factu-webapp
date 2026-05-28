<?php
// ================================================
// includes/arca/ArcaService.php
// Servicio compartido para emitir CAE desde PHP
// Usado por api/facturas.php internamente
// ================================================

require_once __DIR__ . '/Wsaa.php';
require_once __DIR__ . '/Wsfe.php';

class ArcaService {

    private Wsfe   $wsfe;
    private int    $puntoVenta;
    private bool   $inicializado = false;
    private string $error        = '';

    public function __construct(PDO $db, int $usuarioId) {
        try {
            // Obtener datos fiscales
            $stmt = $db->prepare("SELECT cuit, punto_venta FROM configuracion WHERE usuario_id = :uid LIMIT 1");
            $stmt->execute([':uid' => $usuarioId]);
            $config = $stmt->fetch();

            if (!$config || empty($config['cuit'])) {
                $this->error = 'CUIT no configurado.';
                return;
            }

            $cuit             = (int)preg_replace('/[^0-9]/', '', $config['cuit']);
            $this->puntoVenta = (int)$config['punto_venta'];

            // Rutas de certificados
            $certsDir = realpath(__DIR__ . '/../../certs') . DIRECTORY_SEPARATOR;
            $certFile = $certsDir . 'factu.crt';
            $keyFile  = $certsDir . 'factu.key';

            if (!file_exists($certFile) || !file_exists($keyFile)) {
                $this->error = 'Certificados no encontrados.';
                return;
            }

            // Autenticar con WSAA
            $wsaa   = new Wsaa($certFile, $keyFile, $certsDir, true);
            $ticket = $wsaa->obtenerTicket();

            // Inicializar WSFE
            $this->wsfe         = new Wsfe($ticket['token'], $ticket['sign'], $cuit, true);
            $this->inicializado = true;

        } catch (Exception $e) {
            $this->error = 'Error inicializando ARCA: ' . $e->getMessage();
            error_log('[ARCA constructor] ' . $e->getMessage());
        }
    }

    public function estaListo(): bool {
        return $this->inicializado;
    }

    public function getError(): string {
        return $this->error;
    }

    // ── Emitir CAE para una factura ──
    public function emitirCae(array $factura): array {
        if (!$this->inicializado) {
            return ['ok' => false, 'error' => $this->error];
        }

        try {
            // Mapear concepto
            $conceptoMap = [
                'Productos'             => 1,
                'Servicios'             => 2,
                'Productos y Servicios' => 3,
            ];
            $conceptoCodigo = $conceptoMap[$factura['concepto']] ?? 2;

            // Obtener próximo número
            $ultimo  = $this->wsfe->ultimoComprobante($this->puntoVenta, 11);
            $proximo = $ultimo + 1;

            // Preparar fechas
            $fechaCbte      = date('Ymd');
            $fechaServDesde = date('Ymd', strtotime($factura['fecha_servicio']));
            $fechaServHasta = date('Ymd', strtotime($factura['fecha_servicio']));
            $fechaVtoPago   = date('Ymd', strtotime($factura['fecha_cobro']));

            // Emitir
            $resultado = $this->wsfe->emitirComprobante([
                'punto_venta'      => $this->puntoVenta,
                'tipo_cbte'        => 11,
                'concepto'         => $conceptoCodigo,
                'doc_tipo'         => 99,
                'doc_nro'          => 0,
                'cbte_numero'      => $proximo,
                'fecha_cbte'       => $fechaCbte,
                'importe_total'    => (float)$factura['monto_total'],
                'fecha_serv_desde' => $fechaServDesde,
                'fecha_serv_hasta' => $fechaServHasta,
                'fecha_vto_pago'   => $fechaVtoPago,
            ]);

            return [
                'ok'              => true,
                'cae'             => $resultado['cae'],
                'cae_vencimiento' => $resultado['cae_vencimiento'],
                'numero_arca'     => $proximo,
            ];

        } catch (Exception $e) {
            error_log('[ARCA emitirCae] ' . $e->getMessage());
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }
}
