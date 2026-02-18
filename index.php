<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Conversor Word a SCORM - ARELANCE</title>
    <link rel="stylesheet" href="assets/css/styles.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <div class="app-container">
        <!-- Header -->
        <header class="app-header">
            <div class="header-content">
                <div class="logo">
                    <span class="logo-icon">📄➡️📦</span>
                    <div class="logo-text">
                        <h1>Conversor Word a SCORM</h1>
                        <span class="badge">Con IA</span>
                    </div>
                </div>
                <p class="tagline">Transforma documentos Word en paquetes SCORM interactivos con inteligencia artificial</p>
            </div>
        </header>
        
        <!-- Progress Steps -->
        <div class="steps-container">
            <div class="step active" data-step="1">
                <div class="step-circle">1</div>
                <span class="step-label">Cargar</span>
            </div>
            <div class="step-line"></div>
            <div class="step" data-step="2">
                <div class="step-circle">2</div>
                <span class="step-label">Configurar</span>
            </div>
            <div class="step-line"></div>
            <div class="step" data-step="3">
                <div class="step-circle">3</div>
                <span class="step-label">Revisar</span>
            </div>
            <div class="step-line"></div>
            <div class="step" data-step="4">
                <div class="step-circle">4</div>
                <span class="step-label">Descargar</span>
            </div>
        </div>
        
        <!-- Main Content -->
        <main class="main-content">
            
            <!-- Step 1: Upload -->
            <section class="card step-content" id="step-1">
                <h2 class="card-title">
                    <span class="icon">📤</span>
                    Paso 1: Cargar documento Word
                </h2>
                
                <div class="upload-zone" id="upload-zone">
                    <div class="upload-icon">📘</div>
                    <h3>Arrastra tu archivo Word aquí</h3>
                    <p>o haz clic para seleccionar</p>
                    <span class="upload-hint">Soporta archivos .docx (máx. 50MB)</span>
                    <input type="file" id="file-input" accept=".docx" hidden>
                </div>
                
                <div class="file-preview hidden" id="file-preview">
                    <div class="file-icon">✅</div>
                    <div class="file-info">
                        <span class="file-name" id="file-name"></span>
                        <span class="file-size" id="file-size"></span>
                    </div>
                    <button class="btn btn-danger btn-sm" id="btn-remove-file">✕</button>
                </div>
                
                <div class="info-box info">
                    <h4>💡 Estructura recomendada del documento</h4>
                    <ul>
                        <li>Título del módulo con duración (ej: "Módulo 4: Proyecto final (50h)")</li>
                        <li>Unidades didácticas marcadas como "UNIDAD DIDÁCTICA X"</li>
                        <li>Secciones claras con títulos</li>
                        <li>Bloques de código para ejemplos técnicos</li>
                    </ul>
                </div>
                
                <div class="info-box warning" id="ai-status">
                    <h4>🤖 Estado de la IA</h4>
                    <p>Verificando conexión con Claude API...</p>
                </div>
                
                <button class="btn btn-primary btn-lg btn-block" id="btn-analyze" disabled>
                    <span class="btn-icon">🔍</span>
                    Analizar documento con IA
                </button>
            </section>
            
            <!-- Step 2: Configure -->
            <section class="card step-content hidden" id="step-2">
                <h2 class="card-title">
                    <span class="icon">⚙️</span>
                    Paso 2: Configurar módulo
                </h2>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="cfg-code">Código del módulo</label>
                        <input type="text" id="cfg-code" placeholder="Ej: PROY_M4">
                    </div>
                    <div class="form-group">
                        <label for="cfg-hours">Duración total (horas)</label>
                        <input type="number" id="cfg-hours" min="1" value="50">
                    </div>
                    <div class="form-group full">
                        <label for="cfg-title">Título del módulo</label>
                        <input type="text" id="cfg-title" placeholder="Ej: Proyecto final integrador">
                    </div>
                    <div class="form-group full">
                        <label for="cfg-company">Empresa / Copyright</label>
                        <input type="text" id="cfg-company" value="ARELANCE S.L.">
                    </div>
                </div>
                
                <hr class="divider">
                
                <h3 class="section-title">📚 Unidades detectadas</h3>
                <div class="units-list" id="units-list"></div>
                
                <div class="summary-grid" id="summary-stats"></div>
                
                <div class="btn-group">
                    <button class="btn btn-secondary" onclick="goToStep(1)">← Volver</button>
                    <button class="btn btn-primary btn-lg" onclick="goToStep(3)">Continuar →</button>
                </div>
            </section>
            
            <!-- Step 3: Review -->
            <section class="card step-content hidden" id="step-3">
                <h2 class="card-title">
                    <span class="icon">👁️</span>
                    Paso 3: Revisar contenido
                </h2>
                
                <div class="tabs" id="units-tabs">
                    <div class="tabs-header" id="tabs-header"></div>
                    <div class="tabs-content" id="tabs-content"></div>
                </div>
                
                <div class="btn-group">
                    <button class="btn btn-secondary" onclick="goToStep(2)">← Volver</button>
                    <button class="btn btn-success btn-lg" id="btn-generate">
                        <span class="btn-icon">🚀</span>
                        Generar paquete SCORM
                    </button>
                </div>
            </section>
            
            <!-- Step 4: Download -->
            <section class="card step-content hidden" id="step-4">
                <h2 class="card-title">
                    <span class="icon">✅</span>
                    Paso 4: Descargar
                </h2>
                
                <div class="success-box">
                    <div class="success-icon">🎉</div>
                    <h3>¡Paquete SCORM generado correctamente!</h3>
                    <p>Tu contenido formativo está listo para importar en Moodle u otro LMS compatible con SCORM 1.2.</p>
                </div>
                
                <div class="summary-grid" id="final-stats"></div>
                
                <button class="btn btn-success btn-lg btn-block" id="btn-download">
                    <span class="btn-icon">📥</span>
                    Descargar SCORM
                </button>
                
                <button class="btn btn-secondary btn-block" onclick="resetApp()" style="margin-top: 1rem;">
                    <span class="btn-icon">🔄</span>
                    Convertir otro documento
                </button>
            </section>
            
        </main>
        
        <!-- Footer -->
        <footer class="app-footer">
            <p>© 2025 ARELANCE S.L. - Conversor Word a SCORM con IA</p>
        </footer>
    </div>
    
    <!-- Loading Overlay -->
    <div class="loading-overlay hidden" id="loading-overlay">
        <div class="loading-content">
            <div class="spinner"></div>
            <h3 id="loading-title">Procesando...</h3>
            <p id="loading-message">Por favor, espera mientras procesamos tu documento.</p>
            <div class="progress-bar">
                <div class="progress-fill" id="loading-progress"></div>
            </div>
        </div>
    </div>
    
    <script src="assets/js/app.js"></script>
</body>
</html>
