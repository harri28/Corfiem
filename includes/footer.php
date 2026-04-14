<?php
// ============================================================
//  includes/footer.php
//  Uso: include __DIR__ . '/../includes/footer.php';
//  Debe incluirse AL FINAL de cada módulo, antes de cerrar PHP
// ============================================================
?>

    </div><!-- /.main-content -->
</div><!-- /.app-layout -->

<!-- ── Contenedor de Toasts (notificaciones) ─────────────────── -->
<div id="toast-container"></div>

<!-- ── Overlay para sidebar en mobile ────────────────────────── -->
<div id="sidebarOverlay"
     style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.45);z-index:99;"
     onclick="document.getElementById('sidebar').classList.remove('open');
              this.style.display='none';">
</div>

<!-- ── Scripts globales ──────────────────────────────────────── -->
<script src="<?= APP_URL ?>/assets/js/main.js"></script>

<!-- ── Footer corporativo ────────────────────────────────────── -->
<footer style="
    margin-left: var(--sidebar-w);
    background: var(--c-surface);
    border-top: 1px solid var(--c-border);
    padding: 12px 28px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    font-size: 11.5px;
    color: var(--c-text-4);
">
    <span>
        &copy; <?= date('Y') ?> <strong style="color:var(--c-text-3);">CORFIEM</strong>
        &nbsp;·&nbsp; <?= APP_NAME ?> v<?= APP_VERSION ?>
    </span>
    <span>
        Usuario: <strong style="color:var(--c-text-3);">
            <?= htmlspecialchars($_SESSION['usuario_nombre'] ?? '—') ?>
        </strong>
        &nbsp;·&nbsp;
        Rol: <strong style="color:var(--c-text-3);">
            <?= htmlspecialchars($_SESSION['usuario_rol'] ?? '—') ?>
        </strong>
    </span>
</footer>

<!-- Responsive: ocultar footer-margin en mobile -->
<style>
    @media (max-width: 1024px) {
        footer { margin-left: 0 !important; }
    }
</style>

</body>
</html>