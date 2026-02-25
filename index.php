<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SCORM Generator — ARELANCE</title>
    <link rel="icon" type="image/png" href="assets/img/arelance-icono.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/styles.css">
</head>
<body>
    <div class="app-container">

        <!-- Header -->
        <header class="app-header">
            <img src="assets/img/logo.png" alt="Logo" class="app-logo">
            <div class="brand">
                <span class="brand-word"><span class="brand-letter">S</span>CORM</span>
                <span class="brand-word"><span class="brand-letter">G</span>enerator</span>
            </div>
        </header>

        <!-- Progress Steps -->
        <nav class="steps-container">
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
        </nav>
        
        <!-- Main Content -->
        <main class="main-content">
            
            <!-- Step 1: Upload -->
            <section class="card step-content" id="step-1">
                <div class="card-center">
                    <h2 class="card-title">Sube tu documento Word</h2>
                    <p class="card-desc">Genera un paquete SCORM 1.2 interactivo a partir de la estructura de tu documento.</p>
                    
                    <div class="upload-zone" id="upload-zone">
                        <div class="upload-icon">
                            <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                        </div>
                        <p class="upload-text">Arrastra tu archivo aqui o <span class="upload-link">selecciona</span></p>
                        <span class="upload-hint">.docx &mdash; max. 50 MB</span>
                        <input type="file" id="file-input" accept=".docx" hidden>
                    </div>
                    
                    <div class="file-preview hidden" id="file-preview">
                        <div class="file-icon">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                        </div>
                        <div class="file-info">
                            <span class="file-name" id="file-name"></span>
                            <span class="file-size" id="file-size"></span>
                        </div>
                        <button class="btn-icon" id="btn-remove-file" title="Eliminar">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                        </button>
                    </div>

                    <!-- Hidden AI check, no visible banner -->
                    <div id="ai-status" style="display:none"></div>
                    
                    <button class="btn btn-primary btn-lg btn-full" id="btn-analyze" disabled>
                        Analizar y continuar
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
                    </button>
                </div>
            </section>
            
            <!-- Step 2: Configure -->
            <section class="card step-content hidden" id="step-2">
                <h2 class="card-title">Configuracion del modulo</h2>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="cfg-code">Codigo</label>
                        <input type="text" id="cfg-code" placeholder="MOD_01">
                    </div>
                    <div class="form-group">
                        <label for="cfg-hours">Horas</label>
                        <input type="number" id="cfg-hours" min="1" value="50">
                    </div>
                    <div class="form-group full">
                        <label for="cfg-title">Titulo del modulo</label>
                        <input type="text" id="cfg-title" placeholder="Ej: Proyecto final integrador">
                    </div>
                    <div class="form-group full">
                        <label for="cfg-company">Empresa</label>
                        <input type="text" id="cfg-company" value="ARELANCE S.L.">
                    </div>
                </div>
                
                <hr class="divider">
                
                <h3 class="section-title">Unidades detectadas</h3>
                <div class="units-list" id="units-list"></div>
                
                <div class="summary-grid" id="summary-stats"></div>
                
                <hr class="divider">
                
                <h3 class="section-title">Plantilla de estilo</h3>
                <div class="templates-grid" id="templates-grid">
                    <div class="template-loading">Cargando plantillas...</div>
                </div>
                <div class="template-actions">
                    <label class="btn btn-ghost btn-sm" for="template-import-input">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                        Importar plantilla
                    </label>
                    <input type="file" id="template-import-input" accept=".zip" hidden>
                </div>
                
                <div class="btn-group">
                    <button class="btn btn-ghost" onclick="goToStep(1)">Volver</button>
                    <button class="btn btn-primary btn-lg" onclick="goToStep(3)">
                        Continuar
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
                    </button>
                </div>
            </section>
            
            <!-- Step 3: Review -->
            <section class="card step-content hidden" id="step-3">
                <h2 class="card-title">Revisar estructura</h2>
                <p class="card-desc">Esta es la estructura generada por la IA. Revisa que el contenido esté bien organizado antes de generar el SCORM.</p>
                
                <div class="tabs" id="units-tabs">
                    <div class="tabs-header" id="tabs-header"></div>
                    <div class="tabs-content" id="tabs-content"></div>
                </div>
                
                <div class="btn-group">
                    <button class="btn btn-ghost" onclick="goToStep(2)">Volver</button>
                    <button class="btn btn-accent btn-lg" id="btn-generate">
                        Generar SCORM
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                    </button>
                </div>
            </section>
            
            <!-- Step 4: Download -->
            <section class="card step-content hidden" id="step-4">
                <div class="card-center">
                    <div class="success-check">
                        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                    </div>
                    <h2 class="card-title">Paquete generado</h2>
                    <p class="card-desc">Listo para importar en Moodle u otro LMS compatible con SCORM 1.2</p>
                    
                    <div class="summary-grid" id="final-stats"></div>
                    
                    <button class="btn btn-accent btn-lg btn-full" id="btn-download">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                        Descargar SCORM
                    </button>
                    
                    <button class="btn btn-ghost btn-full mt-3" onclick="resetApp()">Convertir otro documento</button>
                </div>
            </section>
            
        </main>
        
        <!-- Footer -->
        <footer class="app-footer">
            <p>ARELANCE &middot; SCORM Generator</p>
        </footer>
    </div>
    
    <!-- Loading Overlay -->
    <div class="loading-overlay hidden" id="loading-overlay">
        <div class="loading-content">
            <div class="spinner"></div>
            <h3 id="loading-title">Procesando...</h3>
            <p id="loading-message">Analizando estructura del documento</p>
            <div class="progress-bar">
                <div class="progress-fill" id="loading-progress"></div>
            </div>
        </div>
    </div>
    
    <script src="assets/js/app.js"></script>
</body>
</html>
