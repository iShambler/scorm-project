<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: text/plain; charset=utf-8');

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/SCORMGenerator.php';

use ScormConverter\SCORMGenerator;

$moduleConfig = [
    'codigo' => 'TEST_01',
    'titulo' => 'Modulo de prueba',
    'duracion_total' => 40,
    'empresa' => 'ARELANCE S.L.'
];

$units = [[
    'numero' => 1,
    'titulo' => 'Introduccion a la tecnologia',
    'duracion' => 20,
    'filename' => 'ud1_intro',
    'resumen' => 'En esta unidad aprenderemos los conceptos basicos de la tecnologia moderna y su impacto en la sociedad.',
    'objetivos' => ['Comprender los fundamentos', 'Aplicar en contexto real', 'Evaluar soluciones'],
    'conceptos_clave' => [
        ['termino' => 'Algoritmo', 'definicion' => 'Conjunto de instrucciones ordenadas para resolver un problema'],
        ['termino' => 'Variable', 'definicion' => 'Espacio en memoria que almacena un valor que puede cambiar'],
        ['termino' => 'Funcion', 'definicion' => 'Bloque de codigo reutilizable que realiza una tarea especifica'],
        ['termino' => 'Bucle', 'definicion' => 'Estructura que repite instrucciones mientras se cumpla una condicion'],
        ['termino' => 'Condicional', 'definicion' => 'Estructura que ejecuta codigo segun se cumpla una condicion'],
    ],
    'secciones' => [
        ['titulo' => 'Historia y evolucion', 'contenido' => "La tecnologia ha evolucionado enormemente en las ultimas decadas.\n\nDesde los primeros ordenadores hasta los smartphones actuales, cada paso ha sido revolucionario.\n\nImportante: La velocidad del cambio tecnologico se acelera exponencialmente."],
        ['titulo' => 'Fundamentos basicos', 'contenido' => "1.1. Conceptos esenciales\n\nTodo sistema informatico se basa en el procesamiento de datos.\n\nHardware: Es la parte fisica del ordenador, incluyendo procesador, memoria y almacenamiento.\n\nSoftware: Son los programas que permiten al usuario interactuar con el hardware."],
        ['titulo' => 'Aplicaciones practicas', 'contenido' => "Las aplicaciones de la tecnologia son infinitas en el mundo actual.\n\n- Comunicaciones instantaneas\n- Automatizacion de procesos\n- Inteligencia artificial\n- Comercio electronico"],
    ],
    'preguntas' => [
        ['pregunta' => 'Que es un algoritmo?', 'opciones' => ['Un tipo de hardware','Un conjunto de instrucciones ordenadas','Un lenguaje de programacion','Un sistema operativo'], 'correcta' => 1, 'explicacion' => 'Un algoritmo es un conjunto ordenado de pasos para resolver un problema.'],
        ['pregunta' => 'Que es una variable?', 'opciones' => ['Una constante','Un espacio en memoria','Un tipo de bucle','Una funcion'], 'correcta' => 1, 'explicacion' => 'Las variables almacenan valores que pueden cambiar durante la ejecucion.'],
    ],
    'codigo' => [
        ['language' => 'Python', 'code' => "def saludar(nombre):\n    print(f'Hola, {nombre}!')\n\nsaludar('Mundo')"]
    ]
], [
    'numero' => 2,
    'titulo' => 'Programacion avanzada',
    'duracion' => 20,
    'filename' => 'ud2_avanzada',
    'resumen' => 'Segunda unidad del modulo.',
    'objetivos' => ['Dominar patrones', 'Implementar soluciones'],
    'conceptos_clave' => [
        ['termino' => 'Herencia', 'definicion' => 'Mecanismo que permite crear clases basadas en otras'],
        ['termino' => 'Polimorfismo', 'definicion' => 'Capacidad de un objeto de tomar multiples formas'],
        ['termino' => 'Encapsulamiento', 'definicion' => 'Ocultacion de datos internos de un objeto'],
        ['termino' => 'Abstraccion', 'definicion' => 'Simplificacion de la complejidad mostrando solo lo esencial'],
    ],
    'secciones' => [
        ['titulo' => 'POO Conceptos', 'contenido' => "La programacion orientada a objetos organiza el codigo en clases.\n\nCada clase define propiedades y metodos."],
        ['titulo' => 'Patrones de diseno', 'contenido' => "Los patrones resuelven problemas comunes de forma elegante."],
    ],
    'preguntas' => [
        ['pregunta' => 'Que es la herencia?', 'opciones' => ['Crear variables','Crear clases desde otras','Un tipo de bucle','Nada'], 'correcta' => 1, 'explicacion' => 'Permite reutilizar codigo.'],
    ],
    'codigo' => []
]];

try {
    $generator = new SCORMGenerator($moduleConfig, $units);
    $zipPath = $generator->generate();
    $size = filesize($zipPath);
    echo "OK - ZIP generado: {$zipPath} ({$size} bytes)\n\n";
    
    // Listar contenido del ZIP
    $zip = new ZipArchive();
    $zip->open($zipPath);
    echo "Archivos en el ZIP:\n";
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $stat = $zip->statIndex($i);
        printf("  %8d  %s\n", $stat['size'], $stat['name']);
    }
    $zip->close();
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString();
}
