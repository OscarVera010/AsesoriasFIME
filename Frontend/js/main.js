/* ==========================================
   js/main.js — Asesorías FIME
   Estructura: Estudiante/ · Tutor/ · Admin/
   Navbar responsive con hamburguesa
========================================== */

/* ---------- 1. RUTA RAÍZ Y API ---------- */
function raiz() {
    const p = window.location.pathname.toLowerCase();
    if (p.includes('/estudiante/') || p.includes('/tutor/') || p.includes('/admin/')) {
        return '../';
    }
    return '';
}

function apiUrl(endpoint) {
    return raiz() + '../Backend/api/' + endpoint.replace(/^\/+/, '');
}

async function apiFetch(endpoint, options = {}) {
    const config = {
        credentials: 'same-origin',
        ...options
    };
    if (config.body && !(config.body instanceof FormData)) {
        config.headers = {
            'Content-Type': 'application/json',
            ...(config.headers || {})
        };
        if (typeof config.body !== 'string') {
            config.body = JSON.stringify(config.body);
        }
    }
    const res = await fetch(apiUrl(endpoint), config);
    const data = await res.json().catch(() => ({}));
    if (!res.ok) {
        throw new Error(data.error || data.message || `Error HTTP ${res.status}`);
    }
    return data;
}

function getCurrentPage() {
    const parts = window.location.pathname.split('/');
    return parts[parts.length - 1] || 'index.html';
}

/* ---------- 2. TEMA ---------- */
function toggleTema() {
    document.body.classList.toggle('dark-mode');
    localStorage.setItem('temaPreferido',
        document.body.classList.contains('dark-mode') ? 'dark' : 'light');
}

/* ---------- 3. USUARIO ---------- */
function obtenerUsuario() {
    try {
        const raw = localStorage.getItem('usuarioFIME');
        if (raw) {
            const u = JSON.parse(raw);
            if (u && u.nombre) return u;
        }
    } catch (e) {}
    return {
        nombre: 'Invitado', matricula: '', carrera: '', carreraCorta: '', rol: 'invitado'
    };
}

/* ---------- 4. UTILIDADES ---------- */
function linkActivo(paginas) {
    return paginas.includes(getCurrentPage()) ? 'fw-bold text-success' : '';
}

function iniciales(nombre) {
    return (nombre || 'U').trim().split(/\s+/).slice(0, 2)
        .map(p => p.charAt(0).toUpperCase()).join('');
}

function rutaPanel(rol) {
    const R = raiz();
    return {
        estudiante: R + 'Estudiante/dashboard_estudiante.html',
        tutor:      R + 'Tutor/dashboard_tutor.html',
        admin:      R + 'Admin/dashboard_admin.html'
    }[rol] || R + 'auth.html';
}

/* ---------- 5. BLOQUES REUTILIZABLES ---------- */
function switchTemaHTML() {
    return `
        <div class="theme-switch-wrapper">
            <label class="theme-switch" for="checkboxTema">
                <input type="checkbox" id="checkboxTema" onchange="toggleTema()">
                <div class="slider"></div>
            </label>
        </div>`;
}

function userBoxHTML(u, rolEtiqueta) {
    return `
        <div class="navbar-user-box">
            <div class="navbar-user-info">
                <span class="navbar-user-name">${u.nombre}</span>
                <span class="navbar-user-role">${rolEtiqueta} · ${u.matricula || ''}</span>
            </div>
            <div class="navbar-user-avatar">${iniciales(u.nombre)}</div>
        </div>`;
}

function btnSalirHTML() {
    const R = raiz();
    return `<a href="${R}index.html" class="btn-neon text-danger w-100 w-lg-auto"
               style="border-color:#dc3545;" id="btnCerrarSesion"
               title="Cerrar sesión">
               <i class="bi bi-box-arrow-right me-1"></i>Salir
            </a>`;
}

/* ---------- 6. NAVBAR PÚBLICO ---------- */
function renderNavbarPublic(el, badge) {
    const R = raiz();
    const u = obtenerUsuario();
    const logueado = u.rol && u.rol !== 'invitado';

    el.className = 'navbar navbar-expand-lg navbar-custom';
    el.innerHTML = `
      <div class="container-fluid px-0">
        <a href="${R}index.html" class="navbar-brand text-decoration-none d-flex align-items-center">
          <img src="${R}img/diosplanN-96.webp" alt="Logo" class="logo-fime me-2">
          Asesorías FIME
        </a>
        ${badge ? `<span class="badge bg-primary ms-2 d-none d-lg-inline-block">${badge}</span>` : ''}

        <button class="navbar-toggler" type="button"
                data-bs-toggle="collapse" data-bs-target="#menuPublic"
                aria-controls="menuPublic" aria-expanded="false" aria-label="Abrir menú">
          <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="menuPublic">
          <div class="navbar-nav ms-auto gap-2 mt-3 mt-lg-0">
            <a href="${R}index.html" class="btn-neon border-0 text-center ${linkActivo(['index.html'])}">Inicio</a>
            <a href="${R}mapa.html" class="btn-neon border-0 text-center ${linkActivo(['mapa.html'])}">Mapa</a>
            <a href="${R}faq.html" class="btn-neon border-0 text-center ${linkActivo(['faq.html'])}">FAQ</a>
            ${logueado
              ? `<a href="${rutaPanel(u.rol)}" class="btn-neon text-center">Ir a mi panel</a>
                 ${btnSalirHTML()}`
              : `<a href="${R}auth.html" class="btn-neon text-center">Iniciar sesión</a>`}
            ${switchTemaHTML()}
          </div>
        </div>
      </div>`;
    bindLogout();
}

/* ---------- 7. NAVBAR ESTUDIANTE ---------- */
function renderNavbarEstudiante(el, badge) {
    const R = raiz();
    const u = obtenerUsuario();

    el.className = 'navbar navbar-expand-lg navbar-custom';
    el.innerHTML = `
      <div class="container-fluid px-0">
        <a href="${R}Estudiante/dashboard_estudiante.html" class="navbar-brand text-decoration-none d-flex align-items-center">
          <img src="${R}img/diosplanN-96.webp" alt="Logo" class="logo-fime me-2">
          Panel de Estudiante
        </a>
        ${badge ? `<span class="badge bg-primary ms-2 d-none d-lg-inline-block">${badge}</span>` : ''}

        <button class="navbar-toggler" type="button"
                data-bs-toggle="collapse" data-bs-target="#menuEstudiante"
                aria-controls="menuEstudiante" aria-expanded="false" aria-label="Abrir menú">
          <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="menuEstudiante">
          <div class="navbar-nav ms-auto gap-2 mt-3 mt-lg-0">
            <a href="${R}index.html" class="btn-neon border-0 text-center ${linkActivo(['index.html'])}">Inicio</a>
            <a href="${R}Estudiante/maestros.html" class="btn-neon border-0 text-center ${linkActivo(['maestros.html'])}">Maestros</a>
            <a href="${R}Estudiante/materias.html" class="btn-neon border-0 text-center ${linkActivo(['materias.html'])}">Materias</a>
            <a href="${R}Estudiante/horario_estudiante.html" class="btn-neon border-0 text-center ${linkActivo(['horario_estudiante.html'])}">Mi Horario</a>
            <a href="${R}Estudiante/peticion_materia.html" class="btn-neon border-0 text-center ${linkActivo(['peticion_materia.html'])}">Peticiones</a>
            ${userBoxHTML(u, 'Estudiante')}
            ${btnSalirHTML()}
            ${switchTemaHTML()}
          </div>
        </div>
      </div>`;
    bindLogout();
}

/* ---------- 8. NAVBAR TUTOR ---------- */
function renderNavbarTutor(el, badge) {
    const R = raiz();
    const u = obtenerUsuario();

    el.className = 'navbar navbar-expand-lg navbar-custom';
    el.innerHTML = `
      <div class="container-fluid px-0">
        <a href="${R}Tutor/dashboard_tutor.html" class="navbar-brand text-decoration-none d-flex align-items-center">
          <img src="${R}img/diosplanN-96.webp" alt="Logo" class="logo-fime me-2">
          Panel de Tutor
        </a>
        ${badge ? `<span class="badge bg-success ms-2 d-none d-lg-inline-block">${badge}</span>` : ''}

        <button class="navbar-toggler" type="button"
                data-bs-toggle="collapse" data-bs-target="#menuTutor"
                aria-controls="menuTutor" aria-expanded="false" aria-label="Abrir menú">
          <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="menuTutor">
          <div class="navbar-nav ms-auto gap-2 mt-3 mt-lg-0">
            <a href="${R}index.html" class="btn-neon border-0 text-center ${linkActivo(['index.html'])}">Inicio</a>
            <a href="${R}Tutor/tutor_alumnos.html" class="btn-neon border-0 text-center ${linkActivo(['tutor_alumnos.html'])}">Alumnos</a>
            <a href="${R}Tutor/crud_materias.html" class="btn-neon border-0 text-center ${linkActivo(['crud_materias.html'])}">Materias</a>
            <a href="${R}Tutor/crud_horarios.html" class="btn-neon border-0 text-center ${linkActivo(['crud_horarios.html'])}">Horarios</a>
            ${userBoxHTML(u, 'Tutor')}
            ${btnSalirHTML()}
            ${switchTemaHTML()}
          </div>
        </div>
      </div>`;
    bindLogout();
}

/* ---------- 9. NAVBAR ADMIN ---------- */
function renderNavbarAdmin(el, badge) {
    const R = raiz();
    const u = obtenerUsuario();

    el.className = 'navbar navbar-expand-lg navbar-custom';
    el.innerHTML = `
      <div class="container-fluid px-0">
        <a href="${R}Admin/dashboard_admin.html" class="navbar-brand text-decoration-none d-flex align-items-center">
          <img src="${R}img/diosplanN-96.webp" alt="Logo" class="logo-fime me-2">
          Portal Jefatura
        </a>
        <span class="badge bg-danger ms-2 d-none d-lg-inline-block">Administración</span>
        ${badge ? `<span class="badge bg-secondary ms-2 d-none d-lg-inline-block">${badge}</span>` : ''}

        <button class="navbar-toggler" type="button"
                data-bs-toggle="collapse" data-bs-target="#menuAdmin"
                aria-controls="menuAdmin" aria-expanded="false" aria-label="Abrir menú">
          <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="menuAdmin">
          <div class="navbar-nav ms-auto gap-2 mt-3 mt-lg-0">
            <a href="${R}index.html" class="btn-neon border-0 text-center ${linkActivo(['index.html'])}">Inicio</a>
            <a href="${R}Admin/admin_solicitudes.html" class="btn-neon border-0 text-center ${linkActivo(['admin_solicitudes.html'])}">Solicitudes</a>
            <a href="${R}Admin/peticiones_materia.html" class="btn-neon border-0 text-center ${linkActivo(['peticiones_materia.html'])}">Peticiones</a>
            <a href="${R}Admin/admin_tutores.html" class="btn-neon border-0 text-center ${linkActivo(['admin_tutores.html'])}">Tutores</a>
            <a href="${R}Admin/admin_catalogo_materias.html" class="btn-neon border-0 text-center ${linkActivo(['admin_catalogo_materias.html'])}">Catálogo</a>
            ${userBoxHTML(u, 'Jefatura')}
            ${btnSalirHTML()}
            ${switchTemaHTML()}
          </div>
        </div>
      </div>`;
    bindLogout();
}

/* ---------- 10. DISPATCHER ---------- */
function renderNavbar() {
    const el = document.getElementById('navbarRol');
    if (!el) return;
    const rol   = el.dataset.rol   || 'public';
    const badge = el.dataset.badge || '';
    switch (rol) {
        case 'estudiante': return renderNavbarEstudiante(el, badge);
        case 'tutor':      return renderNavbarTutor(el, badge);
        case 'admin':      return renderNavbarAdmin(el, badge);
        default:           return renderNavbarPublic(el, badge);
    }
}

/* ---------- 11. LOGOUT ---------- */
function bindLogout() {
    const btn = document.getElementById('btnCerrarSesion');
    if (!btn) return;
    btn.addEventListener('click', async function(e) {
        e.preventDefault();
        try {
            await apiFetch('auth/logout.php', { method: 'POST' });
        } catch (err) {}
        localStorage.removeItem('usuarioFIME');
        mostrarToast('Sesión cerrada. Redirigiendo…', 'success');
        setTimeout(() => window.location.href = raiz() + 'index.html', 700);
    });
}

/* ---------- 12. SINCRONIZACIÓN DE SESIÓN CON PHP ---------- */
async function sincronizarSesion() {
    const nav = document.getElementById('navbarRol');
    const rolPagina = nav ? (nav.dataset.rol || 'public') : 'public';
    const esProtegida = ['estudiante', 'tutor', 'admin'].includes(rolPagina);

    try {
        const data = await apiFetch('auth/me.php');
        if (data && data.usuario) {
            localStorage.setItem('usuarioFIME', JSON.stringify(data.usuario));
            if (esProtegida && data.usuario.rol !== rolPagina) {
                window.location.href = rutaPanel(data.usuario.rol);
                return null;
            }
            renderNavbar();
            const cb = document.getElementById('checkboxTema');
            if (cb && document.body.classList.contains('dark-mode')) cb.checked = true;
            document.dispatchEvent(new CustomEvent('sesionLista', { detail: data.usuario }));
            return data.usuario;
        }
    } catch (err) {
        localStorage.removeItem('usuarioFIME');
        if (esProtegida) {
            window.location.href = raiz() + 'auth.html';
            return null;
        }
        renderNavbar();
    }
    return null;
}

/* ---------- 13. TOAST ---------- */
function mostrarToast(mensaje, color = 'success') {
    let container = document.getElementById('toastContainerGlobal');
    if (!container) {
        container = document.createElement('div');
        container.id = 'toastContainerGlobal';
        container.className = 'toast-container position-fixed bottom-0 end-0 p-3';
        document.body.appendChild(container);
    }
    const el = document.createElement('div');
    el.className = `toast align-items-center text-bg-${color} border-0 shadow-lg`;
    el.setAttribute('role', 'alert');
    el.innerHTML = `
        <div class="d-flex">
            <div class="toast-body fw-semibold">${mensaje}</div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>`;
    container.appendChild(el);
    new bootstrap.Toast(el, { delay: 3500 }).show();
    el.addEventListener('hidden.bs.toast', () => el.remove());
}

/* ---------- 14. INIT ---------- */
window.addEventListener('DOMContentLoaded', () => {
    if (localStorage.getItem('temaPreferido') === 'dark') {
        document.body.classList.add('dark-mode');
    }
    renderNavbar();
    const cb = document.getElementById('checkboxTema');
    if (cb && document.body.classList.contains('dark-mode')) cb.checked = true;
    window.sesionPromise = sincronizarSesion();
});