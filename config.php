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
define('CLAUDE_MAX_TOKENS', 64000);

// =============================================
// BASE DE DATOS (MySQL)
// =============================================
define('DB_HOST', $_ENV['DB_HOST'] ?? 'localhost');
define('DB_NAME', $_ENV['DB_NAME'] ?? 'scorm_generator');
define('DB_USER', $_ENV['DB_USER'] ?? 'root');
define('DB_PASS', $_ENV['DB_PASS'] ?? '');
define('DB_CHARSET', 'utf8mb4');

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

// Enrichment: split inteligente de pantallas largas
define('ENABLE_ENRICHMENT_DEFAULT', true);
define('MAX_SCREEN_CHARS', 8000);

// =============================================
// PEXELS API (Fase 4 — imágenes automáticas)
// Obtén tu key gratis en: https://www.pexels.com/api/
// =============================================
define('PEXELS_API_KEY', $_ENV['PEXELS_API_KEY'] ?? '');

// UNSPLASH API (mantenido como referencia, en desuso)
define('UNSPLASH_API_KEY', $_ENV['UNSPLASH_API_KEY'] ?? '');

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
        "duracion_total": número de horas totales,
        "idioma": "código ISO 639-1 del idioma del documento (es, en, fr, pt, de, it, etc.)"
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
                    "icono_keyword": "keyword EN INGLÉS para imagen de banner (ej: keyboard technology, office workspace)",
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
- "parrafo": texto explicativo (máx 4 frases). NUNCA más de 2 seguidos.
- "definicion": término + definición (campos "termino" y "texto")
- "lista": enumeración (campo "items" con texto COMPLETO de cada punto)
- "tabla": datos tabulares (campo "filas", primera fila = cabecera). DEFAULT para agrupar conceptos con nombre+descripción.
- "importante": advertencia o punto crítico
- "sabias_que": dato complementario o curioso
- "ejemplo": caso práctico o ilustrativo
- "comparativa": 2-4 elementos a contrastar (campo "items" con formato "Nombre: descripción")
- "proceso": pasos secuenciales, protocolos, escalas (campo "items" formato "Título corto: explicación")

REGLA ANTI-TOCHOS: Cada sección DEBE tener al menos 2 componentes interactivos (tabla/lista/comparativa/proceso/definicion/importante/sabias_que/ejemplo). Una sección de solo párrafos NO es e-learning, es un PDF.
TODAS las secciones DEBEN usar "contenido_estructurado" con bloques tipados. NUNCA dejes secciones con solo texto plano.

REGLAS CRÍTICAS:

1. ESTRUCTURA:
   - REGLA DE TAMAÑO: Cada unidad debe tener contenido SUSTANCIAL (mínimo ~1500 palabras o 3+ páginas). Si el documento entero es corto (menos de 5000 palabras / ~10 páginas), haz UNA SOLA UNIDAD con varias secciones.
   - Si el documento tiene capítulos/temas GRANDES (cada uno con 3+ páginas), cada capítulo = una unidad.
   - Si el documento tiene secciones numeradas PEQUEÑAS (1.1, 1.2, 1.3... cada una con pocos párrafos), esas secciones NO son unidades. Son SECCIONES dentro de una misma unidad.
   - Las subsecciones del documento (ej: 4.3.a, 4.3.b) se convierten en secciones independientes si tienen contenido sustancial (más de 2 párrafos). NO las comprimas dentro de otra sección.
   - Si el documento NO tiene estructura clara, CREA una organización lógica con tantas secciones como necesite el contenido (mínimo 3). NO limites artificialmente el número de secciones.
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
{language_instruction}

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
- "proceso": pasos en ACORDEÓN desplegable. Úsalo siempre que el contenido tenga ítems con cierto orden o estructura interna: (1) procedimientos paso a paso secuenciales, (2) protocolos o fases de actuación numeradas, (3) escalas de valoración o criterios de evaluación con ítems ordenados, (4) algoritmos de decisión con pasos definidos. NUNCA para simples listas de conceptos sin orden ni estructura interna.
  FORMATO OBLIGATORIO de cada item de "proceso": "Título corto (3-6 palabras): explicación detallada completa". El título corto es lo que se muestra en la cabecera del acordeón; la explicación se muestra al expandir. Ejemplo correcto: "Inclusión inicial: Una vez identificado el paciente e incluido en el Proceso Asistencial, en un plazo de un mes". Ejemplo incorrecto: "Una vez identificado el paciente e incluido en el Proceso Asistencial en un plazo de un mes" (sin título corto).
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
5. Items de listas = texto ÍNTEGRO del bullet original del Word. CONSERVA TODOS los signos de puntuación, incluyendo los dos puntos (:). NUNCA elimines los dos puntos de los ítems. Correcto: "Alimentación: Independiente = 10 puntos". Incorrecto: "Alimentación Independiente = 10 puntos".

REGLAS DE AGRUPACIÓN (clave para buen diseño):

Cuando el contenido presenta 3 o más elementos del MISMO tipo seguidos, SIEMPRE agrúpalos en UN SOLO componente:

- 3+ conceptos con nombre y descripción → UNA "tabla" con columnas [Nombre, Descripción]
- 3+ ventajas o características → UNA "lista" (no 3 párrafos separados)
- 2-4 opciones contrastadas → UNA "comparativa" (tabs)
- 3+ pasos de un PROCEDIMIENTO REAL, criterios de una ESCALA/PROTOCOLO, o fases de actuación → UN "proceso" (acordeón)

NUNCA hagas esto:
- 3 "definicion" seguidas + 2 items en "lista" para el mismo grupo de conceptos (inconsistente)
- Separar elementos homólogos en tipos de bloque distintos
- Usar "proceso" para listar conceptos sin orden lógico. "Proceso" es para pasos, protocolos, escalas y criterios con secuencia (ej: "Paso 1: acción A"; "Criterio 1: descripción + valor")

Regla simple: si puedes decir "estos N items son del mismo tipo/categoría", van en UN SOLO bloque.

CRITERIO PARA ELEGIR COMPONENTE al agrupar:
- Si cada elemento tiene nombre + descripción (cualquier longitud) → "tabla" [Nombre, Descripción]. La tabla es el DEFAULT para agrupar conceptos.
- Si cada descripción es MUY larga (5+ frases / párrafo completo) → "comparativa" (tabs) si son 2-4 items, o dividir en subsecciones si son más
- Si son solo nombres/frases sin estructura → "lista"
- Si son instrucciones secuenciales reales → "proceso" (acordeón)

REGLAS DE DISEÑO:

1. VARIEDAD VISUAL OBLIGATORIA: Alterna tipos de bloque. PROHIBIDO más de 2 "parrafo" seguidos.
   Si tienes 3+ párrafos seguidos, PARA y transforma alguno: extrae datos clave a una tabla, convierte una enumeración implícita en lista, destaca un dato en sabias_que, marca una advertencia como importante.
   Patrón ideal: parrafo → tabla → sabias_que → lista → parrafo...
   Patrón PROHIBIDO: parrafo → parrafo → parrafo → parrafo (esto NO es e-learning, es un PDF)

   REGLA OBLIGATORIA PARA LISTAS: Toda "lista" DEBE ir precedida de un bloque "parrafo" cuya ÚLTIMA frase introduzca directamente los items (debe terminar con ":" o con una frase tipo "son los siguientes:", "se incluyen:", "se destacan:", etc.). NO basta con que haya un párrafo antes hablando del tema — ese párrafo debe ACABAR presentando la lista. Si el contenido original no tiene frase introductoria, créala a partir del contexto (ej: "Las áreas que evalúa la Escala de Gijón son:").

2. COMPONENTES INTERACTIVOS: Usa estos siempre que el contenido lo permita:
   - Dos cosas que comparar → "comparativa" (genera tabs clicables)
   - Procedimiento/protocolo/escala con pasos o criterios ordenados → "proceso" (genera acordeón)
   - Grupo de conceptos con nombre + descripción → "tabla" (muy visual y compacta, es el DEFAULT)

3. MÁX 2 "definicion" por sección. Si hay 3+, usa tabla o acordeón.

4. ESTRUCTURA:
   - Tantas secciones como necesite el contenido (sin límite artificial). Si el Word tiene 8 secciones, genera 8. NO sacrifiques contenido para reducir secciones.
   - Cada sección debe tener entre 4 y 10 bloques. Si una sección tiene más de 10 bloques, divídela en 2 secciones.
   - Si una sección solo tendría 1-2 bloques (contenido muy corto), fusiónala con la sección anterior o siguiente.
   - Empieza SIEMPRE cada sección con un bloque "parrafo" introductorio de 2-3 frases que contextualice lo que se va a ver.
   - Títulos de sección descriptivos y atractivos
   - icono_keyword específico ("gaming desktop rgb" mejor que "technology")

   REGLA ANTI-TOCHOS (CRÍTICA):
   - El SCORM NO es un documento de texto. Es una experiencia INTERACTIVA. Cada sección DEBE tener al menos 2 componentes interactivos (tabla, comparativa, proceso, lista, definicion, importante, sabias_que o ejemplo).
   - Si una sección tiene 4+ párrafos seguidos, estás haciendo un documento de texto, NO un SCORM. Reorganiza: agrupa datos en tablas, convierte enumeraciones en listas, usa cajas destacadas para datos clave.
   - Patrón MÍNIMO por sección: parrafo(intro) → componente interactivo → [parrafo transición] → componente interactivo → [cierre]
   - NUNCA generes una sección con solo bloques "parrafo". Eso es un fallo grave de diseño instruccional.

5. CONCEPTOS CLAVE (generan flashcards y juego de matching):
   - 5-8 términos REALES extraídos del contenido
   - Definiciones de máx 120 caracteres

EJEMPLO BUEN DISEÑO:
  Componentes de PC: parrafo(intro) → tabla([Componente, Función]: CPU/GPU/RAM/SSD/Placa) → sabias_que → importante
  Ventajas gaming: parrafo(intro) → tabla([Ventaja, Detalle]: Personalización/Rendimiento/Biblioteca/Periféricos)
  Montar un PC: parrafo(intro) → proceso([Instalar CPU, Montar RAM, Conectar GPU...]) ← ESTO sí es proceso (pasos reales)
  Escala de valoración: parrafo(intro) → proceso([Criterio 1: descripción + puntuación, Criterio 2: descripción + puntuación, ...]) ← escala con criterios puntuados = proceso
  Protocolo de N pasos: parrafo(intro) → proceso([Paso 1: acción A, Paso 2: acción B, Paso 3: acción C]) ← fases secuenciales = proceso

EJEMPLO MAL DISEÑO (PROHIBIDO — si generas esto, el SCORM saldrá roto):
  proceso([CPU: es el cerebro, GPU: procesa gráficos, RAM: memoria]) = NO son pasos, son conceptos → usar tabla
  definicion(CPU) → definicion(GPU) → definicion(RAM) → lista([SSD, Placa]) = inconsistente
  parrafo, parrafo, parrafo, parrafo = documento de texto, no e-learning
  Sección con solo texto plano sin ningún componente interactivo = FALLO GRAVE

REGLA TÉCNICA CRÍTICA:
- TODAS las secciones DEBEN tener el campo "contenido_estructurado" con un array de bloques.
- Cada bloque DEBE tener el campo "tipo" con uno de los valores válidos: parrafo, definicion, lista, tabla, comparativa, proceso, importante, sabias_que, ejemplo.
- NO dejes secciones con solo "contenido" (texto plano). SIEMPRE usa "contenido_estructurado" con bloques tipados.
- Si no sabes qué tipo usar para un fragmento → usa "tabla" como default para datos, "lista" para enumeraciones, "importante" para advertencias.

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
{language_instruction}

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

define('PROMPT_RESTRUCTURE', <<<'PROMPT'
Actúa como diseñador instruccional experto en e-learning. Reestructura el siguiente texto de una unidad didáctica para optimizar su conversión a un curso SCORM 1.2 interactivo.

UNIDAD: {unit_title} (numerada como {unit_number})

TEXTO ORIGINAL:
{unit_content}

REGLAS ESTRICTAS:
1. MANTENER la numeración original: si la unidad es {unit_number}, las secciones deben ser {unit_number}.1., {unit_number}.2., etc. y las subsecciones {unit_number}.1.a., {unit_number}.1.b., etc.
2. NO perder información: todo el contenido original debe aparecer en la salida
3. NO inventar contenido nuevo ni añadir datos que no estén en el original
4. NO cambiar el número de la unidad: sigue siendo TEMA {unit_number}

REESTRUCTURACIÓN:
- Párrafos: máximo 3-4 frases cada uno. Dividir párrafos largos.
- Definiciones: convertir a formato "Término: Definición completa." cuando detectes que se define un concepto.
- Listas: agrupar elementos relacionados en listas con viñetas (•).
- Tablas: si hay datos tabulares o comparaciones, presentarlos como tabla con | separador y primera fila como cabecera.
- Procedimientos: si hay pasos secuenciales, numerarlos (1., 2., 3.).
- Advertencias: si hay información crítica o de seguridad, marcarla con "IMPORTANTE:" al inicio.
- Secciones: organizar el contenido en secciones claras con formato {unit_number}.X. Título

FORMATO DE SALIDA:
Devuelve SOLO el texto reestructurado en texto plano (NO JSON, NO markdown con backticks). Mantén la estructura jerárquica:

TEMA {unit_number}: {unit_title}

{unit_number}.1. Primera sección
[contenido reestructurado]

{unit_number}.2. Segunda sección
[contenido reestructurado]

etc.
PROMPT
);

// Prompt para clasificar y convertir imágenes del Word a bloques estructurados (Vision API)
define('PROMPT_CLASSIFY_IMAGE', <<<'PROMPT'
Eres un experto en diseño instruccional para e-learning. Analiza esta imagen de un documento educativo y conviértela en contenido HTML estructurado.
{language_instruction}

CONTEXTO del documento donde aparece la imagen:
{context}

PASO 1 — CLASIFICA la imagen en UNA de estas categorías:
- TABLA_CLINICA: tabla con datos, escalas, puntuaciones, criterios, clasificaciones
- ALGORITMO: diagrama de flujo, árbol de decisión, flujograma con pasos secuenciales
- ESQUEMA: mapa conceptual, diagrama de relaciones, esquema jerárquico, infografía
- FORMULARIO: formulario, ficha de registro, plantilla a rellenar
- GRAFICO: gráfico estadístico (barras, líneas, circular, dispersión)
- DECORATIVA: foto decorativa, ilustración genérica, logo, icono sin información educativa

PASO 2 — CONVIERTE el contenido de la imagen en bloques estructurados.

Responde ÚNICAMENTE con JSON válido (sin markdown, sin ```):
{
    "clasificacion": "TABLA_CLINICA|ALGORITMO|ESQUEMA|FORMULARIO|GRAFICO|DECORATIVA",
    "confianza": 0.0-1.0,
    "descripcion_breve": "descripción de 1 línea de lo que muestra la imagen",
    "bloques": []
}

REGLAS DE CONVERSIÓN POR CATEGORÍA:

TABLA_CLINICA → tipo "tabla":
{"tipo": "tabla", "filas": [["Columna1", "Columna2"], ["dato1", "dato2"]]}
- Transcribe TODAS las filas y columnas visibles.
- Si hay valores numéricos (puntuaciones, rangos), inclúyelos exactos.
- Si un valor no es legible con certeza, escríbelo seguido de " [VERIFICAR]".

ALGORITMO → tipo "proceso":
{"tipo": "proceso", "items": ["Paso 1: descripción completa", "Paso 2: si X entonces Y"]}
- Convierte cada nodo/decisión del diagrama en un paso.
- Para bifurcaciones: "Si [condición] → [resultado A]. Si no → [resultado B]"

ESQUEMA → combinación de bloques:
- Si es jerárquico: {"tipo": "lista", "titulo": "Concepto central", "items": ["Subcategoría 1: detalle", "Subcategoría 2: detalle"]}
- Si tiene categorías con descripciones: {"tipo": "tabla", "filas": [["Concepto", "Descripción"], ...]}

FORMULARIO → tipo "tabla":
{"tipo": "tabla", "filas": [["Campo", "Descripción/Instrucción"], ["Nombre", "Introducir nombre completo"]]}
- Transcribe cada campo del formulario como fila.

GRAFICO → tipo "tabla" + "parrafo":
[
    {"tipo": "parrafo", "texto": "Descripción del gráfico: qué muestra, tendencia principal, conclusión"},
    {"tipo": "tabla", "filas": [["Categoría", "Valor"], ["dato1", "valor1"]]}
]

DECORATIVA → bloques vacío:
{"clasificacion": "DECORATIVA", "confianza": 1.0, "descripcion_breve": "...", "bloques": []}

REGLAS CRÍTICAS:
1. FIDELIDAD: Transcribe TODO lo visible. No inventes datos.
2. VERIFICAR: Si un dato no es 100% legible, añade " [VERIFICAR]" después.
3. CONTEXTO: Usa el contexto del documento para entender la imagen.
4. IDIOMA: Contenido de salida en el mismo idioma que el contexto.
5. Si la imagen está en baja resolución y no puedes extraer info útil, clasifícala como DECORATIVA.
PROMPT
);

define('MAX_IMAGE_SIZE_VISION', 5242880); // 5MB máximo por imagen para Vision API

// Prompt para extraer texto de imágenes cuando el Word es mayoritariamente imágenes (OCR-like)
define('PROMPT_EXTRACT_TEXT_FROM_IMAGE', <<<'PROMPT'
Extrae TODO el texto visible en esta imagen de un documento educativo/formativo.

INSTRUCCIONES:
1. Transcribe el texto tal cual aparece, manteniendo el orden de lectura (arriba-abajo, izquierda-derecha).
2. Si es una tabla, represéntala con líneas separadas. Cada fila en una línea, columnas separadas por " | ".
3. Si es un diagrama de flujo o esquema, describe la estructura con indentación:
   - Nodo principal
     - Rama A → resultado
     - Rama B → resultado
4. Si hay títulos o encabezados, márcalos con MAYÚSCULAS o anteponiendo "## ".
5. Si algún texto no es legible con certeza, escríbelo seguido de " [VERIFICAR]".
6. NO añadas interpretaciones ni resúmenes. Solo transcribe lo visible.
7. Si la imagen es puramente decorativa (foto genérica, logo, icono sin texto relevante), responde solo: [DECORATIVA]

Responde ÚNICAMENTE con el texto extraído, sin formato JSON, sin markdown con backticks.
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
