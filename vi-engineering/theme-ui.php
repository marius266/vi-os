<?php
// Include on display pages only, never on save/redirect handlers.
if (!isset($pdo) || !function_exists('current_user') || !current_user()) return;
$_SESSION['theme_csrf'] ??= bin2hex(random_bytes(32));
$theme = 'light';
try {
    $viThemeStmt = $pdo->prepare('SELECT theme FROM user_preferences WHERE user_id=?');
    $viThemeStmt->execute([(int)current_user()['id']]);
    if ($viThemeStmt->fetchColumn() === 'dark') $theme = 'dark';
} catch (Throwable $e) { error_log('Theme preference read: '.$e->getMessage()); }
?>
<style>
/* Applies to all application screens; printed job cards/reports stay white. */
html.vi-dark {color-scheme:dark}
html.vi-dark body {background:#101827!important;color:#e5e7eb!important}
html.vi-dark .sheet,html.vi-dark .report,html.vi-dark .filters,html.vi-dark .wrap,html.vi-dark .panel,html.vi-dark table,html.vi-dark .container .card,html.vi-dark .section {background-color:#1e293b!important;color:#e5e7eb!important}
html.vi-dark input,html.vi-dark select,html.vi-dark textarea {background:#273449!important;color:#f8fafc!important;border-color:#52617a!important}
html.vi-dark th {background:#334155!important;color:#f8fafc!important}
html.vi-dark td {border-color:#475569!important}
/* Keep job list rows and status badges readable in dark mode. */
html.vi-dark .table-wrap {background:#1e293b!important}
html.vi-dark .table-wrap tbody tr td {background:#1e293b!important;color:#f8fafc!important}
html.vi-dark .table-wrap tbody tr:hover td {background:#334155!important;color:#fff!important}
html.vi-dark .table-wrap .status {background:#475569!important;color:#fff!important}
html.vi-dark .table-wrap tbody tr:hover .status {background:#64748b!important;color:#fff!important}
html.vi-dark .table-wrap tbody td a:not(.btn) {color:#bfdbfe!important}
html.vi-dark .pagination a {background:#334155!important;color:#f8fafc!important;border-color:#64748b!important}
html.vi-dark .summary,html.vi-dark .foot,html.vi-dark .muted,html.vi-dark small,html.vi-dark .toolbar small {color:#cbd5e1!important}
html.vi-dark .section {border-color:#64748b!important}
html.vi-dark a:not(.btn) {color:#93c5fd}
#vi-theme-toggle {position:fixed;right:14px;bottom:14px;z-index:9999;padding:10px 15px;background:#334155;color:white;border:1px solid #94a3b8;border-radius:7px;cursor:pointer;font:600 13px Arial,sans-serif;box-shadow:0 2px 10px #0004}
@media print {
  #vi-theme-toggle {display:none!important}
  html.vi-dark {color-scheme:light!important}
  html.vi-dark body,html.vi-dark .sheet,html.vi-dark .report,html.vi-dark .section,html.vi-dark table {background:#fff!important;color:#000!important}
  html.vi-dark .sheet .field input:not([type="radio"]):not([type="checkbox"]),
  html.vi-dark .sheet .field textarea,html.vi-dark .sheet .field select {
    background:#e9e9e9!important;color:#000!important;-webkit-text-fill-color:#000!important;border:0!important;opacity:1!important;print-color-adjust:exact!important;-webkit-print-color-adjust:exact!important
  }
  html.vi-dark .sheet .costs .total input {background:#dbeafe!important}
  html.vi-dark .sheet input[type="radio"],html.vi-dark .sheet input[type="checkbox"] {
    color-scheme:light!important;accent-color:#155e94!important;background:initial!important;appearance:auto!important;-webkit-appearance:auto!important
  }
  html.vi-dark .sheet .parts-cart-table input {background:transparent!important;color:#000!important;-webkit-text-fill-color:#000!important;border:0!important}
  html.vi-dark .sheet .parts-cart-table th,html.vi-dark .sheet .parts-cart-table td {background:#fff!important;color:#000!important}
  html.vi-dark .report input,html.vi-dark .report select,html.vi-dark .report textarea {background:#fff!important;color:#000!important}
}
</style>
<button id="vi-theme-toggle" type="button" aria-label="Switch color theme"></button>
<script>
(()=>{
 const initial = <?= json_encode($theme) ?>;
 const csrf = <?= json_encode($_SESSION['theme_csrf']) ?>;
 const root = document.documentElement, button = document.getElementById('vi-theme-toggle');
 function apply(mode) {root.classList.toggle('vi-dark',mode==='dark');button.textContent=mode==='dark'?'☀ Light mode':'☾ Dark mode';}
 apply(initial);
 button.addEventListener('click', async ()=>{
   const previous=root.classList.contains('vi-dark')?'dark':'light';
   const next=previous==='dark'?'light':'dark';
   button.disabled=true;
   try {const response=await fetch('theme-save.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:new URLSearchParams({csrf,theme:next})});if(!response.ok)throw Error('Could not save theme');apply(next);}
   catch(e){alert('Could not save theme preference. Check that theme-preferences.sql has been installed.');}
   finally {button.disabled=false;}
 });
})();
</script>
