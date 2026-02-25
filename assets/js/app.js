/**
 * SCORM Generator — App JS
 * ARELANCE S.L. 2025
 */

let currentStep = 1;
let uploadedFile = null;
let analysisData = null;
let downloadData = null;
let selectedTemplate = 'arelance-corporate';
let templatesLoaded = false;

// ── Init ──
document.addEventListener('DOMContentLoaded', function() {
    initUpload();
    initButtons();
    checkAIStatus();
    const importInput = document.getElementById('template-import-input');
    if (importInput) {
        importInput.addEventListener('change', importTemplate);
    }
});

// ── Upload ──
function initUpload() {
    const zone = document.getElementById('upload-zone');
    const input = document.getElementById('file-input');
    const remove = document.getElementById('btn-remove-file');

    zone.addEventListener('click', () => input.click());
    zone.addEventListener('dragover', e => { e.preventDefault(); zone.classList.add('dragover'); });
    zone.addEventListener('dragleave', e => { e.preventDefault(); zone.classList.remove('dragover'); });
    zone.addEventListener('drop', e => {
        e.preventDefault(); zone.classList.remove('dragover');
        if (e.dataTransfer.files.length) handleFile(e.dataTransfer.files[0]);
    });
    input.addEventListener('change', e => { if (e.target.files.length) handleFile(e.target.files[0]); });
    remove.addEventListener('click', e => { e.stopPropagation(); clearFile(); });
}

function handleFile(file) {
    if (!file.name.toLowerCase().endsWith('.docx')) { alert('Solo archivos .docx'); return; }
    if (file.size > 50*1024*1024) { alert('Archivo demasiado grande (max 50MB)'); return; }
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
    if (bytes < 1048576) return (bytes/1024).toFixed(1) + ' KB';
    return (bytes/1048576).toFixed(1) + ' MB';
}

// ── Buttons ──
function initButtons() {
    document.getElementById('btn-analyze').addEventListener('click', analyzeDocument);
    document.getElementById('btn-generate').addEventListener('click', generateSCORM);
    document.getElementById('btn-download').addEventListener('click', downloadSCORM);
}

// ── AI Status ──
function checkAIStatus() {
    // Silent check, no UI feedback needed
    fetch('api/analyze.php', { method: 'POST', body: new FormData() }).catch(() => {});
}

// ── Analyze ──
async function analyzeDocument() {
    if (!uploadedFile) return;
    showLoading('Analizando documento...', 'Procesando estructura y generando contenido');
    const fd = new FormData();
    fd.append('document', uploadedFile);
    try {
        updateProgress(10);
        const resp = await fetch('api/analyze.php', { method: 'POST', body: fd });
        updateProgress(50);
        const result = await resp.json();
        if (!result.success) throw new Error(result.message || 'Error en el analisis');
        updateProgress(80);
        analysisData = result.data;
        document.getElementById('cfg-code').value = analysisData.modulo.codigo || 'MOD_01';
        document.getElementById('cfg-title').value = analysisData.modulo.titulo || '';
        document.getElementById('cfg-hours').value = analysisData.modulo.duracion_total || 50;
        // Sync cfg-hours changes to unit durations
        document.getElementById('cfg-hours').addEventListener('change', function() {
            const total = parseInt(this.value) || 50;
            const numUnits = analysisData.unidades.length;
            const perUnit = Math.max(1, Math.round(total / numUnits));
            analysisData.unidades.forEach((u, i) => { u.duracion = perUnit; });
            analysisData.modulo.duracion_total = total;
            renderUnitsList();
            renderSummaryStats();
        });
        renderUnitsList();
        renderSummaryStats();
        updateProgress(100);
        setTimeout(() => { hideLoading(); goToStep(2); }, 500);
    } catch (err) { hideLoading(); alert('Error: ' + err.message); }
}

// ── Render ──
function renderUnitsList() {
    const c = document.getElementById('units-list');
    let h = '';
    analysisData.unidades.forEach((u, i) => {
        const fc = u.conceptos_clave?.length || 0;
        const q = u.preguntas?.length || 0;
        const s = u.secciones?.length || 0;
        h += '<div class="unit-item">'
            + '<div class="unit-num">UD' + u.numero + '</div>'
            + '<div class="unit-title"><input type="text" class="unit-title-input" data-unit-idx="' + i + '" value="' + esc(u.titulo).replace(/"/g, '&quot;') + '" /></div>'
            + '<div class="unit-stats"><span class="unit-stat">' + s + ' secc.</span><span class="unit-stat">' + fc + ' flash.</span><span class="unit-stat">' + q + ' preg.</span></div>'
            + '<div class="unit-hours"><input type="number" class="unit-hours-input" data-unit-idx="' + i + '" value="' + (u.duracion || 6) + '" min="1" max="200" step="1" />h</div>'
            + '</div>';
    });
    c.innerHTML = h;
    // Sync edits back to data
    c.querySelectorAll('.unit-title-input').forEach(inp => {
        inp.addEventListener('change', e => {
            const idx = parseInt(e.target.dataset.unitIdx);
            analysisData.unidades[idx].titulo = e.target.value;
        });
    });
    c.querySelectorAll('.unit-hours-input').forEach(inp => {
        inp.addEventListener('change', e => {
            const idx = parseInt(e.target.dataset.unitIdx);
            analysisData.unidades[idx].duracion = parseFloat(e.target.value) || 6;
            // Recalcular total
            let total = 0;
            analysisData.unidades.forEach(u => total += (u.duracion || 0));
            document.getElementById('cfg-hours').value = total;
            renderSummaryStats();
        });
    });
}

function renderSummaryStats() {
    let fc=0, q=0, s=0, hrs=0;
    analysisData.unidades.forEach(u => {
        fc += u.conceptos_clave?.length || 0;
        q += u.preguntas?.length || 0;
        s += u.secciones?.length || 0;
        hrs += u.duracion || 0;
    });
    document.getElementById('summary-stats').innerHTML =
        stat(analysisData.unidades.length, 'Unidades') +
        stat(hrs + 'h', 'Duracion') +
        stat(fc, 'Flashcards') +
        stat(q, 'Preguntas');
}

function stat(val, label) {
    return '<div class="summary-item"><div class="value">' + val + '</div><div class="label">' + label + '</div></div>';
}

function renderUnitTabs() {
    const hdr = document.getElementById('tabs-header');
    const cnt = document.getElementById('tabs-content');
    let hh = '', ch = '';
    analysisData.unidades.forEach((u, i) => {
        const act = i === 0 ? ' active' : '';
        hh += '<button class="tab-btn' + act + '" onclick="switchTab(' + i + ')">UD' + u.numero + '</button>';
        
        // Build structured content preview
        let sectionsHtml = '';
        (u.secciones || []).forEach((sec, si) => {
            sectionsHtml += '<div class="preview-section">';
            sectionsHtml += '<h4 class="preview-section-title">' + (si+1) + '. ' + esc(sec.titulo) + '</h4>';
            
            // Render contenido_estructurado blocks
            const blocks = sec.contenido_estructurado || sec.bloques || [];
            if (blocks.length > 0) {
                blocks.forEach(b => {
                    sectionsHtml += renderBlockPreview(b);
                });
            } else if (sec.contenido) {
                // Fallback: plain text content
                sectionsHtml += '<div class="preview-block preview-parrafo">' + esc(sec.contenido).substring(0, 500) + (sec.contenido.length > 500 ? '...' : '') + '</div>';
            }
            sectionsHtml += '</div>';
        });
        
        ch += '<div class="tab-panel' + act + '" id="tab-panel-' + i + '">'
            + '<div class="panel-section"><h4>Resumen</h4><p>' + esc(u.resumen || '') + '</p></div>'
            + '<div class="panel-section"><h4>Objetivos</h4><ul>' + (u.objetivos || []).map(o => renderObjetivo(o)).join('') + '</ul></div>'
            + '<div class="panel-section"><h4>Estructura del contenido (' + (u.secciones?.length||0) + ' secciones)</h4>' + sectionsHtml + '</div>'
            + '<div class="panel-section"><h4>Conceptos clave (' + (u.conceptos_clave?.length||0) + ')</h4><div class="flashcard-grid">'
            + (u.conceptos_clave || []).map(f => '<div class="flashcard-item"><strong>' + esc(f.termino) + '</strong><span>' + esc(f.definicion) + '</span></div>').join('') + '</div></div>'
            + '<div class="panel-section"><h4>Preguntas (' + (u.preguntas?.length||0) + ')</h4>'
            + (u.preguntas || []).map((p,qi) => '<div class="question-item"><strong>' + (qi+1) + '. ' + esc(p.pregunta) + '</strong><ul>'
                + (p.opciones||[]).map((o,oi) => '<li class="' + (oi===p.correcta?'correct':'') + '">' + esc(o) + '</li>').join('') + '</ul></div>').join('') + '</div></div>';
    });
    hdr.innerHTML = hh;
    cnt.innerHTML = ch;
}

function renderObjetivo(obj) {
    const bloomColors = {
        'Recordar': '#6b7280', 'Comprender': '#3b82f6', 'Aplicar': '#10b981',
        'Analizar': '#f59e0b', 'Evaluar': '#ef4444', 'Crear': '#8b5cf6'
    };
    const m = obj.match(/^\[(Recordar|Comprender|Aplicar|Analizar|Evaluar|Crear)\]\s*(.+)$/);
    if (m) {
        const color = bloomColors[m[1]] || '#143554';
        return `<li><span style="background:${color};color:#fff;padding:1px 7px;border-radius:3px;font-size:0.72em;margin-right:6px;font-weight:700;letter-spacing:.03em;vertical-align:middle;">${esc(m[1])}</span>${esc(m[2])}</li>`;
    }
    return `<li>${esc(obj)}</li>`;
}

function renderBlockPreview(b) {
    const tipo = b.tipo || 'parrafo';
    const text = b.texto || b.contenido || '';
    switch(tipo) {
        case 'parrafo':
            return '<div class="preview-block preview-parrafo">' + esc(text) + '</div>';
        case 'definicion':
            return '<div class="preview-block preview-definicion"><strong>' + esc(b.termino || '') + ':</strong> ' + esc(text) + '</div>';
        case 'lista':
            const tit = b.titulo ? '<p class="preview-list-title">' + esc(b.titulo) + '</p>' : '';
            return '<div class="preview-block preview-lista">' + tit + '<ul>' + (b.items||[]).map(it => '<li>' + esc(it) + '</li>').join('') + '</ul></div>';
        case 'tabla':
            if (!b.filas || !b.filas.length) return '';
            let th = '<tr>' + b.filas[0].map(c => '<th>' + esc(c) + '</th>').join('') + '</tr>';
            let tb = b.filas.slice(1).map(r => '<tr>' + r.map(c => '<td>' + esc(c) + '</td>').join('') + '</tr>').join('');
            return '<div class="preview-block preview-tabla"><table>' + th + tb + '</table></div>';
        case 'importante':
            return '<div class="preview-block preview-importante"><strong>Importante:</strong> ' + esc(text) + '</div>';
        case 'sabias_que':
            return '<div class="preview-block preview-sabias"><strong>Sab\u00edas que:</strong> ' + esc(text) + '</div>';
        case 'ejemplo':
            return '<div class="preview-block preview-ejemplo"><strong>Ejemplo:</strong> ' + esc(text) + '</div>';
        case 'comparativa':
            return '<div class="preview-block preview-comparativa"><strong>Comparativa:</strong><ul>' + (b.items||[]).map(it => '<li>' + esc(it) + '</li>').join('') + '</ul></div>';
        default:
            return '<div class="preview-block preview-parrafo">' + esc(text) + '</div>';
    }
}

function switchTab(idx) {
    document.querySelectorAll('.tab-btn').forEach((b,i) => b.classList.toggle('active', i===idx));
    document.querySelectorAll('.tab-panel').forEach((p,i) => p.classList.toggle('active', i===idx));
}

// ── Generate ──
async function generateSCORM() {
    showLoading('Generando SCORM...', 'Creando paquete con HTML, CSS, JS y manifest');
    analysisData.modulo.codigo = document.getElementById('cfg-code').value;
    analysisData.modulo.titulo = document.getElementById('cfg-title').value;
    analysisData.modulo.duracion_total = parseInt(document.getElementById('cfg-hours').value) || 50;
    analysisData.modulo.empresa = document.getElementById('cfg-company').value;
    // Sync edited unit hours/titles from inputs
    document.querySelectorAll('.unit-hours-input').forEach(inp => {
        const idx = parseInt(inp.dataset.unitIdx);
        if (!isNaN(idx) && analysisData.unidades[idx]) {
            analysisData.unidades[idx].duracion = parseFloat(inp.value) || 6;
        }
    });
    document.querySelectorAll('.unit-title-input').forEach(inp => {
        const idx = parseInt(inp.dataset.unitIdx);
        if (!isNaN(idx) && analysisData.unidades[idx]) {
            analysisData.unidades[idx].titulo = inp.value;
        }
    });
    analysisData.template_id = selectedTemplate || 'arelance-corporate';
    try {
        updateProgress(20);
        const resp = await fetch('api/generate.php', { method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify(analysisData) });
        updateProgress(70);
        const result = await resp.json();
        if (!result.success) throw new Error(result.message || 'Error en la generacion');
        downloadData = result.data;
        updateProgress(100);
        renderFinalStats();
        setTimeout(() => { hideLoading(); goToStep(4); }, 500);
    } catch (err) { hideLoading(); alert('Error: ' + err.message); }
}

function renderFinalStats() {
    const s = downloadData.stats;
    document.getElementById('final-stats').innerHTML =
        stat(s.units, 'Unidades') + stat(s.flashcards, 'Flashcards') +
        stat(s.questions, 'Preguntas') + stat(formatSize(downloadData.size), 'Tamano');
}

// ── Download ──
function downloadSCORM() {
    if (!downloadData) return;
    window.location.href = 'api/download.php?id=' + downloadData.download_id + '&filename=' + encodeURIComponent(downloadData.filename);
}

// ── Navigation ──
function goToStep(step) {
    document.querySelectorAll('.step-content').forEach(e => e.classList.add('hidden'));
    document.getElementById('step-' + step).classList.remove('hidden');
    document.querySelectorAll('.step').forEach((el, i) => {
        const n = i + 1;
        el.classList.remove('active','completed');
        if (n < step) { el.classList.add('completed'); el.querySelector('.step-circle').textContent = '\u2713'; }
        else if (n === step) { el.classList.add('active'); el.querySelector('.step-circle').textContent = n; }
        else { el.querySelector('.step-circle').textContent = n; }
    });
    document.querySelectorAll('.step-line').forEach((l,i) => l.classList.toggle('completed', i < step-1));
    currentStep = step;
    if (step === 2 && !templatesLoaded) loadTemplates();
    if (step === 3) renderUnitTabs();
}

function resetApp() {
    currentStep=1; uploadedFile=null; analysisData=null; downloadData=null;
    clearFile(); goToStep(1);
}

// ── Loading ──
function showLoading(t, m) {
    document.getElementById('loading-title').textContent = t;
    document.getElementById('loading-message').textContent = m;
    document.getElementById('loading-progress').style.width = '0%';
    document.getElementById('loading-overlay').classList.remove('hidden');
}
function hideLoading() { document.getElementById('loading-overlay').classList.add('hidden'); }
function updateProgress(p) { document.getElementById('loading-progress').style.width = p + '%'; }

// ── Utils ──
function esc(t) { if (!t) return ''; const d = document.createElement('div'); d.textContent = t; return d.innerHTML; }
// Keep old name for compatibility
var escapeHtml = esc;

// ── Templates ──
async function loadTemplates() {
    const grid = document.getElementById('templates-grid');
    if (!grid) return;
    try {
        const resp = await fetch('api/templates.php');
        const text = await resp.text();
        console.log('Templates API raw:', text);
        let json;
        try { json = JSON.parse(text); } catch(e) { throw new Error('API no devuelve JSON: ' + text.substring(0, 200)); }
        if (!json.success) throw new Error(json.message);
        const templates = json.data.templates;
        selectedTemplate = json.data.default;
        grid.innerHTML = '';
        templates.forEach(tpl => {
            const colors = tpl.colors || {};
            const isDefault = tpl.id === json.data.default;
            const card = document.createElement('div');
            card.className = 'template-card' + (tpl.id === selectedTemplate ? ' selected' : '') + (isDefault ? ' is-default' : '');
            card.dataset.templateId = tpl.id;
            const pri = colors.primary || '#143554', sec = colors.secondary || '#1a4a6e', acc = colors.accent || '#F05726';
            let prev = tpl.preview_exists
                ? '<img src="api/templates.php?preview=' + tpl.id + '" alt="' + esc(tpl.name) + '">'
                : '<div style="color:#fff;font-size:.8rem;font-weight:600;text-align:center;padding:1rem">Aa</div>';
            card.innerHTML = '<div class="template-preview" style="background:linear-gradient(135deg,' + pri + ',' + sec + ' 60%,' + acc + ')">'
                + prev + '<div class="tpl-color-bar"><span style="background:' + pri + '"></span><span style="background:' + sec + '"></span><span style="background:' + acc + '"></span></div></div>'
                + '<div class="template-name">' + esc(tpl.name) + '</div>'
                + '<div class="template-author">' + esc(tpl.author) + ' &middot; v' + tpl.version + '</div>';
            card.addEventListener('click', () => selectTemplate(tpl.id));
            grid.appendChild(card);
        });
        templatesLoaded = true;
    } catch(err) {
        console.error('Templates load error:', err);
        grid.innerHTML = '<div class="template-loading">No se pudieron cargar las plantillas: ' + esc(err.message) + '</div>';
    }
}

function selectTemplate(id) {
    selectedTemplate = id;
    document.querySelectorAll('.template-card').forEach(c => c.classList.toggle('selected', c.dataset.templateId === id));
}

async function importTemplate(e) {
    const file = e.target.files[0]; if (!file) return;
    const fd = new FormData(); fd.append('template', file);
    try {
        const resp = await fetch('api/templates.php?action=import', {method:'POST', body:fd});
        const json = await resp.json();
        alert(json.message);
        if (json.success) { selectedTemplate = json.data.id; loadTemplates(); }
    } catch(err) { alert('Error al importar'); }
    e.target.value = '';
}
