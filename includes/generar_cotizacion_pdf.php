<?php
// ============================================================
//  includes/generar_cotizacion_pdf.php
//  Genera PDF de cotización - Diseño simple y compacto
// ============================================================
ob_start(); // captura cualquier output accidental (warnings, notices)

require_once __DIR__ . '/../config/db.php';

// Apuntar FPDF al directorio real de fuentes (vendor/font/)
define('FPDF_FONTPATH', __DIR__ . '/../vendor/font/');
require_once __DIR__ . '/../vendor/fpdf/fpdf.php';

class CotizacionPDF extends FPDF {
    private $cotizacion;
    
    function __construct($cotizacion_data) {
        parent::__construct('P', 'mm', 'A4');
        $this->cotizacion = $cotizacion_data;
    }
    
    function Header() {
        // Borde superior
        $this->SetDrawColor(27, 58, 107);
        $this->SetLineWidth(0.3);
        $this->Rect(10, 10, 190, 25);
        
        // Logo/Membrete izquierda
        $this->SetFont('Arial', 'B', 12);
        $this->SetTextColor(27, 58, 107);
        $this->SetXY(15, 12);
        $this->Cell(100, 5, utf8_decode(APP_NAME), 0, 1);
        
        // Datos de la empresa
        $this->SetFont('Arial', '', 7);
        $this->SetTextColor(80, 80, 80);
        $this->SetX(15);
        $this->Cell(100, 3, 'RUC: 20123456789', 0, 1);
        $this->SetX(15);
        $this->Cell(100, 3, utf8_decode('Av. Principal 123, Lima, Perú'), 0, 1);
        $this->SetX(15);
        $this->Cell(100, 3, 'Tel: (01) 123-4567 | contacto@corfiem.com', 0, 1);
        
        // Cuadro de cotización (derecha)
        $this->SetFillColor(27, 58, 107);
        $this->SetTextColor(255, 255, 255);
        $this->Rect(145, 12, 50, 20, 'F');
        
        $this->SetFont('Arial', 'B', 8);
        $this->SetXY(145, 14);
        $this->Cell(50, 4, 'COTIZACION', 0, 1, 'C');
        
        $this->SetFont('Arial', 'B', 10);
        $this->SetXY(145, 19);
        $this->Cell(50, 4, $this->cotizacion['numero'], 0, 1, 'C');
        
        $this->SetFont('Arial', '', 6);
        $this->SetXY(145, 24);
        $this->Cell(50, 3, date('d/m/Y', strtotime($this->cotizacion['fecha_emision'])), 0, 1, 'C');
        $this->SetXY(145, 28);
        $this->Cell(50, 3, utf8_decode('Vence: ') . date('d/m/Y', strtotime($this->cotizacion['fecha_vencimiento'])), 0, 1, 'C');
        
        $this->SetTextColor(0, 0, 0);
        $this->Ln(2);
    }
    
    function Footer() {
        $this->SetY(-12);
        $this->SetFont('Arial', 'I', 6);
        $this->SetTextColor(120, 120, 120);
        $this->Cell(0, 3, utf8_decode('Validez: ' . $this->cotizacion['validez_oferta']), 0, 1, 'C');
        $this->Cell(0, 3, utf8_decode(APP_NAME . ' - Gracias por su preferencia'), 0, 0, 'C');
    }
}

// ── Función principal ────────────────────────────────────────
if (isset($_GET['id'])) {
    $cotizacion_id = (int)$_GET['id'];
    
    // Obtener datos de la cotización
    $cot = db_fetch_one(
        "SELECT c.*, p.nombre_contacto, p.empresa, p.ruc, p.telefono, p.email, p.direccion
         FROM cotizaciones c
         JOIN prospectos p ON c.prospecto_id = p.id
         WHERE c.id = ?",
        [$cotizacion_id]
    );
    
    if (!$cot) {
        die('Cotización no encontrada');
    }
    
    // Obtener items
    $items = db_fetch_all(
        "SELECT * FROM cotizacion_items WHERE cotizacion_id = ? ORDER BY orden, id",
        [$cotizacion_id]
    );
    
    // Crear PDF
    $pdf = new CotizacionPDF($cot);
    $pdf->AliasNbPages();
    $pdf->AddPage();
    $pdf->SetFont('Arial', '', 8);
    
    // ── DATOS DEL CLIENTE ─────────────────────────────────────
    $pdf->SetFillColor(240, 240, 240);
    $pdf->SetFont('Arial', 'B', 7);
    $pdf->Cell(190, 4, 'DATOS DEL CLIENTE', 1, 1, 'L', true);
    
    $pdf->SetFont('Arial', '', 7);
    $pdf->SetFillColor(255, 255, 255);
    
    // Razón Social
    $pdf->Cell(30, 4, utf8_decode('Razón Social:'), 1, 0, 'L', true);
    $pdf->SetFont('Arial', 'B', 7);
    $pdf->Cell(160, 4, utf8_decode($cot['empresa'] ?: $cot['nombre_contacto']), 1, 1, 'L', true);
    
    // RUC y Contacto
    $pdf->SetFont('Arial', '', 7);
    $pdf->Cell(30, 4, 'RUC:', 1, 0, 'L', true);
    $pdf->Cell(65, 4, $cot['ruc'] ?? '-', 1, 0, 'L', true);
    $pdf->Cell(25, 4, 'Contacto:', 1, 0, 'L', true);
    $pdf->Cell(70, 4, utf8_decode($cot['nombre_contacto']), 1, 1, 'L', true);
    
    $pdf->Ln(2);
    
    // ── TABLA DE SERVICIOS ────────────────────────────────────
    $pdf->SetFont('Arial', 'B', 7);
    $pdf->SetFillColor(27, 58, 107);
    $pdf->SetTextColor(255, 255, 255);
    
    // Header
    $pdf->Cell(8, 4, utf8_decode('N°'), 1, 0, 'C', true);
    $pdf->Cell(115, 4, utf8_decode('DESCRIPCIÓN'), 1, 0, 'C', true);
    $pdf->Cell(15, 4, 'CANT.', 1, 0, 'C', true);
    $pdf->Cell(25, 4, 'P. UNIT.', 1, 0, 'C', true);
    $pdf->Cell(27, 4, 'IMPORTE', 1, 1, 'C', true);
    
    // Items
    $pdf->SetFont('Arial', '', 6.5);
    $pdf->SetFillColor(255, 255, 255);
    $pdf->SetTextColor(0, 0, 0);
    
    foreach ($items as $idx => $item) {
        $x = $pdf->GetX();
        $y = $pdf->GetY();
        
        // Número
        $pdf->Cell(8, 4, ($idx + 1), 1, 0, 'C');
        
        // Descripción
        $pdf->MultiCell(115, 4, utf8_decode($item['descripcion']), 1, 'L');
        $altura = $pdf->GetY() - $y;
        
        // Volver a posición
        $pdf->SetXY($x + 123, $y);
        
        // Cantidad
        $pdf->Cell(15, $altura, number_format($item['cantidad'], 2), 1, 0, 'C');
        
        // Precio unitario
        $pdf->Cell(25, $altura, MONEDA_SIMBOLO . ' ' . number_format($item['precio_unitario'], 2), 1, 0, 'R');
        
        // Importe
        $pdf->Cell(27, $altura, MONEDA_SIMBOLO . ' ' . number_format($item['subtotal'], 2), 1, 1, 'R');
    }
    
    $pdf->Ln(2);
    
    // ── TOTALES (SIMPLE Y COMPACTO) ──────────────────────────
    $pdf->SetFont('Arial', '', 7);
    
    // Subtotal
    $pdf->Cell(163, 4, 'SUBTOTAL:', 0, 0, 'R');
    $pdf->SetFont('Arial', 'B', 7);
    $pdf->Cell(27, 4, MONEDA_SIMBOLO . ' ' . number_format($cot['subtotal'], 2), 1, 1, 'R');
    
    // Descuento
    if ($cot['descuento'] > 0) {
        $pdf->SetFont('Arial', '', 7);
        $pdf->Cell(163, 4, 'DESCUENTO:', 0, 0, 'R');
        $pdf->SetFont('Arial', 'B', 7);
        $pdf->SetTextColor(200, 30, 30);
        $pdf->Cell(27, 4, '- ' . MONEDA_SIMBOLO . ' ' . number_format($cot['descuento'], 2), 1, 1, 'R');
        $pdf->SetTextColor(0, 0, 0);
    }
    
    // IGV (solo si aplica)
    if ($cot['aplica_igv']) {
        $pdf->SetFont('Arial', '', 7);
        $pdf->Cell(163, 4, 'IGV (18%):', 0, 0, 'R');
        $pdf->SetFont('Arial', 'B', 7);
        $pdf->Cell(27, 4, MONEDA_SIMBOLO . ' ' . number_format($cot['igv'], 2), 1, 1, 'R');
    }

    // TOTAL
    $pdf->SetFillColor(245, 245, 245);
    $pdf->SetFont('Arial', 'B', 7);
    $pdf->Cell(163, 5, 'TOTAL:', 0, 0, 'R');
    $pdf->SetFont('Arial', 'B', 8);
    $pdf->SetTextColor(27, 58, 107);
    $pdf->Cell(27, 5, MONEDA_SIMBOLO . ' ' . number_format($cot['total'], 2), 1, 1, 'R', true);
        
    
    // ── CONDICIONES ───────────────────────────────────────────
    if ($cot['condiciones_pago'] || $cot['tiempo_entrega'] || $cot['observaciones']) {
        $pdf->SetFont('Arial', 'B', 7);
        $pdf->SetFillColor(240, 240, 240);
        $pdf->Cell(190, 4, 'CONDICIONES', 1, 1, 'L', true);
        
        $pdf->SetFont('Arial', '', 6.5);
        $pdf->SetFillColor(255, 255, 255);
        
        if ($cot['condiciones_pago']) {
            $pdf->Cell(35, 4, 'Condiciones de Pago:', 1, 0, 'L', true);
            $pdf->MultiCell(155, 4, utf8_decode($cot['condiciones_pago']), 1, 'L', true);
        }
        
        if ($cot['tiempo_entrega']) {
            $pdf->Cell(35, 4, 'Tiempo de Entrega:', 1, 0, 'L', true);
            $pdf->Cell(155, 4, utf8_decode($cot['tiempo_entrega']), 1, 1, 'L', true);
        }
        
        if ($cot['observaciones']) {
            $pdf->Cell(35, 4, 'Observaciones:', 1, 0, 'L', true);
            $pdf->MultiCell(155, 4, utf8_decode($cot['observaciones']), 1, 'L', true);
        }
    }
    
    // Salida del PDF
    // Salida del PDF
    $filename = 'COT_' . str_replace(['/', ' '], '_', $cot['numero']) . '_' . date('Ymd') . '.pdf';

    // Limpiar cualquier output accidental antes de enviar el PDF
    ob_clean();

    // Si viene preview=1, mostrar en navegador, sino descargar
    if (isset($_GET['preview'])) {
        $pdf->Output('I', $filename); // I = inline (previsualización)
    } else {
        $pdf->Output('D', $filename); // D = descarga directa
    }
    exit;
}