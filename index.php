<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/Auth.php';
$auth = new \ScormConverter\Auth();
$user = $auth->currentUser();
$isLogged = $user !== null;
$isAdmin = $isLogged && $user['role'] === 'admin';
?>
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

<?php if (!$isLogged): ?>
<!-- ==================== LOGIN ==================== -->
<div class="app-container">
    <header class="app-header">
        <img src="assets/img/logo.png" alt="Logo" class="app-logo">
        <div class="brand">
            <span class="brand-word"><span class="brand-letter">S</span>CORM</span>
            <span class="brand-word"><span class="brand-letter">G</span>enerator</span>
        </div>
    </header>

    <main class="main-content login-wrap">
        <section class="card">
            <div class="card-center">
                <div class="login-icon">
                    <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                </div>
                <h2 class="card-title">Iniciar sesión</h2>
                <p class="card-desc">Accede para generar paquetes SCORM</p>

                <div id="login-error" class="login-error hidden"></div>

                <div class="form-grid login-form">
                    <div class="form-group full">
                        <label for="login-user">Usuario</label>
                        <input type="text" id="login-user" placeholder="Tu usuario" autocomplete="username" autofocus>
                    </div>
                    <div class="form-group full">
                        <label for="login-pass">Contraseña</label>
                        <input type="password" id="login-pass" placeholder="Tu contraseña" autocomplete="current-password">
                    </div>
                </div>

                <button class="btn btn-accent btn-lg btn-full" id="btn-login" style="margin-top:1.25rem">
                    Entrar
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
                </button>
            </div>
        </section>
    </main>

    <footer class="app-footer">
        <p>ARELANCE &middot; SCORM Generator</p>
    </footer>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const btnLogin = document.getElementById('btn-login');
    const inputUser = document.getElementById('login-user');
    const inputPass = document.getElementById('login-pass');
    const errorEl = document.getElementById('login-error');

    async function doLogin() {
        const username = inputUser.value.trim();
        const password = inputPass.value;
        if (!username || !password) { showError('Introduce usuario y contraseña'); return; }

        btnLogin.disabled = true;
        btnLogin.textContent = 'Entrando...';
        errorEl.classList.add('hidden');

        try {
            const resp = await fetch('api/auth.php?action=login', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ username, password })
            });
            const data = await resp.json();
            if (data.success) {
                window.location.reload();
            } else {
                showError(data.message || 'Credenciales incorrectas');
                btnLogin.disabled = false;
                btnLogin.innerHTML = 'Entrar <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>';
            }
        } catch(e) {
            showError('Error de conexión');
            btnLogin.disabled = false;
            btnLogin.textContent = 'Entrar';
        }
    }

    function showError(msg) {
        errorEl.textContent = msg;
        errorEl.classList.remove('hidden');
    }

    btnLogin.addEventListener('click', doLogin);
    inputPass.addEventListener('keydown', e => { if (e.key === 'Enter') doLogin(); });
    inputUser.addEventListener('keydown', e => { if (e.key === 'Enter') inputPass.focus(); });
});
</script>

<?php else: ?>
<!-- ==================== APP ==================== -->
<div class="app-container">

    <!-- Header -->
    <header class="app-header">
        <img src="assets/img/logo.png" alt="Logo" class="app-logo">
        <div class="brand">
            <span class="brand-word"><span class="brand-letter">S</span>CORM</span>
            <span class="brand-word"><span class="brand-letter">G</span>enerator</span>
        </div>
        <!-- User bar -->
        <span class="user-bar-name"><?= htmlspecialchars($user['username']) ?></span>
        <div class="user-bar-actions">

            <?php if ($isAdmin): ?>
            <button class="btn btn-ghost btn-sm" id="btn-admin-panel" title="Gestión de usuarios">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/></svg>
                Usuarios
            </button>
            <?php endif; ?>
            <button class="btn btn-ghost btn-sm" id="btn-logout" title="Cerrar sesión">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
            </button>
        </div>
    </header>

    <!-- Progress Steps -->
    <nav class="steps-container" id="main-steps">
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
                <div class="form-group"><label for="cfg-code">Codigo</label><input type="text" id="cfg-code" placeholder="MOD_01"></div>
                <div class="form-group"><label for="cfg-hours">Horas</label><input type="number" id="cfg-hours" min="1" value="50"></div>
                <div class="form-group full"><label for="cfg-title">Titulo del modulo</label><input type="text" id="cfg-title" placeholder="Ej: Proyecto final integrador"></div>
                <div class="form-group full"><label for="cfg-company">Empresa</label><input type="text" id="cfg-company" value="ARELANCE S.L."></div>
            </div>
            <hr class="divider">
            <h3 class="section-title">Unidades detectadas</h3>
            <div class="units-list" id="units-list"></div>
            <div class="summary-grid" id="summary-stats"></div>
            <hr class="divider">
            <h3 class="section-title">Opciones avanzadas</h3>
            <div class="form-grid">
                <div class="form-group full" style="display:flex;align-items:center;gap:1rem;">
                    <label for="cfg-enrichment" style="flex:1;">
                        <strong>Enriquecer contenido</strong>
                        <span style="display:block;font-size:.85em;color:#666;margin-top:.25rem;">Divide pantallas largas de forma inteligente respetando los límites naturales entre bloques de contenido.</span>
                    </label>
                    <label class="toggle-switch" style="flex-shrink:0;">
                        <input type="checkbox" id="cfg-enrichment" <?= ENABLE_ENRICHMENT_DEFAULT ? 'checked' : '' ?>>
                        <span class="toggle-slider"></span>
                    </label>
                </div>
            </div>
            <hr class="divider">
            <h3 class="section-title">Plantilla de estilo</h3>
            <div class="templates-grid" id="templates-grid"><div class="template-loading">Cargando plantillas...</div></div>
            <div class="template-actions">
                <label class="btn btn-ghost btn-sm" for="template-import-input">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                    Importar plantilla
                </label>
                <input type="file" id="template-import-input" accept=".zip" hidden>
            </div>
            <div class="btn-group">
                <button class="btn btn-ghost" onclick="goToStep(1)">Volver</button>
                <button class="btn btn-primary btn-lg" onclick="goToStep(3)">Continuar <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg></button>
            </div>
        </section>
        
        <!-- Step 3: Review -->
        <section class="card step-content hidden" id="step-3">
            <h2 class="card-title">Revisar estructura</h2>
            <p class="card-desc">Esta es la estructura generada por la IA. Revisa que el contenido esté bien organizado antes de generar el SCORM.</p>
            <div class="tabs" id="units-tabs"><div class="tabs-header" id="tabs-header"></div><div class="tabs-content" id="tabs-content"></div></div>
            <div class="btn-group">
                <button class="btn btn-ghost" onclick="goToStep(2)">Volver</button>
                <button class="btn btn-accent btn-lg" id="btn-generate">Generar SCORM <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg></button>
            </div>
        </section>
        
        <!-- Step 4: Download -->
        <section class="card step-content hidden" id="step-4">
            <div class="card-center">
                <div class="success-check"><svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg></div>
                <h2 class="card-title">Paquete generado</h2>
                <p class="card-desc">Listo para importar en Moodle u otro LMS compatible con SCORM 1.2</p>
                <div class="summary-grid" id="final-stats"></div>
                <button class="btn btn-accent btn-lg btn-full" id="btn-download"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg> Descargar SCORM</button>
                <button class="btn btn-ghost btn-full mt-3" onclick="resetApp()">Convertir otro documento</button>
            </div>
        </section>

        <!-- ==================== ADMIN PANEL ==================== -->
        <?php if ($isAdmin): ?>
        <section class="card step-content hidden" id="admin-panel">
            <div class="admin-header-row">
                <h2 class="card-title">Gestión de usuarios</h2>
                <div style="display:flex;gap:.5rem">
                    <button class="btn btn-accent btn-sm" id="btn-open-create-modal">Nuevo usuario</button>
                    <button class="btn btn-ghost btn-sm" id="btn-admin-back">Volver</button>
                </div>
            </div>

            <!-- Tabla de usuarios -->
            <h3 class="section-title">Usuarios registrados</h3>
            <div class="admin-table-wrap">
                <table class="admin-table" id="users-table">
                    <thead><tr><th>ID</th><th>Usuario</th><th>Rol</th><th>Creado</th><th>Acciones</th></tr></thead>
                    <tbody></tbody>
                </table>
            </div>
            <div class="admin-pagination" id="admin-pagination"></div>
        </section>
        <?php endif; ?>

    </main>

    <!-- Modal Crear usuario -->
    <?php if ($isAdmin): ?>
    <div class="modal-overlay hidden" id="create-user-modal">
        <div class="modal-box">
            <div class="modal-header">
                <h3 class="modal-title">Nuevo usuario</h3>
                <button class="btn-icon" id="btn-close-modal" title="Cerrar">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
            </div>
            <div class="form-grid" style="grid-template-columns:1fr">
                <div class="form-group"><label for="new-username">Usuario</label><input type="text" id="new-username" placeholder="nombre" minlength="3"></div>
                <div class="form-group"><label for="new-password">Contraseña</label><input type="text" id="new-password" placeholder="contraseña"></div>
                <div class="form-group"><label for="new-role">Rol</label>
                    <select id="new-role" class="admin-select"><option value="user">Usuario</option><option value="admin">Administrador</option></select>
                </div>
            </div>
            <div id="create-user-msg" class="admin-msg hidden"></div>
            <button class="btn btn-accent btn-lg btn-full" id="btn-create-user" style="margin-top:1rem">Crear usuario</button>
        </div>
    </div>
    <?php endif; ?>
    
    <footer class="app-footer"><p>ARELANCE &middot; SCORM Generator</p></footer>
</div>

<!-- Loading Overlay -->
<div class="loading-overlay hidden" id="loading-overlay">
    <div class="loading-content">
        <div class="spinner"></div>
        <h3 id="loading-title">Procesando...</h3>
        <p id="loading-message">Analizando estructura del documento</p>
        <div class="progress-bar"><div class="progress-fill" id="loading-progress"></div></div>
    </div>
</div>

<script src="assets/js/app.js"></script>
<script>
// ── Logout ──
document.getElementById('btn-logout')?.addEventListener('click', async function() {
    await fetch('api/auth.php?action=logout', { method: 'POST' });
    window.location.reload();
});

<?php if ($isAdmin): ?>
// ── Admin Panel ──
(function() {
    const btnOpen = document.getElementById('btn-admin-panel');
    const btnBack = document.getElementById('btn-admin-back');
    const panel = document.getElementById('admin-panel');
    const steps = document.getElementById('main-steps');

    let previousStep = 1;

    // ── Modal crear usuario ──
    const modal = document.getElementById('create-user-modal');
    document.getElementById('btn-open-create-modal').addEventListener('click', () => modal.classList.remove('hidden'));
    document.getElementById('btn-close-modal').addEventListener('click', () => modal.classList.add('hidden'));
    modal.addEventListener('click', e => { if (e.target === modal) modal.classList.add('hidden'); });

    const container = document.querySelector('.app-container');

    btnOpen.addEventListener('click', function() {
        previousStep = typeof currentStep !== 'undefined' ? currentStep : 1;
        document.querySelectorAll('.step-content').forEach(e => e.classList.add('hidden'));
        steps.style.display = 'none';
        container.classList.add('no-center');
        panel.classList.remove('hidden');
        loadUsers();
    });

    btnBack.addEventListener('click', function() {
        panel.classList.add('hidden');
        steps.style.display = '';
        container.classList.remove('no-center');
        goToStep(previousStep);
    });

    // ── Load users ──
    async function loadUsers() {
        try {
            const resp = await fetch('api/auth.php?action=list');
            const json = await resp.json();
            if (!json.success) return;
            renderUsersTable(json.data);
        } catch(e) { console.error(e); }
    }

    let allUsers = [];
    let currentPage = 1;
    let perPage = 4;

    function renderUsersTable(users) {
        allUsers = users;
        renderPage();
    }

    function renderPage() {
        const totalPages = Math.ceil(allUsers.length / perPage);
        if (currentPage > totalPages) currentPage = totalPages || 1;

        const start = (currentPage - 1) * perPage;
        const pageUsers = allUsers.slice(start, start + perPage);

        const tbody = document.querySelector('#users-table tbody');
        tbody.innerHTML = '';
        pageUsers.forEach(u => {
            const tr = document.createElement('tr');
            const isCurrentAdmin = (u.id === 1);
            tr.innerHTML = `
                <td>${u.id}</td>
                <td><strong>${esc(u.username)}</strong></td>
                <td>
                    <select class="admin-role-select" data-uid="${u.id}" ${isCurrentAdmin ? 'disabled' : ''}>
                        <option value="user" ${u.role==='user'?'selected':''}>Usuario</option>
                        <option value="admin" ${u.role==='admin'?'selected':''}>Administrador</option>
                    </select>
                </td>
                <td class="admin-date">${u.created_at || '—'}</td>
                <td>${isCurrentAdmin ? '' : '<button class=\"btn-icon admin-del-btn\" data-uid=\"'+u.id+'\" title=\"Eliminar\"><svg width=\"16\" height=\"16\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\"><polyline points=\"3 6 5 6 21 6\"/><path d=\"M19 6l-1 14H6L5 6\"/><path d=\"M10 11v6\"/><path d=\"M14 11v6\"/><path d=\"M9 6V4h6v2\"/></svg></button>'}</td>`;
            tbody.appendChild(tr);
        });

        // Bind role change
        tbody.querySelectorAll('.admin-role-select').forEach(sel => {
            sel.addEventListener('change', async function() {
                const uid = parseInt(this.dataset.uid);
                const role = this.value;
                try {
                    const resp = await fetch('api/auth.php?action=update_role', {
                        method: 'POST', headers: {'Content-Type':'application/json'},
                        body: JSON.stringify({ id: uid, role })
                    });
                    const json = await resp.json();
                    if (!json.success) { alert(json.message); loadUsers(); }
                } catch(e) { alert('Error'); loadUsers(); }
            });
        });

        // Bind delete
        tbody.querySelectorAll('.admin-del-btn').forEach(btn => {
            btn.addEventListener('click', async function() {
                const uid = parseInt(this.dataset.uid);
                if (!confirm('¿Eliminar este usuario?')) return;
                try {
                    const resp = await fetch('api/auth.php?action=delete', {
                        method: 'POST', headers: {'Content-Type':'application/json'},
                        body: JSON.stringify({ id: uid })
                    });
                    const json = await resp.json();
                    if (json.success) loadUsers(); else alert(json.message);
                } catch(e) { alert('Error'); }
            });
        });

        renderPagination(totalPages);
    }

    function renderPagination(totalPages) {
        const wrap = document.getElementById('admin-pagination');
        if (allUsers.length <= 4) { wrap.innerHTML = ''; return; }

        let html = '<div class="pg-left">';
        html += '<span class="pg-info">' + allUsers.length + ' usuarios</span>';
        html += '</div>';
        html += '<div class="pg-center">';
        html += '<button class="pg-btn" data-pg="prev" ' + (currentPage <= 1 ? 'disabled' : '') + '>&lsaquo;</button>';
        for (let i = 1; i <= totalPages; i++) {
            html += '<button class="pg-btn' + (i === currentPage ? ' pg-active' : '') + '" data-pg="' + i + '">' + i + '</button>';
        }
        html += '<button class="pg-btn" data-pg="next" ' + (currentPage >= totalPages ? 'disabled' : '') + '>&rsaquo;</button>';
        html += '</div>';
        html += '<div class="pg-right">';
        html += '<select class="pg-per-page">';
        [4, 8, 16, 32].forEach(n => {
            html += '<option value="' + n + '"' + (n === perPage ? ' selected' : '') + '>' + n + ' / pág</option>';
        });
        html += '</select></div>';

        wrap.innerHTML = html;

        // Bind page buttons
        wrap.querySelectorAll('.pg-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const v = this.dataset.pg;
                if (v === 'prev') currentPage--;
                else if (v === 'next') currentPage++;
                else currentPage = parseInt(v);
                renderPage();
            });
        });

        // Bind per-page select
        wrap.querySelector('.pg-per-page')?.addEventListener('change', function() {
            perPage = parseInt(this.value);
            currentPage = 1;
            renderPage();
        });
    }

    // ── Create user ──
    document.getElementById('btn-create-user').addEventListener('click', async function() {
        const username = document.getElementById('new-username').value.trim();
        const password = document.getElementById('new-password').value;
        const role = document.getElementById('new-role').value;
        const msgEl = document.getElementById('create-user-msg');

        if (!username || !password) { showMsg(msgEl, 'Completa todos los campos', true); return; }
        if (username.length < 3) { showMsg(msgEl, 'Usuario mínimo 3 caracteres', true); return; }
        if (password.length < 4) { showMsg(msgEl, 'Contraseña mínimo 4 caracteres', true); return; }

        try {
            const resp = await fetch('api/auth.php?action=create', {
                method: 'POST', headers: {'Content-Type':'application/json'},
                body: JSON.stringify({ username, password, role })
            });
            const json = await resp.json();
            if (json.success) {
                document.getElementById('new-username').value = '';
                document.getElementById('new-password').value = '';
                document.getElementById('new-role').value = 'user';
                modal.classList.add('hidden');
                loadUsers();
            } else {
                showMsg(msgEl, json.message, true);
            }
        } catch(e) { showMsg(msgEl, 'Error de conexión', true); }
    });

    function showMsg(el, msg, isError) {
        el.textContent = msg;
        el.className = 'admin-msg' + (isError ? ' admin-msg-error' : ' admin-msg-ok');
        el.classList.remove('hidden');
        setTimeout(() => el.classList.add('hidden'), 3500);
    }

    function esc(t) { if (!t) return ''; const d = document.createElement('div'); d.textContent = t; return d.innerHTML; }
})();
<?php endif; ?>
</script>

<?php endif; ?>

</body>
</html>
