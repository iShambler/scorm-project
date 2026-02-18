# CLAUDE.md — Conversor Word a SCORM

## Descripción del proyecto

Aplicación web PHP que convierte documentos Word (.docx) en paquetes SCORM 1.2 con diseño instruccional profesional. Utiliza la API de Claude (Anthropic) para generar contenido educativo: flashcards, preguntas de autoevaluación y estructura pedagógica. Funciona también sin IA con análisis basado en patrones.

**Empresa:** ARELANCE S.L.
**Licencia:** Propietaria — © 2025 ARELANCE S.L.

---

## Arquitectura

```
scorm_converter/
├── index.php                    # Frontend: SPA con wizard de 4 pasos
├── config.php                   # Configuración global, constantes, prompts IA, helpers
├── .htaccess                    # Protección de carpetas y rewrite rules
├── CLAUDE.md                    # Este archivo
├── README.md                    # Documentación de instalación y uso
│
├── api/                         # Endpoints REST (JSON)
│   ├── analyze.php              # POST — Sube .docx, extrae texto, analiza con IA
│   ├── generate.php             # POST — Genera paquete SCORM desde datos analizados
│   └── download.php             # GET  — Descarga el .zip SCORM generado
│
├── includes/                    # Clases PHP (namespace ScormConverter)
│   ├── WordProcessor.php        # Extrae texto, párrafos, tablas y código de .docx
│   ├── AIProcessor.php          # Comunicación con Claude API (análisis + preguntas)
│   └── SCORMGenerator.php       # Genera HTML, CSS, JS, manifest y empaqueta ZIP
│
├── assets/
│   ├── css/styles.css           # Estilos del frontend (wizard)
│   └── js/app.js                # Lógica del frontend: upload, pasos, edición, descarga
│
├── uploads/                     # Archivos .docx subidos (temporal, limpieza auto 1h)
├── temp/                        # ZIPs generados y carpetas de trabajo temporal
└── logs/                        # error.log
```

## Stack tecnológico

- **Backend:** PHP 7.4+ (sin framework)
- **Frontend:** HTML5, CSS3 vanilla, JavaScript vanilla (ES6+)
- **IA:** Anthropic Claude API (`claude-sonnet-4-20250514`)
- **Servidor:** Apache con mod_rewrite (`.htaccess`)
- **Dependencias externas:** Ninguna (no usa Composer). Solo extensiones PHP nativas: `zip`, `curl`, `dom`, `json`, `mbstring`

## Flujo de la aplicación

1. **Upload** → El usuario arrastra un `.docx` → `api/analyze.php`
2. **Análisis** → `WordProcessor` extrae texto → `AIProcessor` genera estructura JSON (módulo, unidades, flashcards, preguntas)
3. **Revisión** → El frontend muestra los datos en un wizard editable (4 pasos)
4. **Generación** → `api/generate.php` recibe el JSON editado → `SCORMGenerator` crea el paquete
5. **Descarga** → `api/download.php` sirve el ZIP SCORM 1.2

## Convenciones de código

### PHP
- Namespace: `ScormConverter`
- Clases en PascalCase, métodos en camelCase
- Type hints en parámetros y retorno
- Constantes globales definidas en `config.php` con `define()`
- Respuestas API siempre vía `jsonResponse(bool $success, $data, string $message)`
- Errores registrados con `logError()` en `logs/error.log`
- Archivos temporales se limpian automáticamente tras 1 hora

### JavaScript
- ES6+ (const/let, arrow functions, template literals, async/await)
- Sin frameworks ni bundlers
- Lógica centralizada en `assets/js/app.js`
- Comunicación con API vía `fetch()`

### CSS
- Vanilla, sin preprocesadores
- Variables CSS para colores corporativos (definidos también en `config.php`)

### Colores corporativos
- Primary: `#143554` (azul oscuro)
- Secondary: `#1a4a6e` (azul medio)
- Accent: `#F05726` (naranja)
- Success: `#22c55e` (verde)

## Reglas para Claude Code

### General
- Idioma del código: inglés para nombres de variables/funciones/clases, español para comentarios, mensajes al usuario y contenido educativo
- No introducir dependencias externas (Composer, npm) salvo que sea estrictamente necesario y se apruebe
- Mantener la app como SPA servida desde `index.php`
- Todo archivo nuevo PHP debe incluir `require_once __DIR__ . '/../config.php'` (o ruta relativa correcta)
- No romper la compatibilidad con PHP 7.4

### API
- Los endpoints siempre responden JSON con la estructura `{success, data, message, timestamp}`
- Validar siempre: método HTTP, presencia de archivos/datos, extensiones, tamaño
- Capturar excepciones y devolver errores descriptivos

### SCORM
- Estándar objetivo: SCORM 1.2 (no 2004)
- El manifest debe ser `imsmanifest.xml` válido
- Cada unidad didáctica es un SCO independiente
- Los HTML generados deben ser autocontenidos (CSS y JS embebidos o relativos al ZIP)

### IA / Prompts
- Los prompts están centralizados en `config.php` como constantes (`PROMPT_ANALYZE`, `PROMPT_QUESTIONS`)
- La respuesta de la IA siempre debe ser JSON puro (sin markdown, sin backticks)
- Si la IA falla, la app debe funcionar con análisis básico (fallback graceful)
- Modelo actual: `claude-sonnet-4-20250514`, configurable en `CLAUDE_MODEL`

### Seguridad
- Nunca exponer la API key en el frontend
- Sanitizar todos los inputs del usuario
- Los archivos subidos se almacenan con ID único (no nombre original)
- Las carpetas `uploads/`, `temp/`, `logs/` están protegidas con `.htaccess`

## Comandos útiles

```bash
# Iniciar servidor local de desarrollo
php -S localhost:8000

# Verificar extensiones PHP necesarias
php -m | grep -E "zip|curl|dom|json|mbstring"

# Ver logs de errores
tail -f logs/error.log

# Limpiar archivos temporales manualmente
find uploads/ temp/ -type f -mmin +60 -delete
```

## Tareas pendientes / Mejoras futuras

- [ ] Soporte para SCORM 2004
- [ ] Soporte para importar PDF además de .docx
- [ ] Panel de administración para gestionar conversiones
- [ ] Tests unitarios (PHPUnit)
- [ ] Dockerización del entorno
- [ ] Mejora del análisis sin IA (NLP básico con patrones más avanzados)
- [ ] Exportar también a xAPI / cmi5
- [ ] Caché de respuestas de IA para documentos repetidos
