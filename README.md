# Conversor Word a SCORM con IA

Aplicación PHP para convertir documentos Word (.docx) en paquetes SCORM 1.2 con diseño instruccional profesional, utilizando inteligencia artificial (Claude API) para generar contenido educativo.

## 📋 Requisitos

- PHP 7.4 o superior
- Extensiones PHP:
  - `zip` (para crear paquetes SCORM)
  - `curl` (para API de Claude)
  - `dom` (para procesar XML)
  - `json`
  - `mbstring`
- Servidor web (Apache/Nginx)
- API Key de Anthropic (Claude) - opcional pero recomendado

## 🚀 Instalación

### 1. Subir archivos al servidor

Sube todos los archivos de la carpeta `scorm_converter` a tu hosting:

```
public_html/
└── scorm_converter/
    ├── api/
    │   ├── analyze.php
    │   ├── generate.php
    │   └── download.php
    ├── assets/
    │   ├── css/
    │   │   └── styles.css
    │   └── js/
    │       └── app.js
    ├── includes/
    │   ├── WordProcessor.php
    │   ├── AIProcessor.php
    │   └── SCORMGenerator.php
    ├── uploads/
    ├── temp/
    ├── logs/
    ├── config.php
    ├── index.php
    └── .htaccess
```

### 2. Configurar permisos

Asegúrate de que las siguientes carpetas tengan permisos de escritura (755 o 775):

```bash
chmod 755 uploads/
chmod 755 temp/
chmod 755 logs/
```

### 3. Configurar API de Claude (opcional pero recomendado)

Edita el archivo `config.php` y añade tu API key de Anthropic:

```php
define('CLAUDE_API_KEY', 'tu-api-key-aqui');
```

Obtén tu API key en: https://console.anthropic.com/

### 4. Configurar PHP (si es necesario)

Asegúrate de que tu servidor PHP tiene los siguientes valores adecuados en `php.ini`:

```ini
upload_max_filesize = 50M
post_max_size = 50M
max_execution_time = 300
memory_limit = 256M
```

## 🎯 Uso

1. Accede a la aplicación en tu navegador: `https://tudominio.com/scorm_converter/`

2. **Paso 1**: Arrastra o selecciona tu documento Word (.docx)

3. **Paso 2**: Revisa y ajusta la configuración del módulo:
   - Código del módulo
   - Título
   - Duración en horas
   - Empresa/Copyright

4. **Paso 3**: Revisa el contenido generado:
   - Flashcards (tarjetas de estudio)
   - Preguntas de autoevaluación
   - Secciones de contenido

5. **Paso 4**: Descarga el paquete SCORM (.zip)

6. Importa el archivo ZIP en Moodle u otro LMS compatible con SCORM 1.2

## 📄 Estructura del documento Word

Para mejores resultados, el documento Word debe tener:

```
MÓDULO X: Título del módulo (Xh)

UNIDAD DIDÁCTICA 1: Título de la unidad (Xh)
[Contenido de la unidad...]

UNIDAD DIDÁCTICA 2: Título de la unidad (Xh)
[Contenido de la unidad...]
```

### Recomendaciones:
- Usa títulos claros para las secciones
- Incluye términos técnicos con sus definiciones
- Los bloques de código serán detectados automáticamente
- Mantén una estructura consistente

## 🤖 Funcionalidades de IA

Cuando la API de Claude está configurada, la aplicación:

1. **Analiza el contenido** del documento para extraer la estructura
2. **Genera flashcards** con los conceptos clave del contenido
3. **Crea preguntas de autoevaluación** relevantes y variadas
4. **Resume y estructura** el contenido de forma pedagógica
5. **Detecta código fuente** y lo formatea correctamente

Sin IA, la aplicación funciona con análisis básico basado en patrones.

## 📦 Contenido del paquete SCORM generado

```
MODULO_SCORM.zip
├── css/
│   └── estilos.css          # Estilos profesionales
├── js/
│   ├── scorm_api.js         # Comunicación SCORM 1.2
│   └── interactividad.js    # Flashcards, tabs, autoevaluación
├── scos/
│   ├── ud1_xxx.html         # Página de cada unidad
│   ├── ud2_xxx.html
│   └── ...
├── ejemplos/                 # Carpeta para archivos adicionales
└── imsmanifest.xml          # Manifest SCORM 1.2
```

## 🎨 Diseño instruccional incluido

Cada unidad generada incluye:

- **Cabecera**: Título del módulo, unidad y duración
- **Barra de progreso**: Seguimiento de lectura
- **Flashcards interactivas**: Efecto de volteo 3D
- **Contenido en pestañas**: Organización por secciones
- **Bloques de código**: Syntax highlighting
- **Autoevaluación**: Preguntas con feedback inmediato
- **Navegación**: Entre unidades y menú superior
- **Responsive**: Adaptado a móviles

## 🔒 Seguridad

- Los archivos subidos se eliminan automáticamente después de 1 hora
- Validación de tipos de archivo (solo .docx)
- Límite de tamaño de archivo (50MB)
- Sanitización de nombres de archivo
- Protección de carpetas con .htaccess

## 🐛 Solución de problemas

### Error "API Key de Claude no configurada"
- Edita `config.php` y añade tu API key válida
- La aplicación funcionará sin IA pero con análisis básico

### Error "No se pudo crear el archivo ZIP"
- Verifica que la extensión `zip` de PHP esté habilitada
- Comprueba los permisos de la carpeta `temp/`

### Error al subir archivo
- Aumenta `upload_max_filesize` y `post_max_size` en php.ini
- Verifica permisos de la carpeta `uploads/`

### El documento no se analiza correctamente
- Asegúrate de que el formato sea .docx (no .doc)
- Verifica que el documento tenga estructura clara

## 📄 Licencia

© 2025 ARELANCE S.L. - Todos los derechos reservados.

## 🤝 Soporte

Para soporte técnico, contacta con el equipo de desarrollo.
