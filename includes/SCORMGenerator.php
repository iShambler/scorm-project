<?php
/**
 * Generador SCORM 1.2 Profesional — Estilo APTE/Arelance v3
 * 
 * Genera paquetes SCORM con diseño instruccional profesional:
 *   - Portada con branding
 *   - Índice visual, menú lateral stepper
 *   - Contenido con acordeones, cajas destacadas, flashcards, matching
 *   - Autoevaluación con feedback por opción
 *   - Conclusiones y pantalla final
 *   - SCORM 1.2 completo (APIWrapper ADL)
 */
namespace ScormConverter;

use ZipArchive;
require_once __DIR__ . '/IconifyHelper.php';

class SCORMGenerator
{
    private array $moduleConfig;
    private array $units;
    private string $tempPath;
    private array $images;
    private string $templateId;

    public function __construct(array $moduleConfig, array $units, array $images = [], string $templateId = 'arelance-corporate')
    {
        $this->moduleConfig = $moduleConfig;
        $this->units = $units;
        $this->images = $images;
        $this->templateId = $templateId;
        $this->tempPath = TEMP_PATH . '/' . generateUniqueId();
        foreach (['', '/css', '/js', '/scos', '/img'] as $d) {
            @mkdir($this->tempPath . $d, 0755, true);
        }
    }

    // ── ENTRY POINT ──
    public function generate(): string
    {
        $this->saveImages();
        $this->writeCSS();
        $this->writeJS_ScormApi();
        $this->writeJS_Funciones();
        $this->writeJS_Stepper();
        $this->writeJS_Nav();
        $this->writeJS_Aut();
        $this->writeUnitPages();
        $this->writeManifest();

        $zipPath = TEMP_PATH . '/' . $this->moduleConfig['codigo'] . '_SCORM.zip';
        $this->createZip($zipPath);
        $this->cleanup();
        return $zipPath;
    }

    // =====================================================================
    //  SAVE IMAGES TO PACKAGE
    // =====================================================================
    private function saveImages(): void
    {
        if (empty($this->images)) return;
        $imgDir = $this->tempPath . '/img';
        foreach ($this->images as $img) {
            if (!empty($img['data']) && !empty($img['filename'])) {
                file_put_contents($imgDir . '/' . $img['filename'], $img['data']);
            }
        }
    }

    // =====================================================================
    //  UNIT PAGES
    // =====================================================================
    private function writeUnitPages(): void
    {
        $total = count($this->units);
        foreach ($this->units as $i => $unit) {
            $container = $this->buildContainer($unit);
            file_put_contents($this->tempPath . '/scos/' . $unit['filename'] . '_container.html', $container);
            $html = $this->buildUnitHTML($unit, $i, $total);
            file_put_contents($this->tempPath . '/scos/' . $unit['filename'] . '.html', $html);
        }
    }

    private function buildContainer(array $unit): string
    {
        $t = $this->e($unit['titulo']);
        return '<!DOCTYPE html>
<html lang="es"><head><meta charset="utf-8"><title>' . $t . '</title>
<script src="../js/scorm_api.js"></script></head>
<body onbeforeunload="doUnload();" onunload="doUnload();">
<iframe id="framecontenido" src="' . $unit['filename'] . '.html" 
  style="position:absolute;top:0;left:0;width:100%;height:100%;border:none;margin:0;padding:0;overflow:auto;z-index:999"></iframe>
<script src="../js/funciones.js"></script>
<script>iniciarTemporizacion();marcarIniciado();</script>
</body></html>';
    }

    private function buildUnitHTML(array $unit, int $idx, int $totalUnits): string
    {
        $mod   = $this->e($this->moduleConfig['titulo']);
        $cod   = $this->e($this->moduleConfig['codigo']);
        $emp   = $this->e($this->moduleConfig['empresa'] ?? DEFAULT_COMPANY);
        $udN   = $unit['numero'];
        $udT   = $this->e($unit['titulo']);
        $dur   = $unit['duracion'] ?? '';

        $steps = $this->buildSteps($unit, $idx, $totalUnits);

        // Sidebar menu items
        $sidebar = '';
        foreach ($steps as $s) {
            $c = !empty($s['epi']) ? ' epigrafe' : '';
            $h = !empty($s['hide']) ? ' d-none' : '';
            $sidebar .= '<div class="steps-step mt-2"><a href="#' . $s['id'] . '" class="tema' . $c . $h . '">' . $s['label'] . '</a></div>' . "\n";
        }

        // Steps content
        $stepsHtml = implode("\n", array_column($steps, 'html'));

        // Objetivos
        $objHtml = '';
        if (!empty($unit['objetivos'])) {
            $objHtml = '<h2 class="site__objetivos">Objetivos</h2><ul class="objetivos">';
            foreach ($unit['objetivos'] as $o) $objHtml .= '<li>' . $this->e($o) . '</li>';
            $objHtml .= '</ul>';
        }

        // Episodes (index cards)
        $epsHtml = '';
        if (!empty($unit['secciones'])) {
            $epsHtml = '<section class="episodes">';
            foreach ($unit['secciones'] as $si => $sec) {
                $n = str_pad($si + 1, 2, '0', STR_PAD_LEFT);
                $sid = 'step-' . $udN . '-' . chr(97 + $si);
                $epsHtml .= '<article class="episode"><h2 class="episode__number">' . $n . '</h2>'
                    . '<div class="episode__detail setup-panel"><a href="#' . $sid . '" class="episode__title tema"><h4>' . $this->e($sec['titulo']) . '</h4></a></div></article>';
            }
            $epsHtml .= '</section>';
        }

        // Resumen
        $resHtml = !empty($unit['resumen']) ? '<p class="site__intro">' . $this->e($unit['resumen']) . '</p>' : '';

        return '<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,shrink-to-fit=no">
<title>Unidad ' . $udN . ': ' . $udT . '</title>
<link href="../css/estilos.css" rel="stylesheet">
</head>
<body>

<!-- PRELOADER -->
<div class="preloader"><div class="lds-ellipsis"><div></div><div></div><div></div><div></div></div></div>

<!-- PORTADA -->
<div class="content content--intro">
  <div class="content__inner">
    <div class="corpo"><span class="corpo-logo">' . $emp . '</span><p>Aula digital de formaci&oacute;n</p></div>
    <div class="portada-center">
      <p class="portada__modulo">' . $cod . '</p>
      <span class="portada__badge">Unidad ' . $udN . '</span>
      <h1 class="portada__title">' . $udT . '</h1>
      <p class="portada__duracion">&#9201; ' . $dur . ' horas</p>
      <a href="#" class="btn-comenzar cierra">Comenzar &rarr;</a>
    </div>
  </div>
</div>

<!-- MENU LATERAL -->
<div class="overlaymenu-backdrop"></div>
<div class="overlaymenu">
  <div class="overlaymenu-header"><a href="#" class="cierra cierra_menu">&times;</a></div>
  <div class="steps-form"><div class="steps-row setup-panel">
    <p class="portada__modulo">' . $cod . '</p>
    <h2>Ud. ' . $udN . ': ' . $udT . '</h2>
    ' . $sidebar . '
  </div></div>
</div>

<!-- NAV -->
<nav class="topnav">
  <a href="#" class="abre menuicono">&#9776;</a>
  <span class="topnav-title">Ud.&nbsp;' . $udN . '</span>
  <div class="topnav-units">' . $this->buildUnitNav($idx) . '</div>
  <span class="topnav-empresa">' . $emp . '</span>
</nav>

<!-- HOME -->
<div class="home">
  <div class="main">
    <section class="site">
      <h1 class="site__title">Unidad ' . $udN . ':<br>' . $udT . '</h1>
      <div class="site__separator"></div>
      ' . $resHtml . $objHtml . '
    </section>
    ' . $epsHtml . '
  </div>
  <footer class="main-footer"><p>&copy; ' . date('Y') . ' ' . $emp . '</p></footer>
</div>

<!-- CONTENIDO -->
<div class="contenido">
  <a href="#" class="back float-right btn__back" title="Inicio">&#8592; Inicio</a>
  <div class="titulotema">
    <h5 id="numero" class="text-on-back"></h5>
    <h1 id="unidad" class="profile-title"></h1>
  </div>
  <div class="container">' . $stepsHtml . '</div>
</div>

<script src="../js/stepper.js"></script>
<script src="../js/nav.js"></script>
<script src="../js/aut.js"></script>
</body></html>';
    }

    // =====================================================================
    //  BUILD ALL STEPS
    // =====================================================================
    private function buildSteps(array $unit, int $idx, int $totalUnits): array
    {
        $udN = $unit['numero'];
        $steps = [];

        // --- INDICE ---
        $lix = '';
        if (!empty($unit['secciones'])) {
            $lix = '<ul class="lista_indice">';
            foreach ($unit['secciones'] as $s) $lix .= '<li>' . $this->e($s['titulo']) . '</li>';
            $lix .= '</ul>';
        }
        $steps[] = [
            'id' => 'step-index', 'label' => 'Ud. ' . $udN . ': ' . $unit['titulo'],
            'epi' => false, 'hide' => false,
            'html' => $this->wrapStep('step-index', false, true,
                '<h3>&Iacute;ndice</h3>'
                . '<div class="card-box intro"><h2 class="titulounidad"><span class="text-accent">'
                . str_pad($udN, 2, '0', STR_PAD_LEFT) . '</span> ' . $this->e($unit['titulo']) . '</h2>'
                . $lix . '<p class="duracion-badge">&#9201; ' . ($unit['duracion'] ?? '') . ' horas</p></div>')
        ];

        // --- SECCIONES ---
        $isEnriched = !empty($unit['_enriched']);
        if (!empty($unit['secciones'])) {
            foreach ($unit['secciones'] as $si => $sec) {
                $sn = $si + 1;
                $secId = 'step-' . $udN . '-' . chr(97 + $si);
                $secTitle = $this->e($sec['titulo']);
                $secHeader = '<hr class="line-accent"><h3><span class="sec-num">' . $udN . '.' . $sn . '.</span> ' . $secTitle . '</h3>';

                // Determinar bloques: buscar en 3 formatos posibles
                $blocks = $sec['bloques'] ?? [];
                if (empty($blocks) && !empty($sec['contenido_estructurado'])) {
                    // Nuevo formato: convertir contenido_estructurado a bloques compatibles
                    $blocks = $this->convertStructuredToBlocks($sec['contenido_estructurado']);
                }

                if (!empty($blocks)) {
                    // Renderizar con componentes visuales
                    $fc = $this->renderBlocks($blocks);
                    if (mb_strlen($fc) > 8000) {
                        $halfBlocks = array_chunk($blocks, (int)ceil(count($blocks) / 2));
                        foreach ($halfBlocks as $pi => $blockChunk) {
                            $pid = $secId . ($pi > 0 ? '_' . ($pi + 1) : '');
                            $isFirst = ($pi === 0);
                            $steps[] = [
                                'id' => $pid,
                                'label' => $isFirst ? $udN . '.' . $sn . '. ' . $sec['titulo'] : $udN . '.' . $sn . '.',
                                'epi' => true, 'hide' => !$isFirst,
                                'html' => $this->wrapStep($pid, true, true,
                                    $secHeader . '<div class="content-body">' . $this->renderBlocks($blockChunk) . '</div>')
                            ];
                        }
                    } else {
                        $imgHtml = $this->renderSectionImage($sec, $si);
                        $steps[] = [
                            'id' => $secId,
                            'label' => $udN . '.' . $sn . '. ' . $sec['titulo'],
                            'epi' => true, 'hide' => false,
                            'html' => $this->wrapStep($secId, true, true,
                                $secHeader . $imgHtml . '<div class="content-body">' . $fc . '</div>')
                        ];
                    }
                } else {
                    // Fallback: texto plano con formateo regex
                    $content = $sec['contenido'] ?? '';
                    $parts = $this->splitContent($content, 3000);
                    foreach ($parts as $pi => $part) {
                        $pid = $secId . ($pi > 0 ? '_' . ($pi + 1) : '');
                        $isFirst = ($pi === 0);
                        $fc = $this->formatContentRich($part);
                        $steps[] = [
                            'id' => $pid,
                            'label' => $isFirst ? $udN . '.' . $sn . '. ' . $sec['titulo'] : $udN . '.' . $sn . '.',
                            'epi' => true, 'hide' => !$isFirst,
                            'html' => $this->wrapStep($pid, true, true,
                                $secHeader . '<div class="content-body">' . $fc . '</div>')
                        ];
                    }
                }
            }
        }

        // --- CODIGO ---
        if (!empty($unit['codigo'])) {
            $ch = '';
            foreach (array_slice($unit['codigo'], 0, 4) as $c) {
                $l = strtoupper($this->e($c['language'] ?? 'code'));
                $ch .= '<div class="code-block"><div class="code-header">' . $l . '</div><pre><code>' . $this->e($c['code']) . '</code></pre></div>';
            }
            $steps[] = [
                'id' => 'step-code', 'label' => 'Ejemplos de c&oacute;digo', 'epi' => true, 'hide' => false,
                'html' => $this->wrapStep('step-code', true, true,
                    '<hr class="line-accent"><h3>Ejemplos de c&oacute;digo</h3>' . $ch)
            ];
        }

        // --- FLASHCARDS ---
        if (!empty($unit['conceptos_clave'])) {
            $fh = '<p>Haz clic en cada tarjeta para ver la definici&oacute;n:</p><div class="flashcards-grid">';
            foreach ($unit['conceptos_clave'] as $c) {
                $fh .= '<div class="flashcard" onclick="this.classList.toggle(\'flipped\')"><div class="flashcard-inner">'
                    . '<div class="flashcard-front"><h4>' . $this->e($c['termino']) . '</h4><span class="flashcard-hint">Clic para ver</span></div>'
                    . '<div class="flashcard-back"><p>' . $this->e($c['definicion']) . '</p></div>'
                    . '</div></div>';
            }
            $fh .= '</div>';
            $steps[] = [
                'id' => 'step-flashcards', 'label' => 'Conceptos clave', 'epi' => true, 'hide' => false,
                'html' => $this->wrapStep('step-flashcards', true, true,
                    '<hr class="line-accent"><h3>Conceptos clave</h3>' . $fh)
            ];
        }

        // --- MATCHING ---
        if (!empty($unit['conceptos_clave']) && count($unit['conceptos_clave']) >= 4) {
            $items = array_slice($unit['conceptos_clave'], 0, 5);
            $mh = '<p>Arrastra cada definici&oacute;n junto al concepto correcto:</p><div class="matching-game"><div class="matching-conceptos">';
            foreach ($items as $mi => $c) {
                $mh .= '<div class="matching-row"><div class="matching-term" data-match="m' . $mi . '">' . $this->e($c['termino'])
                    . '</div><div class="matching-dropzone" data-expect="m' . $mi . '"><span class="dropzone-hint">Suelta aqu&iacute;</span></div></div>';
            }
            $mh .= '</div><div class="matching-definiciones">';
            $sh = $items; shuffle($sh);
            foreach ($sh as $c) {
                $oi = array_search($c, $items);
                $defText = $c['definicion'];
                if (mb_strlen($defText) > 120) {
                    $defText = mb_substr($defText, 0, 120);
                    $lastSpace = mb_strrpos($defText, ' ');
                    if ($lastSpace > 80) $defText = mb_substr($defText, 0, $lastSpace);
                    $defText .= '...';
                }
                $mh .= '<div class="matching-def" draggable="true" data-match="m' . $oi . '">' . $this->e($defText) . '</div>';
            }
            $mh .= '</div></div><button class="btn-action" onclick="comprobarMatching(this)">Comprobar</button><div class="matching-resultado"></div>';
            $steps[] = [
                'id' => 'step-matching', 'label' => 'Relaciona conceptos', 'epi' => true, 'hide' => true,
                'html' => $this->wrapStep('step-matching', true, true,
                    '<hr class="line-accent"><h3>Relaciona conceptos</h3>' . $mh)
            ];
        }

        // --- AUTOEVALUACION ---
        if (!empty($unit['preguntas'])) {
            $tq = count($unit['preguntas']);
            foreach ($unit['preguntas'] as $qi => $q) {
                $qn = $qi + 1;
                $ci = $q['correcta'] ?? 0;
                $oh = '';
                foreach ($q['opciones'] as $oi => $op) {
                    $v = ($oi === $ci) ? 'correcta' : 'incorrecta' . $oi;
                    $oh .= '<label class="quiz-label"><input type="radio" name="opcion_q' . $qn . '" value="' . $v . '"> ' . $this->e($op) . '</label>';
                }
                $eo = addslashes($this->e($q['explicacion'] ?? 'Correcto.'));
                $eb = addslashes($this->e($q['explicacion'] ?? 'Revisa el contenido.'));
                $steps[] = [
                    'id' => 'step-auto' . $qn, 'label' => 'Autoevaluaci&oacute;n ' . $qn,
                    'epi' => true, 'hide' => ($qn > 1),
                    'html' => $this->wrapStep('step-auto' . $qn, true, true,
                        '<hr class="line-accent"><h3>Autoevaluaci&oacute;n ' . $qn . '/' . $tq . '</h3>'
                        . '<div class="card-box"><p class="pregunta">' . $this->e($q['pregunta']) . '</p>' . $oh
                        . '<center><button class="btn-action mt-3" onclick="capturarQ(' . $qn . ',\'' . $eo . '\',\'' . $eb . '\')">Comprobar</button></center>'
                        . '<div id="resultado_q' . $qn . '"></div></div>')
                ];
            }
        }

        // --- CONCLUSIONES ---
        $conc = '<h4>Lo que has aprendido:</h4>';
        // Fase 2: usar conclusiones generadas por IA si están disponibles
        $concItems = !empty($unit['conclusiones_ia']) ? $unit['conclusiones_ia'] : ($unit['objetivos'] ?? []);
        if (!empty($concItems)) {
            $conc .= '<ul class="conclusiones">';
            foreach ($concItems as $o) $conc .= '<li>' . $this->e($o) . '</li>';
            $conc .= '</ul>';
        }
        $steps[] = [
            'id' => 'step-conclusion', 'label' => 'Conclusiones', 'epi' => false, 'hide' => true,
            'html' => $this->wrapStep('step-conclusion', true, true,
                '<h3>Conclusiones</h3>'
                . '<div class="card-box intro">' . $conc . '</div>')
        ];

        // --- FINAL ---
        $nav = '<div class="final-nav">';
        if ($idx > 0) {
            $p = $this->units[$idx - 1];
            $nav .= '<a href="' . $p['filename'] . '.html" data-container="' . $p['filename'] . '_container.html" class="btn-nav ud-link">&larr; UD ' . $p['numero'] . '</a>';
        }
        $nav .= '<button class="btn-nav back">&#127968; Inicio</button>';
        if ($idx < $totalUnits - 1) {
            $n = $this->units[$idx + 1];
            $nav .= '<a href="' . $n['filename'] . '.html" data-container="' . $n['filename'] . '_container.html" class="btn-nav ud-link">UD ' . $n['numero'] . ' &rarr;</a>';
        } else {
            $nav .= '<span class="btn-nav completed">&#10003; M&oacute;dulo completado</span>';
        }
        $nav .= '</div>';

        $steps[] = [
            'id' => 'step-final', 'label' => 'Fin de unidad', 'epi' => false, 'hide' => true,
            'html' => $this->wrapStep('step-final', true, false,
                '<div class="card-box intro final-box"><div class="final-content">'
                . '<h1 class="enhorabuena">&#127881; &iexcl;Enhorabuena!</h1>'
                . '<h2>Has completado la Unidad ' . $udN . ': ' . $this->e($unit['titulo']) . '</h2>'
                . $nav
                . '<p class="copyright">&copy; ' . date('Y') . ' ' . $this->e($this->moduleConfig['empresa'] ?? DEFAULT_COMPANY) . '</p>'
                . '</div></div>')
        ];

        return $steps;
    }

    private function wrapStep(string $id, bool $prev, bool $next, string $inner): string
    {
        return '<div class="row setup-content" id="' . $id . '"><div class="col-12">' . $inner . '</div>'
            . ($prev ? '<a class="prevBtn"></a>' : '') . ($next ? '<a class="nextBtn"></a>' : '') . '</div>';
    }

    // =====================================================================
    //  CONVERT contenido_estructurado → bloques (bridge between formats)
    // =====================================================================
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
                    // Convert filas (array of arrays) to pipe-separated strings
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
                    $block['etiqueta'] = 'Sabías que';
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
                default: // parrafo and anything else
                    $block['contenido'] = $item['texto'] ?? $item['contenido'] ?? '';
                    break;
            }

            $blocks[] = $block;
        }
        return $blocks;
    }

    // =====================================================================
    //  BLOCK RENDERER — Fase 2: IA-classified blocks
    // =====================================================================
    private function renderBlocks(array $bloques): string
    {
        $html = '';
        $accN = 0;
        $tabN = 0;

        foreach ($bloques as $b) {
            $tipo = $b['tipo'] ?? 'parrafo';
            $contenido = $b['contenido'] ?? '';
            $termino = $b['termino'] ?? '';
            $items = $b['items'] ?? [];
            $etiqueta = $b['etiqueta'] ?? '';

            switch ($tipo) {
                case 'definicion':
                    $html .= '<div class="idevice def"><div class="idevice-icon">' . self::svg('book', '#143554', 24) . '</div>'
                        . '<p><strong>' . $this->e($termino) . ':</strong> ' . $this->e($contenido) . '</p></div>';
                    break;

                case 'lista':
                    $html .= '<ul class="lista_apte">';
                    if (!empty($items)) {
                        foreach ($items as $it) $html .= '<li>' . $this->e($it) . '</li>';
                    } else {
                        // Fallback: split contenido by newlines
                        foreach (preg_split('/\n+/', $contenido) as $line) {
                            $line = trim($line);
                            if (!empty($line)) $html .= '<li>' . $this->e(preg_replace('/^[\-\*\x{2022}\d\.\)]+\s+/u', '', $line)) . '</li>';
                        }
                    }
                    $html .= '</ul>';
                    break;

                case 'comparativa':
                    $tid = 'tabs' . ($tabN++);
                    $html .= '<div class="tabs-container" id="' . $tid . '"><div class="tabs-nav">';
                    foreach ($items as $ti => $item) {
                        $parts = explode(':', $item, 2);
                        $tabLabel = trim($parts[0]);
                        $active = $ti === 0 ? ' active' : '';
                        $html .= '<button class="tab-btn' . $active . '" onclick="switchTab(\'' . $tid . '\',' . $ti . ')">' . $this->e($tabLabel) . '</button>';
                    }
                    $html .= '</div>';
                    foreach ($items as $ti => $item) {
                        $parts = explode(':', $item, 2);
                        $tabContent = trim($parts[1] ?? $parts[0]);
                        $display = $ti === 0 ? 'block' : 'none';
                        $html .= '<div class="tab-panel" id="' . $tid . '_p' . $ti . '" style="display:' . $display . '"><p>' . $this->e($tabContent) . '</p></div>';
                    }
                    $html .= '</div>';
                    break;

                case 'proceso':
                    $aid = 'proc' . ($accN++);
                    $html .= '<div class="accordion-container">';
                    foreach ($items as $pi => $paso) {
                        $cid = $aid . 'p' . $pi;
                        // Extraer titulo corto: antes del primer punto, o primeras 60 chars
                        $dotPos = mb_strpos($paso, '. ');
                        if ($dotPos !== false && $dotPos < 80) {
                            $accTitle = mb_substr($paso, 0, $dotPos);
                        } else {
                            $spacePos = mb_strrpos(mb_substr($paso, 0, 60), ' ');
                            $accTitle = $spacePos ? mb_substr($paso, 0, $spacePos) . '...' : mb_substr($paso, 0, 60) . '...';
                        }
                        $html .= '<div class="accordion-item">'
                            . '<div class="accordion-header" onclick="toggleAccordion(\'' . $cid . '\')">' 
                            . '<span class="acc-title">Paso ' . ($pi + 1) . ': ' . $this->e($accTitle) . '</span>'
                            . '<span class="accordion-arrow">&#8594;</span></div>'
                            . '<div class="accordion-body" id="' . $cid . '"><p>' . $this->e($paso) . '</p></div></div>';
                    }
                    $html .= '</div>';
                    break;

                case 'tip_importante':
                    $html .= '<div class="idevice importante"><div class="idevice-icon">' . self::svg('warning', '#d97706', 24) . '</div>'
                        . '<p><strong>' . $this->e($etiqueta ?: 'Importante') . ':</strong> ' . $this->e($contenido) . '</p></div>';
                    break;

                case 'tip_saber':
                    $html .= '<div class="idevice saber"><div class="idevice-icon">' . self::svg('lightbulb', '#16a34a', 24) . '</div>'
                        . '<p><strong>' . $this->e($etiqueta ?: 'Sab&iacute;as que') . ':</strong> ' . $this->e($contenido) . '</p></div>';
                    break;

                case 'tip_practica':
                    $html .= '<div class="idevice practica"><div class="idevice-icon">' . self::svg('edit', '#db2777', 24) . '</div>'
                        . '<p><strong>' . $this->e($etiqueta ?: 'Pr&aacute;ctica') . ':</strong> ' . $this->e($contenido) . '</p></div>';
                    break;

                case 'ejemplo':
                    $html .= '<div class="idevice saber"><div class="idevice-icon">' . self::svg('clipboard', '#16a34a', 24) . '</div>'
                        . '<p><strong>' . $this->e($etiqueta ?: 'Ejemplo') . ':</strong> ' . $this->e($contenido) . '</p></div>';
                    break;

                case 'tabla':
                    if (!empty($items)) {
                        $html .= '<div class="table-responsive"><table class="tabla-contenido">';
                        foreach ($items as $ri => $row) {
                            $cells = array_map('trim', explode('|', $row));
                            $tag = ($ri === 0) ? 'th' : 'td';
                            $html .= '<tr>';
                            foreach ($cells as $cell) {
                                $html .= '<' . $tag . '>' . $this->e($cell) . '</' . $tag . '>';
                            }
                            $html .= '</tr>';
                        }
                        $html .= '</table></div>';
                    } elseif (!empty($contenido)) {
                        // Fallback: contenido como texto con formato tabla
                        $lines = preg_split('/\n+/', $contenido);
                        $html .= '<div class="table-responsive"><table class="tabla-contenido">';
                        foreach ($lines as $ri => $line) {
                            $line = preg_replace('/^\[Tabla:\s*/', '', trim($line));
                            $line = rtrim($line, ']');
                            $cells = array_map('trim', explode('|', $line));
                            $tag = ($ri === 0) ? 'th' : 'td';
                            $html .= '<tr>';
                            foreach ($cells as $cell) {
                                $html .= '<' . $tag . '>' . $this->e($cell) . '</' . $tag . '>';
                            }
                            $html .= '</tr>';
                        }
                        $html .= '</table></div>';
                    }
                    break;

                case 'codigo':
                    $html .= '<div class="code-block"><div class="code-header">C&Oacute;DIGO</div>'
                        . '<pre><code>' . $this->e($contenido) . '</code></pre></div>';
                    break;

                case 'parrafo':
                default:
                    $html .= '<p>' . $this->e($contenido) . '</p>';
                    break;
            }
        }
        return $html;
    }

    // =====================================================================
    //  CONTENT FORMATTER — Rich components (Fase 1 fallback: regex-based)
    // =====================================================================
    private function formatContentRich(string $content): string
    {
        if (empty(trim($content))) return '';

        $paras = preg_split('/\n\n+/', $content);
        $paras = array_values(array_filter(array_map('trim', $paras), fn($p) => !empty($p)));
        if (empty($paras)) return '<p>' . $this->e($content) . '</p>';

        // Classify paragraphs
        $items = [];
        foreach ($paras as $i => $p) {
            $len = mb_strlen($p);
            if (preg_match('/^\d+\.\d+\.?\s+(.+)$/u', $p, $m) && $i < count($paras) - 1) {
                $items[] = ['type' => 'subtitle', 'text' => trim($m[1])]; continue;
            }
            if (preg_match('/^([A-Z\x{00C0}-\x{024F}][^:]{2,40}):\s*(.{30,})$/u', $p, $m)) {
                $items[] = ['type' => 'definition', 'term' => trim($m[1]), 'text' => trim($m[2])]; continue;
            }
            if (preg_match('/^(Nota|Importante|Recuerda|Atenci[oó]n|Tip|Consejo|Advertencia)[:\s]+(.+)$/isu', $p, $m)) {
                $items[] = ['type' => 'tip', 'label' => ucfirst(trim($m[1])), 'text' => trim($m[2])]; continue;
            }
            if (preg_match('/^[\-\*\x{2022}]\s+/u', $p) || preg_match('/^\d+[\.\)]\s+/', $p)) {
                $items[] = ['type' => 'list_item', 'text' => preg_replace('/^[\-\*\x{2022}\d\.\)]+\s+/u', '', $p)]; continue;
            }
            if ($len > 15 && $len < 120 && !preg_match('/[.!?]\s*$/', $p)) {
                $np = ($i + 1 < count($paras)) && mb_strlen($paras[$i + 1]) < 120 && mb_strlen($paras[$i + 1]) > 15;
                $pp = !empty($items) && in_array(end($items)['type'], ['list_item', 'bullet']);
                if ($np || $pp) { $items[] = ['type' => 'bullet', 'text' => $p]; continue; }
            }
            $items[] = ['type' => 'paragraph', 'text' => $p];
        }

        // Group consecutive same-type items
        $groups = [];
        $defBuf = []; $listBuf = [];
        foreach ($items as $item) {
            if ($item['type'] === 'definition') {
                if (!empty($listBuf)) { $groups[] = ['type' => 'list', 'items' => $listBuf]; $listBuf = []; }
                $defBuf[] = $item; continue;
            }
            if (!empty($defBuf)) {
                $groups[] = count($defBuf) >= 2
                    ? ['type' => 'accordion', 'items' => $defBuf]
                    : ['type' => 'highlight', 'term' => $defBuf[0]['term'], 'text' => $defBuf[0]['text']];
                $defBuf = [];
            }
            if ($item['type'] === 'list_item' || $item['type'] === 'bullet') {
                $listBuf[] = $item; continue;
            }
            if (!empty($listBuf)) { $groups[] = ['type' => 'list', 'items' => $listBuf]; $listBuf = []; }
            $groups[] = $item;
        }
        if (!empty($defBuf)) {
            $groups[] = count($defBuf) >= 2
                ? ['type' => 'accordion', 'items' => $defBuf]
                : ['type' => 'highlight', 'term' => $defBuf[0]['term'], 'text' => $defBuf[0]['text']];
        }
        if (!empty($listBuf)) { $groups[] = ['type' => 'list', 'items' => $listBuf]; }

        // Render
        $html = '';
        $accN = 0;
        foreach ($groups as $g) {
            switch ($g['type']) {
                case 'subtitle':
                    $html .= '<h4 class="section-subtitle">' . $this->e($g['text']) . '</h4>'; break;
                case 'accordion':
                $aid = 'acc' . ($accN++);
                $svgPool = ['book','lightbulb','layers','target','database','globe','shield','chart','puzzle','tool'];
                $html .= '<div class="accordion-container">';
                foreach ($g['items'] as $di => $d) {
                $cid = $aid . 'c' . $di;
                $html .= '<div class="accordion-item">'
                . '<div class="accordion-header" onclick="toggleAccordion(\'' . $cid . '\')">' 
                        . '<span class="acc-title">' . $this->e($d['term']) . '</span>'
                            . '<span class="accordion-arrow">&#8594;</span></div>'
                            . '<div class="accordion-body" id="' . $cid . '"><p>' . $this->e($d['text']) . '</p></div></div>';
                    }
                    $html .= '</div>'; break;
                case 'highlight':
                    $html .= '<div class="idevice def"><div class="idevice-icon">' . self::svg('book', '#143554', 24) . '</div>'
                        . '<p><strong>' . $this->e($g['term']) . ':</strong> ' . $this->e($g['text']) . '</p></div>'; break;
                case 'tip':
                    $cls = preg_match('/^(Importante|Atenci|Advertencia)/i', $g['label']) ? 'importante' : 'saber';
                    $svgN = $cls === 'importante' ? 'warning' : 'lightbulb';
                    $svgC = $cls === 'importante' ? '#d97706' : '#16a34a';
                    $html .= '<div class="idevice ' . $cls . '"><div class="idevice-icon">' . self::svg($svgN, $svgC, 24) . '</div>'
                        . '<p><strong>' . $this->e($g['label']) . ':</strong> ' . $this->e($g['text']) . '</p></div>'; break;
                case 'list':
                    $html .= '<ul class="lista_apte">';
                    foreach ($g['items'] as $li) $html .= '<li>' . $this->e($li['text']) . '</li>';
                    $html .= '</ul>'; break;
                case 'paragraph': default:
                    $html .= '<p>' . $this->e($g['text']) . '</p>'; break;
            }
        }
        $html = preg_replace('/<h4 class="section-subtitle">[^<]*<\/h4>\s*$/', '', $html);
        return $html ?: '<p>' . $this->e($content) . '</p>';
    }

    private function splitContent(string $c, int $max): array
    {
        if (mb_strlen($c) <= $max) return [$c];
        $ps = preg_split('/\n\n+/', $c);
        $pages = []; $cur = '';
        foreach ($ps as $p) {
            if (mb_strlen($cur . "\n\n" . $p) > $max && !empty($cur)) {
                $pages[] = trim($cur); $cur = $p;
            } else { $cur .= (empty($cur) ? '' : "\n\n") . $p; }
        }
        if (!empty(trim($cur))) $pages[] = trim($cur);
        return $pages ?: [$c];
    }

    // =====================================================================
    //  JS FILES
    // =====================================================================
    private function writeJS_ScormApi(): void
    {
        file_put_contents($this->tempPath . '/js/scorm_api.js', 'var debug=false,output=window.console,_NoError={code:"0",string:"No Error",diagnostic:"No Error"},_GeneralException={code:"101",string:"General Exception",diagnostic:"General Exception"},initialized=false,apiHandle=null;function doLMSInitialize(){if(initialized)return"true";var a=getAPIHandle();if(!a)return"false";var r=a.LMSInitialize("");return r.toString()=="true"&&(initialized=true),r.toString()}function doLMSFinish(){var a=getAPIHandle();if(!a)return"false";var r=a.LMSFinish("");return initialized=false,r.toString()}function doLMSGetValue(n){var a=getAPIHandle(),r="";return a&&(initialized||doLMSInitialize())&&(r=a.LMSGetValue(n),ErrorHandler()),r.toString()}function doLMSSetValue(n,v){var a=getAPIHandle(),r="false";return a&&(initialized||doLMSInitialize())&&(r=a.LMSSetValue(n,v)),r.toString()}function doLMSCommit(){var a=getAPIHandle(),r="false";return a&&(initialized||doLMSInitialize())&&(r=a.LMSCommit("")),r.toString()}function doLMSGetLastError(){var a=getAPIHandle();return a?a.LMSGetLastError().toString():_GeneralException.code}function doLMSGetErrorString(e){var a=getAPIHandle();return a?a.LMSGetErrorString(e).toString():_GeneralException.string}function doLMSGetDiagnostic(e){var a=getAPIHandle();return a?a.LMSGetDiagnostic(e).toString():"Unable to locate API"}function ErrorHandler(){var e={code:_NoError.code,string:_NoError.string,diagnostic:_NoError.diagnostic},a=getAPIHandle();return a?(e.code=a.LMSGetLastError().toString(),e.code!=_NoError.code&&(e.string=a.LMSGetErrorString(e.code),e.diagnostic=a.LMSGetDiagnostic("")),e):(e.code=_GeneralException.code,e.string=_GeneralException.string,e)}function getAPIHandle(){return apiHandle||(apiHandle=getAPI()),apiHandle}function findAPI(w){for(var t=0;!w.API&&w.parent&&w.parent!=w;){if(++t>7)return null;w=w.parent}return w.API}function getAPI(){var a=findAPI(window);return!a&&window.opener&&(a=findAPI(window.opener)),a}function message(s){debug&&output.log(s)}');
    }

    private function writeJS_Funciones(): void
    {
        file_put_contents($this->tempPath . '/js/funciones.js',
            'var startTimeStamp=0,processedUnload=false;doLMSFinish();'
            . 'function iniciarTemporizacion(){startTimeStamp=new Date;doLMSSetValue("cmi.core.score.min",0);doLMSSetValue("cmi.core.score.max",100);doLMSCommit()}'
            . 'function doUnload(){if(processedUnload)return;processedUnload=true;var e=new Date,s=Math.floor((e-startTimeStamp)/1e3),h=Math.floor(s/3600);s%=3600;var m=Math.floor(s/60);s%=60;doLMSSetValue("cmi.core.session_time",Z(h,4)+":"+Z(m,2)+":"+Z(s,2));doLMSCommit();doLMSFinish()}'
            . 'function Z(n,d){var s=String(n);while(s.length<d)s="0"+s;return s}'
            . 'function marcarIniciado(){doLMSGetValue("cmi.core.lesson_status")!="completed"&&(doLMSSetValue("cmi.core.lesson_status","incomplete"),doLMSCommit())}'
            . 'function marcarFinalizado(){doLMSSetValue("cmi.core.lesson_status","completed");doLMSCommit()}'
            . 'function guardarPuntuacion(p){var a=parseInt(doLMSGetValue("cmi.core.score.raw"))||0;p>a&&(doLMSSetValue("cmi.core.score.raw",p),doLMSCommit())}'
        );
    }

    private function writeJS_Stepper(): void
    {
        $js = <<<'JSEND'
document.addEventListener('DOMContentLoaded',function(){
var links=document.querySelectorAll('.setup-panel a,.setup-panel .tema');
var wells=document.querySelectorAll('.setup-content');
var nexts=document.querySelectorAll('.nextBtn');
var prevs=document.querySelectorAll('.prevBtn');
var done=[],pct=0,total=document.querySelectorAll('.steps-form .steps-step').length;
var step=total>0?100/total:100;
wells.forEach(function(w){w.style.display='none'});
links.forEach(function(l){l.addEventListener('click',function(e){e.preventDefault();var h=this.getAttribute('href');if(!h||h==='#')return;var t=document.querySelector(h);if(!t)return;wells.forEach(function(w){w.style.display='none'});t.style.display='block';mark(t);window.scrollTo(0,0)})});
nexts.forEach(function(b){b.addEventListener('click',function(){var c=this.closest('.setup-content');if(!c)return;var a=Array.from(wells),i=a.indexOf(c);if(i<a.length-1){wells.forEach(function(w){w.style.display='none'});a[i+1].style.display='block';mark(a[i+1]);window.scrollTo(0,0)}})});
prevs.forEach(function(b){b.addEventListener('click',function(){var c=this.closest('.setup-content');if(!c)return;var a=Array.from(wells),i=a.indexOf(c);if(i>0){wells.forEach(function(w){w.style.display='none'});a[i-1].style.display='block';mark(a[i-1]);window.scrollTo(0,0)}})});
if(wells.length>0){wells[0].style.display='block';mark(wells[0])}
function mark(el){var id=el.getAttribute('id');if(!id||done.indexOf(id)!==-1)return;done.push(id);pct+=parseFloat(step);if(pct>=100){pct=100;try{parent.guardarPuntuacion(pct);parent.marcarFinalizado()}catch(e){}}else{try{parent.guardarPuntuacion(pct)}catch(e){}}}
});
function toggleAccordion(id){var e=document.getElementById(id);if(!e)return;var o=e.style.display==='block';e.style.display=o?'none':'block';var h=e.previousElementSibling;if(h)h.classList.toggle('open',!o)}
(function(){var dr=null;
document.addEventListener('dragstart',function(e){if(!e.target.classList.contains('matching-def'))return;dr=e.target;e.target.classList.add('dragging');e.dataTransfer.effectAllowed='move'});
document.addEventListener('dragend',function(e){if(e.target.classList.contains('matching-def'))e.target.classList.remove('dragging');document.querySelectorAll('.matching-dropzone').forEach(function(z){z.classList.remove('drag-over')})});
document.addEventListener('dragover',function(e){var z=e.target.closest('.matching-dropzone');if(z){e.preventDefault();z.classList.add('drag-over')}});
document.addEventListener('dragleave',function(e){var z=e.target.closest('.matching-dropzone');if(z)z.classList.remove('drag-over')});
document.addEventListener('drop',function(e){var z=e.target.closest('.matching-dropzone');if(!z||!dr)return;e.preventDefault();z.classList.remove('drag-over');var ex=z.querySelector('.matching-def');if(ex){z.closest('.matching-game').querySelector('.matching-definiciones').appendChild(ex);ex.classList.remove('placed')}z.innerHTML='';z.appendChild(dr);dr.classList.add('placed');dr.classList.remove('dragging');dr=null});
document.addEventListener('click',function(e){var d=e.target.closest('.matching-def.placed');if(!d)return;var z=d.closest('.matching-dropzone');if(!z)return;z.closest('.matching-game').querySelector('.matching-definiciones').appendChild(d);d.classList.remove('placed');z.innerHTML='<span class="dropzone-hint">Suelta aqu\u00ed</span>'});
})();
function comprobarMatching(btn){var a=btn.closest('.setup-content'),zs=a.querySelectorAll('.matching-dropzone'),t=zs.length,ok=0;zs.forEach(function(z){var d=z.querySelector('.matching-def');z.classList.remove('correcto','incorrecto');if(d&&d.dataset.match===z.dataset.expect){z.classList.add('correcto');ok++}else{z.classList.add('incorrecto')}});var r=a.querySelector('.matching-resultado');if(r){var p=Math.round(ok/t*100);r.style.display='block';r.className='matching-resultado '+(p===100?'passed':'failed');r.innerHTML=ok+'/'+t+' correctas ('+p+'%)'+(p===100?' \u00a1Perfecto!':' Revisa e intenta de nuevo.')}}
function switchTab(containerId,idx){var c=document.getElementById(containerId);if(!c)return;c.querySelectorAll('.tab-btn').forEach(function(b,i){b.classList.toggle('active',i===idx)});c.querySelectorAll('.tab-panel').forEach(function(p,i){p.style.display=i===idx?'block':'none'})}
JSEND;
        file_put_contents($this->tempPath . '/js/stepper.js', $js);
    }

    private function writeJS_Nav(): void
    {
        $js = <<<'JSEND'
document.addEventListener('DOMContentLoaded',function(){
function openMenu(){document.querySelector('.overlaymenu').classList.add('open');var b=document.querySelector('.overlaymenu-backdrop');if(b){b.classList.add('open')}}
function closeMenu(){document.querySelector('.overlaymenu').classList.remove('open');var b=document.querySelector('.overlaymenu-backdrop');if(b){b.classList.remove('open')}}
window.addEventListener('load',function(){var p=document.querySelector('.preloader');if(p){p.style.opacity='0';setTimeout(function(){p.style.display='none'},500)}});
document.querySelectorAll('.cierra').forEach(function(e){e.addEventListener('click',function(ev){ev.preventDefault();var i=document.querySelector('.content--intro');if(i)i.style.display='none';closeMenu();window.scrollTo(0,0)})});
document.querySelectorAll('.abre').forEach(function(e){e.addEventListener('click',function(ev){ev.preventDefault();openMenu()})});
document.querySelectorAll('.cierra_menu').forEach(function(e){e.addEventListener('click',function(ev){ev.preventDefault();closeMenu()})});
var bd=document.querySelector('.overlaymenu-backdrop');if(bd){bd.addEventListener('click',function(){closeMenu()})};
document.querySelectorAll('.tema').forEach(function(e){e.addEventListener('click',function(){window.scrollTo(0,0);var h=document.querySelector('.home'),c=document.querySelector('.contenido');if(h)h.style.display='none';if(c)c.style.display='block';closeMenu()})});
document.querySelectorAll('.back').forEach(function(e){e.addEventListener('click',function(ev){ev.preventDefault();var h=document.querySelector('.home'),c=document.querySelector('.contenido');if(h)h.style.display='block';if(c)c.style.display='none';closeMenu()})});
// Detectar si estamos dentro de un LMS (iframe con SCORM API) y ajustar enlaces entre UDs
var inLMS=false;
try{inLMS=window.parent&&window.parent!==window&&(typeof window.parent.doLMSSetValue==='function'||typeof window.parent.API!=='undefined')}catch(e){inLMS=true}
if(inLMS){document.querySelectorAll('a.ud-link').forEach(function(a){var c=a.getAttribute('data-container');if(c)a.setAttribute('href',c)})}
});
JSEND;
        file_put_contents($this->tempPath . '/js/nav.js', $js);
    }

    private function writeJS_Aut(): void
    {
        $js = <<<'JSEND'
function capturarQ(qn,expOk,expBad){
var rs=document.getElementsByName('opcion_q'+qn),sel='ninguno';
for(var i=0;i<rs.length;i++){if(rs[i].checked)sel=rs[i].value}
var d=document.getElementById('resultado_q'+qn);
if(sel==='ninguno'){d.innerHTML='<div class="alert alert-warning">Elige una opci\u00f3n.</div>';return}
if(sel==='correcta'){d.innerHTML='<div class="alert alert-success"><strong>\u00a1Correcto!</strong> '+expOk+'</div>'}
else{d.innerHTML='<div class="alert alert-danger"><strong>Incorrecto.</strong> '+expBad+'</div>'}
}
JSEND;
        file_put_contents($this->tempPath . '/js/aut.js', $js);
    }

    // =====================================================================
    //  CSS — Cargado desde plantilla (TemplateManager)
    // =====================================================================
    private function writeCSS(): void
    {
        require_once __DIR__ . '/TemplateManager.php';
        $tm = new TemplateManager();

        try {
            $css = $tm->buildCSS($this->templateId);
            // Copiar assets de la plantilla (logo, fondos, fuentes)
            $tm->copyAssets($this->templateId, $this->tempPath);
        } catch (\Exception $e) {
            // Fallback: CSS mínimo si falla la plantilla
            $css = $this->getFallbackCSS();
        }

        file_put_contents($this->tempPath . '/css/estilos.css', $css);
    }

    /**
     * CSS de emergencia si no se puede cargar ninguna plantilla
     */
    private function getFallbackCSS(): string
    {
        $primary   = defined('COLOR_PRIMARY') ? COLOR_PRIMARY : '#143554';
        $secondary = defined('COLOR_SECONDARY') ? COLOR_SECONDARY : '#1a4a6e';
        $accent    = defined('COLOR_ACCENT') ? COLOR_ACCENT : '#F05726';

        $css = <<<CSSEND
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@400;500;600;700&display=swap');
:root{--primary:{$primary};--secondary:{$secondary};--accent:{$accent};--bg:#f8f9fa;--card:#fff;--text:#333;--text-light:#666;--border:#e0e0e0;--success:#22c55e;--danger:#ef4444;--warning:#f59e0b;--radius:12px;--shadow:0 4px 20px rgba(0,0,0,.08)}
*{box-sizing:border-box;margin:0;padding:0}
html{height:100%;font-size:16px;scroll-behavior:smooth}
body{min-height:100%;font-family:'Inter',sans-serif;color:var(--text);background:var(--bg);line-height:1.7;-webkit-font-smoothing:antialiased}
h1,h2,h3,h4,h5{font-family:'Poppins',sans-serif;line-height:1.3;color:var(--primary)}
a{color:var(--accent);text-decoration:none;font-weight:600}a:hover{text-decoration:underline}
img{max-width:100%}.d-none{display:none!important}
.mt-2{margin-top:.5rem}.mt-3{margin-top:1rem}.mt-4{margin-top:1.5rem}.mt-5{margin-top:2rem}
.float-right{float:right}.text-accent{color:var(--accent)}.text-center{text-align:center}
.row{display:flex;flex-wrap:wrap;margin:0 -15px}
.col-12{flex:0 0 100%;max-width:100%;padding:0 15px}
.col-lg-8{flex:0 0 66.67%;max-width:66.67%;padding:0 15px}
@media(max-width:768px){.col-lg-8{flex:0 0 100%;max-width:100%}}
.preloader{position:fixed;inset:0;z-index:9999;background:var(--primary);display:flex;align-items:center;justify-content:center;transition:opacity .5s}
.lds-ellipsis{display:inline-block;position:relative;width:80px;height:20px}
.lds-ellipsis div{position:absolute;width:13px;height:13px;border-radius:50%;background:#fff;animation:lds-e .6s infinite}
.lds-ellipsis div:nth-child(1){left:8px;animation:lds-e1 .6s infinite}
.lds-ellipsis div:nth-child(2){left:8px;animation:lds-e2 .6s infinite}
.lds-ellipsis div:nth-child(3){left:32px;animation:lds-e2 .6s infinite}
.lds-ellipsis div:nth-child(4){left:56px;animation:lds-e3 .6s infinite}
@keyframes lds-e1{0%{transform:scale(0)}100%{transform:scale(1)}}
@keyframes lds-e2{0%{transform:translate(0)}100%{transform:translate(24px)}}
@keyframes lds-e3{0%{transform:scale(1)}100%{transform:scale(0)}}
.content--intro{position:fixed;inset:0;z-index:500;background:linear-gradient(135deg,var(--primary) 0%,var(--secondary) 50%,var(--accent) 100%);display:flex;align-items:center;justify-content:center}
.content__inner{text-align:center;color:#fff;padding:2rem}
.corpo{background:rgba(255,255,255,.12);backdrop-filter:blur(10px);border-radius:var(--radius);padding:1rem 2.5rem;margin-bottom:3rem;display:flex;align-items:center;gap:1.5rem;justify-content:center;flex-wrap:wrap}
.corpo-logo{font-family:'Poppins',sans-serif;font-size:1.3rem;font-weight:700;letter-spacing:1px}
.corpo p{font-size:1rem;font-weight:300;margin:0;opacity:.9}
.portada-center{max-width:700px}
.portada__modulo{font-size:.9rem;text-transform:uppercase;letter-spacing:3px;opacity:.8;margin-bottom:.5rem}
.portada__badge{display:inline-block;background:var(--accent);color:#fff;padding:.4rem 1.5rem;border-radius:30px;font-weight:600;font-size:.95rem;margin-bottom:1rem}
.portada__title{font-size:clamp(1.8rem,4vw,3rem);font-weight:700;margin-bottom:.5rem;line-height:1.2;color:#fff;text-shadow:0 2px 20px rgba(0,0,0,.4),0 0 40px rgba(0,0,0,.2)}
.portada__duracion{font-size:.9rem;opacity:.7;margin-bottom:2rem}
.btn-comenzar{display:inline-block;background:#fff;color:var(--primary);padding:.85rem 2.5rem;border-radius:50px;font-weight:700;font-size:1rem;transition:all .3s;text-decoration:none;box-shadow:0 4px 20px rgba(0,0,0,.2)}
.btn-comenzar:hover{transform:translateY(-2px);box-shadow:0 8px 30px rgba(0,0,0,.3);text-decoration:none}
.overlaymenu{position:fixed;top:0;left:-320px;width:310px;height:100%;z-index:1000;background:linear-gradient(180deg,var(--primary) 0%,var(--secondary) 100%);overflow-y:auto;padding:1.5rem 2rem;color:#fff;transition:left .35s ease;box-shadow:4px 0 25px rgba(0,0,0,.3)}
.overlaymenu.open{left:0}
.overlaymenu-backdrop{position:fixed;inset:0;background:rgba(0,0,0,.4);z-index:999;display:none;opacity:0;transition:opacity .3s}
.overlaymenu-backdrop.open{display:block;opacity:1}
.overlaymenu-header{display:flex;justify-content:flex-end;margin-bottom:1rem}
.cierra_menu{color:#fff;font-size:2rem;text-decoration:none;opacity:.8;transition:opacity .2s}.cierra_menu:hover{opacity:1;text-decoration:none}
@media(max-width:480px){.overlaymenu{width:85vw;left:-85vw}}
.steps-form h2{color:#fff;font-size:1.3rem;margin-bottom:1rem;padding-bottom:.5rem;border-bottom:1px solid rgba(255,255,255,.2)}
.steps-step a{color:rgba(255,255,255,.9);font-weight:400;font-size:1rem;display:block;padding:.4rem 0;transition:all .2s;text-decoration:none}
.steps-step a:hover{color:#fff;padding-left:8px;text-decoration:none}
.steps-step .epigrafe{font-size:.85rem;padding-left:1.5rem;opacity:.8;font-weight:300}
.topnav{position:fixed;top:0;left:0;right:0;background:#fff;box-shadow:0 2px 10px rgba(0,0,0,.08);display:flex;align-items:center;z-index:100;height:50px;padding:0 1rem;gap:.5rem}
.menuicono{font-size:1.4rem;color:var(--primary);text-decoration:none;padding:.5rem}
.topnav-title{font-weight:600;color:var(--primary);font-size:.95rem;white-space:nowrap}
.topnav-units{display:flex;align-items:center;gap:4px;flex:1;justify-content:center;overflow-x:auto}
.topnav-ud{display:inline-flex;align-items:center;justify-content:center;padding:.25rem .7rem;border-radius:20px;font-size:.78rem;font-weight:600;color:var(--text-light);background:var(--bg);text-decoration:none;transition:all .2s;white-space:nowrap;border:1.5px solid transparent}
.topnav-ud:hover{color:var(--primary);background:#e8edf2;text-decoration:none}
.topnav-ud.ud-active{color:#fff;background:var(--primary);border-color:var(--primary)}
.topnav-empresa{font-size:.8rem;color:var(--text-light);font-weight:500;white-space:nowrap}
@media(max-width:600px){.topnav-ud{padding:.2rem .5rem;font-size:.7rem}.topnav-empresa{display:none}}
.home{padding-top:60px;min-height:100vh}.main{max-width:900px;margin:0 auto;padding:2rem 1.5rem}
.site__title{font-size:2rem;color:var(--primary);margin-bottom:.5rem}
.site__separator{width:80px;height:4px;background:var(--accent);border-radius:2px;margin-bottom:1.5rem}
.site__intro{font-size:1.05rem;color:var(--text-light);margin-bottom:1.5rem;line-height:1.8}
.site__objetivos{font-size:1.3rem;color:var(--primary);margin:1.5rem 0 .75rem}
.objetivos{list-style:none;padding:0}
.objetivos li{position:relative;padding-left:2rem;margin-bottom:.6rem;font-size:.95rem;line-height:1.6}
.objetivos li::before{content:"";position:absolute;left:.2rem;top:.55rem;width:10px;height:6px;border-left:2.5px solid var(--accent);border-bottom:2.5px solid var(--accent);transform:rotate(-45deg)}
.episodes{margin-top:2rem}
.episode{display:flex;align-items:center;gap:1.5rem;padding:1rem 0;border-bottom:1px solid var(--border)}
.episode__number{font-family:'Poppins',sans-serif;font-size:2.5rem;font-weight:700;color:var(--accent);opacity:.3;min-width:60px}
.episode__detail{flex:1}
.episode__title{text-decoration:none;color:var(--primary);transition:color .2s}.episode__title:hover{color:var(--accent);text-decoration:none}
.episode__title h4{margin:0;font-size:1.05rem;font-weight:600}
.main-footer{background:var(--primary);color:rgba(255,255,255,.7);text-align:center;padding:1rem;font-size:.85rem;margin-top:3rem}
.contenido{display:none;padding-top:60px;min-height:100vh}
.btn__back{display:inline-block;color:var(--primary);font-weight:600;font-size:.9rem;padding:.8rem 1rem;text-decoration:none}.btn__back:hover{color:var(--accent);text-decoration:none}
.titulotema{padding:0 1.5rem;margin-bottom:1rem}
.text-on-back{font-size:5rem;font-weight:800;color:var(--primary);opacity:.06;position:absolute;top:55px;right:20px;font-family:'Poppins',sans-serif}
.profile-title{font-size:1.5rem;color:var(--primary);margin-top:.5rem}
.container{max-width:960px;margin:0 auto;padding:0 1.5rem 3rem}
.setup-content{display:none;animation:fadeSlideIn .4s ease-out}
@keyframes fadeSlideIn{from{opacity:0;transform:translateY(20px)}to{opacity:1;transform:translateY(0)}}
.setup-content h3{font-size:1.4rem;color:var(--primary);margin-bottom:1rem;display:flex;align-items:center;gap:.5rem;flex-wrap:wrap}
.sec-num{color:var(--accent);font-weight:700;margin-right:.3rem}
.icon-bullet{font-size:1.3rem}.titleicon{display:flex;align-items:center;gap:.5rem}
.sec-header-icon svg{flex-shrink:0}
.sec-banner{border-radius:var(--radius);overflow:hidden;position:relative;margin-bottom:1.5rem}
.sec-banner img{display:block;width:100%;max-height:280px;object-fit:cover}
.sec-img-credit{position:absolute;bottom:8px;right:12px;font-size:.72rem;color:#fff;background:rgba(0,0,0,.5);padding:3px 10px;border-radius:4px;z-index:2}
.layout-full img{max-height:260px}
.layout-card{margin:0 auto 1.5rem;max-width:580px;text-align:center;background:var(--card);padding:.5rem .5rem .2rem;box-shadow:var(--shadow);border-radius:var(--radius)}.layout-card img{max-height:240px;object-fit:contain;border-radius:8px}
.sec-img-caption{display:block;font-size:.75rem;color:var(--text-light);margin-top:.4rem;padding-bottom:.3rem;font-style:italic}
.layout-gradient img{max-height:280px}
.sec-gradient-overlay{position:absolute;inset:0;background:linear-gradient(0deg,rgba(0,0,0,.35) 0%,transparent 50%)}
@media(max-width:600px){.sec-banner img{max-height:200px}.layout-card img{max-height:180px}}
.idevice{animation:fadeSlideIn .4s ease-out}
.accordion-item{animation:fadeSlideIn .3s ease-out}
.accordion-item:nth-child(2){animation-delay:.05s}
.accordion-item:nth-child(3){animation-delay:.1s}
.accordion-item:nth-child(4){animation-delay:.15s}
.flashcard{animation:fadeSlideIn .4s ease-out}
.flashcard:nth-child(2){animation-delay:.1s}
.flashcard:nth-child(3){animation-delay:.2s}
.line-accent{border:none;height:3px;background:linear-gradient(90deg,var(--accent),transparent);margin-bottom:1rem}
.card-box{background:var(--card);border-radius:var(--radius);padding:1.5rem 2rem;box-shadow:var(--shadow);margin-bottom:1.5rem}
.card-box.intro{border-left:5px solid var(--accent)}
.titulounidad{font-size:1.5rem;margin-bottom:1rem}
.duracion-badge{display:inline-block;background:var(--accent);color:#fff;padding:.3rem 1rem;border-radius:20px;font-size:.85rem;font-weight:500;margin-top:1rem}
.lista_indice{list-style:none;padding:0;margin:1rem 0}
.lista_indice li{padding:.4rem 0 .4rem 1.5rem;position:relative;font-size:.95rem;border-bottom:1px solid var(--border);list-style:none}
.lista_indice li::before{content:"";position:absolute;left:.3rem;top:.85rem;width:0;height:0;border-top:5px solid transparent;border-bottom:5px solid transparent;border-left:7px solid var(--accent)}
.content-body{font-size:1rem;line-height:1.8}
.content-body p{margin-bottom:1rem}
.content-body b,.content-body strong{color:var(--primary)}
.section-subtitle{color:var(--secondary);font-size:1.15rem;margin:1.5rem 0 .75rem;padding-bottom:.3rem;border-bottom:2px solid var(--accent)}
.lista_apte{list-style:none;padding:0;margin:1rem 0}
.lista_apte li{padding:.5rem 0 .5rem 2rem;position:relative;border-bottom:1px solid #f0f0f0;font-size:.95rem;line-height:1.6}
.lista_apte li::before{content:"";position:absolute;left:.5rem;top:.95rem;width:8px;height:8px;border-radius:50%;background:var(--accent);display:block}
.conclusiones{list-style:none;padding:0;margin:1rem 0}
.conclusiones li{padding:.6rem 0 .6rem 2rem;position:relative;font-size:.95rem;line-height:1.6}
.conclusiones li::before{content:"";position:absolute;left:.2rem;top:.7rem;width:10px;height:6px;border-left:2.5px solid var(--success);border-bottom:2.5px solid var(--success);transform:rotate(-45deg)}
.accordion-container{margin:1.5rem 0;display:flex;flex-direction:column;gap:.75rem}
.accordion-item{background:var(--card);border-radius:var(--radius);border-left:4px solid var(--secondary);box-shadow:var(--shadow);overflow:hidden;transition:border-color .3s}
.accordion-item:hover{border-left-color:var(--accent)}
.accordion-header{display:flex;align-items:center;gap:1rem;padding:1rem 1.2rem;cursor:pointer;transition:background .2s;color:var(--primary)}
.accordion-header:hover{background:rgba(0,0,0,.02)}
.accordion-header .acc-icon-wrap{flex-shrink:0;width:40px;height:40px;border-radius:10px;background:linear-gradient(135deg,var(--secondary),var(--primary));display:flex;align-items:center;justify-content:center}
.accordion-header .acc-icon-wrap svg{stroke:#fff}
.accordion-header .acc-title{flex:1;font-weight:600;font-size:1rem;line-height:1.4}
.accordion-header.open .acc-title{color:var(--accent)}
.accordion-arrow{flex-shrink:0;transition:transform .3s;color:var(--accent);font-size:1.1rem}
.accordion-header.open .accordion-arrow{transform:rotate(90deg)}
.accordion-body{display:none;padding:0 1.2rem 1.2rem 4.5rem;font-size:.95rem;line-height:1.7;color:var(--text);border-top:1px solid var(--border)}
.idevice{border-radius:var(--radius);padding:1.2rem 1.5rem 1.2rem 4rem;margin:1.5rem 0;position:relative;font-size:.95rem;line-height:1.7}
.idevice-icon{position:absolute;left:.8rem;top:1rem;font-size:1.3rem;display:flex;align-items:center;justify-content:center;width:36px;height:36px}
.idevice.importante{background:#fff3e0;border-left:4px solid var(--warning)}
.idevice.saber{background:#e8f5e9;border-left:4px solid var(--success)}
.idevice.def{background:#e3f2fd;border-left:4px solid var(--primary)}
.idevice.practica{background:#fce4ec;border-left:4px solid #e91e63}
.table-responsive{overflow-x:auto;margin:1.5rem 0}
.tabla-contenido{width:100%;border-collapse:collapse;border-radius:var(--radius);overflow:hidden;box-shadow:var(--shadow);font-size:.93rem}
.tabla-contenido th{background:var(--primary);color:#fff;padding:.75rem 1rem;text-align:left;font-weight:600;font-size:.9rem}
.tabla-contenido td{padding:.65rem 1rem;border-bottom:1px solid var(--border);background:var(--card)}
.tabla-contenido tr:nth-child(even) td{background:var(--bg)}
.tabla-contenido tr:hover td{background:#e8edf2}
.code-block{margin:1rem 0;border-radius:var(--radius);overflow:hidden;box-shadow:var(--shadow)}
.code-header{background:var(--primary);color:#fff;padding:.5rem 1rem;font-size:.8rem;font-weight:600;letter-spacing:1px}
.code-block pre{margin:0;padding:1.2rem;background:#1e1e2e;color:#cdd6f4;overflow-x:auto;font-size:.85rem;line-height:1.6;font-family:'Fira Code','Courier New',monospace}
.flashcards-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:1rem;margin:1rem 0}
.flashcard{perspective:1000px;height:200px;cursor:pointer}
.flashcard-inner{position:relative;width:100%;height:100%;transition:transform .6s;transform-style:preserve-3d;border-radius:var(--radius);box-shadow:var(--shadow)}
.flashcard.flipped .flashcard-inner{transform:rotateY(180deg)}
.flashcard-front,.flashcard-back{position:absolute;inset:0;backface-visibility:hidden;border-radius:var(--radius);display:flex;flex-direction:column;align-items:center;justify-content:center;padding:1.5rem 1.2rem;text-align:center}
.flashcard-front{background:linear-gradient(135deg,var(--primary),var(--secondary));color:#fff}
.flashcard-front h4{font-size:1.1rem;margin-bottom:.5rem;color:#fff;font-weight:600;word-break:break-word}
.flashcard-hint{font-size:.8rem;opacity:.7;color:#fff}
.flashcard-back{background:var(--card);color:var(--text);transform:rotateY(180deg);border:2px solid var(--accent);overflow-y:auto}
.flashcard-back p{font-size:.88rem;line-height:1.5;color:var(--text)}
.matching-game{margin:1rem 0;display:flex;gap:2rem;flex-wrap:wrap}
.matching-conceptos{flex:1;min-width:250px}
.matching-definiciones{flex:1;min-width:250px;display:flex;flex-direction:column;gap:.5rem}
.matching-row{display:flex;gap:.5rem;margin-bottom:.5rem;align-items:stretch}
.matching-term{background:var(--primary);color:#fff;padding:.6rem 1rem;border-radius:8px;font-weight:600;font-size:.9rem;min-width:120px;display:flex;align-items:center}
.matching-dropzone{flex:1;border:2px dashed var(--border);border-radius:8px;padding:.5rem;min-height:50px;display:flex;align-items:center;justify-content:center;transition:all .2s}
.matching-dropzone.drag-over{border-color:var(--accent);background:rgba(240,87,38,.05)}
.matching-dropzone.correcto{border-color:var(--success);background:rgba(34,197,94,.08)}
.matching-dropzone.incorrecto{border-color:var(--danger);background:rgba(239,68,68,.08)}
.dropzone-hint{color:#aaa;font-size:.8rem}
.matching-def{background:var(--card);border:1px solid var(--border);padding:.5rem .8rem;border-radius:8px;font-size:.85rem;cursor:grab;transition:all .2s;line-height:1.4}
.matching-def:hover{border-color:var(--accent);box-shadow:0 2px 8px rgba(0,0,0,.1)}
.matching-def.dragging{opacity:.5}.matching-def.placed{cursor:pointer;border-color:var(--secondary)}
.matching-resultado{margin-top:1rem;padding:.8rem 1rem;border-radius:8px;font-weight:600;text-align:center;display:none}
.matching-resultado.passed{background:#dcfce7;color:#166534}
.matching-resultado.failed{background:#fee2e2;color:#991b1b}
.pregunta{font-weight:600;font-size:1.05rem;color:var(--primary);margin-bottom:1rem;line-height:1.6}
.quiz-label{display:block;padding:.8rem 1rem .8rem 2.5rem;margin:.4rem 0;border:1px solid var(--border);border-radius:8px;cursor:pointer;transition:all .2s;position:relative;font-size:.95rem;line-height:1.5}
.quiz-label:hover{border-color:var(--accent);background:rgba(240,87,38,.03)}
.quiz-label input[type="radio"]{position:absolute;left:.8rem;top:50%;transform:translateY(-50%);accent-color:var(--accent)}
.alert{padding:1rem 1.2rem;border-radius:8px;margin-top:1rem;font-size:.95rem;line-height:1.6}
.alert-success{background:#dcfce7;color:#166534;border:1px solid #86efac}
.alert-danger{background:#fee2e2;color:#991b1b;border:1px solid #fca5a5}
.alert-warning{background:#fef3c7;color:#92400e;border:1px solid #fde68a}
.btn-action{background:var(--accent);color:#fff;border:none;padding:.7rem 2rem;border-radius:50px;font-weight:600;font-size:.95rem;cursor:pointer;transition:all .3s}
.btn-action:hover{background:var(--primary);transform:translateY(-1px)}
.prevBtn,.nextBtn{position:fixed;top:50%;transform:translateY(-50%);width:48px;height:48px;border-radius:50%;background:var(--card);box-shadow:0 4px 15px rgba(0,0,0,.12);display:flex;align-items:center;justify-content:center;cursor:pointer;z-index:50;transition:background .3s,border-color .3s;text-decoration:none;border:2px solid var(--border);font-size:0;line-height:0;overflow:hidden;animation:none!important}
.prevBtn{left:12px}.nextBtn{right:12px}
.prevBtn:hover,.nextBtn:hover{background:var(--primary);border-color:var(--primary)}
.prevBtn::after,.nextBtn::after{content:"";display:block;width:10px;height:10px;border-top:2.5px solid var(--primary);border-right:2.5px solid var(--primary)}
.prevBtn::after{transform:rotate(-135deg);margin-left:3px}
.nextBtn::after{transform:rotate(45deg);margin-right:3px}
.prevBtn:hover::after,.nextBtn:hover::after{border-color:#fff}
@media(max-width:768px){.prevBtn,.nextBtn{width:40px;height:40px;top:auto;bottom:15px;transform:none}.prevBtn{left:15px}.nextBtn{right:15px}}
.final-box{text-align:center;padding:3rem 2rem}
.enhorabuena{font-size:2.2rem;color:var(--accent);margin-bottom:.5rem}
.final-nav{display:flex;gap:1rem;justify-content:center;flex-wrap:wrap;margin:2rem 0}
.btn-nav{display:inline-block;padding:.7rem 1.5rem;border-radius:50px;font-weight:600;font-size:.9rem;text-decoration:none;transition:all .3s;border:2px solid var(--primary);color:var(--primary);cursor:pointer;background:transparent}
.btn-nav:hover{background:var(--primary);color:#fff;text-decoration:none}
.btn-nav.completed{border-color:var(--success);color:var(--success)}
.copyright{margin-top:2rem;font-size:.8rem;color:var(--text-light)}
.tabs-container{margin:1.5rem 0;border-radius:var(--radius);overflow:hidden;border:1px solid var(--border);background:var(--card)}
.tabs-nav{display:flex;border-bottom:2px solid var(--border);overflow-x:auto}
.tab-btn{flex:1;padding:.8rem 1.2rem;border:none;background:var(--bg);color:var(--text-light);font-weight:600;font-size:.9rem;cursor:pointer;transition:all .2s;border-bottom:3px solid transparent;min-width:100px;font-family:'Inter',sans-serif}
.tab-btn:hover{background:#fff;color:var(--primary)}
.tab-btn.active{background:#fff;color:var(--accent);border-bottom-color:var(--accent)}
.tab-panel{padding:1.2rem 1.5rem;font-size:.95rem;line-height:1.7}
@media(max-width:600px){.matching-game{flex-direction:column}.flashcards-grid{grid-template-columns:1fr 1fr}.portada__title{font-size:1.6rem}.tabs-nav{flex-wrap:wrap}.tab-btn{min-width:50%}}
CSSEND;
        return $css;
    }

    // =====================================================================
    //  MANIFEST
    // =====================================================================
    private function writeManifest(): void
    {
        $modTitle = $this->e($this->moduleConfig['titulo']);
        $orgId = 'ORG-' . strtoupper(substr(md5(uniqid()), 0, 16));
        $manifestId = 'MANIFEST-' . strtoupper(substr(md5(uniqid()), 0, 16));
        $itemsXml = '';
        $resourcesXml = '';

        foreach ($this->units as $unit) {
            $itemId = 'ITEM-' . strtoupper(substr(md5($unit['filename']), 0, 16));
            $resId  = 'RES-' . strtoupper(substr(md5($unit['filename'] . 'r'), 0, 16));
            $itemsXml .= '      <item identifier="' . $itemId . '" isvisible="true" identifierref="' . $resId . '"><title>' . $this->e($unit['titulo']) . '</title></item>' . "\n";
            $resourcesXml .= '    <resource identifier="' . $resId . '" type="webcontent" adlcp:scormtype="sco" href="scos/' . $unit['filename'] . '_container.html">
      <file href="scos/' . $unit['filename'] . '_container.html"/>
      <file href="scos/' . $unit['filename'] . '.html"/>
      <file href="js/scorm_api.js"/><file href="js/funciones.js"/><file href="js/stepper.js"/><file href="js/nav.js"/><file href="js/aut.js"/><file href="css/estilos.css"/>
    </resource>' . "\n";
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?>
<manifest xmlns="http://www.imsproject.org/xsd/imscp_rootv1p1p2" xmlns:adlcp="http://www.adlnet.org/xsd/adlcp_rootv1p2" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" identifier="' . $manifestId . '" xsi:schemaLocation="http://www.imsproject.org/xsd/imscp_rootv1p1p2 imscp_rootv1p1p2.xsd http://www.adlnet.org/xsd/adlcp_rootv1p2 adlcp_rootv1p2.xsd">
  <organizations default="' . $orgId . '">
    <organization identifier="' . $orgId . '" structure="hierarchical">
      <title>' . $modTitle . '</title>
' . $itemsXml . '    </organization>
  </organizations>
  <resources>
' . $resourcesXml . '  </resources>
</manifest>';
        file_put_contents($this->tempPath . '/imsmanifest.xml', $xml);
    }

    // =====================================================================
    //  ZIP + CLEANUP + HELPERS
    // =====================================================================
    private function createZip(string $zipPath): void
    {
        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \Exception("No se pudo crear el archivo ZIP: $zipPath");
        }
        $basePath = realpath($this->tempPath);
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($basePath, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );
        foreach ($files as $file) {
            $filePath = $file->getRealPath();
            $relativePath = str_replace('\\', '/', substr($filePath, strlen($basePath) + 1));
            $zip->addFile($filePath, $relativePath);
        }
        $zip->close();
    }

    private function cleanup(): void { $this->deleteDir($this->tempPath); }

    private function deleteDir(string $dir): void
    {
        if (!is_dir($dir)) return;
        foreach (array_diff(scandir($dir), ['.', '..']) as $item) {
            $path = $dir . '/' . $item;
            is_dir($path) ? $this->deleteDir($path) : unlink($path);
        }
        rmdir($dir);
    }

    /**
     * Construye la barra de navegación entre unidades en el header
     */
    private function buildUnitNav(int $currentIdx): string
    {
        $html = '';
        foreach ($this->units as $i => $u) {
            $active = ($i === $currentIdx) ? ' ud-active' : '';
            // data-container para LMS, href directo para preview local
            $html .= '<a href="' . $u['filename'] . '.html" data-container="' . $u['filename'] . '_container.html" class="topnav-ud ud-link' . $active . '" title="' . $this->e($u['titulo']) . '">Ud.' . $u['numero'] . '</a>';
        }
        return $html;
    }

    private function e(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }

    /**
     * Renderiza imagen de sección con 3 layouts alternados (Fase 4)
     * Layout 0: banner full-width
     * Layout 1: card centrada con caption
     * Layout 2: banner con overlay gradient
     */
    private function renderSectionImage(array $sec, int $sectionIndex = 0): string
    {
        $img = $sec['image'] ?? '';
        if (empty($img)) return '';
        $credit = $sec['image_credit'] ?? '';
        $alt = $this->e($sec['titulo'] ?? 'Imagen de sección');
        $creditHtml = !empty($credit) ? '<span class="sec-img-credit">Foto: ' . $this->e($credit) . '</span>' : '';
        $src = '../img/' . $this->e($img);
        $layout = $sectionIndex % 3;

        switch ($layout) {
            case 0: // Banner full-width
                return '<div class="sec-banner layout-full">' . $creditHtml . '<img src="' . $src . '" alt="' . $alt . '" loading="lazy"></div>';
            case 1: // Card centrada con caption
                $creditCaption = !empty($credit) ? '<figcaption class="sec-img-caption">Foto: ' . $this->e($credit) . '</figcaption>' : '';
                return '<figure class="sec-banner layout-card"><img src="' . $src . '" alt="' . $alt . '" loading="lazy">' . $creditCaption . '</figure>';
            case 2: // Banner con gradient overlay
                return '<div class="sec-banner layout-gradient"><img src="' . $src . '" alt="' . $alt . '" loading="lazy"><div class="sec-gradient-overlay"></div>' . $creditHtml . '</div>';
            default:
                return '<div class="sec-banner layout-full">' . $creditHtml . '<img src="' . $src . '" alt="' . $alt . '" loading="lazy"></div>';
        }
    }

    // =====================================================================
    //  FASE 3: SVG ICON LIBRARY
    // =====================================================================
    private static function svg(string $name, string $color = '#1a7f64', int $size = 32): string
    {
        $icons = [
            'book' => '<path d="M4 19.5A2.5 2.5 0 016.5 17H20" /><path d="M4 19.5A2.5 2.5 0 004.5 22H20V2H6.5A2.5 2.5 0 004 4.5v15z" />',
            'lightbulb' => '<path d="M9 21h6" /><path d="M12 3a6 6 0 014 10.5V17H8v-3.5A6 6 0 0112 3z" />',
            'warning' => '<path d="M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z" /><line x1="12" y1="9" x2="12" y2="13" /><line x1="12" y1="17" x2="12.01" y2="17" />',
            'check' => '<polyline points="20 6 9 17 4 12" />',
            'list' => '<line x1="8" y1="6" x2="21" y2="6" /><line x1="8" y1="12" x2="21" y2="12" /><line x1="8" y1="18" x2="21" y2="18" /><line x1="3" y1="6" x2="3.01" y2="6" /><line x1="3" y1="12" x2="3.01" y2="12" /><line x1="3" y1="18" x2="3.01" y2="18" />',
            'code' => '<polyline points="16 18 22 12 16 6" /><polyline points="8 6 2 12 8 18" />',
            'brain' => '<path d="M12 2a7 7 0 017 7c0 2.38-1.19 4.47-3 5.74V17a2 2 0 01-2 2h-4a2 2 0 01-2-2v-2.26C6.19 13.47 5 11.38 5 9a7 7 0 017-7z" /><path d="M9 21h6" />',
            'target' => '<circle cx="12" cy="12" r="10" /><circle cx="12" cy="12" r="6" /><circle cx="12" cy="12" r="2" />',
            'puzzle' => '<path d="M19.439 7.85c-.049.322.059.648.289.878l1.568 1.568c.47.47.706 1.087.706 1.704s-.235 1.233-.706 1.704l-1.611 1.611a.98.98 0 01-.837.276c-.47-.07-.802-.48-.968-.925a2.501 2.501 0 10-3.214 3.214c.446.166.855.497.925.968a.979.979 0 01-.276.837l-1.61 1.61a2.404 2.404 0 01-1.705.707 2.402 2.402 0 01-1.704-.706l-1.568-1.568a1.026 1.026 0 00-.877-.29c-.493.074-.84.504-1.02.968a2.5 2.5 0 11-3.237-3.237c.464-.18.894-.527.967-1.02a1.026 1.026 0 00-.289-.877l-1.568-1.568A2.402 2.402 0 011.998 12c0-.617.236-1.234.706-1.704L4.315 8.685a.98.98 0 01.837-.276c.47.07.802.48.968.925a2.501 2.501 0 103.214-3.214c-.446-.166-.855-.497-.925-.968a.979.979 0 01.276-.837l1.61-1.61a2.404 2.404 0 011.705-.707c.618 0 1.234.236 1.704.706l1.568 1.568c.23.23.556.338.877.29.493-.074.84-.504 1.02-.968a2.5 2.5 0 113.237 3.237c-.464.18-.894.527-.967 1.02z" />',
            'arrow-right' => '<line x1="5" y1="12" x2="19" y2="12" /><polyline points="12 5 19 12 12 19" />',
            'info' => '<circle cx="12" cy="12" r="10" /><line x1="12" y1="16" x2="12" y2="12" /><line x1="12" y1="8" x2="12.01" y2="8" />',
            'edit' => '<path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7" /><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z" />',
            'layers' => '<polygon points="12 2 2 7 12 12 22 7 12 2" /><polyline points="2 17 12 22 22 17" /><polyline points="2 12 12 17 22 12" />',
            'database' => '<ellipse cx="12" cy="5" rx="9" ry="3" /><path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3" /><path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5" />',
            'chart' => '<line x1="18" y1="20" x2="18" y2="10" /><line x1="12" y1="20" x2="12" y2="4" /><line x1="6" y1="20" x2="6" y2="14" />',
            'globe' => '<circle cx="12" cy="12" r="10" /><line x1="2" y1="12" x2="22" y2="12" /><path d="M12 2a15.3 15.3 0 014 10 15.3 15.3 0 01-4 10 15.3 15.3 0 01-4-10 15.3 15.3 0 014-10z" />',
            'shield' => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" />',
            'tool' => '<path d="M14.7 6.3a1 1 0 000 1.4l1.6 1.6a1 1 0 001.4 0l3.77-3.77a6 6 0 01-7.94 7.94l-6.91 6.91a2.12 2.12 0 01-3-3l6.91-6.91a6 6 0 017.94-7.94l-3.76 3.76z" />',
            'users' => '<path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2" /><circle cx="9" cy="7" r="4" /><path d="M23 21v-2a4 4 0 00-3-3.87" /><path d="M16 3.13a4 4 0 010 7.75" />',
            'star' => '<polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2" />',
            'clipboard' => '<path d="M16 4h2a2 2 0 012 2v14a2 2 0 01-2 2H6a2 2 0 01-2-2V6a2 2 0 012-2h2" /><rect x="8" y="2" width="8" height="4" rx="1" ry="1" />',
        ];
        $d = $icons[$name] ?? $icons['info'];
        return '<svg xmlns="http://www.w3.org/2000/svg" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="' . $color . '" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' . $d . '</svg>';
    }
}
