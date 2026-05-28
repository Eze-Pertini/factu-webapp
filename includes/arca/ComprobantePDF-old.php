<?php
// ================================================
// includes/arca/ComprobantePDF.php
// Genera el PDF de un comprobante electronico argentino
// con formato oficial AFIP/ARCA para Factura C Monotributista.
// FPDF debe cargarse desde facturas.php antes de este archivo.
// ================================================

class ComprobantePDF extends FPDF
{
    private array $factura;
    private array $config;

    // Paleta de colores (R, G, B)
    private const AZUL     = [26,  86,  219]; // --brand-primary del sistema
    private const AZUL_BG  = [235, 242, 255];
    private const GRIS_OSC = [80,  80,  80];
    private const GRIS_BG  = [247, 248, 250];
    private const NEGRO    = [30,  30,  30];
    private const BLANCO   = [255, 255, 255];
    private const BORDE    = [210, 218, 234];

    public function __construct(array $factura, array $config)
    {
        parent::__construct('P', 'mm', 'A4');
        $this->factura = $factura;
        $this->config  = $config;
        $this->SetMargins(14, 14, 14);
        $this->SetAutoPageBreak(true, 18);
        $this->AddPage();
        $this->buildDocument();
    }

    // FPDF llama a estos automaticamente — los dejamos vacios
    public function Header(): void {}

    public function Footer(): void
    {
        $this->SetY(-12);
        $this->SetFont('Arial', 'I', 7);
        $this->SetTextColor(...self::GRIS_OSC);
        $this->Cell(0, 5, $this->u('Comprobante generado electronicamente — Pag. ' . $this->PageNo()), 0, 0, 'C');
    }

    // ── CONSTRUCCION ──────────────────────────────────────────────

    private function buildDocument(): void
    {
        $this->sectionEncabezado();
        $this->sectionDatosCliente();
        $this->sectionConceptos();
        $this->sectionTotales();
        $this->sectionTransparenciaFiscal();
        $this->sectionCAE();
    }

    // ── 1. ENCABEZADO ─────────────────────────────────────────────
    // Layout: [datos emisor | C COD.11 | numero/fecha/cuit]

    private function sectionEncabezado(): void
    {
        $yTop  = 14;
        $altura = 50;
        $ancho  = 182;
        $xDiv   = 14 + $ancho / 2; // x=105, centro de la pagina

        // Borde exterior
        $this->SetDrawColor(...self::BORDE);
        $this->SetFillColor(...self::BLANCO);
        $this->Rect(14, $yTop, $ancho, $altura, 'DF');

        // ── Columna izquierda: datos del emisor ──
        $razonSocial   = $this->config['razon_social']     ?? '';
        $cuit          = $this->config['cuit']             ?? '';
        $condicion     = $this->config['condicion_fiscal'] ?? 'Monotributista';
        $categoriaMono = $this->config['categoria_mono']   ?? '';
        $domicilio     = $this->config['domicilio']        ?? '';
        $pisoDpto      = $this->config['piso_dpto']        ?? '';
        $ciudad        = $this->config['ciudad']           ?? '';
        $provincia     = $this->config['provincia']        ?? '';
        $cp            = $this->config['codigo_postal']    ?? '';
        $telefono      = $this->config['telefono']         ?? '';
        $emailEmis     = $this->config['email_contacto']   ?? '';
        $iibb          = $this->config['iibb']             ?? $cuit;
        $pto           = str_pad($this->config['punto_venta'] ?? '1', 4, '0', STR_PAD_LEFT);
        $inicioAct     = $this->formatFecha($this->config['inicio_actividades'] ?? '');

        // Razon social en negrita
        $this->SetFont('Arial', 'B', 10);
        $this->SetTextColor(...self::NEGRO);
        $this->SetXY(17, $yTop + 3);
        $this->Cell(84, 5, $this->u($razonSocial), 0, 1, 'L');

        // Domicilio: calle + piso/dpto en una linea
        $lineasDom = [];
        if ($domicilio) {
            $linea = $domicilio . ($pisoDpto ? ' ' . $pisoDpto : '');
            $lineasDom[] = $this->u($linea);
        }
        $localidad = trim(implode(' - ', array_filter([$ciudad, $provincia])));
        if ($localidad) {
            $lineasDom[] = $this->u($localidad . ($cp ? ' - CP: ' . $cp : ''));
        }

        $lineasInfo = array_values(array_filter(array_merge(
            $lineasDom,
            [
                $telefono  ? $this->u('Tel: ' . $telefono)   : '',
                $emailEmis ? $this->u($emailEmis)            : '',
                'CUIT: ' . $cuit,
                $this->u($condicion . ($categoriaMono ? ' - Cat. ' . $categoriaMono : '')),
                'IIBB: ' . $iibb,
                $this->u('Inicio Actividades: ' . $inicioAct),
            ]
        )));

        $this->SetFont('Arial', '', 7.5);
        $this->SetTextColor(...self::GRIS_OSC);
        $y = $yTop + 10;
        foreach ($lineasInfo as $linea) {
            if ($y > $yTop + $altura - 3) break;
            $this->SetXY(17, $y);
            $this->Cell(84, 4, $linea, 0, 1, 'L');
            $y += 4;
        }

        // ── Divisor vertical central ──
        $this->SetDrawColor(...self::BORDE);
        $this->Line($xDiv, $yTop, $xDiv, $yTop + $altura);

        // ── Centro: cuadro con letra C ──
        $letraW = 20;
        $letraX = $xDiv - ($letraW / 2);
        $letraY = $yTop + 7;

        $this->SetDrawColor(...self::NEGRO);
        $this->Rect($letraX, $letraY, $letraW, 17, 'D');

        $this->SetFont('Arial', 'B', 22);
        $this->SetTextColor(...self::NEGRO);
        $this->SetXY($letraX, $letraY + 1);
        $this->Cell($letraW, 9, 'C', 0, 1, 'C');

        $this->SetFont('Arial', '', 6.5);
        $this->SetTextColor(...self::GRIS_OSC);
        $this->SetXY($letraX, $letraY + 11);
        $this->Cell($letraW, 4, 'COD. 11', 0, 1, 'C');

        $this->SetFont('Arial', 'B', 7.5);
        $this->SetTextColor(...self::NEGRO);
        $this->SetXY($letraX - 6, $letraY + 26);
        $this->Cell($letraW + 12, 4, $this->u('FACTURA C'), 0, 1, 'C');

        // ── Columna derecha: alineada a la derecha para no pisar la C ──
        $xR = $xDiv + 4;
        $wR = (14 + $ancho) - $xR - 2; // hasta el margen derecho
        $numero = $this->factura['numero_arca'] ?? ltrim(explode('-', $this->factura['numero'] ?? '0-0')[1] ?? '0', '0');
        $numFmt = $pto . '-' . str_pad((int)$numero, 8, '0', STR_PAD_LEFT);
        $fecha  = $this->formatFecha($this->factura['fecha_emision'] ?? '');

        $this->SetFont('Arial', 'B', 8.5);
        $this->SetTextColor(...self::NEGRO);
        $this->SetXY($xR, $yTop + 4);
        $this->Cell($wR, 5, $this->u('FACTURA C N') . chr(176) . $numFmt, 0, 1, 'R');

        $datosComp = [
            'Fecha:'       => $fecha,
            'CUIT:'        => $cuit,
            'Inicio Act.:' => $inicioAct,
            'IIBB:'        => $iibb,
            'Razon Social:'=> $this->u($razonSocial),
        ];
        $y = $yTop + 11;
        foreach ($datosComp as $label => $val) {
            $this->SetXY($xR, $y);
            $this->SetFont('Arial', 'B', 7);
            $this->SetTextColor(...self::NEGRO);
            $this->Cell(28, 4, $this->u($label), 0, 0, 'R');
            $this->SetFont('Arial', '', 7);
            $this->SetTextColor(...self::GRIS_OSC);
            $this->Cell($wR - 29, 4, $val, 0, 1, 'R');
            $y += 4;
        }

        $this->SetDrawColor(...self::BORDE);
        $this->SetY($yTop + $altura + 4);
    }

    // ── 2. DATOS CLIENTE / CONDICIONES ───────────────────────────

    private function sectionDatosCliente(): void
    {
        $yStart = $this->GetY();
        $altura = 30;
        $ancho  = 182;
        $xMid   = 14 + $ancho / 2;

        $this->SetDrawColor(...self::BORDE);
        $this->SetFillColor(...self::GRIS_BG);
        $this->Rect(14, $yStart, $ancho, $altura, 'DF');
        $this->Line($xMid, $yStart, $xMid, $yStart + $altura);

        // ── Titulo izquierda ──
        $this->SetFont('Arial', 'B', 7.5);
        $this->SetTextColor(...self::AZUL);
        $this->SetXY(17, $yStart + 2);
        $this->Cell(80, 4, $this->u('INFORMACION DEL CLIENTE'), 0, 1, 'L');

        $nombre  = $this->factura['cliente_nombre']   ?? '';
        $email   = $this->factura['cliente_email']    ?? '';
        $docTipo = $this->factura['cliente_doc_tipo'] ?? '';
        $docNro  = $this->factura['cliente_doc_nro']  ?? '';

        $nombreMostrar = $nombre ?: 'Consumidor Final';
        $docLabel = match(strtoupper($docTipo)) {
            'CUIT'  => 'CUIT',
            'CUIL'  => 'CUIL',
            'DNI'   => 'DNI',
            default => 'Doc.',
        };

        $datosCliente = [
            'Cliente:'      => $this->u($nombreMostrar),
            'Email:'        => $this->u($email ?: '-'),
            $docLabel . ':' => $docNro ?: '-',
            'Condicion:'    => $this->u('Consumidor Final'),
        ];
        $y = $yStart + 8;
        foreach ($datosCliente as $label => $val) {
            $this->SetXY(17, $y);
            $this->SetFont('Arial', 'B', 7.5);
            $this->SetTextColor(...self::NEGRO);
            $this->Cell(24, 4, $this->u($label), 0, 0, 'L');
            $this->SetFont('Arial', '', 7.5);
            $this->SetTextColor(...self::GRIS_OSC);
            $this->Cell(62, 4, $val, 0, 1, 'L');
            $y += 4;
        }

        // ── Titulo derecha ──
        $this->SetFont('Arial', 'B', 7.5);
        $this->SetTextColor(...self::AZUL);
        $this->SetXY($xMid + 3, $yStart + 2);
        $this->Cell(80, 4, $this->u('CONDICIONES DE VENTA'), 0, 1, 'L');

        // forma_pago dinamico: viene del tipo MP o del formulario manual
        $formaPago = $this->factura['forma_pago'] ?? '';
        $condVentaLabel = $formaPago ?: 'Contado';

        $condVenta = [
            $this->u('Condicion de venta:') => $this->u($condVentaLabel),
            $this->u('Tipo:')               => $this->u($this->factura['concepto'] ?? 'Servicios'),
            $this->u('F. Servicio desde:')  => $this->formatFecha($this->factura['fecha_servicio'] ?? ''),
            $this->u('F. Vto. de pago:')    => $this->formatFecha($this->factura['fecha_cobro'] ?? ''),
        ];
        $y = $yStart + 8;
        foreach ($condVenta as $label => $val) {
            $this->SetXY($xMid + 3, $y);
            $this->SetFont('Arial', 'B', 7.5);
            $this->SetTextColor(...self::NEGRO);
            $this->Cell(34, 4, $label, 0, 0, 'L');
            $this->SetFont('Arial', '', 7.5);
            $this->SetTextColor(...self::GRIS_OSC);
            $this->Cell(46, 4, $val, 0, 1, 'L');
            $y += 4;
        }

        $this->SetY($yStart + $altura + 4);
    }


    // ── 3. TABLA DE CONCEPTOS ──────────────────────────────────────

    private function sectionConceptos(): void
    {
        $cols = [
            ['label' => 'Cantidad',       'w' => 22,  'align' => 'C'],
            ['label' => 'Codigo',         'w' => 22,  'align' => 'C'],
            ['label' => 'Descripcion',    'w' => 76,  'align' => 'L'],
            ['label' => '% Bonif.',       'w' => 20,  'align' => 'C'],
            ['label' => 'Precio Unit.',   'w' => 22,  'align' => 'R'],
            ['label' => 'Subtotal',       'w' => 20,  'align' => 'R'],
        ];

        // Header tabla
        $this->SetFillColor(...self::AZUL);
        $this->SetTextColor(...self::BLANCO);
        $this->SetFont('Arial', 'B', 7.5);
        $this->SetDrawColor(...self::BORDE);

        foreach ($cols as $col) {
            $this->Cell($col['w'], 6, $this->u($col['label']), 1, 0, $col['align'], true);
        }
        $this->Ln();

        // Fila de datos
        $this->SetFillColor(...self::BLANCO);
        $this->SetTextColor(...self::NEGRO);
        $this->SetFont('Arial', '', 8);

        $descripcion = $this->factura['producto'] ?? $this->factura['concepto'] ?? 'Servicios';
        $monto       = (float)($this->factura['monto_total'] ?? 0);

        $fila = [
            '1,00',
            '-',
            $this->u($descripcion),
            '0,00',
            $this->formatMonto($monto),
            $this->formatMonto($monto),
        ];

        foreach ($cols as $i => $col) {
            $this->Cell($col['w'], 7, $fila[$i], 1, 0, $col['align'], true);
        }
        $this->Ln(10);
    }

    // ── 4. TOTALES ────────────────────────────────────────────────

    private function sectionTotales(): void
    {
        $monto  = (float)($this->factura['monto_total'] ?? 0);
        $xRight = 196 - 44; // margen derecho - ancho celdas
        $wLabel = 24;
        $wVal   = 20;

        $this->SetFont('Arial', '', 8);
        $this->SetTextColor(...self::NEGRO);
        $this->SetDrawColor(...self::BORDE);

        // Subtotal
        $this->SetX($xRight);
        $this->Cell($wLabel, 5, 'Subtotal', 1, 0, 'R');
        $this->Cell($wVal,   5, $this->formatMonto($monto), 1, 1, 'R');

        // Descuento
        $this->SetX($xRight);
        $this->Cell($wLabel, 5, $this->u('Total Descuento'), 1, 0, 'R');
        $this->Cell($wVal,   5, '$ 0,00', 1, 1, 'R');

        // TOTAL destacado
        $this->SetFillColor(...self::AZUL);
        $this->SetTextColor(...self::BLANCO);
        $this->SetFont('Arial', 'B', 9);
        $this->SetX($xRight);
        $this->Cell($wLabel, 7, 'TOTAL', 1, 0, 'R', true);
        $this->Cell($wVal,   7, $this->formatMonto($monto), 1, 1, 'R', true);

        $this->SetTextColor(...self::NEGRO);
        $this->Ln(4);
    }

    // ── 5. TRANSPARENCIA FISCAL ───────────────────────────────────

    private function sectionTransparenciaFiscal(): void
    {
        $this->SetFont('Arial', 'B', 7.5);
        $this->SetTextColor(...self::NEGRO);
        $this->SetX(14);
        $this->Cell(182, 5, $this->u('Regimen de Transparencia Fiscal al Consumidor (Ley 27.743)'), 0, 1, 'L');

        $this->SetFont('Arial', '', 7.5);
        $this->SetTextColor(...self::GRIS_OSC);
        $this->SetX(14);
        $this->Cell(182, 4, 'IVA CONTENIDO', 0, 1, 'L');
        $this->Ln(3);
    }

    // ── 6. CAE ────────────────────────────────────────────────────

    private function sectionCAE(): void
    {
        $cae  = $this->factura['cae']             ?? '';
        $venc = $this->factura['cae_vencimiento'] ?? '';

        $this->SetDrawColor(...self::BORDE);

        if (empty($cae)) {
            $this->SetFillColor(255, 243, 205);
            $this->SetTextColor(133, 100, 4);
            $this->SetFont('Arial', 'B', 8);
            $this->SetX(14);
            $this->Cell(182, 8, $this->u('ATENCION: Comprobante sin CAE — No valido ante AFIP/ARCA'), 1, 1, 'C', true);
            return;
        }

        $yStart = $this->GetY();
        $altura = 18;

        // Fondo
        $this->SetFillColor(...self::AZUL_BG);
        $this->Rect(14, $yStart, 182, $altura, 'DF');

        // CAE Nro
        $this->SetFont('Arial', 'B', 8);
        $this->SetTextColor(...self::AZUL);
        $this->SetXY(17, $yStart + 3);
        $this->Cell(60, 5, 'CAE N' . chr(176) . ': ' . $cae, 0, 0, 'L');

        // Vencimiento
        $this->SetFont('Arial', '', 8);
        $this->SetTextColor(...self::GRIS_OSC);
        $this->SetXY(17, $yStart + 10);
        $this->Cell(120, 4, $this->u('Fecha de Vto. de CAE: ') . $this->formatFechaCAE($venc), 0, 1, 'L');

        // Nota legal
        $this->SetFont('Arial', 'I', 6.5);
        $this->SetTextColor(...self::GRIS_OSC);
        $this->SetXY(90, $yStart + 3);
        $this->MultiCell(103, 3.5,
            $this->u('Comprobante autorizado por ARCA (ex-AFIP) en el marco del regimen de facturacion electronica. ') .
            $this->u('El CAE es la constancia de validacion del comprobante ante el organismo recaudador.'),
            0, 'L'
        );
    }

    // ── HELPERS ───────────────────────────────────────────────────

    /**
     * Convierte UTF-8 a ISO-8859-1 para FPDF.
     * Elimina caracteres no representables en lugar de romper el PDF.
     */
    private function u(string $str): string
    {
        // Reemplazar caracteres especiales frecuentes en español
        // que no tienen equivalente exacto en ISO-8859-1 o que
        // FPDF no renderiza bien
        $replacements = [
            "\u{2014}" => '-',   // em dash
            "\u{2013}" => '-',   // en dash
            "\u{201C}" => '"',   // comilla izquierda
            "\u{201D}" => '"',   // comilla derecha
            "\u{2019}" => "'",   // apostrofe
            "\u{00B0}" => chr(176), // grado — ya en ISO
        ];
        $str = strtr($str, $replacements);
        return mb_convert_encoding($str, 'ISO-8859-1', 'UTF-8');
    }

    private function formatFecha(string $fecha): string
    {
        if (empty($fecha)) return '—';
        try {
            return (new DateTime($fecha))->format('d/m/Y');
        } catch (Exception) {
            return $fecha;
        }
    }

    private function formatFechaCAE(string $fecha): string
    {
        // ARCA devuelve YYYYMMDD; en DB queda YYYY-MM-DD
        if (strlen($fecha) === 8 && ctype_digit($fecha)) {
            return substr($fecha, 6, 2) . '/' . substr($fecha, 4, 2) . '/' . substr($fecha, 0, 4);
        }
        return $this->formatFecha($fecha);
    }

    private function formatMonto(float $monto): string
    {
        return '$ ' . number_format($monto, 2, ',', '.');
    }

    // ── SALIDA ────────────────────────────────────────────────────

    public function descargar(string $filename = 'comprobante.pdf'): void
    {
        $this->Output('D', $filename);
    }

    public function getString(): string
    {
        return $this->Output('S');
    }
}
