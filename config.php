<?php
/**
 * Configuración del Conversor Word a SCORM
 * ARELANCE S.L. - 2025
 */

// Mostrar errores en desarrollo (cambiar a false en producción)
define('DEBUG_MODE', true);

if (DEBUG_MODE) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// =============================================
// CONFIGURACIÓN DE LA API DE CLAUDE (ANTHROPIC)
// =============================================
// Obtén tu API key en: https://console.anthropic.com/
// Cargar variables de entorno desde .env
$envFile = __DIR__ . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '#') === 0) continue;
        if (strpos($line, '=') === false) continue;
        list($key, $value) = explode('=', $line, 2);
        $_ENV[trim($key)] = trim($value);
    }
}

define('CLAUDE_API_KEY', $_ENV['CLAUDE_API_KEY'] ?? 'tu-api-key-aqui');
define('CLAUDE_MODEL', 'claude-sonnet-4-20250514');
define('CLAUDE_MAX_TOKENS', 4096);

// =============================================
// CONFIGURACIÓN DE RUTAS
// =============================================
define('BASE_PATH', __DIR__);
define('UPLOADS_PATH', BASE_PATH . '/uploads');
define('TEMP_PATH', BASE_PATH . '/temp');
define('TEMPLATES_PATH', BASE_PATH . '/templates');

// =============================================
// CONFIGURACIÓN DE ARCHIVOS
// =============================================
define('MAX_FILE_SIZE', 50 * 1024 * 1024); // 50 MB
define('ALLOWED_EXTENSIONS', ['docx']);

// =============================================
// CONFIGURACIÓN POR DEFECTO DEL SCORM
// =============================================
define('DEFAULT_COMPANY', 'ARELANCE S.L.');
define('DEFAULT_HOURS', 50);
define('SCORM_VERSION', '1.2');

// =============================================
// UNSPLASH API (Fase 4 — imágenes automáticas)
// Obtén tu key gratis en: https://unsplash.com/developers
// =============================================
define('UNSPLASH_API_KEY', $_ENV['UNSPLASH_API_KEY'] ?? 'tu-unsplash-key-aqui');

// =============================================
// COLORES CORPORATIVOS
// =============================================
define('COLOR_PRIMARY', '#143554');
define('COLOR_SECONDARY', '#1a4a6e');
define('COLOR_ACCENT', '#F05726');
define('COLOR_SUCCESS', '#22c55e');

// =============================================
// PROMPTS PARA LA IA
// =============================================
define('PROMPT_ANALYZE', <<<'PROMPT'
Eres un experto en diseño instruccional y creación de contenidos formativos. Analiza el siguiente contenido de un documento Word y extrae la información estructurada.

CONTENIDO DEL DOCUMENTO:
{content}

Responde ÚNICAMENTE con un JSON válido (sin markdown, sin explicaciones) con esta estructura exacta:
{
    "modulo": {
        "codigo": "código detectado o sugerido (ej: MOD_01, PROY_M4)",
        "titulo": "título del módulo",
        "duracion_total": número de horas totales
    },
    "unidades": [
        {
            "numero": 1,
            "titulo": "título de la unidad (solo primera letra mayúscula)",
            "duracion": horas de la unidad,
            "resumen": "resumen breve de 2-3 líneas",
            "objetivos": ["objetivo 1", "objetivo 2", "objetivo 3"],
            "conceptos_clave": [
                {"termino": "término", "definicion": "definición clara y concisa"}
            ],
            "secciones": [
                {"titulo": "título de la sección temática", "contenido": ""}
            ]
        }
    ]
}

REGLAS IMPORTANTES:
- Los títulos deben seguir las normas de la RAE: solo primera letra en mayúscula (excepto nombres propios)
- Detecta TODAS las unidades didácticas del documento
- Genera entre 4-8 conceptos clave (flashcards) por unidad basados en el contenido real
- Los conceptos deben ser relevantes y las definiciones claras
- Si no detectas estructura clara, organiza el contenido de forma lógica
- En "secciones" genera SOLO los títulos temáticos (3-5 por unidad), deja "contenido" vacío (se rellenará después con el texto real del documento)
- Los títulos de secciones deben reflejar los temas principales de cada unidad
PROMPT
);

define('PROMPT_ENRICH_SECTIONS', <<<'PROMPT'
Eres un experto en diseño instruccional para e-learning. Tu tarea es enriquecer las secciones de una unidad didáctica clasificando cada bloque de contenido por tipo de componente visual.

UNIDAD: {unit_title}
SECCIONES (títulos propuestos por análisis previo): {section_titles}

CONTENIDO REAL DEL DOCUMENTO (texto extraído del Word):
{unit_content}

Distribuye el contenido real entre las secciones y clasifica CADA bloque según su naturaleza. Responde ÚNICAMENTE con un JSON válido:
{
    "secciones": [
        {
            "titulo": "título de la sección",
            "icono_keyword": "una palabra clave EN INGLÉS que describa el tema de esta sección para buscar un icono (ej: climate, database, security, leaf, chart, team, innovation, law, chemistry, network)",
            "bloques": [
                {
                    "tipo": "parrafo|definicion|lista|comparativa|proceso|tip_importante|tip_saber|tip_practica|ejemplo|codigo",
                    "contenido": "texto del bloque",
                    "termino": "solo para tipo definicion: el término que se define",
                    "items": ["solo para tipo lista/comparativa/proceso: array de items"],
                    "etiqueta": "solo para tips: Importante/Recuerda/Sabías que/Práctica/Ejemplo"
                }
            ]
        }
    ],
    "conclusiones": ["frase resumen 1", "frase resumen 2", "frase resumen 3"]
}

TIPOS DE COMPONENTE (elige el más adecuado para cada fragmento):
- "parrafo": texto explicativo normal
- "definicion": un concepto y su definición (incluir campo "termino")
- "lista": enumeración de elementos (incluir campo "items")
- "comparativa": comparación entre 2+ elementos, se mostrará como pestañas/tabs (incluir "items" con formato "Nombre: descripción")
- "proceso": pasos secuenciales, se mostrará como acordeón (incluir "items" con cada paso)
- "tip_importante": advertencia o punto crítico (incluir "etiqueta": "Importante")
- "tip_saber": dato curioso o complementario (incluir "etiqueta": "Sabías que")
- "tip_practica": ejercicio o caso práctico (incluir "etiqueta": "Práctica")
- "ejemplo": ejemplo ilustrativo (incluir "etiqueta": "Ejemplo")
- "codigo": fragmento de código fuente

REGLAS:
- Usa el contenido REAL del documento, no inventes texto
- Cada sección debe tener entre 3-8 bloques
- Varía los tipos de componente para mantener el interés visual
- Si una sección no tiene contenido claro, redistribuye el contenido disponible
- Las conclusiones deben ser 3-5 frases que resuman lo aprendido en la unidad
- Prioriza: al menos 1 definicion, 1 lista y 1 tip por sección cuando el contenido lo permita
- Los bloques tipo "comparativa" deben tener exactamente 2-4 items
- Los bloques tipo "proceso" deben tener 3-6 pasos
PROMPT
);

define('PROMPT_QUESTIONS', <<<'PROMPT'
Eres un experto en evaluación educativa. Genera preguntas de autoevaluación para la siguiente unidad didáctica.

UNIDAD: {unit_title}
CONTENIDO: {unit_content}
CONCEPTOS CLAVE: {concepts}

Genera exactamente 5 preguntas de opción múltiple. Responde ÚNICAMENTE con un JSON válido:
{
    "preguntas": [
        {
            "pregunta": "texto de la pregunta",
            "opciones": ["opción a", "opción b", "opción c", "opción d"],
            "correcta": 0,
            "explicacion": "breve explicación de por qué es correcta"
        }
    ]
}

REGLAS:
- Las preguntas deben evaluar comprensión, no solo memorización
- Incluir preguntas de aplicación práctica
- Las opciones incorrectas deben ser plausibles
- "correcta" es el índice (0-3) de la respuesta correcta
- Variar el tipo de preguntas: conceptuales, de aplicación, de análisis
PROMPT
);

// =============================================
// FUNCIONES AUXILIARES
// =============================================

/**
 * Genera un ID único para archivos temporales
 */
function generateUniqueId(): string {
    return uniqid('scorm_', true);
}

/**
 * Limpia el nombre de archivo para usar como identificador
 */
function slugify(string $text): string {
    $text = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
    $text = preg_replace('/[^a-zA-Z0-9\s]/', '', $text);
    $text = strtolower(trim($text));
    $text = preg_replace('/\s+/', '_', $text);
    return substr($text, 0, 30);
}

/**
 * Respuesta JSON estandarizada
 */
function jsonResponse(bool $success, $data = null, string $message = ''): void {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => $success,
        'data' => $data,
        'message' => $message,
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Log de errores
 */
function logError(string $message, array $context = []): void {
    $logFile = BASE_PATH . '/logs/error.log';
    $logDir = dirname($logFile);
    
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    $entry = date('[Y-m-d H:i:s]') . ' ' . $message;
    if (!empty($context)) {
        $entry .= ' | Context: ' . json_encode($context);
    }
    $entry .= PHP_EOL;
    
    file_put_contents($logFile, $entry, FILE_APPEND | LOCK_EX);
}

/**
 * Limpia archivos temporales antiguos (más de 1 hora)
 */
function cleanupTempFiles(): void {
    $tempFiles = glob(TEMP_PATH . '/*');
    $now = time();
    
    foreach ($tempFiles as $file) {
        if (is_file($file) && ($now - filemtime($file)) > 3600) {
            unlink($file);
        }
    }
}

// Crear directorios si no existen
foreach ([UPLOADS_PATH, TEMP_PATH, BASE_PATH . '/logs'] as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}
