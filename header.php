<?php require_once 'config.php'; ?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#4f46e5">
<title><?= $title ?? 'Laser Engrave' ?> — <?= htmlspecialchars(NAMA_TOKO) ?></title>

<!-- ============ PWA / Native-like Android ============ -->
<link rel="manifest" href="manifest.php">
<meta name="application-name" content="<?= htmlspecialchars(NAMA_TOKO) ?>">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="<?= htmlspecialchars(NAMA_TOKO) ?>">
<meta name="format-detection" content="telephone=no">
<meta name="msapplication-TileColor" content="#4f46e5">
<meta name="msapplication-tap-highlight" content="no">
<link rel="icon" type="image/png" sizes="192x192" href="icons/icon-192.png">
<link rel="icon" type="image/png" sizes="512x512" href="icons/icon-512.png">
<link rel="apple-touch-icon" href="icons/icon-192.png">
<link rel="apple-touch-icon" sizes="152x152" href="icons/icon-152.png">
<link rel="apple-touch-icon" sizes="180x180" href="icons/icon-192.png">
<?php if (LOGO_PATH && file_exists(LOGO_PATH)): ?>
  <link rel="apple-touch-icon" href="<?= LOGO_PATH ?>">
<?php endif; ?>

<style>
:root{
  --primary:#4f46e5;
  --primary-dark:#4338ca;
  --primary-container:#e0e0ff;
  --on-primary-container:#1a1a4a;
  --success:#059669;
  --success-container:#d1fae5;
  --danger:#dc2626;
  --danger-container:#fee2e2;
  --warning:#d97706;
  --warning-container:#fef3c7;
  --info:#2563eb;
  --bg:#f5f5f9;
  --surface:#ffffff;
  --surface-2:#f8f8fc;
  --surface-3:#f1f1f7;
  --border:#e5e5ef;
  --outline:#c8c8d8;
  --text:#1c1b1f;
  --muted:#6b6b80;
  --on-surface-variant:#49465a;
  --sab:env(safe-area-inset-bottom,0px);
  --sat:env(safe-area-inset-top,0px);
  --ease:cubic-bezier(.2,0,0,1);
  --ease-out:cubic-bezier(0,0,0,1);
  --shadow-1:0 1px 2px rgba(0,0,0,.06),0 1px 3px rgba(0,0,0,.08);
  --shadow-2:0 2px 6px rgba(0,0,0,.08),0 1px 2px rgba(0,0,0,.06);
  --shadow-3:0 4px 12px rgba(30,27,90,.12),0 2px 4px rgba(0,0,0,.06);
}
*{box-sizing:border-box;margin:0;padding:0;-webkit-tap-highlight-color:transparent}
html{-webkit-text-size-adjust:100%;height:100%}
body{
  font-family:'Roboto','Google Sans',system-ui,-apple-system,'Segoe UI',sans-serif;
  background:var(--bg);color:var(--text);line-height:1.45;font-size:14px;
  overscroll-behavior-y:contain;min-height:100%;
  -webkit-font-smoothing:antialiased;
}
body.lock{overflow:hidden}
svg.i{width:24px;height:24px;fill:currentColor;flex-shrink:0;display:block}

/* ===== App bar (Material 3 Top App Bar) ===== */
.appbar{
  position:sticky;top:0;z-index:100;
  background:var(--primary);color:#fff;
  padding-top:var(--sat);
  transition:box-shadow .2s var(--ease);
}
.appbar.scrolled{box-shadow:0 2px 8px rgba(30,27,90,.28)}
.appbar-in{
  display:flex;align-items:center;gap:4px;
  height:56px;padding:0 6px 0 4px;
}
.icon-btn{
  width:48px;height:48px;border-radius:50%;border:0;background:none;color:inherit;
  display:grid;place-items:center;cursor:pointer;position:relative;overflow:hidden;
  text-decoration:none;flex-shrink:0;transition:background .15s;
}
.icon-btn:active{background:rgba(255,255,255,.18)}
.appbar-title{
  flex:1;min-width:0;font-size:20px;font-weight:500;letter-spacing:.15px;
  white-space:nowrap;overflow:hidden;text-overflow:ellipsis;
  padding-left:4px;
}
.user-avatar{
  width:32px;height:32px;border-radius:50%;
  background:#fff;color:var(--primary);
  display:grid;place-items:center;font-weight:700;font-size:14px;
  box-shadow:0 1px 3px rgba(0,0,0,.12);
}

/* ===== Navigation Drawer ===== */
.scrim{
  position:fixed;inset:0;background:rgba(0,0,0,.4);
  opacity:0;pointer-events:none;transition:opacity .28s var(--ease);z-index:1100;
  backdrop-filter:blur(2px);
}
.drawer{
  position:fixed;top:0;bottom:0;left:0;width:min(86vw,304px);
  background:var(--surface);z-index:1110;
  transform:translateX(-105%);
  transition:transform .3s var(--ease-out);
  display:flex;flex-direction:column;
  border-radius:0 28px 28px 0;
  overflow-y:auto;padding-bottom:var(--sab);
  overscroll-behavior:contain;
}
body.drawer-open .drawer{transform:none;box-shadow:0 8px 40px rgba(0,0,0,.28)}
body.drawer-open .scrim{opacity:1;pointer-events:auto}
.drawer-head{
  background:linear-gradient(145deg,var(--primary) 0%,#6366f1 55%,#818cf8 100%);
  color:#fff;
  padding:calc(28px + var(--sat)) 24px 22px;
  margin-bottom:6px;
}
.drawer-logo{
  width:56px;height:56px;border-radius:16px;background:#fff;
  display:grid;place-items:center;overflow:hidden;margin-bottom:14px;
  color:var(--primary);font-size:24px;font-weight:700;
  box-shadow:0 2px 8px rgba(0,0,0,.12);
}
.drawer-logo img{width:100%;height:100%;object-fit:contain}
.drawer-name{font-size:18px;font-weight:600;line-height:1.25;letter-spacing:.1px}
.drawer-user{font-size:13px;opacity:.88;margin-top:3px;font-weight:400}
.drawer a{
  display:flex;align-items:center;gap:16px;
  height:52px;margin:2px 12px;padding:0 16px;
  border-radius:26px;color:var(--on-surface-variant);
  text-decoration:none;font-weight:500;font-size:14.5px;
  position:relative;overflow:hidden;transition:background .15s,color .15s;
}
.drawer a:active{background:var(--surface-3)}
.drawer a.active{
  background:var(--primary-container);color:var(--on-primary-container);font-weight:600;
}
.drawer a.active svg{color:var(--primary)}
.drawer hr{border:0;border-top:1px solid var(--border);margin:10px 20px}
.drawer a.out{color:#b91c1c;margin-top:auto}

/* ===== Bottom Navigation (MD3) ===== */
.bottomnav{
  position:fixed;left:0;right:0;bottom:0;z-index:900;
  background:var(--surface);
  display:flex;justify-content:space-around;
  padding:6px 4px calc(8px + var(--sab));
  border-top:1px solid var(--border);
  box-shadow:0 -1px 8px rgba(20,20,60,.05);
}
.bn-item{
  flex:1;display:flex;flex-direction:column;align-items:center;gap:2px;
  text-decoration:none;color:var(--muted);
  font-size:11px;font-weight:500;min-width:0;
  padding:4px 0;transition:color .15s;
}
.bn-ind{
  width:64px;height:32px;border-radius:16px;
  display:grid;place-items:center;
  transition:background .2s,color .2s,transform .15s;
  position:relative;overflow:hidden;
}
.bn-item.active{color:var(--on-primary-container);font-weight:600}
.bn-item.active .bn-ind{background:var(--primary-container);color:var(--on-primary-container)}
.bn-item:active .bn-ind{background:var(--surface-3);transform:scale(.96)}
.bn-label{white-space:nowrap;letter-spacing:.2px}
body.sub .bottomnav,body.kb .bottomnav,body.kb .fab{display:none}

/* ===== FAB (Extended on desktop) ===== */
.fab{
  position:fixed;right:16px;bottom:calc(80px + var(--sab));z-index:910;
  height:56px;min-width:56px;padding:0 16px;
  border-radius:16px;
  background:var(--primary-container);color:var(--on-primary-container);
  display:flex;align-items:center;justify-content:center;gap:10px;
  text-decoration:none;font-weight:600;font-size:14px;
  box-shadow:var(--shadow-3);
  overflow:hidden;transition:transform .15s,box-shadow .15s;
}
.fab:active{transform:scale(.94);box-shadow:var(--shadow-2)}
.fab .fab-label{display:none}

/* ===== Layout ===== */
.container{
  max-width:1100px;margin:0 auto;
  padding:16px 14px calc(140px + var(--sab));
  animation:pageIn .28s var(--ease);
}
body.sub .container{padding-bottom:calc(36px + var(--sab))}
@keyframes pageIn{from{opacity:0;transform:translateY(6px)}to{opacity:1;transform:none}}
h1{font-size:22px;margin-bottom:16px;font-weight:600;letter-spacing:.15px;color:var(--text)}
.card{
  background:var(--surface);border:1px solid var(--border);
  border-radius:20px;padding:16px;margin-bottom:14px;
  overflow-x:auto;box-shadow:var(--shadow-1);
}
.card h3{margin-bottom:12px;font-weight:600;font-size:15px;letter-spacing:.1px}

/* ===== Stat cards ===== */
.stat-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:10px;margin-bottom:16px}
.stat{
  background:var(--surface);border:1px solid var(--border);
  border-radius:18px;padding:14px 15px;
  box-shadow:var(--shadow-1);
  transition:transform .15s;
}
.stat:active{transform:scale(.98)}
.stat:nth-child(1){background:linear-gradient(135deg,#eff6ff,#dbeafe);border-color:transparent}
.stat:nth-child(2){background:linear-gradient(135deg,#ecfdf5,#d1fae5);border-color:transparent}
.stat:nth-child(3){background:linear-gradient(135deg,#fef2f2,#fee2e2);border-color:transparent}
.stat:nth-child(4){background:linear-gradient(135deg,#f5f3ff,#ede9fe);border-color:transparent}
.stat:nth-child(5){background:linear-gradient(135deg,#fffbeb,#fef3c7);border-color:transparent}
.stat:last-child:nth-child(odd){grid-column:1/-1}
.stat-label{
  font-size:11px;color:var(--muted);letter-spacing:.4px;
  font-weight:600;text-transform:uppercase;
}
.stat-value{font-size:18px;font-weight:700;margin-top:6px;word-break:break-word;letter-spacing:-.2px}
.stat-value.green{color:#047857}.stat-value.red{color:#b91c1c}
.stat-value.orange{color:#b45309}.stat-value.blue{color:#1d4ed8}

/* ===== Tables ===== */
table{width:100%;border-collapse:collapse;font-size:13.5px;min-width:580px}
th{
  text-align:left;padding:11px 12px;background:var(--surface-3);
  color:var(--muted);font-size:11px;font-weight:600;
  letter-spacing:.35px;text-transform:uppercase;white-space:nowrap;
}
th:first-child{border-radius:12px 0 0 12px}th:last-child{border-radius:0 12px 12px 0}
td{padding:11px 12px;border-bottom:1px solid #eeeef4;vertical-align:middle}
tr:last-child td{border-bottom:0}
tr:hover td{background:#fafaff}

/* ===== Buttons ===== */
.btn{
  position:relative;overflow:hidden;
  display:inline-flex;align-items:center;justify-content:center;gap:6px;
  padding:0 20px;height:42px;border-radius:21px;
  border:1px solid transparent;cursor:pointer;
  font-size:14px;font-weight:600;letter-spacing:.2px;
  text-decoration:none;transition:box-shadow .15s,background .15s,filter .1s;
  font-family:inherit;white-space:nowrap;
}
.btn:active{filter:brightness(.94)}
.btn-primary{
  background:var(--primary);color:#fff;
  box-shadow:0 1px 3px rgba(79,70,229,.35);
}
.btn-primary:hover{background:var(--primary-dark)}
.btn-success{background:var(--success);color:#fff}
.btn-danger{background:var(--danger);color:#fff}
.btn-warning{background:#f59e0b;color:#422006}
.btn-outline{
  background:transparent;color:var(--primary);border-color:var(--outline);
}
.btn-outline:hover{background:var(--primary-container)}
.btn-sm{height:32px;padding:0 12px;font-size:12.5px;border-radius:16px}
.badge{
  display:inline-flex;align-items:center;height:24px;padding:0 10px;
  border-radius:8px;font-size:11px;font-weight:600;text-transform:capitalize;
}
.badge-lunas{background:var(--success-container);color:#065f46}
.badge-hutang{background:var(--warning-container);color:#92400e}
.badge-danger{background:var(--danger-container);color:#991b1b}

/* ===== Forms ===== */
.form-group{margin-bottom:14px}
.form-group label{
  display:block;font-size:12.5px;font-weight:600;
  margin-bottom:6px;color:var(--on-surface-variant);
}
.form-control{
  width:100%;padding:12px 14px;
  border:1.5px solid var(--outline);border-radius:14px;
  font-size:16px;font-family:inherit;background:var(--surface);
  min-height:50px;transition:border-color .15s,box-shadow .15s;
  color:var(--text);
}
.form-control:focus{
  outline:none;border-color:var(--primary);
  box-shadow:0 0 0 3px rgba(79,70,229,.15);
}
.form-control:disabled{background:var(--surface-3);color:var(--muted)}
select.form-control{appearance:none;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3E%3Cpath fill='%236b6b80' d='M1.41 0 6 4.58 10.59 0 12 1.41l-6 6-6-6z'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 14px center;padding-right:36px}
.row{display:grid;grid-template-columns:1fr;gap:12px}
.alert{padding:12px 16px;border-radius:14px;margin-bottom:14px;font-size:14px;font-weight:500}
.alert-success{background:var(--success-container);color:#065f46}
.alert-danger{background:var(--danger-container);color:#991b1b}

.text-right{text-align:right}.text-center{text-align:center}
.text-muted{color:var(--muted)}.fw-bold{font-weight:700}
.mb-2{margin-bottom:8px}.mt-2{margin-top:8px}
.d-flex{display:flex;gap:8px;align-items:center;flex-wrap:wrap}
.justify-between{justify-content:space-between}
.btn-wa-sm{position:relative;overflow:hidden}

/* ===== Native list cards (mobile) ===== */
.list-card{
  background:var(--surface);border:1px solid var(--border);
  border-radius:16px;padding:14px 16px;margin-bottom:10px;
  box-shadow:var(--shadow-1);display:block;text-decoration:none;color:inherit;
  transition:transform .12s,box-shadow .12s;
}
.list-card:active{transform:scale(.985);box-shadow:none}
.list-card-top{display:flex;justify-content:space-between;align-items:flex-start;gap:10px;margin-bottom:6px}
.list-card-title{font-size:15px;font-weight:600;color:var(--text);line-height:1.3}
.list-card-meta{font-size:12.5px;color:var(--muted);margin-top:2px}
.list-card-row{display:flex;justify-content:space-between;align-items:center;margin-top:8px;padding-top:8px;border-top:1px solid var(--border)}
.list-card-amount{font-size:15px;font-weight:700}
.list-card-actions{display:flex;gap:6px;flex-wrap:wrap;margin-top:10px}

/* ===== Search / filter bar ===== */
.filter-bar{
  display:flex;flex-direction:column;gap:10px;margin-bottom:14px;
}
.filter-bar .form-control{min-height:46px}

/* ===== Snackbar ===== */
.snackbar{
  position:fixed;left:12px;right:12px;bottom:calc(84px + var(--sab));
  z-index:1300;max-width:520px;margin:0 auto;
  background:#2d2d3a;color:#fff;border-radius:12px;
  padding:14px 16px;display:flex;align-items:center;gap:12px;
  font-size:14px;box-shadow:0 6px 20px rgba(0,0,0,.28);
  animation:snackIn .28s var(--ease);
}
.snackbar.err{background:#7f1d1d}
.snackbar span{flex:1}
.snackbar button{
  background:none;border:0;color:#c7d2fe;font-weight:700;
  font-size:13px;cursor:pointer;font-family:inherit;padding:4px 8px;
}
.snackbar.hide{opacity:0;transform:translateY(12px);transition:.25s}
@keyframes snackIn{from{opacity:0;transform:translateY(16px)}to{opacity:1;transform:none}}
body.sub .snackbar{bottom:calc(20px + var(--sab))}

/* ===== Ripple ===== */
.rip{
  position:absolute;border-radius:50%;background:currentColor;
  opacity:.14;transform:scale(0);animation:rip .5s ease-out forwards;
  pointer-events:none;
}
@keyframes rip{to{transform:scale(1);opacity:0}}

/* ===== Footer ===== */
.app-footer{text-align:center;padding:8px 0 24px;color:#9a9ab0;font-size:12px}

/* ===== Empty state ===== */
.empty-state{
  text-align:center;padding:36px 20px;color:var(--muted);
}
.empty-state .empty-icon{font-size:40px;margin-bottom:10px;opacity:.7}
.empty-state p{font-size:14px;margin-bottom:14px}

/* ===== Mobile: table -> cards ===== */
@media (max-width:767px){
  h1{display:none}
  .container>h1,.container h1{display:none}
  .app-footer{display:none}
  .card{padding:14px;border-radius:18px}
  table.m-cards{min-width:0!important;display:block}
  table.m-cards thead{display:none}
  table.m-cards tbody,table.m-cards tfoot{display:block}
  table.m-cards tr{
    display:flex;flex-direction:column;
    background:var(--surface-2);border-radius:16px;
    padding:12px 14px;margin-bottom:10px;
    border:1px solid var(--border);
  }
  table.m-cards td{
    display:flex;justify-content:space-between;align-items:center;
    gap:12px;padding:4px 0;border:0;text-align:right!important;
    background:none!important;min-height:24px;font-size:13.5px;
  }
  table.m-cards td::before{
    content:attr(data-label);color:var(--muted);
    font-size:12px;font-weight:500;text-align:left;flex-shrink:0;
  }
  table.m-cards td.m-title{
    justify-content:flex-start;font-size:15px;font-weight:600;
    padding-bottom:6px;text-align:left!important;
  }
  table.m-cards td.m-title::before,
  table.m-cards td:not([data-label])::before{display:none}
  table.m-cards td:not([data-label]):not(.m-title){
    justify-content:flex-end;flex-wrap:wrap;margin-top:6px;
    padding-top:10px;border-top:1px solid var(--border);
  }
  table.m-cards td[colspan]{justify-content:center!important}
  .has-fab a.btn[href="transaksi_form.php"]{display:none}
  .justify-between{flex-wrap:nowrap}
  .desktop-only{display:none!important}
  .mobile-only{display:block!important}
}
@media (min-width:768px){
  .mobile-only{display:none!important}
}

/* ===== Phones ===== */
@media (max-width:480px){
  form>.btn{width:100%;margin-bottom:8px}
  .stat-value{font-size:16px}
}

/* ===== Tablet/Desktop: permanent drawer ===== */
@media (min-width:900px){
  .app-shell{padding-left:288px}
  .drawer{
    transform:none!important;box-shadow:none;
    border-radius:0;border-right:1px solid var(--border);width:288px;
  }
  .scrim{display:none}
  .appbar-menu,.bottomnav{display:none!important}
  .appbar-in{padding-left:12px}
  .container{padding:24px 28px 100px}
  h1{display:block!important}
  .row{grid-template-columns:repeat(auto-fit,minmax(220px,1fr))}
  .stat-grid{grid-template-columns:repeat(auto-fit,minmax(180px,1fr))}
  .stat:last-child:nth-child(odd){grid-column:auto}
  .fab{bottom:28px;right:28px;padding:0 20px 0 16px;border-radius:16px}
  .fab .fab-label{display:inline}
  .snackbar{bottom:24px}
  .app-footer{display:block}
  .btn{height:40px}
  .filter-bar{flex-direction:row;align-items:flex-end}
  .filter-bar .form-group{flex:1;margin-bottom:0}
}

@media (min-width:768px) and (max-width:899px){
  .row{grid-template-columns:repeat(auto-fit,minmax(220px,1fr))}
  .stat-grid{grid-template-columns:repeat(3,1fr)}
}

/* ============ PWA INSTALL BANNER ============ */
#pwaBanner{
  display:none;position:fixed;bottom:calc(88px + var(--sab));left:14px;right:14px;
  max-width:420px;margin:0 auto;background:var(--surface);
  border:1px solid var(--border);border-radius:20px;
  padding:14px 16px;box-shadow:var(--shadow-3);
  z-index:1500;align-items:center;gap:12px;
  animation:pwaSlide .3s var(--ease-out);
}
@keyframes pwaSlide{from{opacity:0;transform:translateY(20px)}to{opacity:1;transform:translateY(0)}}
#pwaBanner.show{display:flex}
#pwaBanner .pwa-icon{
  width:44px;height:44px;flex-shrink:0;border-radius:12px;
  background:linear-gradient(135deg,var(--primary),#818cf8);
  display:grid;place-items:center;color:#fff;font-weight:700;font-size:18px;
}
#pwaBanner .pwa-info{flex:1;min-width:0}
#pwaBanner .pwa-title{font-size:13.5px;font-weight:700;color:var(--text);margin-bottom:2px;line-height:1.3}
#pwaBanner .pwa-sub{font-size:12px;color:var(--muted);line-height:1.4}
#pwaBanner .pwa-actions{display:flex;gap:6px;flex-shrink:0;align-items:center}
#pwaBanner button{border:none;cursor:pointer;font-family:inherit;font-weight:600;border-radius:10px;transition:.15s}
#pwaBanner .pwa-install{background:var(--primary);color:#fff;padding:9px 16px;font-size:13px}
#pwaBanner .pwa-install:active{filter:brightness(.92)}
#pwaBanner .pwa-dismiss{
  background:transparent;color:var(--muted);padding:6px 8px;font-size:18px;
  line-height:1;border-radius:8px;
}
#pwaBanner .pwa-dismiss:active{background:var(--surface-3)}
@media (min-width:900px){
  #pwaBanner{bottom:24px;left:auto;right:100px;margin:0}
}
</style>
</head>
<?php
$ICO = [
  'dash' => 'M3 13h8V3H3v10zm0 8h8v-6H3v6zm10 0h8V11h-8v10zm0-18v6h8V3h-8z',
  'rcpt' => 'M19.5 3.5 18 2l-1.5 1.5L15 2l-1.5 1.5L12 2l-1.5 1.5L9 2 7.5 3.5 6 2v14H3v3c0 1.66 1.34 3 3 3h12c1.66 0 3-1.34 3-3V2l-1.5 1.5zM19 19c0 .55-.45 1-1 1s-1-.45-1-1v-3H8V5h11v14zM9 7h6v2H9zm7 0h2v2h-2zm-7 3h6v2H9zm7 0h2v2h-2z',
  'link' => 'M3.9 12c0-1.71 1.39-3.1 3.1-3.1h4V7H7c-2.76 0-5 2.24-5 5s2.24 5 5 5h4v-1.9H7c-1.71 0-3.1-1.39-3.1-3.1zM8 13h8v-2H8v2zm9-6h-4v1.9h4c1.71 0 3.1 1.39 3.1 3.1s-1.39 3.1-3.1 3.1h-4V17h4c2.76 0 5-2.24 5-5s-2.24-5-5-5z',
  'ppl'  => 'M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5s-3 1.34-3 3 1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5zm8 0c-.29 0-.62.02-.97.05 1.16.84 1.97 1.97 1.97 3.45V19h6v-2.5c0-2.33-4.67-3.5-7-3.5z',
  'wal'  => 'M21 18v1c0 1.1-.9 2-2 2H5c-1.11 0-2-.9-2-2V5c0-1.1.89-2 2-2h14c1.1 0 2 .9 2 2v1h-9c-1.11 0-2 .9-2 2v8c0 1.1.89 2 2 2h9zm-9-2h10V8H12v8zm4-2.5c-.83 0-1.5-.67-1.5-1.5s.67-1.5 1.5-1.5 1.5.67 1.5 1.5-.67 1.5-1.5 1.5z',
  'bar'  => 'M5 9.2h3V19H5zM10.6 5h2.8v14h-2.8zm5.6 8H19v6h-2.8z',
  'save' => 'M17 3H5c-1.11 0-2 .9-2 2v14c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V7l-4-4zm-5 16c-1.66 0-3-1.34-3-3s1.34-3 3-3 3 1.34 3 3-1.34 3-3 3zm3-10H5V5h10v4z',
  'cog'  => 'M19.14 12.94c.04-.3.06-.61.06-.94 0-.32-.02-.64-.07-.94l2.03-1.58c.18-.14.23-.41.12-.61l-1.92-3.32c-.12-.22-.37-.29-.59-.22l-2.39.96c-.5-.38-1.03-.7-1.62-.94l-.36-2.54c-.04-.24-.24-.41-.48-.41h-3.84c-.24 0-.43.17-.47.41l-.36 2.54c-.59.24-1.13.57-1.62.94l-2.39-.96c-.22-.08-.47 0-.59.22L2.74 8.87c-.12.21-.08.47.12.61l2.03 1.58c-.05.3-.09.63-.09.94s.02.64.07.94l-2.03 1.58c-.18.14-.23.41-.12.61l1.92 3.32c.12.22.37.29.59.22l2.39-.96c.5.38 1.03.7 1.62.94l.36 2.54c.05.24.24.41.48.41h3.84c.24 0 .44-.17.47-.41l.36-2.54c.59-.24 1.13-.56 1.62-.94l2.39.96c.22.08.47 0 .59-.22l1.92-3.32c.12-.22.07-.47-.12-.61l-2.01-1.58zM12 15.6c-1.98 0-3.6-1.62-3.6-3.6s1.62-3.6 3.6-3.6 3.6 1.62 3.6 3.6-1.62 3.6-3.6 3.6z',
  'out'  => 'M17 7l-1.41 1.41L18.17 11H8v2h10.17l-2.58 2.58L17 17l5-5zM4 5h8V3H4c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h8v-2H4V5z',
  'more' => 'M6 10c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2zm12 0c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2zm-6 0c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2z',
  'menu' => 'M3 18h18v-2H3v2zm0-5h18v-2H3v2zm0-7v2h18V6H3z',
  'back' => 'M20 11H7.83l5.59-5.59L12 4l-8 8 8 8 1.41-1.41L7.83 13H20v-2z',
  'add'  => 'M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z',
];
if (!function_exists('ico')) {
  function ico($n) { global $ICO; return '<svg class="i" viewBox="0 0 24 24" aria-hidden="true"><path d="'.$ICO[$n].'"/></svg>'; }
}
$cur = basename($_SERVER['PHP_SELF']);
$NAV = [
  ['index.php',       'Dashboard',     'dash', ['index.php']],
  ['transaksi.php',   'Transaksi',     'rcpt', ['transaksi.php','transaksi_form.php','transaksi_view.php']],
  ['gabung_nota.php', 'Gabung Hutang', 'link', ['gabung_nota.php','nota_gabung.php']],
  ['pelanggan.php',   'Pelanggan',     'ppl',  ['pelanggan.php']],
  ['pengeluaran.php', 'Pengeluaran',   'wal',  ['pengeluaran.php']],
  ['laporan.php',     'Laporan',       'bar',  ['laporan.php']],
  ['backup.php',      'Backup',        'save', ['backup.php']],
  ['pengaturan.php',  'Pengaturan',    'cog',  ['pengaturan.php']],
];
$BOTTOM = ['index.php','transaksi.php','pelanggan.php','laporan.php'];
$bottomPages = [];
foreach ($NAV as $n) if (in_array($n[0], $BOTTOM)) $bottomPages = array_merge($bottomPages, $n[3]);
$moreActive = !in_array($cur, $bottomPages);
$subBack = ['transaksi_form.php'=>'transaksi.php','transaksi_view.php'=>'transaksi.php','nota_gabung.php'=>'gabung_nota.php'];
$isSub   = isset($subBack[$cur]);
$hasFab  = in_array($cur, ['index.php','transaksi.php']);
$appTitle = $title ?? NAMA_TOKO;
$namaUser = $_SESSION['nama'] ?? '';
?>
<body class="<?= $isSub ? 'sub' : '' ?> <?= $hasFab ? 'has-fab' : '' ?>">
<div class="scrim" id="scrim"></div>

<aside class="drawer" id="drawer" aria-label="Menu">
  <div class="drawer-head">
    <div class="drawer-logo">
      <?php if (LOGO_PATH && file_exists(LOGO_PATH)): ?><img src="<?= LOGO_PATH ?>" alt="Logo"><?php else: ?><?= htmlspecialchars(strtoupper(mb_substr(NAMA_TOKO, 0, 1))) ?><?php endif; ?>
    </div>
    <div class="drawer-name"><?= htmlspecialchars(NAMA_TOKO) ?></div>
    <?php if ($namaUser): ?><div class="drawer-user"><?= htmlspecialchars($namaUser) ?></div><?php endif; ?>
  </div>
  <?php foreach ($NAV as $i => $n): ?>
    <?php if ($i === 6): ?><hr><?php endif; ?>
    <a href="<?= $n[0] ?>" class="<?= in_array($cur, $n[3]) ? 'active' : '' ?>"><?= ico($n[2]) ?><?= $n[1] ?></a>
  <?php endforeach; ?>
  <a href="logout.php" class="out" onclick="return confirm('Yakin ingin keluar?')"><?= ico('out') ?>Keluar</a>
</aside>

<div class="app-shell">
  <header class="appbar" id="appbar">
    <div class="appbar-in">
      <?php if ($isSub): ?>
        <a class="icon-btn" href="<?= $subBack[$cur] ?>" aria-label="Kembali"><?= ico('back') ?></a>
      <?php else: ?>
        <button class="icon-btn appbar-menu" id="menuBtn" aria-label="Menu"><?= ico('menu') ?></button>
      <?php endif; ?>
      <div class="appbar-title"><?= htmlspecialchars($appTitle) ?></div>
      <button class="icon-btn" onclick="openDrawer()" aria-label="Akun"><span class="user-avatar"><?= htmlspecialchars(strtoupper(mb_substr($namaUser ?: 'U', 0, 1))) ?></span></button>
    </div>
  </header>

<?php if ($hasFab): ?>
  <a href="transaksi_form.php" class="fab" aria-label="Transaksi baru"><?= ico('add') ?><span class="fab-label">Transaksi Baru</span></a>
<?php endif; ?>

<nav class="bottomnav" aria-label="Navigasi utama">
  <?php foreach ($NAV as $n): if (!in_array($n[0], $BOTTOM)) continue; $act = in_array($cur, $n[3]); ?>
    <a href="<?= $n[0] ?>" class="bn-item <?= $act ? 'active' : '' ?>"><span class="bn-ind"><?= ico($n[2]) ?></span><span class="bn-label"><?= $n[1] ?></span></a>
  <?php endforeach; ?>
  <a href="#" class="bn-item <?= $moreActive ? 'active' : '' ?>" onclick="openDrawer();return false"><span class="bn-ind"><?= ico('more') ?></span><span class="bn-label">Lainnya</span></a>
</nav>

<main class="container">
<?php if ($f = getFlash()): ?>
  <div class="snackbar <?= $f['type']=='success'?'':'err' ?>" id="snack" role="status"><span><?= htmlspecialchars($f['msg']) ?></span><button onclick="hideSnack()">OK</button></div>
<?php endif; ?>

<!-- ============ PWA INSTALL BANNER ============ -->
<div id="pwaBanner" role="dialog" aria-label="Install aplikasi">
  <div class="pwa-icon"><?= strtoupper(mb_substr(NAMA_TOKO, 0, 1)) ?></div>
  <div class="pwa-info">
    <div class="pwa-title">Install ke Home Screen?</div>
    <div class="pwa-sub" id="pwaBannerSub">Buka lebih cepat & bisa offline</div>
  </div>
  <div class="pwa-actions">
    <button class="pwa-install" id="pwaInstallBtn" onclick="pwaInstall()">Install</button>
    <button class="pwa-dismiss" onclick="pwaDismiss()" title="Tutup" aria-label="Tutup">×</button>
  </div>
</div>

<script>
/* ============ PWA: SIMPAN NAMA TOKO UNTUK OFFLINE PAGE ============ */
try { localStorage.setItem('pwa_shop_name', <?= json_encode(NAMA_TOKO) ?>); } catch(e) {}

/* ============ PWA: SERVICE WORKER REGISTRATION ============ */
if ('serviceWorker' in navigator) {
  window.addEventListener('load', () => {
    navigator.serviceWorker.register('service-worker.js')
      .then(reg => console.log('[PWA] Service Worker registered:', reg.scope))
      .catch(err => console.warn('[PWA] SW registration failed:', err));
  });
}

/* ============ PWA: INSTALL PROMPT ============ */
let deferredPrompt = null;
const isStandalone = window.matchMedia('(display-mode: standalone)').matches
                  || window.navigator.standalone === true;
const isIos = /iphone|ipad|ipod/i.test(navigator.userAgent);
const isSafari = /^((?!chrome|crios|fxios|edgios|android).)*safari/i.test(navigator.userAgent);
const dismissed = (() => { try { return localStorage.getItem('pwa_dismissed') === '1'; } catch(e){ return false; } })();

window.addEventListener('beforeinstallprompt', (e) => {
  e.preventDefault();
  deferredPrompt = e;
  if (!isStandalone && !dismissed) showPwaBanner();
});

window.addEventListener('appinstalled', () => {
  try { localStorage.setItem('pwa_dismissed', '1'); } catch(e) {}
  hidePwaBanner();
  deferredPrompt = null;
});

/* iOS Safari tidak fire beforeinstallprompt — tampilkan hint manual */
if (isIos && isSafari && !isStandalone && !dismissed) {
  window.addEventListener('load', () => {
    setTimeout(() => {
      const sub = document.getElementById('pwaBannerSub');
      const btn = document.getElementById('pwaInstallBtn');
      if (sub) sub.textContent = 'Tap Share → "Add to Home Screen"';
      if (btn) btn.textContent = 'OK';
      showPwaBanner();
    }, 1500);
  });
}

function showPwaBanner() {
  const b = document.getElementById('pwaBanner');
  if (b) b.classList.add('show');
}
function hidePwaBanner() {
  const b = document.getElementById('pwaBanner');
  if (b) b.classList.remove('show');
}
function pwaDismiss() {
  try { localStorage.setItem('pwa_dismissed', '1'); } catch(e) {}
  hidePwaBanner();
}
async function pwaInstall() {
  if (!deferredPrompt) {
    // iOS / browser tanpa prompt — arahkan manual atau tutup
    if (isIos) {
      try { localStorage.setItem('pwa_dismissed', '1'); } catch(e) {}
      hidePwaBanner();
      return;
    }
    hidePwaBanner();
    return;
  }
  deferredPrompt.prompt();
  try {
    await deferredPrompt.userChoice;
  } catch(e) {}
  deferredPrompt = null;
  hidePwaBanner();
}
</script>