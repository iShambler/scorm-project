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
define('CLAUDE_MAX_TOKENS', 16000);

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
Eres un experto en diseño instruccional y creación de contenidos formativos para e-learning. Tu tarea es analizar un documento Word y crear la ESTRUCTURA COMPLETA del curso con todo el contenido asignado.

CONTENIDO DEL DOCUMENTO:
{content}

Responde ÚNICAMENTE con un JSON válido (sin markdown, sin explicaciones) con esta estructura:
{
    "modulo": {
        "codigo": "código detectado o sugerido (ej: MOD_01, MOD_02)",
        "titulo": "título del módulo",
        "duracion_total": número de horas totales
    },
    "unidades": [
        {
            "numero": 1,
            "titulo": "título de la unidad",
            "duracion": horas estimadas,
            "resumen": "resumen de 2-3 líneas para la portada",
            "objetivos": ["objetivo 1", "objetivo 2", "objetivo 3", "objetivo 4"],
            "secciones": [
                {
                    "titulo": "título de la sección",
                    "contenido_estructurado": [
                        {"tipo": "parrafo", "texto": "texto del párrafo"},
                        {"tipo": "definicion", "termino": "término", "texto": "definición"},
                        {"tipo": "lista", "titulo": "título opcional de la lista", "items": ["item 1 completo", "item 2 completo"]},
                        {"tipo": "tabla", "filas": [["Col1","Col2"],["dato1","dato2"]]},
                        {"tipo": "importante", "texto": "texto destacado"},
                        {"tipo": "ejemplo", "texto": "caso práctico"}
                    ]
                }
            ],
            "conceptos_clave": [
                {"termino": "término", "definicion": "definición concisa"}
            ]
        }
    ]
}

TIPOS DE BLOQUE para "contenido_estructurado":
- "parrafo": texto explicativo (máx 4 frases)
- "definicion": término + definición (campos "termino" y "texto")
- "lista": enumeración (campo "items" con texto COMPLETO de cada punto)
- "tabla": datos tabulares (campo "filas", primera fila = cabecera)
- "importante": advertencia o punto crítico
- "sabias_que": dato complementario o curioso
- "ejemplo": caso práctico o ilustrativo
- "comparativa": 2-4 elementos a contrastar (campo "items" con formato "Nombre: descripción")

REGLAS CRÍTICAS:

1. ESTRUCTURA:
   - REGLA DE TAMAÑO: Cada unidad debe tener contenido SUSTANCIAL (mínimo ~1500 palabras o 3+ páginas). Si el documento entero es corto (menos de 5000 palabras / ~10 páginas), haz UNA SOLA UNIDAD con varias secciones.
   - Si el documento tiene capítulos/temas GRANDES (cada uno con 3+ páginas), cada capítulo = una unidad.
   - Si el documento tiene secciones numeradas PEQUEÑAS (1.1, 1.2, 1.3... cada una con pocos párrafos), esas secciones NO son unidades. Son SECCIONES dentro de una misma unidad.
   - Las subsecciones del documento (ej: 4.3.a, 4.3.b) se convierten en secciones dentro de la unidad correspondiente.
   - Si el documento NO tiene estructura clara, CREA una organización lógica dividiendo el contenido en 3-5 secciones por unidad.
   - NUNCA crees unidades con menos de 2 secciones o con muy poco contenido. Es mejor tener 1-2 unidades ricas que 6 unidades vacías.

2. CONTENIDO COMPLETO:
   - TODA la información del documento debe estar en "contenido_estructurado". No pierdas NADA.
   - Cada párrafo, lista, definición, dato del Word debe aparecer como un bloque.
   - Los items de las listas deben contener el TEXTO COMPLETO del bullet original, no resumen.
   - Cuando haya varias listas con distinto contexto, crea bloques separados (cada uno con su párrafo introductorio).

3. FIDELIDAD:
   - Mantén el texto original. NO resumas, NO sintetices, NO reescribas. Tu trabajo es ORGANIZAR y elegir el tipo de bloque adecuado.
   - Si el documento dice "La disminución del Índice de Barthel en 20 puntos (salvo cuando el Barthel sea menor de 60 puntos)", eso aparece íntegro.

4. TABLAS:
   - Cuando el texto menciona escalas con puntuaciones, clasificaciones o rangos, SIEMPRE usar tipo "tabla".
   - Las tablas que ya existen en el documento se preservan como tipo "tabla".

5. FORMATO:
   - Títulos: primera letra mayúscula, resto minúscula (excepto nombres propios y siglas)
   - 4-8 conceptos_clave por unidad basados en el contenido real
   - 3-5 objetivos por unidad
   - Párrafos de máximo 4 frases. Si hay un párrafo largo, divídelo en varios bloques.
PROMPT
);

// Prompt para estructurar UNA unidad con todo su contenido
// Se llama por cada UD detectada, pasándole solo el texto de esa UD
// ENFOQUE: Diseñador instruccional e-learning con reglas claras de fidelidad
define('PROMPT_STRUCTURE_UNIT', <<<'PROMPT'
Eres un DISEÑADOR INSTRUCCIONAL EXPERTO en e-learning. Tu trabajo es ORGANIZAR contenido existente en una EXPERIENCIA DE APRENDIZAJE INTERACTIVA para SCORM, eligiendo el mejor componente visual para cada fragmento.

UNIDAD: {unit_title}

CONTENIDO DE LA UNIDAD:
{unit_content}

Responde ÚNICAMENTE con un JSON válido (sin markdown, sin ```json, sin explicaciones).
IMPORTANTE para JSON válido: escapa comillas dobles dentro del texto como \" y no uses caracteres de control.

{
    "secciones": [
        {
            "titulo": "título atractivo de la sección",
            "icono_keyword": "keyword EN INGLÉS para imagen (ej: gaming computer, processor chip, cooling fan)",
            "contenido_estructurado": [
                {"tipo": "parrafo", "texto": "texto introductorio"},
                {"tipo": "definicion", "termino": "CPU", "texto": "El procesador central..."},
                {"tipo": "lista", "titulo": "título opcional", "items": ["item completo 1", "item completo 2"]},
                {"tipo": "tabla", "filas": [["Col1","Col2"],["dato1","dato2"]]},
                {"tipo": "comparativa", "items": ["Opción A: descripción", "Opción B: descripción"]},
                {"tipo": "proceso", "items": ["Paso 1. Explicación detallada", "Paso 2. Explicación"]},
                {"tipo": "importante", "texto": "advertencia extraída del contenido"},
                {"tipo": "sabias_que", "texto": "dato curioso BASADO en el contenido"},
                {"tipo": "ejemplo", "texto": "caso práctico del contenido"}
            ]
        }
    ],
    "resumen": "resumen de 2-3 líneas para la portada",
    "objetivos": ["[Comprender] Explicar cómo funciona...", "[Aplicar] Implementar...", "[Analizar] Distinguir entre..."],
    "conceptos_clave": [
        {"termino": "término", "definicion": "definición concisa (max 120 chars)"}
    ]
}

COMPONENTES DISPONIBLES (usa variedad, nunca más de 2 párrafos seguidos):
- "parrafo": texto introductorio o transicional. MÁXIMO 3-4 frases / 150 palabras. Si el contenido original es más largo, divídelo en MÚLTIPLES bloques "parrafo" separados (uno por párrafo o idea). Nunca acumules más de 150 palabras en un solo bloque parrafo.
- "definicion": término + explicación en caja destacada azul
- "lista": bullets visuales. Items = texto COMPLETO del original
- "tabla": datos comparativos. Campo "filas", primera fila = cabecera
- "comparativa": 2-4 elementos en TABS interactivas ("items" formato "Nombre: descripción")
- "proceso": pasos en ACORDEÓN desplegable. SOLO para procedimientos REALES paso a paso (ej: montar un PC, instalar software). NUNCA para listar conceptos o componentes.
- "importante": caja amarilla de advertencia
- "sabias_que": caja verde de dato complementario
- "ejemplo": caja con caso práctico

REGLAS DE FIDELIDAD (prioridad máxima):

1. TODO EL CONTENIDO DEL DOCUMENTO DEBE APARECER. No pierdas ni una frase, dato o cifra.
2. NO INVENTES información nueva. Solo reorganiza y presenta lo que ya existe.
3. Los "sabias_que" y "ejemplo" SOLO pueden contener información que ESTÁ en el documento.
   - Si el documento menciona un dato curioso → conviértelo en "sabias_que"
   - Si el documento tiene un caso práctico → conviértelo en "ejemplo"
   - Si NO hay datos curiosos ni ejemplos en el documento → NO los añadas
4. Al dividir párrafos largos: corta por oraciones completas, sin cambiar el orden ni mezclar párrafos distintos.
5. Items de listas = texto ÍNTEGRO del bullet original del Word.

REGLAS DE AGRUPACIÓN (clave para buen diseño):

Cuando el contenido presenta 3 o más elementos del MISMO tipo seguidos, SIEMPRE agrúpalos en UN SOLO componente:

- 3+ conceptos con nombre y descripción → UNA "tabla" con columnas [Nombre, Descripción]
- 3+ ventajas o características → UNA "lista" (no 3 párrafos separados)
- 2-4 opciones contrastadas → UNA "comparativa" (tabs)
- 3+ pasos de un PROCEDIMIENTO REAL (instrucciones que se siguen en orden) → UN "proceso" (acordeón)

NUNCA hagas esto:
- 3 "definicion" seguidas + 2 items en "lista" para el mismo grupo de conceptos (inconsistente)
- Separar elementos homólogos en tipos de bloque distintos
- Usar "proceso" para listar conceptos, componentes o características. "Proceso" es SOLO para procedimientos paso a paso (ej: "Paso 1: Instalar drivers, Paso 2: Configurar BIOS")

Regla simple: si puedes decir "estos N items son del mismo tipo/categoría", van en UN SOLO bloque.

CRITERIO PARA ELEGIR COMPONENTE al agrupar:
- Si cada elemento tiene nombre + descripción (cualquier longitud) → "tabla" [Nombre, Descripción]. La tabla es el DEFAULT para agrupar conceptos.
- Si cada descripción es MUY larga (5+ frases / párrafo completo) → "comparativa" (tabs) si son 2-4 items, o dividir en subsecciones si son más
- Si son solo nombres/frases sin estructura → "lista"
- Si son instrucciones secuenciales reales → "proceso" (acordeón)

REGLAS DE DISEÑO:

1. VARIEDAD VISUAL: Alterna tipos de bloque. Nunca más de 2 "parrafo" seguidos.
   Patrón ideal: parrafo → tabla → sabias_que → lista → parrafo...

2. COMPONENTES INTERACTIVOS: Usa estos siempre que el contenido lo permita:
   - Dos cosas que comparar → "comparativa" (genera tabs clicables)
   - Procedimiento real con pasos ordenados → "proceso" (genera acordeón)
   - Grupo de conceptos con nombre + descripción → "tabla" (muy visual y compacta, es el DEFAULT)

3. MÁX 2 "definicion" por sección. Si hay 3+, usa tabla o acordeón.

4. ESTRUCTURA:
   - 3-6 secciones por unidad, cada una con 4-8 bloques
   - Empieza cada sección con un párrafo introductorio
   - Títulos de sección descriptivos y atractivos
   - icono_keyword específico ("gaming desktop rgb" mejor que "technology")

5. CONCEPTOS CLAVE (generan flashcards y juego de matching):
   - 5-8 términos REALES extraídos del contenido
   - Definiciones de máx 120 caracteres

EJEMPLO BUEN DISEÑO:
  Componentes de PC: parrafo(intro) → tabla([Componente, Función]: CPU/GPU/RAM/SSD/Placa) → sabias_que → importante
  Ventajas gaming: parrafo(intro) → tabla([Ventaja, Detalle]: Personalización/Rendimiento/Biblioteca/Periféricos)
  Montar un PC: parrafo(intro) → proceso([Instalar CPU, Montar RAM, Conectar GPU...]) ← ESTO sí es proceso (pasos reales)

EJEMPLO MAL DISEÑO (evitar):
  proceso([CPU: es el cerebro, GPU: procesa gráficos, RAM: memoria]) = NO son pasos, son conceptos → usar tabla
  definicion(CPU) → definicion(GPU) → definicion(RAM) → lista([SSD, Placa]) = inconsistente
  parrafo, parrafo, parrafo, parrafo = documento de texto, no e-learning

OBJETIVOS DE APRENDIZAJE (Taxonomía de Bloom):
Genera 3-4 objetivos. Cada uno DEBE empezar con su nivel de Bloom entre corchetes, seguido de un verbo de acción específico de ese nivel:
- [Recordar]: identificar, reconocer, listar, nombrar, enumerar, definir
- [Comprender]: explicar, describir, resumir, interpretar, clasificar, distinguir
- [Aplicar]: implementar, usar, ejecutar, resolver, demostrar, calcular
- [Analizar]: comparar, examinar, diferenciar, organizar, desglosar, contrastar
- [Evaluar]: valorar, justificar, criticar, defender, priorizar, argumentar
- [Crear]: diseñar, formular, construir, planificar, proponer, desarrollar
Varía los niveles: incluye siempre al menos [Comprender] y [Aplicar]. No repitas el mismo nivel dos veces.
Ejemplos correctos: "[Comprender] Explicar los componentes principales de una placa base", "[Aplicar] Seleccionar los componentes adecuados según el presupuesto y el uso previsto"
PROMPT
);

// Prompt alternativo para enriquecer secciones (mismo esquema JSON que PROMPT_STRUCTURE_UNIT)
define('PROMPT_ENRICH_SECTIONS', <<<'PROMPT'
Eres un experto en diseño instruccional para e-learning. TRANSFORMA el contenido de un documento Word en bloques didácticos interactivos.

UNIDAD: {unit_title}
SECCIONES (títulos propuestos): {section_titles}

CONTENIDO DEL DOCUMENTO:
{unit_content}

Responde ÚNICAMENTE con JSON válido (sin markdown, sin ```json). Escapa comillas dobles como \".

{
    "secciones": [
        {
            "titulo": "título de la sección",
            "icono_keyword": "keyword EN INGLÉS para imagen",
            "contenido_estructurado": [
                {"tipo": "parrafo", "texto": "texto del bloque"},
                {"tipo": "definicion", "termino": "término", "texto": "definición"},
                {"tipo": "lista", "titulo": "título opcional", "items": ["item 1", "item 2"]},
                {"tipo": "tabla", "filas": [["Col1","Col2"],["dato1","dato2"]]},
                {"tipo": "comparativa", "items": ["A: desc", "B: desc"]},
                {"tipo": "proceso", "items": ["Paso 1. Detalle", "Paso 2. Detalle"]},
                {"tipo": "importante", "texto": "advertencia"},
                {"tipo": "sabias_que", "texto": "dato curioso del contenido"},
                {"tipo": "ejemplo", "texto": "caso práctico del contenido"}
            ]
        }
    ],
    "conclusiones": ["Has aprendido a...", "Ahora puedes..."]
}

REGLAS:
1. FIDELIDAD: Mantén TODO el texto original. NO resumas, NO inventes. Elige el mejor componente visual para cada fragmento.
2. Items de listas = TEXTO COMPLETO del bullet original.
3. Al dividir párrafos: corta por oraciones completas, sin mezclar párrafos distintos.
4. "sabias_que" y "ejemplo" SOLO con información que YA está en el documento.
5. Variedad visual: nunca más de 2 "parrafo" seguidos. Alterna componentes.
6. Tablas para escalas, clasificaciones, datos numéricos comparativos.
7. "comparativa" para 2-4 elementos contrastados explícitamente.
8. "proceso" SOLO para procedimientos con 3+ pasos largos (no listas simples).
9. Conclusiones: 3-5 frases como logros del alumno.
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
