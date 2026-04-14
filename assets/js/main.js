// ============================================================
//  assets/js/main.js — JavaScript global ERP CORFIEM
//  Funciones: Modales · Toasts · Sidebar · Utilidades AJAX
// ============================================================

// ── Modales ───────────────────────────────────────────────────
/**
 * Abre un modal por su ID
 * @param {string} id - ID del elemento .modal-overlay
 */
function openModal(id) {
    const el = document.getElementById(id);
    if (el) {
        el.classList.add('open');
        document.body.style.overflow = 'hidden';
    }
}

/**
 * Cierra un modal por su ID
 */
function closeModal(id) {
    const el = document.getElementById(id);
    if (el) {
        el.classList.remove('open');
        document.body.style.overflow = '';
    }
}

// Cerrar modal al hacer clic en el overlay (fondo oscuro)
document.addEventListener('click', (e) => {
    if (e.target.classList.contains('modal-overlay')) {
        e.target.classList.remove('open');
        document.body.style.overflow = '';
    }
});

// Cerrar con tecla Escape
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal-overlay.open').forEach(m => {
            m.classList.remove('open');
        });
        document.body.style.overflow = '';
    }
});

// ── Toast Notifications ───────────────────────────────────────
/**
 * Muestra una notificación tipo toast
 * @param {string} title   - Título del toast
 * @param {string} msg     - Mensaje descriptivo (opcional)
 * @param {'success'|'error'|'warning'|'info'} type - Tipo visual
 * @param {number} duration - Duración en ms (default 3500)
 */
function showToast(title, msg = '', type = 'info', duration = 3500) {
    const container = document.getElementById('toast-container');
    if (!container) return;

    const icons = {
        success: '<polyline points="20,6 9,17 4,12"/>',
        error:   '<circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/>',
        warning: '<path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>',
        info:    '<circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>',
    };

    const strokeColors = {
        success: 'var(--c-success)',
        error:   'var(--c-danger)',
        warning: 'var(--c-warning)',
        info:    'var(--c-navy)',
    };

    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    toast.innerHTML = `
        <div class="toast-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="${strokeColors[type] || '#000'}" stroke-width="2">
                ${icons[type] || icons.info}
            </svg>
        </div>
        <div>
            <div class="toast-title">${escHtml(title)}</div>
            ${msg ? `<div class="toast-msg">${escHtml(msg)}</div>` : ''}
        </div>
    `;

    container.appendChild(toast);

    // Auto-eliminar
    setTimeout(() => {
        toast.style.transition = 'opacity 0.3s, transform 0.3s';
        toast.style.opacity    = '0';
        toast.style.transform  = 'translateX(20px)';
        setTimeout(() => toast.remove(), 320);
    }, duration);

    // Click para cerrar
    toast.addEventListener('click', () => toast.remove());
}

// ── Sidebar (responsive) ──────────────────────────────────────
const sidebar    = document.getElementById('sidebar');
const hamburger  = document.getElementById('hamburgerBtn');

if (hamburger) {
    hamburger.addEventListener('click', () => {
        sidebar?.classList.toggle('open');
    });
}

// Cerrar sidebar en mobile al navegar
document.querySelectorAll('.sidebar-nav a').forEach(link => {
    link.addEventListener('click', () => {
        if (window.innerWidth <= 1024) {
            sidebar?.classList.remove('open');
        }
    });
});

// ── Confirmación de eliminación ───────────────────────────────
/**
 * Confirma una acción destructiva antes de ejecutarla
 * @param {string} msg     - Mensaje de confirmación
 * @param {Function} onConfirm - Callback si confirma
 */
function confirmAction(msg, onConfirm) {
    // Se puede reemplazar por un modal custom en el futuro
    if (window.confirm(msg)) onConfirm();
}

// ── Peticiones AJAX helper ────────────────────────────────────
/**
 * POST AJAX con FormData o JSON
 * @param {string} url
 * @param {FormData|Object} data
 * @returns {Promise<Object>}
 */
async function apiPost(url, data) {
    const isFormData = data instanceof FormData;
    const opts = {
        method: 'POST',
        headers: isFormData ? {} : { 'Content-Type': 'application/json' },
        body:    isFormData ? data : JSON.stringify(data),
    };
    const res = await fetch(url, opts);
    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    return res.json();
}

async function apiGet(url, params = {}) {
    const qs  = new URLSearchParams(params).toString();
    const res = await fetch(qs ? `${url}?${qs}` : url);
    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    return res.json();
}

// ── Utilidades de formato ─────────────────────────────────────
/**
 * Escapa HTML para prevenir XSS en JS
 */
function escHtml(str) {
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

/**
 * Formatea un número como moneda
 */
function formatCurrency(n, symbol = '$') {
    return symbol + Number(n).toLocaleString('es-PE', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
}

/**
 * Formatea fecha ISO a dd/mm/yyyy
 */
function formatDate(iso) {
    if (!iso) return '—';
    const d = new Date(iso);
    return d.toLocaleDateString('es-PE', { day:'2-digit', month:'2-digit', year:'numeric' });
}

/**
 * Retorna tiempo relativo: "Hace 2 horas"
 */
function timeAgo(iso) {
    const diff = Date.now() - new Date(iso).getTime();
    const m = Math.floor(diff / 60000);
    if (m < 1)   return 'Ahora mismo';
    if (m < 60)  return `Hace ${m} min`;
    const h = Math.floor(m / 60);
    if (h < 24)  return `Hace ${h} h`;
    const days = Math.floor(h / 24);
    return `Hace ${days} d`;
}

// ── Confirmación antes de eliminar (botones data-confirm) ─────
document.addEventListener('click', (e) => {
    const btn = e.target.closest('[data-confirm]');
    if (!btn) return;
    e.preventDefault();
    const msg = btn.dataset.confirm || '¿Estás seguro de esta acción?';
    confirmAction(msg, () => {
        // Si el botón está dentro de un form, enviarlo
        const form = btn.closest('form');
        if (form) { form.submit(); }
        // Si tiene href, navegar
        else if (btn.href) { window.location.href = btn.href; }
        // Si tiene data-action, ejecutar función
        else if (btn.dataset.action) {
            const fn = window[btn.dataset.action];
            if (typeof fn === 'function') fn(btn);
        }
    });
});

// ── Auto-cerrar alertas flash PHP ─────────────────────────────
document.querySelectorAll('[data-flash]').forEach(el => {
    setTimeout(() => {
        el.style.transition = 'opacity 0.5s';
        el.style.opacity    = '0';
        setTimeout(() => el.remove(), 500);
    }, 4000);
});

// ── Búsqueda en tablas (client-side) ─────────────────────────
/**
 * Filtra filas de una tabla según un input de búsqueda
 * @param {HTMLInputElement} input
 * @param {string} tableId
 */
function filterTable(input, tableId) {
    const q     = input.value.toLowerCase();
    const tbody = document.querySelector(`#${tableId} tbody`);
    if (!tbody) return;
    tbody.querySelectorAll('tr').forEach(row => {
        row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
    });
}

// ── Inicialización ────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {

    // Notificación de éxito si viene desde un redirect con ?ok=1
    const params = new URLSearchParams(window.location.search);
    if (params.get('ok') === '1') {
        showToast('Operación exitosa', 'El registro se guardó correctamente.', 'success');
        // Limpiar param de la URL sin recargar
        window.history.replaceState({}, '', window.location.pathname);
    }

    // Tooltips simples via title
    document.querySelectorAll('[title]').forEach(el => {
        // puedes expandir esto con una librería de tooltips si se necesita
    });

    // Actualizar timestamps relativos cada minuto
    setInterval(() => {
        document.querySelectorAll('[data-timestamp]').forEach(el => {
            el.textContent = timeAgo(el.dataset.timestamp);
        });
    }, 60000);
});