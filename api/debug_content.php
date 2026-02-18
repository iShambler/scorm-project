<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/WordProcessor.php';

use ScormConverter\WordProcessor;

ini_set('display_errors', 0);
header('Content-Type: application/json; charset=utf-8');

// Buscar el último docx subido
$files = glob(UPLOADS_PATH . '/*.docx');
if (empty($files)) {
    echo json_encode(['error' => 'No hay archivos']);
    exit;
}

usort($files, function($a, $b) { return filemtime($b) - filemtime($a); });
$lastFile = $files[0];

$wp = new WordProcessor($lastFile);
$data = $wp->process();
$structure = $wp->detectStructure();

$result = [
    'file' => basename($lastFile),
    'total_paragraphs' => count($data['paragraphs']),
    'total_chars' => $data['char_count'],
    'structure_title' => $structure['title'],
    'units_count' => count($structure['units']),
    'units' => []
];

foreach ($structure['units'] as $unit) {
    $contentJoined = implode("\n\n", $unit['content'] ?? []);
    $result['units'][] = [
        'number' => $unit['number'],
        'title' => $unit['title'],
        'content_paragraphs' => count($unit['content'] ?? []),
        'content_chars' => strlen($contentJoined),
        'first_200_chars' => mb_substr($contentJoined, 0, 200),
    ];
}

// Mostrar los primeros 20 párrafos para ver qué hay
$result['first_20_paragraphs'] = array_slice(
    array_map(function($p) {
        return [
            'text' => mb_substr($p['text'], 0, 120),
            'style' => $p['style'],
            'is_heading' => $p['is_heading']
        ];
    }, $data['paragraphs']),
    0, 20
);

// Test de regex contra los párrafos reales
$result['regex_tests'] = [];
foreach ($data['paragraphs'] as $p) {
    $t = $p['text'];
    if (preg_match('/M[OÓ]DULO/iu', $t)) {
        $result['regex_tests'][] = ['match' => 'MODULO', 'text' => mb_substr($t, 0, 80), 'hex' => bin2hex(mb_substr($t, 0, 20))];
    }
    if (preg_match('/UNIDAD/iu', $t)) {
        $result['regex_tests'][] = ['match' => 'UNIDAD', 'text' => mb_substr($t, 0, 80), 'hex' => bin2hex(mb_substr($t, 0, 30))];
    }
}

echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
