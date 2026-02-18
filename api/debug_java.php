<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/WordProcessor.php';

use ScormConverter\WordProcessor;

header('Content-Type: application/json; charset=utf-8');

$wp = new WordProcessor('/mnt/user-data/uploads/Java_Guia_Completa_15_paginas.docx');
$data = $wp->process();
$structure = $wp->detectStructure();

$result = [
    'total_paragraphs' => count($data['paragraphs']),
    'total_chars' => $data['char_count'],
    'structure_title' => $structure['title'],
    'units_count' => count($structure['units']),
];

// Mostrar primeros 40 párrafos
$result['first_40_paragraphs'] = array_slice(
    array_map(function($p) {
        return [
            'text' => mb_substr($p['text'], 0, 100),
            'style' => $p['style'],
            'heading' => $p['is_heading']
        ];
    }, $data['paragraphs']),
    0, 40
);

// Mostrar unidades encontradas
foreach ($structure['units'] as $u) {
    $content = implode("\n\n", $u['content'] ?? []);
    $result['units'][] = [
        'n' => $u['number'],
        'title' => $u['title'],
        'paragraphs' => count($u['content'] ?? []),
        'chars' => strlen($content),
    ];
}

echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
