/**
 * JavaScript del Conversor Word a SCORM
 * ARELANCE S.L. - 2025
 */

// =============================================
// ESTADO GLOBAL
// =============================================
let currentStep = 1;
let uploadedFile = null;
let analysisData = null;
let downloadData = null;

// =============================================
// INICIALIZACIÓN
// =============================================
document.addEventListener('DOMContentLoaded', function() {
    initUpload();
    initButtons();
    checkAIStatus();
});

// =============================================
// UPLOAD
// =============================================
function initUpload() {
    const uploadZone = document.getElementById('upload-zone');
    const fileInput = document.getElementById('file-input');
    const btnRemove = document.getElementById('btn-remove-file');
    
    // Click en zona de upload
    uploadZone.addEventListener('click', () => fileInput.click());
    
    // Drag & drop
    uploadZone.addEventListener('dragover', (e) => {
        e.preventDefault();
        uploadZone.classList.add('dragover');
    });
    
    uploadZone.addEventListener('dragleave', (e) => {
        e.preventDefault();
        uploadZone.classList.remove('dragover');
    });
    
    uploadZone.addEventListener('drop', (e) => {
        e.preventDefault();
        uploadZone.classList.remove('dragover');
        if (e.dataTransfer.files.length > 0) {
            handleFile(e.dataTransfer.files[0]);
        }
    });
    
    // Input file change
    fileInput.addEventListener('change', (e) => {
        if (e.target.files.length > 0) {
            handleFile(e.target.files[0]);
        }
    });
    
    // Remove file
    btnRemove.addEventListener('click', (e) => {
        e.stopPropagation();
        clearFile();
    });
}

function handleFile(file) {
    if (!file.name.toLowerCase().endsWith('.docx')) {
        alert('Por favor, selecciona un archivo .docx');
        return;
    }
    
    if (file.size > 50 * 1024 * 1024) {
        alert('El archivo excede el tamaño máximo de 50MB');
        return;
    }
    
    uploadedFile = file;
    
    document.getElementById('file-name').textContent = file.name;
    document.getElementById('file-size').textContent = formatSize(file.size);
    document.getElementById('file-preview').classList.remove('hidden');
    document.getElementById('btn-analyze').disabled = false;
}

function clearFile() {
    uploadedFile = null;
    document.getElementById('file-input').value = '';
    document.getElementById('file-preview').classList.add('hidden');
    document.getElementById('btn-analyze').disabled = true;
}

function formatSize(bytes) {
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
    return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
}

// =============================================
// BOTONES
// =============================================
function initButtons() {
    document.getElementById('btn-analyze').addEventListener('click', analyzeDocument);
    document.getElementById('btn-generate').addEventListener('click', generateSCORM);
    document.getElementById('btn-download').addEventListener('click', downloadSCORM);
}

// =============================================
// VERIFICAR ESTADO IA
// =============================================
function checkAIStatus() {
    const statusBox = document.getElementById('ai-status');
    
    // Por ahora, mostrar que la IA está disponible si se configura
    fetch('api/analyze.php', {
        method: 'POST',
        body: new FormData() // Empty request to check
    })
    .then(response => response.json())
    .then(data => {
        // Si devuelve error de archivo, la API funciona
        statusBox.className = 'info-box success';
        statusBox.innerHTML = `
            <h4>🤖 IA habilitada</h4>
            <p>Claude API está configurada. El análisis incluirá generación inteligente de contenido.</p>
        `;
    })
    .catch(error => {
        statusBox.className = 'info-box warning';
        statusBox.innerHTML = `
            <h4>⚠️ Modo básico</h4>
            <p>La API de Claude no está configurada. Se usará análisis básico sin IA.</p>
        `;
    });
}

// =============================================
// ANÁLISIS DEL DOCUMENTO
// =============================================
async function analyzeDocument() {
    if (!uploadedFile) return;
    
    showLoading('Analizando documento...', 'La IA está procesando tu documento Word para extraer la estructura y generar contenido.');
    
    const formData = new FormData();
    formData.append('document', uploadedFile);
    
    try {
        updateProgress(10);
        
        const response = await fetch('api/analyze.php', {
            method: 'POST',
            body: formData
        });
        
        updateProgress(50);
        
        const result = await response.json();
        
        if (!result.success) {
            throw new Error(result.message || 'Error al analizar el documento');
        }
        
        updateProgress(80);
        
        analysisData = result.data;
        
        // Llenar formulario de configuración
        document.getElementById('cfg-code').value = analysisData.modulo.codigo || 'MOD_01';
        document.getElementById('cfg-title').value = analysisData.modulo.titulo || '';
        document.getElementById('cfg-hours').value = analysisData.modulo.duracion_total || 50;
        
        // Renderizar unidades
        renderUnitsList();
        
        // Mostrar estadísticas
        renderSummaryStats();
        
        updateProgress(100);
        
        setTimeout(() => {
            hideLoading();
            goToStep(2);
        }, 500);
        
    } catch (error) {
        hideLoading();
        alert('Error: ' + error.message);
    }
}

// =============================================
// RENDERIZADO
// =============================================
function renderUnitsList() {
    const container = document.getElementById('units-list');
    let html = '';
    
    analysisData.unidades.forEach((unit, index) => {
        const flashcards = unit.conceptos_clave?.length || 0;
        const questions = unit.preguntas?.length || 0;
        const sections = unit.secciones?.length || 0;
        
        html += `
            <div class="unit-item">
                <div class="unit-header">
                    <div class="unit-num">UD${unit.numero}</div>
                    <div class="unit-title">${escapeHtml(unit.titulo)}</div>
                    <div class="unit-hours">${unit.duracion}h</div>
                </div>
                <div class="unit-stats">
                    <span class="unit-stat">🎴 ${flashcards} flashcards</span>
                    <span class="unit-stat">📑 ${sections} secciones</span>
                    <span class="unit-stat">❓ ${questions} preguntas</span>
                </div>
            </div>
        `;
    });
    
    container.innerHTML = html;
}

function renderSummaryStats() {
    let totalFlashcards = 0;
    let totalQuestions = 0;
    let totalSections = 0;
    let totalHours = 0;
    
    analysisData.unidades.forEach(unit => {
        totalFlashcards += unit.conceptos_clave?.length || 0;
        totalQuestions += unit.preguntas?.length || 0;
        totalSections += unit.secciones?.length || 0;
        totalHours += unit.duracion || 0;
    });
    
    const html = `
        <div class="summary-item">
            <div class="icon">📚</div>
            <div class="value">${analysisData.unidades.length}</div>
            <div class="label">Unidades</div>
        </div>
        <div class="summary-item">
            <div class="icon">⏱️</div>
            <div class="value">${totalHours}h</div>
            <div class="label">Duración</div>
        </div>
        <div class="summary-item">
            <div class="icon">🎴</div>
            <div class="value">${totalFlashcards}</div>
            <div class="label">Flashcards</div>
        </div>
        <div class="summary-item">
            <div class="icon">❓</div>
            <div class="value">${totalQuestions}</div>
            <div class="label">Preguntas</div>
        </div>
    `;
    
    document.getElementById('summary-stats').innerHTML = html;
}

function renderUnitTabs() {
    const header = document.getElementById('tabs-header');
    const content = document.getElementById('tabs-content');
    
    let headerHtml = '';
    let contentHtml = '';
    
    analysisData.unidades.forEach((unit, index) => {
        headerHtml += `
            <button class="tab-btn ${index === 0 ? 'active' : ''}" onclick="switchTab(${index})">
                UD${unit.numero}
            </button>
        `;
        
        contentHtml += `
            <div class="tab-panel ${index === 0 ? 'active' : ''}" id="tab-panel-${index}">
                <div class="panel-section">
                    <h4>📌 Objetivos</h4>
                    <ul>
                        ${(unit.objetivos || []).map(obj => `<li>${escapeHtml(obj)}</li>`).join('')}
                    </ul>
                </div>
                
                <div class="panel-section">
                    <h4>🎴 Flashcards (${unit.conceptos_clave?.length || 0})</h4>
                    <div class="flashcard-grid">
                        ${(unit.conceptos_clave || []).map(fc => `
                            <div class="flashcard-item">
                                <strong>${escapeHtml(fc.termino)}</strong>
                                <span>${escapeHtml(fc.definicion)}</span>
                            </div>
                        `).join('')}
                    </div>
                </div>
                
                <div class="panel-section">
                    <h4>❓ Preguntas de autoevaluación (${unit.preguntas?.length || 0})</h4>
                    ${(unit.preguntas || []).map((q, qi) => `
                        <div class="question-item">
                            <strong>${qi + 1}. ${escapeHtml(q.pregunta)}</strong>
                            <ul>
                                ${(q.opciones || []).map((opt, oi) => `
                                    <li class="${oi === q.correcta ? 'correct' : ''}">${escapeHtml(opt)}</li>
                                `).join('')}
                            </ul>
                        </div>
                    `).join('')}
                </div>
            </div>
        `;
    });
    
    header.innerHTML = headerHtml;
    content.innerHTML = contentHtml;
}

function switchTab(index) {
    document.querySelectorAll('.tab-btn').forEach((btn, i) => {
        btn.classList.toggle('active', i === index);
    });
    document.querySelectorAll('.tab-panel').forEach((panel, i) => {
        panel.classList.toggle('active', i === index);
    });
}

// =============================================
// GENERACIÓN SCORM
// =============================================
async function generateSCORM() {
    showLoading('Generando SCORM...', 'Creando el paquete con todos los archivos HTML, CSS, JavaScript y el manifest.');
    
    // Actualizar configuración desde el formulario
    analysisData.modulo.codigo = document.getElementById('cfg-code').value;
    analysisData.modulo.titulo = document.getElementById('cfg-title').value;
    analysisData.modulo.duracion_total = parseInt(document.getElementById('cfg-hours').value) || 50;
    analysisData.modulo.empresa = document.getElementById('cfg-company').value;
    
    try {
        updateProgress(20);
        
        const response = await fetch('api/generate.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(analysisData)
        });
        
        updateProgress(70);
        
        const result = await response.json();
        
        if (!result.success) {
            throw new Error(result.message || 'Error al generar el SCORM');
        }
        
        downloadData = result.data;
        
        updateProgress(100);
        
        // Renderizar estadísticas finales
        renderFinalStats();
        
        setTimeout(() => {
            hideLoading();
            goToStep(4);
        }, 500);
        
    } catch (error) {
        hideLoading();
        alert('Error: ' + error.message);
    }
}

function renderFinalStats() {
    const stats = downloadData.stats;
    
    const html = `
        <div class="summary-item">
            <div class="icon">📄</div>
            <div class="value">${stats.units}</div>
            <div class="label">HTML generados</div>
        </div>
        <div class="summary-item">
            <div class="icon">🎴</div>
            <div class="value">${stats.flashcards}</div>
            <div class="label">Flashcards</div>
        </div>
        <div class="summary-item">
            <div class="icon">❓</div>
            <div class="value">${stats.questions}</div>
            <div class="label">Preguntas</div>
        </div>
        <div class="summary-item">
            <div class="icon">📦</div>
            <div class="value">${formatSize(downloadData.size)}</div>
            <div class="label">Tamaño</div>
        </div>
    `;
    
    document.getElementById('final-stats').innerHTML = html;
}

// =============================================
// DESCARGA
// =============================================
function downloadSCORM() {
    if (!downloadData) return;
    
    window.location.href = `api/download.php?id=${downloadData.download_id}&filename=${encodeURIComponent(downloadData.filename)}`;
}

// =============================================
// NAVEGACIÓN
// =============================================
function goToStep(step) {
    // Ocultar todos los pasos
    document.querySelectorAll('.step-content').forEach(el => {
        el.classList.add('hidden');
    });
    
    // Mostrar paso actual
    document.getElementById(`step-${step}`).classList.remove('hidden');
    
    // Actualizar indicadores
    document.querySelectorAll('.step').forEach((el, i) => {
        const stepNum = i + 1;
        el.classList.remove('active', 'completed');
        
        if (stepNum < step) {
            el.classList.add('completed');
            el.querySelector('.step-circle').textContent = '✓';
        } else if (stepNum === step) {
            el.classList.add('active');
            el.querySelector('.step-circle').textContent = stepNum;
        } else {
            el.querySelector('.step-circle').textContent = stepNum;
        }
    });
    
    // Actualizar líneas
    document.querySelectorAll('.step-line').forEach((line, i) => {
        line.classList.toggle('completed', i < step - 1);
    });
    
    currentStep = step;
    
    // Renderizar contenido específico del paso
    if (step === 3) {
        renderUnitTabs();
    }
}

function resetApp() {
    currentStep = 1;
    uploadedFile = null;
    analysisData = null;
    downloadData = null;
    
    clearFile();
    goToStep(1);
}

// =============================================
// LOADING
// =============================================
function showLoading(title, message) {
    document.getElementById('loading-title').textContent = title;
    document.getElementById('loading-message').textContent = message;
    document.getElementById('loading-progress').style.width = '0%';
    document.getElementById('loading-overlay').classList.remove('hidden');
}

function hideLoading() {
    document.getElementById('loading-overlay').classList.add('hidden');
}

function updateProgress(percent) {
    document.getElementById('loading-progress').style.width = percent + '%';
}

// =============================================
// UTILIDADES
// =============================================
function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
