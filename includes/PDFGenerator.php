<?php
/**
 * PDFGenerator — Genera documento PDF del curso
 * Usa TCPDF para crear un PDF profesional con el contenido del módulo SCORM.
 */
namespace ScormConverter;

require_once __DIR__ . '/../libs/tcpdf/tcpdf.php';

/**
 * Subclase TCPDF con footer personalizado (sin branding TCPDF)
 */
class CoursePDF extends \TCPDF
{
    public function Footer()
    {
        if (!$this->print_footer) return;
        $this->SetY(-12);
        $this->SetFont('dejavusans', '', 8);
        $this->SetTextColor(153, 153, 153);
        $this->Cell(0, 8, $this->getAliasNumPage() . ' / ' . $this->getAliasNbPages(), 0, 0, 'C');
    }

    public function Header()
    {
        // Sin header por defecto
    }
}

class PDFGenerator
{
    private array $moduleConfig;
    private array $units;
    private string $templateId;
    private array $colors;
    private ?string $logoPath = null;
    private CoursePDF $pdf;

    public function __construct(array $moduleConfig, array $units, string $templateId = 'arelance-corporate')
    {
        $this->moduleConfig = $moduleConfig;
        $this->units = $units;
        $this->templateId = $templateId;
        $this->colors = [
            'primary' => '#143554',
            'accent'  => '#F05726',
            'secondary' => '#1a4a6e'
        ];
    }

    /**
     * Genera el PDF y devuelve la ruta del archivo
     */
    public function generate(): string
    {
        $this->resolveTheme();
        $this->initPDF();
        $this->writeCoverPage();

        foreach ($this->units as $unit) {
            $this->writeUnitTitle($unit);
            $this->writeObjectives($unit);
            $this->writeSections($unit);
            $this->writeFlashcards($unit);
            $this->writeQuestions($unit);
            $this->writeConclusions($unit);
        }

        $this->writeBackCover();

        // Generar el TOC al final (cuando todos los bookmarks ya existen)
        // Se inserta en la página 2 (después de la portada)
        $this->pdf->addTOCPage('', '', 1);
        $this->pdf->setPrintHeader(false);
        $this->pdf->setPrintFooter(false);
        $this->pdf->SetFont('dejavusans', 'B', 18);
        $this->pdf->SetTextColor(...$this->hexToRGB($this->colors['primary']));
        $this->pdf->Cell(0, 15, $this->decode('Índice'), 0, 1, 'C');
        $this->pdf->Ln(5);
        $this->pdf->SetFont('dejavusans', '', 11);
        $this->pdf->SetTextColor(51, 51, 51);
        $this->pdf->addTOC(2, 'dejavusans', '.', $this->decode('Índice'), '', [51, 51, 51]);
        $this->pdf->endTOCPage();

        $pdfPath = (defined('TEMP_PATH') ? TEMP_PATH : sys_get_temp_dir()) . '/pdf_' . uniqid() . '.pdf';
        $this->pdf->Output($pdfPath, 'F');
        return $pdfPath;
    }

    // ── TEMA ──

    private function resolveTheme(): void
    {
        require_once __DIR__ . '/TemplateManager.php';
        $tm = new TemplateManager();

        if (strpos($this->templateId, 'theme_') === 0) {
            $themeId = (int)substr($this->templateId, 6);
            require_once __DIR__ . '/Auth.php';
            $auth = new Auth();
            $db = $auth->getDb();
            $theme = $tm->getUserThemeById($db, $themeId);
            if ($theme) {
                $this->colors['primary'] = $theme['color_primary'];
                $this->colors['accent'] = $theme['color_accent'];
                $this->colors['secondary'] = $this->lightenColor($theme['color_primary'], 20);
                if (!empty($theme['logo_filename'])) {
                    $logoSrc = (defined('UPLOAD_PATH') ? UPLOAD_PATH : __DIR__ . '/../uploads') . '/logos/' . $theme['logo_filename'];
                    if (file_exists($logoSrc)) $this->logoPath = $logoSrc;
                }
            }
        } else {
            $tpl = $tm->loadTemplate($this->templateId);
            if ($tpl) {
                $c = $tpl['colors'] ?? [];
                $this->colors['primary'] = $c['primary'] ?? '#143554';
                $this->colors['accent'] = $c['accent'] ?? '#F05726';
                $this->colors['secondary'] = $c['secondary'] ?? '#1a4a6e';
                if (!empty($tpl['logo']) && !empty($tpl['dir'])) {
                    $logoSrc = $tpl['dir'] . '/' . $tpl['logo'];
                    if (file_exists($logoSrc)) $this->logoPath = $logoSrc;
                }
            }
        }
    }

    // ── INIT ──

    private function initPDF(): void
    {
        $this->pdf = new CoursePDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $this->pdf->SetCreator('SCORM Converter — Arelance');
        $this->pdf->SetAuthor($this->moduleConfig['empresa'] ?? 'Arelance');
        $this->pdf->SetTitle($this->moduleConfig['titulo'] ?? 'Curso');
        $this->pdf->SetSubject($this->moduleConfig['codigo'] ?? '');
        $this->pdf->setFontSubsetting(true);

        // Márgenes
        $this->pdf->SetMargins(15, 25, 15);
        $this->pdf->SetHeaderMargin(5);
        $this->pdf->SetFooterMargin(10);
        $this->pdf->SetAutoPageBreak(true, 20);
        $this->pdf->setPrintHeader(false);
        $this->pdf->setPrintFooter(false);
    }

    // ── PORTADA ──

    private function writeCoverPage(): void
    {
        $this->pdf->AddPage();
        $this->pdf->setPrintHeader(false);
        $this->pdf->setPrintFooter(false);

        $pri = $this->hexToRGB($this->colors['primary']);
        $acc = $this->hexToRGB($this->colors['accent']);

        // Barra superior con color primary (banda ancha)
        $this->pdf->SetFillColor(...$pri);
        $this->pdf->Rect(0, 0, 210, 90, 'F');

        // Barra accent decorativa
        $this->pdf->SetFillColor(...$acc);
        $this->pdf->Rect(0, 90, 210, 3, 'F');

        // Logo centrado en la barra superior
        if ($this->logoPath) {
            $this->pdf->Image($this->logoPath, 82, 12, 45, 0, '', '', '', true, 300, 'C');
            $this->pdf->SetY(50);
        } else {
            $this->pdf->SetY(25);
        }

        // Empresa en la barra
        $this->pdf->SetFont('dejavusans', 'B', 14);
        $this->pdf->SetTextColor(255, 255, 255);
        $emp = $this->moduleConfig['empresa'] ?? 'Arelance';
        if (!$this->logoPath) {
            $this->pdf->Cell(0, 10, $this->decode($emp), 0, 1, 'C');
        }

        // Código módulo (solo si no hay logo, para evitar solapamiento)
        if (!$this->logoPath) {
            $this->pdf->SetFont('dejavusans', '', 11);
            $this->pdf->SetTextColor(255, 255, 255, 70);
            $this->pdf->Cell(0, 8, $this->decode($this->moduleConfig['codigo'] ?? ''), 0, 1, 'C');
        }

        // Título del módulo (debajo de la barra)
        $this->pdf->SetY(105);
        $this->pdf->SetFont('dejavusans', 'B', 26);
        $this->pdf->SetTextColor(...$pri);
        $this->pdf->MultiCell(0, 14, $this->decode($this->moduleConfig['titulo'] ?? 'Curso'), 0, 'C');

        // Info de duración y unidades
        $this->pdf->Ln(10);
        $this->pdf->SetFont('dejavusans', '', 12);
        $this->pdf->SetTextColor(102, 102, 102);
        $dur = $this->moduleConfig['duracion_total'] ?? '';
        $nUnits = count($this->units);
        $info = '';
        if ($dur) $info .= $this->decode('Duración: ' . $dur . ' horas');
        if ($nUnits) $info .= ($info ? '  |  ' : '') . $nUnits . ($nUnits === 1 ? ' unidad' : ' unidades');
        $this->pdf->Cell(0, 8, $info, 0, 1, 'C');

        // Línea separadora accent
        $this->pdf->Ln(15);
        $this->pdf->SetDrawColor(...$acc);
        $this->pdf->SetLineWidth(0.5);
        $this->pdf->Line(60, $this->pdf->GetY(), 150, $this->pdf->GetY());

        // Pie de portada
        $this->pdf->SetY(260);
        $this->pdf->SetFont('dejavusans', '', 9);
        $this->pdf->SetTextColor(153, 153, 153);
        $this->pdf->Cell(0, 5, $this->decode('© ' . date('Y') . ' ' . $emp . ' — Documento generado automáticamente'), 0, 1, 'C');
    }

    // ── TÍTULO UNIDAD ──

    private function writeUnitTitle(array $unit): void
    {
        $this->pdf->AddPage();
        $this->enableHeaderFooter($unit);

        $pri = $this->hexToRGB($this->colors['primary']);
        $acc = $this->hexToRGB($this->colors['accent']);

        // Barra lateral izquierda decorativa
        $this->pdf->SetFillColor(...$pri);
        $this->pdf->Rect(0, 0, 6, 297, 'F');

        // Badge "Unidad N"
        $this->pdf->SetY(50);
        $this->pdf->SetFont('dejavusans', 'B', 12);
        $this->pdf->SetFillColor(...$acc);
        $this->pdf->SetTextColor(255, 255, 255);
        $badge = $this->decode('Unidad ' . ($unit['numero'] ?? ''));
        $badgeW = $this->pdf->GetStringWidth($badge) + 16;
        $this->pdf->SetX((210 - $badgeW) / 2);
        $this->pdf->Cell($badgeW, 10, $badge, 0, 1, 'C', true, '', 0, false, 'T', 'M');

        // Título
        $this->pdf->Ln(8);
        $this->pdf->SetFont('dejavusans', 'B', 22);
        $this->pdf->SetTextColor(...$pri);
        $this->pdf->MultiCell(0, 12, $this->decode($unit['titulo'] ?? ''), 0, 'C');

        // Duración
        if (!empty($unit['duracion'])) {
            $this->pdf->Ln(5);
            $this->pdf->SetFont('dejavusans', '', 11);
            $this->pdf->SetTextColor(102, 102, 102);
            $this->pdf->Cell(0, 7, $this->decode('Duración: ' . $unit['duracion'] . ' horas'), 0, 1, 'C');
        }

        // Resumen
        if (!empty($unit['resumen'])) {
            $this->pdf->Ln(10);
            $this->pdf->SetFont('dejavusans', 'I', 10);
            $this->pdf->SetTextColor(80, 80, 80);
            $this->pdf->SetX(25);
            $this->pdf->MultiCell(160, 6, $this->decode($unit['resumen']), 0, 'C');
        }

        // Bookmark para el TOC
        $this->pdf->Bookmark($this->decode('Unidad ' . ($unit['numero'] ?? '') . ': ' . ($unit['titulo'] ?? '')), 0);
    }

    // ── OBJETIVOS ──

    private function writeObjectives(array $unit): void
    {
        if (empty($unit['objetivos'])) return;

        $this->pdf->Ln(8);
        $this->sectionHeading('Objetivos de aprendizaje');
        $pri = $this->colors['primary'];

        $html = '<ul style="font-size:10px; line-height:1.6;">';
        foreach ($unit['objetivos'] as $obj) {
            $clean = preg_replace('/^\[(Recordar|Comprender|Aplicar|Analizar|Evaluar|Crear)\]\s*/u', '', $obj);
            $html .= '<li style="margin-bottom:4px;">' . $this->e($clean) . '</li>';
        }
        $html .= '</ul>';
        $this->pdf->writeHTML($html, true, false, true, false, '');
    }

    // ── SECCIONES ──

    private function writeSections(array $unit): void
    {
        if (empty($unit['secciones'])) return;

        foreach ($unit['secciones'] as $si => $sec) {
            $titulo = $sec['titulo'] ?? ('Sección ' . ($si + 1));
            $this->pdf->Bookmark($this->decode($titulo), 1);
            $this->sectionHeading($titulo);

            // Contenido estructurado
            $bloques = [];
            if (!empty($sec['contenido_estructurado'])) {
                $bloques = $this->convertStructuredToBlocks($sec['contenido_estructurado']);
            }

            if (!empty($bloques)) {
                $this->renderBlocks($bloques);
            } elseif (!empty($sec['contenido'])) {
                $this->pdf->SetFont('dejavusans', '', 10);
                $this->pdf->SetTextColor(51, 51, 51);
                $this->pdf->MultiCell(0, 6, $this->decode($sec['contenido']), 0, 'L');
                $this->pdf->Ln(4);
            }
        }
    }

    // ── RENDER BLOQUES ──

    private function renderBlocks(array $bloques): void
    {
        $pri = $this->colors['primary'];
        $acc = $this->colors['accent'];

        foreach ($bloques as $b) {
            $tipo = $b['tipo'] ?? 'parrafo';
            $contenido = $b['contenido'] ?? '';
            $termino = $b['termino'] ?? '';
            $items = $b['items'] ?? [];
            $etiqueta = $b['etiqueta'] ?? '';

            switch ($tipo) {
                case 'definicion':
                    $html = '<br><div style="padding:10px 14px; margin:10px 0; background-color:#f0f4f8;">'
                        . '<span style="font-weight:bold; color:' . $pri . ';">' . $this->e($termino) . ':</span> '
                        . '<span style="font-size:10px;">' . $this->e($contenido) . '</span></div><br>';
                    $this->pdf->writeHTML($html, true, false, true, false, '');
                    break;

                case 'lista':
                    $html = '';
                    if (!empty($contenido)) {
                        $html .= '<p style="font-weight:bold; font-size:10px; margin-bottom:2px;">' . $this->e($contenido) . '</p>';
                    }
                    $html .= '<ul style="font-size:10px;">';
                    if (!empty($items)) {
                        foreach ($items as $it) $html .= '<li>' . $this->e($it) . '</li>';
                    } else {
                        foreach (preg_split('/\n+/', $contenido) as $line) {
                            $line = trim($line);
                            if (!empty($line)) $html .= '<li>' . $this->e(preg_replace('/^[\-\*\x{2022}\d\.\)]+\s+/u', '', $line)) . '</li>';
                        }
                    }
                    $html .= '</ul>';
                    $this->pdf->writeHTML($html, true, false, true, false, '');
                    break;

                case 'tabla':
                    $html = '<table border="1" cellpadding="4" style="font-size:9px; border-color:#cccccc;">';
                    if (!empty($items)) {
                        foreach ($items as $ri => $row) {
                            $cells = array_map('trim', explode('|', $row));
                            $html .= '<tr>';
                            foreach ($cells as $cell) {
                                if ($ri === 0) {
                                    $html .= '<th style="background-color:' . $pri . '; color:#ffffff; font-weight:bold;">' . $this->e($cell) . '</th>';
                                } else {
                                    $html .= '<td>' . $this->e($cell) . '</td>';
                                }
                            }
                            $html .= '</tr>';
                        }
                    }
                    $html .= '</table><br>';
                    $this->pdf->writeHTML($html, true, false, true, false, '');
                    break;

                case 'comparativa':
                    if (!empty($items)) {
                        $html = '<table border="1" cellpadding="5" style="font-size:9px; border-color:#cccccc;">';
                        // Cabeceras
                        $html .= '<tr>';
                        foreach ($items as $item) {
                            $parts = explode(':', $item, 2);
                            $html .= '<th style="background-color:' . $acc . '; color:#ffffff; font-weight:bold;">' . $this->e(trim($parts[0])) . '</th>';
                        }
                        $html .= '</tr><tr>';
                        foreach ($items as $item) {
                            $parts = explode(':', $item, 2);
                            $html .= '<td>' . $this->e(trim($parts[1] ?? '')) . '</td>';
                        }
                        $html .= '</tr></table><br>';
                        $this->pdf->writeHTML($html, true, false, true, false, '');
                    }
                    break;

                case 'proceso':
                    if (!empty($items)) {
                        $html = '';
                        foreach ($items as $pi => $paso) {
                            $stepN = $pi + 1;
                            $html .= '<div style="margin:3px 0; font-size:10px;">'
                                . '<span style="font-weight:bold; color:' . $acc . ';">Paso ' . $stepN . ':</span> '
                                . $this->e($paso) . '</div>';
                        }
                        $this->pdf->writeHTML($html, true, false, true, false, '');
                    }
                    break;

                case 'tip_importante':
                    $html = '<br><div style="background-color:#FEF3C7; padding:10px 14px; margin:10px 0; font-size:10px;">'
                        . '<span style="font-weight:bold; color:#D97706;">' . $this->e($etiqueta ?: 'Importante') . ':</span> '
                        . $this->e($contenido) . '</div><br>';
                    $this->pdf->writeHTML($html, true, false, true, false, '');
                    break;

                case 'tip_saber':
                    $html = '<br><div style="background-color:#DCFCE7; padding:10px 14px; margin:10px 0; font-size:10px;">'
                        . '<span style="font-weight:bold; color:#16A34A;">' . $this->e($etiqueta ?: $this->decode('Sabías que')) . ':</span> '
                        . $this->e($contenido) . '</div><br>';
                    $this->pdf->writeHTML($html, true, false, true, false, '');
                    break;

                case 'tip_practica':
                    $html = '<br><div style="background-color:#FDF2F8; padding:10px 14px; margin:10px 0; font-size:10px;">'
                        . '<span style="font-weight:bold; color:#DB2777;">' . $this->e($etiqueta ?: $this->decode('Práctica')) . ':</span> '
                        . $this->e($contenido) . '</div><br>';
                    $this->pdf->writeHTML($html, true, false, true, false, '');
                    break;

                case 'ejemplo':
                    $html = '<br><div style="background-color:#F0FDF4; padding:10px 14px; margin:10px 0; font-size:10px;">'
                        . '<span style="font-weight:bold; color:#22C55E;">' . $this->e($etiqueta ?: 'Ejemplo') . ':</span> '
                        . $this->e($contenido) . '</div><br>';
                    $this->pdf->writeHTML($html, true, false, true, false, '');
                    break;

                case 'codigo':
                    $html = '<div style="background-color:#F3F4F6; border:1px solid #D1D5DB; padding:8px; margin:6px 0;">'
                        . '<pre style="font-family:courier; font-size:8px;">' . $this->e($contenido) . '</pre></div>';
                    $this->pdf->writeHTML($html, true, false, true, false, '');
                    break;

                case 'parrafo':
                default:
                    $html = '<p style="font-size:10px; margin-bottom:4px;">' . $this->e($contenido) . '</p>';
                    $this->pdf->writeHTML($html, true, false, true, false, '');
                    break;
            }
        }
    }

    // ── FLASHCARDS ──

    private function writeFlashcards(array $unit): void
    {
        if (empty($unit['conceptos_clave'])) return;

        $this->pdf->Ln(4);
        $this->sectionHeading('Conceptos clave');
        $pri = $this->colors['primary'];

        $html = '<table border="1" cellpadding="6" style="font-size:10px; border-color:#cccccc;">'
            . '<tr><th style="background-color:' . $pri . '; color:#ffffff; font-weight:bold; width:30%;">Concepto</th>'
            . '<th style="background-color:' . $pri . '; color:#ffffff; font-weight:bold; width:70%;">' . $this->decode('Definición') . '</th></tr>';

        foreach ($unit['conceptos_clave'] as $fc) {
            $concepto = $fc['concepto'] ?? $fc['termino'] ?? '';
            $def = $fc['definicion'] ?? $fc['descripcion'] ?? '';
            $html .= '<tr><td style="font-weight:bold;">' . $this->e($concepto) . '</td>'
                . '<td>' . $this->e($def) . '</td></tr>';
        }
        $html .= '</table>';
        $this->pdf->writeHTML($html, true, false, true, false, '');
    }

    // ── PREGUNTAS ──

    private function writeQuestions(array $unit): void
    {
        if (empty($unit['preguntas'])) return;

        $this->pdf->AddPage();
        $this->pdf->Bookmark($this->decode('Autoevaluación — Ud. ' . ($unit['numero'] ?? '')), 1);
        $this->sectionHeading($this->decode('Autoevaluación'));
        $acc = $this->colors['accent'];

        foreach ($unit['preguntas'] as $qi => $q) {
            $qNum = $qi + 1;
            $pregunta = $q['pregunta'] ?? $q['enunciado'] ?? '';
            $opciones = $q['opciones'] ?? [];
            $correcta = $q['respuesta_correcta'] ?? $q['correcta'] ?? 0;
            $explicacion = $q['explicacion'] ?? '';

            $html = '<div style="margin-bottom:10px;">';
            $html .= '<p style="font-weight:bold; font-size:10px; color:#333333;">' . $qNum . '. ' . $this->e($pregunta) . '</p>';

            $letters = ['A', 'B', 'C', 'D', 'E', 'F'];
            // Normalizar a 0-based: si correcta >= 1 y parece ser 1-based (1-4), convertir
            $correctIdx = (int)$correcta;
            if ($correctIdx >= 1 && $correctIdx <= count($opciones)) {
                $correctIdx = $correctIdx - 1; // 1-based → 0-based
            }
            foreach ($opciones as $oi => $op) {
                $opText = is_array($op) ? ($op['texto'] ?? $op['opcion'] ?? '') : $op;
                $isCorrect = ($oi === $correctIdx);
                $style = $isCorrect
                    ? 'font-size:10px; font-weight:bold; color:#16A34A;'
                    : 'font-size:10px; color:#555555;';
                $mark = $isCorrect ? ' ✓' : '';
                $letter = $letters[$oi] ?? ($oi + 1);
                $html .= '<p style="' . $style . ' padding-left:15px;">' . $letter . ') ' . $this->e($opText) . $mark . '</p>';
            }

            if (!empty($explicacion)) {
                $html .= '<p style="font-size:9px; color:#666666; font-style:italic; padding-left:15px; margin-top:2px;">'
                    . $this->e($explicacion) . '</p>';
            }
            $html .= '</div>';
            $this->pdf->writeHTML($html, true, false, true, false, '');
        }
    }

    // ── CONCLUSIONES ──

    private function writeConclusions(array $unit): void
    {
        if (empty($unit['conclusiones_ia'])) return;

        $this->pdf->Ln(4);
        $this->sectionHeading('Conclusiones');

        $html = '<ul style="font-size:10px; line-height:1.6;">';
        foreach ($unit['conclusiones_ia'] as $c) {
            $text = is_array($c) ? ($c['texto'] ?? $c['conclusion'] ?? '') : $c;
            if (!empty($text)) {
                $html .= '<li>' . $this->e($text) . '</li>';
            }
        }
        $html .= '</ul>';
        $this->pdf->writeHTML($html, true, false, true, false, '');
    }

    // ── CONTRAPORTADA ──

    private function writeBackCover(): void
    {
        $this->pdf->AddPage();
        $this->pdf->setPrintHeader(false);
        $this->pdf->setPrintFooter(false);

        $pri = $this->hexToRGB($this->colors['primary']);
        $acc = $this->hexToRGB($this->colors['accent']);

        // Barra inferior
        $this->pdf->SetFillColor(...$pri);
        $this->pdf->Rect(0, 260, 210, 37, 'F');
        $this->pdf->SetFillColor(...$acc);
        $this->pdf->Rect(0, 257, 210, 3, 'F');

        // Logo centrado (tamaño moderado)
        if ($this->logoPath) {
            $this->pdf->Image($this->logoPath, 82, 90, 45, 0, '', '', '', true, 300, 'C');
        }

        // Empresa
        $this->pdf->SetY(150);
        $this->pdf->SetFont('dejavusans', 'B', 18);
        $this->pdf->SetTextColor(...$pri);
        $this->pdf->Cell(0, 12, $this->decode($this->moduleConfig['empresa'] ?? 'Arelance'), 0, 1, 'C');

        // Copyright
        $this->pdf->Ln(5);
        $this->pdf->SetFont('dejavusans', '', 10);
        $this->pdf->SetTextColor(102, 102, 102);
        $this->pdf->Cell(0, 7, $this->decode('© ' . date('Y') . ' — Todos los derechos reservados'), 0, 1, 'C');
        $this->pdf->Cell(0, 7, $this->decode('Documento generado automáticamente'), 0, 1, 'C');
    }

    // ── HELPERS ──

    /**
     * Activa header/footer con info de la unidad
     */
    private function enableHeaderFooter(array $unit): void
    {
        $this->pdf->setPrintHeader(false);
        $this->pdf->setPrintFooter(true);
    }

    /**
     * Encabezado de sección con línea de color
     */
    private function sectionHeading(string $title): void
    {
        $pri = $this->hexToRGB($this->colors['primary']);
        $acc = $this->hexToRGB($this->colors['accent']);

        $this->pdf->Ln(4);
        $this->pdf->SetFont('dejavusans', 'B', 13);
        $this->pdf->SetTextColor(...$pri);
        $this->pdf->Cell(0, 8, $this->decode($title), 0, 1, 'L');

        // Línea accent debajo
        $y = $this->pdf->GetY();
        $this->pdf->SetDrawColor(...$acc);
        $this->pdf->SetLineWidth(0.6);
        $this->pdf->Line(15, $y, 80, $y);
        $this->pdf->Ln(4);

        // Resetear color texto
        $this->pdf->SetFont('dejavusans', '', 10);
        $this->pdf->SetTextColor(51, 51, 51);
    }

    /**
     * Convierte contenido_estructurado al formato de bloques interno
     * (duplicado de SCORMGenerator para evitar acoplamiento)
     */
    private function convertStructuredToBlocks(array $structured): array
    {
        $blocks = [];
        foreach ($structured as $item) {
            $tipo = $item['tipo'] ?? 'parrafo';
            $block = ['tipo' => $tipo];

            switch ($tipo) {
                case 'definicion':
                    $block['termino'] = $item['termino'] ?? '';
                    $block['contenido'] = $item['texto'] ?? $item['contenido'] ?? '';
                    break;
                case 'lista':
                    $block['contenido'] = $item['titulo'] ?? '';
                    $block['items'] = $item['items'] ?? [];
                    break;
                case 'tabla':
                    if (!empty($item['filas'])) {
                        $block['items'] = array_map(function($row) {
                            return implode(' | ', $row);
                        }, $item['filas']);
                    } else {
                        $block['items'] = $item['items'] ?? [];
                    }
                    $block['contenido'] = '';
                    break;
                case 'comparativa':
                    $block['items'] = $item['items'] ?? [];
                    $block['contenido'] = $item['texto'] ?? $item['contenido'] ?? '';
                    break;
                case 'importante':
                    $block['tipo'] = 'tip_importante';
                    $block['contenido'] = $item['texto'] ?? $item['contenido'] ?? '';
                    $block['etiqueta'] = 'Importante';
                    break;
                case 'sabias_que':
                    $block['tipo'] = 'tip_saber';
                    $block['contenido'] = $item['texto'] ?? $item['contenido'] ?? '';
                    $block['etiqueta'] = $this->decode('Sabías que');
                    break;
                case 'ejemplo':
                    $block['tipo'] = 'ejemplo';
                    $block['contenido'] = $item['texto'] ?? $item['contenido'] ?? '';
                    $block['etiqueta'] = 'Ejemplo';
                    break;
                case 'proceso':
                    $block['items'] = $item['items'] ?? [];
                    $block['contenido'] = $item['texto'] ?? $item['contenido'] ?? '';
                    break;
                default:
                    $block['contenido'] = $item['texto'] ?? $item['contenido'] ?? '';
                    break;
            }
            $blocks[] = $block;
        }
        return $blocks;
    }

    private function e(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }

    /**
     * Decodifica entidades para TCPDF (que espera UTF-8 puro, no entidades)
     */
    private function decode(string $text): string
    {
        return html_entity_decode($text, ENT_QUOTES, 'UTF-8');
    }

    private function hexToRGB(string $hex): array
    {
        $hex = ltrim($hex, '#');
        return [
            (int)hexdec(substr($hex, 0, 2)),
            (int)hexdec(substr($hex, 2, 2)),
            (int)hexdec(substr($hex, 4, 2))
        ];
    }

    private function lightenColor(string $hex, float $percent): string
    {
        $hex = ltrim($hex, '#');
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
        $r = min(255, (int)($r + (255 - $r) * $percent / 100));
        $g = min(255, (int)($g + (255 - $g) * $percent / 100));
        $b = min(255, (int)($b + (255 - $b) * $percent / 100));
        return '#' . str_pad(dechex($r), 2, '0', STR_PAD_LEFT)
                    . str_pad(dechex($g), 2, '0', STR_PAD_LEFT)
                    . str_pad(dechex($b), 2, '0', STR_PAD_LEFT);
    }
}
