<?php
/**
 * theme_config.php - Configuración centralizada de tema
 *
 * Se ejecuta SINCRÓNICAMENTE en <head>.
 * Lee localStorage → setea data-theme + CSS vars + TODAS las reglas accent.
 * CERO flash porque todo va en el <head> antes del paint.
 */
?>
<script>
(function(){
try{
var s=localStorage;
function g(k,d){try{var v=s.getItem(k);return v!==null?v:d;}catch(e){return d;}}
var perDev=(s.getItem('transnubet_per_device')||'true')!=='false';
var manualDev=s.getItem('transnubet_device_manual');
if(!(manualDev==='pc'||manualDev==='tableta'||manualDev==='movil'))manualDev=null;
/* Clasificacion estable del dispositivo: el lado corto de la PANTALLA no depende
   del <meta viewport>. Evita que en moviles el arranque mida ~980 px y lea las
   preferencias de '@tableta' cuando el panel las guarda en '@movil'. */
function clasificarDispositivo(){
    var sw=(window.screen&&window.screen.width)||0;
    var sh=(window.screen&&window.screen.height)||0;
    var ladoCorto=(sw>0&&sh>0)?Math.min(sw,sh):0;
    if(ladoCorto>0&&ladoCorto<=520)return'movil';
    var w=window.innerWidth||document.documentElement.clientWidth;
    return w<=768?'movil':(w<=1024?'tableta':'pc');
}
var DEV=clasificarDispositivo();
if(!perDev&&manualDev)DEV=manualDev;
function gd(k,def){
    var kk=(k!=='transnubet_per_device'&&k!=='transnubet_device_manual')?k+'@'+DEV:k;
    var v=s.getItem(kk);
    if(v===null&&kk!==k)v=s.getItem(k);
    return v!==null?v:def;
}
var theme=gd('transnubet_theme','dark');
if(theme!=='light'&&theme!=='dark'&&theme!=='blue'&&theme!=='verde'&&theme!=='orgullo')theme='dark';
var accDef={dark:'blue',light:'blue',blue:'cyan',verde:'green',orgullo:'purple'};
var accent=gd('transnubet_accent_'+theme,null)||accDef[theme]||'blue';
var radius=gd('transnubet_radius','10');
var fontSize=gd('transnubet_font_size','1.0');
var density=gd('transnubet_density','normal');
var sidebarCompact=gd('transnubet_sidebar_compact','false');
var sidebarOpacity=gd('transnubet_sidebar_opacity','95');
var contentWidth=gd('transnubet_content_width','100');
var animations=gd('transnubet_animations','true');
var focusMode=gd('transnubet_focus_mode','false');
var tooltips=gd('transnubet_tooltips','true');

var accents={
  blue:{c:'#3b82f6',r:'59,130,246',d:'#2563eb',l:'#93c5fd',bg:'rgba(59,130,246,0.12)',bg2:'rgba(59,130,246,0.06)'},
  purple:{c:'#8b5cf6',r:'139,92,246',d:'#7c3aed',l:'#c4b5fd',bg:'rgba(139,92,246,0.12)',bg2:'rgba(139,92,246,0.06)'},
  green:{c:'#10b981',r:'16,185,129',d:'#059669',l:'#6ee7b7',bg:'rgba(16,185,129,0.12)',bg2:'rgba(16,185,129,0.06)'},
  amber:{c:'#f59e0b',r:'245,158,11',d:'#d97706',l:'#fcd34d',bg:'rgba(245,158,11,0.12)',bg2:'rgba(245,158,11,0.06)'},
  red:{c:'#ef4444',r:'239,68,68',d:'#dc2626',l:'#fca5a5',bg:'rgba(239,68,68,0.12)',bg2:'rgba(239,68,68,0.06)'},
  cyan:{c:'#06b6d4',r:'6,182,212',d:'#0891b2',l:'#67e8f9',bg:'rgba(6,182,212,0.12)',bg2:'rgba(6,182,212,0.06)'},
  teal:{c:'#14b8a6',r:'20,184,166',d:'#0d9488',l:'#5eead4',bg:'rgba(20,184,166,0.12)',bg2:'rgba(20,184,166,0.06)'},
  pink:{c:'#ec4899',r:'236,72,153',d:'#db2777',l:'#f9a8d4',bg:'rgba(236,72,153,0.12)',bg2:'rgba(236,72,153,0.06)'},
  indigo:{c:'#6366f1',r:'99,102,241',d:'#4f46e5',l:'#a5b4fc',bg:'rgba(99,102,241,0.12)',bg2:'rgba(99,102,241,0.06)'},
  rose:{c:'#f43f5e',r:'244,63,94',d:'#e11d48',l:'#fda4af',bg:'rgba(244,63,94,0.12)',bg2:'rgba(244,63,94,0.06)'},
  orange:{c:'#f97316',r:'249,115,22',d:'#ea580c',l:'#fdba74',bg:'rgba(249,115,22,0.12)',bg2:'rgba(249,115,22,0.06)'},
  lime:{c:'#84cc16',r:'132,204,22',d:'#65a30d',l:'#bef264',bg:'rgba(132,204,22,0.12)',bg2:'rgba(132,204,22,0.06)'},
  fuchsia:{c:'#d946ef',r:'217,70,239',d:'#c026d3',l:'#f0abfc',bg:'rgba(217,70,239,0.12)',bg2:'rgba(217,70,239,0.06)'},
  slate:{c:'#64748b',r:'100,116,139',d:'#475569',l:'#94a3b8',bg:'rgba(100,116,139,0.12)',bg2:'rgba(100,116,139,0.06)'},
  forest:{c:'#228b22',r:'34,139,34',d:'#186618',l:'#5cb85c',bg:'rgba(34,139,34,0.12)',bg2:'rgba(34,139,34,0.06)'},
  navy:{c:'#1e3a8a',r:'30,58,138',d:'#172554',l:'#60a5fa',bg:'rgba(30,58,138,0.12)',bg2:'rgba(30,58,138,0.06)'}
};
var ac=accents[accent]||accents.blue;
var dens={compact:'0.5rem',normal:'0.75rem',comfortable:'1rem'};
var gap=dens[density]||'0.75rem';
var swFull=sidebarCompact==='true'?'5rem':'16.5625rem';
var cw=contentWidth==='100'?'100%':contentWidth+'px';
var sop=(parseInt(sidebarOpacity,10)||95)/100;
var anim=animations==='true';
var fs=parseFloat(fontSize)||1;
var fm=focusMode==='true';
var d=document.documentElement;

d.setAttribute('data-theme',theme);
d.setAttribute('data-device',DEV);
if(!perDev&&manualDev){d.setAttribute('data-device-manual','1');}else{d.removeAttribute('data-device-manual');}
if(theme==='dark'||theme==='blue'||theme==='verde'||theme==='orgullo'){d.classList.add('dark');}else{d.classList.remove('dark');}
if(fm){d.classList.add('focus-mode');}else{d.classList.remove('focus-mode');}
if(!anim){d.classList.add('no-animations');}else{d.classList.remove('no-animations');}
if(tooltips!=='true'){d.classList.add('no-tooltips');}else{d.classList.remove('no-tooltips');}

var c=':root{'
+'--accent:'+ac.c+';'
+'--accent-rgb:'+ac.r+';'
+'--accent-dark:'+ac.d+';'
+'--accent-light:'+ac.l+';'
+'--accent-bg:'+ac.bg+';'
+'--accent-bg2:'+ac.bg2+';'
+'--radius:'+radius+'px;'
+'--font-scale:'+fs+';'
+'--density-gap:'+gap+';'
+'--win-sidebar-width:'+swFull+';'
+'--win-sidebar-collapsed-width:4.6875rem;'
+'--sidebar-bg-opacity:'+sop+';'
+'--content-max-width:'+cw+';'
+'}'
+'.win-sidebar{background:rgba(16,16,22,'+sop+')!important;width:'+swFull+'!important;}'
+'html[data-theme="light"] .win-sidebar{background:rgba(255,255,255,'+sop+')!important;}'
+'html[data-theme="blue"] .win-sidebar{background:rgba(12,22,42,'+sop+')!important;}'
+'.win-sidebar.collapsed{width:4.6875rem!important;}'
+'.main-container{margin-left:'+swFull+'!important;transition:margin-left 0.3s ease!important;}'
+'body:has(.win-sidebar.collapsed) .main-container{margin-left:4.6875rem!important;}'
+'html.focus-mode .win-sidebar{transform:translateX(-100%)!important;}'
+'html.focus-mode .main-container{margin-left:0!important;}'
+'html.focus-mode .date-badge{display:none!important;}'
+'html.focus-mode .page-title p{display:none!important;}'
+'html.focus-mode .win-sidebar:hover,html.focus-mode .win-sidebar:focus-within,html.focus-mode body:has(.focus-tab:hover) .win-sidebar,html.focus-mode .win-sidebar.focus-pinned,html.focus-mode .win-sidebar.mobile-open{transform:translateX(0)!important;}'
+'html.focus-mode .focus-tab{display:flex!important;}'
+'.win-sidebar.focus-pinned+.focus-tab{opacity:1;color:var(--accent);}.win-sidebar.focus-pinned+.focus-tab i,html.focus-mode .win-sidebar:hover+.focus-tab i,html.focus-mode body:has(.focus-tab:hover) .win-sidebar+.focus-tab i{transform:rotate(180deg);}'
+'html.focus-mode .main-container{margin-left:0!important;margin-right:0!important;width:auto!important;max-width:none!important;}'
+'html.focus-mode .main-container>*{max-width:none!important;}'
+'html.focus-mode .page-content,html.focus-mode .fluid-container{max-width:none!important;}'
+'html.focus-mode .win-sidebar.focus-pinned .sidebar-close-focus{display:flex!important;background:rgba(255,255,255,0.08);color:#94a3b8;}'
+'html.focus-mode .win-sidebar.focus-pinned .sidebar-close-focus:hover{background:rgba(var(--accent-rgb),0.18)!important;color:var(--accent)!important;}'
+'[data-theme="light"] .sidebar-close-focus{background:rgba(0,0,0,0.06);color:#64748b;}'
+'.main-container.expanded{width:auto!important;max-width:none!important;}'
+'.main-container.expanded>*{max-width:none!important;}'
+'@media (max-width:768px){.main-container,.page-content,.fluid-container{max-width:none!important;}}'
+'.main-container>.glass-card,.main-container>.mb-3,.main-container>.fade-in-up{max-width:calc('+cw+' - 2.5rem)!important;}'
+'body{font-size:calc(1rem * '+fs+')!important;}'
+'.page-title h1{font-size:calc(1.5rem * '+fs+')!important;}';

/* ===== TOOLTIPS ON/OFF ===== */
c+='html.no-tooltips [data-tooltip]::after,html.no-tooltips [data-tooltip]::before{display:none!important;content:none!important;}';
c+='html.no-tooltips .data-tooltip-js{display:none!important;}';

/* ===== DARK THEME ACCENT ===== */
var H='html ';

/* Sidebar active */
c+=H+'.nav-item.active{background:var(--accent)!important;color:#fff!important;box-shadow:0 0.125rem 0.5rem rgba(var(--accent-rgb),0.3)!important;}';
c+=H+'.nav-item.active i{color:#fff!important;}';
c+=H+'.nav-item.active .sidebar-text{color:#fff!important;}';
c+=H+'.nav-item.active .nav-badge{background:rgba(255,255,255,0.25)!important;color:#fff!important;}';
c+=H+'.nav-item.active .nav-group-chevron{color:#fff!important;}';
/* Sidebar hover */
c+=H+'.nav-item:hover{background:var(--accent-bg)!important;color:var(--accent)!important;}';
c+=H+'.nav-item:hover i{color:var(--accent)!important;}';
/* Override: active+hover must keep white text */
c+=H+'.nav-item.active:hover{background:var(--accent-dark)!important;color:#fff!important;}';
c+=H+'.nav-item.active:hover i{color:#fff!important;}';
c+=H+'.nav-item.active:hover .sidebar-text{color:#fff!important;}';
c+=H+'.nav-item.active:hover .nav-badge{background:rgba(255,255,255,0.25)!important;color:#fff!important;}';
c+=H+'.nav-item.active:hover .nav-group-chevron{color:#fff!important;}';
c+=H+'.sidebar-logo h3{background:linear-gradient(135deg,var(--accent),var(--accent-light));-webkit-background-clip:text;background-clip:text;color:transparent!important;}';
c+=H+'.sidebar-toggle:hover{background:rgba(var(--accent-rgb),0.15)!important;color:var(--accent)!important;}';
c+=H+'.nav-group:hover .nav-group-chevron{color:var(--accent)!important;}';
c+=H+'.nav-group.open .nav-group-chevron{color:var(--accent)!important;}';
c+=H+'.sidebar-profile:hover{background:var(--accent-bg2)!important;border-color:var(--accent-bg)!important;}';
c+=H+'.profile-avatar-link:hover .profile-avatar{border-color:var(--accent)!important;box-shadow:0 0 0 0.1875rem var(--accent-bg)!important;}';
/* Page title */
c+=H+'.page-title h1 i{color:var(--accent)!important;}';
c+=H+'.page-title p{color:var(--muted)!important;}';
/* Glass card */
c+=H+'.glass-card:hover{border-color:rgba(var(--accent-rgb),0.3)!important;box-shadow:0 0.75rem 2.5rem rgba(0,0,0,0.35),0 0 0 0.0625rem rgba(var(--accent-rgb),0.15)!important;transform:translateY(-0.125rem)!important;}';
/* Card titles */
c+=H+'.card-content h5{color:var(--txt)!important;}';
c+=H+'.card-content h5 i{color:var(--accent)!important;}';
c+=H+'.card-content p{color:var(--muted)!important;}';
/* Links */
c+=H+'a{color:var(--accent)!important;}';
c+=H+'a:hover{color:var(--accent-light)!important;}';
/* Buttons */
c+=H+'.btn-win-primary{background:linear-gradient(135deg,var(--accent),var(--accent-dark))!important;border:none!important;color:#fff!important;}';
c+=H+'.btn-win-primary:hover{filter:brightness(1.2)!important;transform:translateY(-0.0625rem)!important;}';
c+=H+'.btn-win:not(.btn-win-primary){border-color:rgba(var(--accent-rgb),0.3)!important;color:var(--accent)!important;}';
c+=H+'.btn-win:hover{background:rgba(var(--accent-rgb),0.2)!important;border-color:var(--accent)!important;color:var(--accent)!important;transform:translateY(-0.0625rem)!important;}';
c+=H+'.btn-win:active{transform:translateY(0)!important;}';
c+=H+'.btn-win-sm:hover{background:rgba(var(--accent-rgb),0.15)!important;color:var(--accent)!important;}';
c+=H+'.btn-icon:hover{box-shadow:0 0.125rem 0.5rem rgba(var(--accent-rgb),0.3)!important;}';
/* Badges */
c+=H+'.badge{background:rgba(var(--accent-rgb),0.15)!important;color:var(--accent)!important;}';
c+=H+'.badge.bg-success{background:var(--color-success)!important;color:#fff!important;}';
c+=H+'.badge-codigo{background:rgba(var(--accent-rgb),0.15)!important;color:var(--accent)!important;}';
c+=H+'.dropdown-item-text .badge{background:linear-gradient(135deg,var(--accent),var(--accent-light))!important;color:#fff!important;border:none!important;}';
/* DataTables */
c+=H+'table.dataTable thead th{background:rgba(var(--accent-rgb),0.1)!important;color:var(--accent-light)!important;border-bottom:0.0625rem solid rgba(var(--accent-rgb),0.2)!important;}';
c+=H+'table.dataTable tbody tr:hover{background:rgba(var(--accent-rgb),0.08)!important;}';
c+=H+'.dataTables_wrapper .dataTables_paginate .paginate_button.current{background:linear-gradient(135deg,var(--accent),var(--accent-dark))!important;border-color:var(--accent)!important;color:#fff!important;}';
c+=H+'.dataTables_wrapper .dataTables_paginate .paginate_button:hover{background:rgba(var(--accent-rgb),0.25)!important;border-color:var(--accent)!important;}';
c+=H+'.dataTables_wrapper .dataTables_filter input:focus{border-color:var(--accent)!important;box-shadow:0 0 0 0.125rem rgba(var(--accent-rgb),0.2)!important;}';
/* Forms */
c+=H+'.form-select:focus,'+H+'.form-control:focus{border-color:var(--accent)!important;box-shadow:0 0 0 0.125rem rgba(var(--accent-rgb),0.2)!important;}';
c+=H+'.form-select:hover,'+H+'.form-control:hover{border-color:rgba(var(--accent-rgb),0.3)!important;}';
c+=H+'.input-group-text{background:rgba(var(--accent-rgb),0.06)!important;border-color:rgba(var(--accent-rgb),0.15)!important;color:var(--accent)!important;}';
/* Dropdowns DARK */
c+=H+'.dropdown-menu-win{background:rgba(20,20,25,0.98)!important;border:0.0625rem solid rgba(var(--accent-rgb),0.2)!important;backdrop-filter:blur(1.25rem)!important;box-shadow:0 0.5rem 2rem rgba(0,0,0,0.4),0 0 0 0.0625rem rgba(var(--accent-rgb),0.08)!important;}';
c+=H+'.dropdown-menu-win .dropdown-item{color:rgba(255,255,255,0.85)!important;border-radius:0.5rem!important;margin:0.125rem 0.25rem!important;padding:0.625rem 1rem!important;transition:all 0.15s!important;}';
c+=H+'.dropdown-menu-win .dropdown-item:hover{background:rgba(var(--accent-rgb),0.18)!important;color:var(--accent)!important;}';
c+=H+'.dropdown-menu-win .dropdown-item:active{background:rgba(var(--accent-rgb),0.28)!important;color:#fff!important;}';
c+=H+'.dropdown-menu-win .dropdown-divider{border-color:rgba(var(--accent-rgb),0.12)!important;margin:0.25rem 0.75rem!important;}';
c+=H+'.dropdown-menu-win .dropdown-item-text{color:rgba(255,255,255,0.85)!important;padding:0.625rem 1rem!important;}';
c+=H+'.dropdown-menu-win .dropdown-item.text-danger:hover{background:rgba(239,68,68,0.15)!important;color:#ef4444!important;}';
/* Tabs */
c+=H+'.nav-tabs-modern .nav-link{color:var(--muted)!important;border:0.0625rem solid transparent!important;}';
c+=H+'.nav-tabs-modern .nav-link:hover{color:var(--accent)!important;background:rgba(var(--accent-rgb),0.06)!important;border-color:rgba(var(--accent-rgb),0.2)!important;}';
c+=H+'.nav-tabs-modern .nav-link.active{color:var(--accent)!important;background:rgba(var(--accent-rgb),0.1)!important;border-color:var(--accent)!important;}';
/* Misc */
c+=H+'.date-badge{color:var(--accent)!important;}';
c+=H+'.user-avatar{background:linear-gradient(135deg,var(--accent),var(--accent-light))!important;}';
c+=H+'::-webkit-scrollbar-thumb{background:rgba(var(--accent-rgb),0.25)!important;border-radius:0.625rem;}';
c+=H+'::-webkit-scrollbar-thumb:hover{background:rgba(var(--accent-rgb),0.4)!important;}';
c+=H+'.swal2-confirm{background:var(--accent)!important;}';
c+=H+'.swal2-cancel{background:rgba(var(--accent-rgb),0.1)!important;color:var(--accent)!important;border:0.0625rem solid rgba(var(--accent-rgb),0.2)!important;}';
c+=H+'.swal2-cancel:hover{background:rgba(var(--accent-rgb),0.2)!important;}';
c+=H+'.swal2-title{color:var(--txt)!important;}';
c+=H+'.swal2-html-container{color:var(--muted)!important;}';
c+=H+'.accordion-button:focus{box-shadow:0 0 0 0.125rem rgba(var(--accent-rgb),0.25)!important;}';
c+=H+'.accordion-button:not(.collapsed){color:var(--accent)!important;background:rgba(var(--accent-rgb),0.05)!important;}';
c+=H+'.list-group-item{border-color:rgba(var(--accent-rgb),0.08)!important;}';
c+=H+'.list-group-item:hover{background:rgba(var(--accent-rgb),0.05)!important;border-color:rgba(var(--accent-rgb),0.15)!important;}';
c+=H+'.progress-bar{background:linear-gradient(90deg,var(--accent),var(--accent-light))!important;}';
c+=H+'.snc-stat-card{border-color:rgba(var(--accent-rgb),0.15)!important;}';
c+=H+'.snc-stat-card:hover{border-color:var(--accent)!important;box-shadow:0 0.25rem 1rem rgba(var(--accent-rgb),0.1)!important;}';
c+=H+'#sncFiltrosWrap{border-color:rgba(var(--accent-rgb),0.12)!important;}';
c+=H+'#sncFiltrosToggle .fa-filter{color:var(--accent)!important;}';
c+=H+'#sncFiltrosToggle span{color:var(--accent)!important;}';
c+='html .table-snc td a:hover,html .table td a:hover{color:var(--accent-light)!important;text-decoration:underline!important;}';
c+='html #auto-hide-toolbar{border-top:0.0625rem solid rgba(var(--accent-rgb),0.15)!important;}';
c+=H+'.sidebar-footer{border-top:0.0625rem solid rgba(var(--accent-rgb),0.1)!important;}';
c+='html input[type="checkbox"]:checked,html input[type="radio"]:checked{accent-color:var(--accent)!important;}';
c+='html .selector-box{border:0.0625rem solid rgba(var(--accent-rgb),0.1)!important;}';
c+='html .selector-box:hover{border-color:var(--accent)!important;background:rgba(var(--accent-rgb),0.05)!important;}';
c+='html .selector-box.active{border-color:var(--accent)!important;background:rgba(var(--accent-rgb),0.08)!important;}';
c+='html [data-tooltip-theme="primary"]{border-color:rgba(var(--accent-rgb),0.3)!important;}';

/* ===== LIGHT THEME ACCENT ===== */
var L='html[data-theme="light"] ';
c+=L+'.nav-item.active{background:var(--accent)!important;color:#fff!important;box-shadow:0 0.125rem 0.5rem rgba(var(--accent-rgb),0.3)!important;}';
c+=L+'.nav-item.active i{color:#fff!important;}';
c+=L+'.nav-item.active .sidebar-text{color:#fff!important;}';
c+=L+'.nav-item.active .nav-badge{background:rgba(255,255,255,0.25)!important;color:#fff!important;}';
c+=L+'.nav-item.active .nav-group-chevron{color:#fff!important;}';
c+=L+'.nav-item:hover{background:rgba(var(--accent-rgb),0.16)!important;color:var(--accent-dark)!important;border-color:rgba(var(--accent-rgb),0.22)!important;transform:translateX(0.25rem)!important;}';
c+=L+'.nav-item:hover i{color:var(--accent-dark)!important;transform:scale(1.15)!important;}';
/* Override: active+hover must keep white text in light theme too */
c+=L+'.nav-item.active:hover{background:var(--accent-dark)!important;color:#fff!important;}';
c+=L+'.nav-item.active:hover i{color:#fff!important;}';
c+=L+'.nav-item.active:hover .sidebar-text{color:#fff!important;}';
c+=L+'.nav-item.active:hover .nav-badge{background:rgba(255,255,255,0.25)!important;color:#fff!important;}';
c+=L+'.nav-item.active:hover .nav-group-chevron{color:#fff!important;}';
c+=L+'.sidebar-logo h3{background:linear-gradient(135deg,var(--accent-dark),var(--accent));-webkit-background-clip:text;background-clip:text;color:transparent!important;}';
c+=L+'.sidebar-toggle:hover{background:rgba(var(--accent-rgb),0.15)!important;color:var(--accent-dark)!important;}';
c+=L+'.page-title h1 i{color:var(--accent-dark)!important;}';
c+=L+'.glass-card:hover{border-color:rgba(var(--accent-rgb),0.35)!important;box-shadow:0 0.5rem 1.5rem rgba(0,0,0,0.08),0 0 0 0.0625rem rgba(var(--accent-rgb),0.1)!important;}';
c+=L+'.card-content h5 i{color:var(--accent-dark)!important;}';
c+=L+'a{color:var(--accent-dark)!important;}';
c+=L+'a:hover{color:var(--accent)!important;}';
c+=L+'.btn-win-primary{background:var(--accent-dark)!important;color:#fff!important;}';
c+=L+'.btn-win-primary:hover{filter:brightness(1.1)!important;transform:translateY(-0.0625rem)!important;}';
c+=L+'.btn-win:not(.btn-win-primary){border-color:rgba(var(--accent-rgb),0.25)!important;color:var(--accent-dark)!important;}';
c+=L+'.btn-win:hover{background:rgba(var(--accent-rgb),0.1)!important;border-color:var(--accent-dark)!important;color:var(--accent-dark)!important;}';
c+=L+'.btn-win-sm:hover{background:rgba(var(--accent-rgb),0.08)!important;color:var(--accent-dark)!important;}';
c+=L+'.badge{background:rgba(var(--accent-rgb),0.1)!important;color:var(--accent-dark)!important;}';
c+=L+'.badge-codigo{background:rgba(var(--accent-rgb),0.1)!important;color:var(--accent-dark)!important;}';
c+=L+'.dropdown-item-text .badge{background:linear-gradient(135deg,var(--accent-dark),var(--accent))!important;color:#fff!important;}';
c+='html[data-theme="light"] table.dataTable thead th{background:rgba(var(--accent-rgb),0.06)!important;color:var(--accent-dark)!important;border-bottom:0.125rem solid rgba(var(--accent-rgb),0.15)!important;}';
c+='html[data-theme="light"] table.dataTable tbody tr:hover td{background:rgba(var(--accent-rgb),0.15)!important;}';
c+='html[data-theme="light"] table.dataTable > tbody > tr:hover > *{box-shadow:inset 0 0 0 9999px rgba(var(--accent-rgb),0.16),inset 0 0 0 9999px rgba(0,0,0,0.09)!important;}';
c+='html[data-theme="light"] table.dataTable > tbody > tr:hover > td{background-color:rgba(var(--accent-rgb),0.10)!important;}';
c+='html[data-theme="light"] .dataTables_wrapper .dataTables_paginate .paginate_button.current{background:var(--accent-dark)!important;border-color:var(--accent-dark)!important;color:#fff!important;}';
c+='html[data-theme="light"] .dataTables_wrapper .dataTables_paginate .paginate_button:hover{background:rgba(var(--accent-rgb),0.18)!important;border-color:var(--accent-dark)!important;}';
c+='html[data-theme="light"] .form-select:focus,html[data-theme="light"] .form-control:focus{border-color:var(--accent-dark)!important;box-shadow:0 0 0 0.125rem rgba(var(--accent-rgb),0.12)!important;}';
c+='html[data-theme="light"] .form-select:hover,html[data-theme="light"] .form-control:hover{border-color:rgba(var(--accent-rgb),0.3)!important;}';
c+='html[data-theme="light"] .input-group-text{background:rgba(var(--accent-rgb),0.04)!important;color:var(--accent-dark)!important;}';
/* Dropdowns LIGHT */
c+='html[data-theme="light"] .dropdown-menu-win{background:rgba(255,255,255,0.98)!important;border:0.0625rem solid rgba(var(--accent-rgb),0.18)!important;backdrop-filter:blur(1.25rem)!important;box-shadow:0 0.5rem 2rem rgba(0,0,0,0.1),0 0 0 0.0625rem rgba(var(--accent-rgb),0.06)!important;}';
c+='html[data-theme="light"] .dropdown-menu-win .dropdown-item{color:#1f2937!important;border-radius:0.5rem!important;margin:0.125rem 0.25rem!important;padding:0.625rem 1rem!important;transition:all 0.15s!important;}';
c+='html[data-theme="light"] .dropdown-menu-win .dropdown-item:hover{background:rgba(var(--accent-rgb),0.22)!important;color:var(--accent-dark)!important;}';
c+='html[data-theme="light"] .dropdown-menu-win .dropdown-item:active{background:rgba(var(--accent-rgb),0.2)!important;color:#fff!important;}';
c+='html[data-theme="light"] .dropdown-menu-win .dropdown-divider{border-color:rgba(var(--accent-rgb),0.1)!important;margin:0.25rem 0.75rem!important;}';
c+='html[data-theme="light"] .dropdown-menu-win .dropdown-item-text{color:#1f2937!important;padding:0.625rem 1rem!important;}';
c+='html[data-theme="light"] .dropdown-menu-win .dropdown-item.text-danger:hover{background:rgba(239,68,68,0.1)!important;color:#dc2626!important;}';
/* Tabs LIGHT */
c+=L+'.nav-tabs-modern .nav-link:hover{color:var(--accent-dark)!important;background:rgba(var(--accent-rgb),0.15)!important;border-color:rgba(var(--accent-rgb),0.3)!important;}';
c+=L+'.nav-tabs-modern .nav-link.active{color:var(--accent-dark)!important;background:rgba(var(--accent-rgb),0.18)!important;border-color:var(--accent-dark)!important;}';
/* Misc LIGHT */
c+=L+'.date-badge{color:var(--accent-dark)!important;}';
c+=L+'.user-avatar{background:linear-gradient(135deg,var(--accent-dark),var(--accent))!important;}';
c+='html[data-theme="light"] .swal2-confirm{background:var(--accent-dark)!important;color:#fff!important;}';
c+='html[data-theme="light"] .swal2-cancel{background:rgba(var(--accent-rgb),0.06)!important;color:var(--accent-dark)!important;border:0.0625rem solid rgba(var(--accent-rgb),0.15)!important;}';
c+='html[data-theme="light"] .swal2-cancel:hover{background:rgba(var(--accent-rgb),0.12)!important;}';
c+='html[data-theme="light"] .accordion-button:not(.collapsed){color:var(--accent-dark)!important;background:rgba(var(--accent-rgb),0.04)!important;}';
c+='html[data-theme="light"] .list-group-item:hover{background:rgba(var(--accent-rgb),0.04)!important;}';
c+='html[data-theme="light"] .progress-bar{background:linear-gradient(90deg,var(--accent-dark),var(--accent))!important;}';
c+='html[data-theme="light"] .btn-icon:hover{box-shadow:0 0.125rem 0.5rem rgba(var(--accent-rgb),0.15)!important;}';
c+='html[data-theme="light"] .table-snc td a:hover,html[data-theme="light"] .table td a:hover{color:var(--accent)!important;}';
c+='html[data-theme="light"] input[type="checkbox"]:checked,html[data-theme="light"] input[type="radio"]:checked{accent-color:var(--accent-dark)!important;}';
c+='html[data-theme="light"] #sncFiltrosWrap{border-color:rgba(var(--accent-rgb),0.15)!important;}';
c+='html[data-theme="light"] #sncFiltrosToggle .fa-filter{color:var(--accent-dark)!important;}';
c+='html[data-theme="light"] #sncFiltrosToggle span{color:var(--accent-dark)!important;}';
c+='html[data-theme="light"] .snc-stat-card{border-color:rgba(var(--accent-rgb),0.2)!important;}';
c+='html[data-theme="light"] .selector-box:hover{border-color:var(--accent-dark)!important;background:rgba(var(--accent-rgb),0.04)!important;}';
c+='html[data-theme="light"] .selector-box.active{border-color:var(--accent-dark)!important;background:rgba(var(--accent-rgb),0.06)!important;}';
c+='html[data-theme="light"] #logoutSidebarBtn:hover{background:var(--accent-bg)!important;color:var(--accent)!important;border-color:var(--accent)!important;}';

/* ===== BLUE THEME (light navy / LogoTN) ===== */
var B='html[data-theme="blue"] ';
/* Variables base — NOTABLEMENTE más claras que dark */
c+=B+'{--bg:#152540;--panel:#1a3050;--panel-2:#1e3858;--card:rgba(26,48,80,0.88);--card-hover:rgba(30,56,88,0.95);--border:rgba(130,190,255,0.18);--border-2:rgba(130,190,255,0.28);--txt:#dce8ff;--muted:#9dbde8;--faint:#6a9bd4;--shadow:0 0.75rem 2.125rem rgba(0,0,0,0.35);}';
/* Sidebar */
c+=B+'.win-sidebar{background:rgba(12,22,42,0.97)!important;}';
c+=B+'.sidebar-logo h3{background:linear-gradient(135deg,#60a5fa,#93c5fd);-webkit-background-clip:text;background-clip:text;color:transparent!important;}';
c+=B+'.sidebar-logo small{color:rgba(130,190,255,0.5)!important;}';
/* Sidebar nav items — base claro, hover oscuro */
c+=B+'.nav-item{color:rgba(180,210,255,0.78)!important;background:rgba(130,190,255,0.05)!important;}';
c+=B+'.nav-item:hover{background:rgba(10,20,40,0.65)!important;color:var(--accent)!important;border-color:rgba(59,130,246,0.2)!important;}';
c+=B+'.nav-item:hover i{color:var(--accent)!important;}';
c+=B+'.nav-item.active{background:var(--accent)!important;color:#fff!important;box-shadow:0 0.125rem 0.5rem rgba(var(--accent-rgb),0.3)!important;}';
c+=B+'.nav-item.active i{color:#fff!important;}';
c+=B+'.nav-item.active .sidebar-text{color:#fff!important;}';
c+=B+'.nav-item.active .nav-badge{background:rgba(255,255,255,0.25)!important;color:#fff!important;}';
c+=B+'.nav-item.active .nav-group-chevron{color:#fff!important;}';
c+=B+'.nav-item.active:hover{background:var(--accent-dark)!important;color:#fff!important;}';
c+=B+'.nav-item.active:hover i{color:#fff!important;}';
c+=B+'.nav-item.active:hover .sidebar-text{color:#fff!important;}';
c+=B+'.nav-item.active:hover .nav-badge{background:rgba(255,255,255,0.25)!important;color:#fff!important;}';
c+=B+'.nav-item.active:hover .nav-group-chevron{color:#fff!important;}';
c+=B+'.nav-badge{background:rgba(96,165,250,0.18)!important;color:#93c5fd!important;}';
c+=B+'.sidebar-toggle:hover{background:rgba(10,20,40,0.5)!important;color:var(--accent)!important;}';
c+=B+'.sidebar-profile:hover{background:rgba(10,20,40,0.4)!important;border-color:rgba(59,130,246,0.2)!important;}';
c+=B+'.profile-avatar-link:hover .profile-avatar{border-color:var(--accent)!important;box-shadow:0 0 0 0.1875rem rgba(59,130,246,0.2)!important;}';
c+=B+'.sidebar-footer{border-top:0.0625rem solid rgba(130,190,255,0.12)!important;}';
/* Topbar — más claro que dark */
c+=B+'.win-topbar{background:rgba(20,36,60,0.85)!important;border-color:rgba(130,190,255,0.15)!important;color:#dce8ff!important;}';
/* Cards — más azul y claro */
c+=B+'.glass-card{background:rgba(26,48,80,0.82)!important;border-color:rgba(130,190,255,0.18)!important;color:#dce8ff!important;}';
c+=B+'.glass-card:hover{border-color:rgba(59,130,246,0.35)!important;box-shadow:0 0.75rem 2.5rem rgba(0,0,0,0.3),0 0 0 0.0625rem rgba(59,130,246,0.18)!important;background:rgba(22,42,72,0.9)!important;transform:translateY(-0.125rem)!important;}';
c+=B+'.card-content h5{color:#dce8ff!important;}';
c+=B+'.card-content h5 i{color:var(--accent)!important;}';
c+=B+'.card-content p{color:#9dbde8!important;}';
/* Links */
c+=B+'a{color:var(--accent)!important;}';
c+=B+'a:hover{color:var(--accent-dark)!important;}';
/* Buttons — hover oscuro */
c+=B+'.btn-win-primary{background:linear-gradient(135deg,var(--accent),var(--accent-dark))!important;border:none!important;color:#fff!important;}';
c+=B+'.btn-win-primary:hover{filter:brightness(0.85)!important;transform:translateY(-0.0625rem)!important;}';
c+=B+'.btn-win:not(.btn-win-primary){border-color:rgba(130,190,255,0.3)!important;color:#93c5fd!important;}';
c+=B+'.btn-win:hover{background:rgba(10,20,40,0.5)!important;border-color:var(--accent)!important;color:var(--accent)!important;transform:translateY(-0.0625rem)!important;}';
c+=B+'.btn-win:active{transform:translateY(0)!important;}';
c+=B+'.btn-win-sm:hover{background:rgba(10,20,40,0.4)!important;color:#93c5fd!important;}';
c+=B+'.btn-icon:hover{box-shadow:0 0.125rem 0.5rem rgba(var(--accent-rgb),0.3)!important;}';
/* Badges */
c+=B+'.badge{background:rgba(96,165,250,0.2)!important;color:#93c5fd!important;}';
c+=B+'.badge.bg-success{background:var(--color-success)!important;color:#fff!important;}';
c+=B+'.badge-codigo{background:rgba(96,165,250,0.2)!important;color:#93c5fd!important;}';
c+=B+'.dropdown-item-text .badge{background:linear-gradient(135deg,var(--accent),var(--accent-light))!important;color:#fff!important;border:none!important;}';
/* DataTables — hover oscuro */
c+=B+'table.dataTable thead th{background:rgba(59,130,246,0.15)!important;color:#93c5fd!important;border-bottom:0.0625rem solid rgba(59,130,246,0.25)!important;}';
c+=B+'table.dataTable tbody tr:hover{background:rgba(10,20,40,0.5)!important;}';
c+=B+'.dataTables_wrapper .dataTables_paginate .paginate_button.current{background:linear-gradient(135deg,var(--accent),var(--accent-dark))!important;border-color:var(--accent)!important;color:#fff!important;}';
c+=B+'.dataTables_wrapper .dataTables_paginate .paginate_button:hover{background:rgba(10,20,40,0.5)!important;border-color:var(--accent)!important;}';
c+=B+'.dataTables_wrapper .dataTables_filter input:focus{border-color:var(--accent)!important;box-shadow:0 0 0 0.125rem rgba(59,130,246,0.25)!important;}';
/* Forms — focus más claro, hover oscuro */
c+=B+'.form-select:focus,'+B+'.form-control:focus{border-color:var(--accent)!important;box-shadow:0 0 0 0.125rem rgba(59,130,246,0.25)!important;}';
c+=B+'.form-select:hover,'+B+'.form-control:hover{border-color:rgba(59,130,246,0.4)!important;}';
c+=B+'.input-group-text{background:rgba(59,130,246,0.1)!important;border-color:rgba(59,130,246,0.2)!important;color:#93c5fd!important;}';
/* Dropdowns BLUE — fondo claro, hover oscuro */
c+=B+'.dropdown-menu-win{background:rgba(22,40,68,0.98)!important;border:0.0625rem solid rgba(130,190,255,0.2)!important;backdrop-filter:blur(1.25rem)!important;box-shadow:0 0.5rem 2rem rgba(0,0,0,0.4),0 0 0 0.0625rem rgba(59,130,246,0.1)!important;}';
c+=B+'.dropdown-menu-win .dropdown-item{color:rgba(220,232,255,0.88)!important;border-radius:0.5rem!important;margin:0.125rem 0.25rem!important;padding:0.625rem 1rem!important;transition:all 0.15s!important;}';
c+=B+'.dropdown-menu-win .dropdown-item:hover{background:rgba(10,20,40,0.55)!important;color:var(--accent)!important;}';
c+=B+'.dropdown-menu-win .dropdown-item:active{background:rgba(59,130,246,0.3)!important;color:#fff!important;}';
c+=B+'.dropdown-menu-win .dropdown-divider{border-color:rgba(130,190,255,0.12)!important;margin:0.25rem 0.75rem!important;}';
c+=B+'.dropdown-menu-win .dropdown-item-text{color:rgba(220,232,255,0.88)!important;padding:0.625rem 1rem!important;}';
c+=B+'.dropdown-menu-win .dropdown-item.text-danger:hover{background:rgba(239,68,68,0.18)!important;color:#ef4444!important;}';
/* Tabs */
c+=B+'.nav-tabs-modern .nav-link{color:#9dbde8!important;border:0.0625rem solid transparent!important;}';
c+=B+'.nav-tabs-modern .nav-link:hover{color:var(--accent)!important;background:rgba(10,20,40,0.4)!important;border-color:rgba(59,130,246,0.25)!important;}';
c+=B+'.nav-tabs-modern .nav-link.active{color:var(--accent)!important;background:rgba(59,130,246,0.15)!important;border-color:var(--accent)!important;}';
/* Misc */
c+=B+'.date-badge{color:var(--accent)!important;background:rgba(59,130,246,0.12)!important;}';
c+=B+'.user-avatar{background:linear-gradient(135deg,var(--accent),var(--accent-light))!important;}';
c+=B+'::-webkit-scrollbar-thumb{background:rgba(59,130,246,0.3)!important;border-radius:0.625rem;}';
c+=B+'::-webkit-scrollbar-thumb:hover{background:rgba(59,130,246,0.5)!important;}';
c+=B+'.swal2-confirm{background:var(--accent)!important;}';
c+=B+'.swal2-cancel{background:rgba(59,130,246,0.12)!important;color:var(--accent)!important;border:0.0625rem solid rgba(59,130,246,0.25)!important;}';
c+=B+'.swal2-cancel:hover{background:rgba(10,20,40,0.5)!important;}';
c+=B+'.swal2-title{color:#dce8ff!important;}';
c+=B+'.swal2-html-container{color:#9dbde8!important;}';
c+=B+'.accordion-button:focus{box-shadow:0 0 0 0.125rem rgba(59,130,246,0.3)!important;}';
c+=B+'.accordion-button:not(.collapsed){color:var(--accent)!important;background:rgba(59,130,246,0.08)!important;}';
c+=B+'.list-group-item{border-color:rgba(130,190,255,0.12)!important;}';
c+=B+'.list-group-item:hover{background:rgba(10,20,40,0.35)!important;border-color:rgba(59,130,246,0.2)!important;}';
c+=B+'.progress-bar{background:linear-gradient(90deg,var(--accent),var(--accent-light))!important;}';
c+=B+'.snc-stat-card{border-color:rgba(130,190,255,0.18)!important;}';
c+=B+'.snc-stat-card:hover{border-color:var(--accent)!important;box-shadow:0 0.25rem 1rem rgba(59,130,246,0.15)!important;}';
c+=B+'#sncFiltrosWrap{border-color:rgba(130,190,255,0.15)!important;}';
c+=B+'#sncFiltrosToggle .fa-filter{color:var(--accent)!important;}';
c+=B+'#sncFiltrosToggle span{color:var(--accent)!important;}';
c+='html[data-theme="blue"] .table-snc td a:hover,html[data-theme="blue"] .table td a:hover{color:var(--accent-dark)!important;text-decoration:underline!important;}';
c+='html[data-theme="blue"] #auto-hide-toolbar{border-top:0.0625rem solid rgba(130,190,255,0.15)!important;}';
c+='html input[type="checkbox"]:checked,html input[type="radio"]:checked{accent-color:var(--accent)!important;}';
c+='html .selector-box{border:0.0625rem solid rgba(130,190,255,0.12)!important;}';
c+='html .selector-box:hover{border-color:var(--accent)!important;background:rgba(10,20,40,0.35)!important;}';
c+='html .selector-box.active{border-color:var(--accent)!important;background:rgba(59,130,246,0.12)!important;}';
c+='html [data-tooltip-theme="primary"]{border-color:rgba(59,130,246,0.35)!important;}';
/* Focus mode blue */
c+='html[data-theme="blue"] .sidebar-close-focus{background:rgba(130,190,255,0.1);color:#9dbde8;}';
c+='html[data-theme="blue"] .sidebar-close-focus:hover{background:rgba(10,20,40,0.5)!important;color:var(--accent)!important;}';
/* Body & page title */
c+=B+'body{background:var(--bg)!important;color:#dce8ff!important;}';
c+=B+'.page-title h1{color:#dce8ff!important;}';
c+=B+'.page-title h1 i{color:var(--accent)!important;}';
c+=B+'.page-title p{color:#9dbde8!important;}';

/* ===== VERDE / NATURALEZA THEME ===== */
var V='html[data-theme="verde"] ';
/* Variables base — verde oscuro natural */
c+=V+'{--bg:#0f1f12;--panel:#162a18;--panel-2:#1a301e;--card:rgba(22,42,24,0.88);--card-hover:rgba(28,52,30,0.95);--border:rgba(120,200,130,0.18);--border-2:rgba(120,200,130,0.28);--txt:#d4f0d8;--muted:#8ec99a;--faint:#5ea86a;--shadow:0 0.75rem 2.125rem rgba(0,0,0,0.35);}';
/* Sidebar */
c+=V+'.win-sidebar{background:rgba(8,18,10,0.97)!important;}';
c+=V+'.sidebar-logo h3{background:linear-gradient(135deg,#34d399,#6ee7b7);-webkit-background-clip:text;background-clip:text;color:transparent!important;}';
c+=V+'.sidebar-logo small{color:rgba(120,200,130,0.5)!important;}';
/* Sidebar nav items */
c+=V+'.nav-item{color:rgba(180,230,185,0.78)!important;background:rgba(120,200,130,0.05)!important;}';
c+=V+'.nav-item:hover{background:rgba(5,25,10,0.65)!important;color:var(--accent)!important;border-color:rgba(52,211,153,0.2)!important;}';
c+=V+'.nav-item:hover i{color:var(--accent)!important;}';
c+=V+'.nav-item.active{background:var(--accent)!important;color:#fff!important;box-shadow:0 0.125rem 0.5rem rgba(var(--accent-rgb),0.3)!important;}';
c+=V+'.nav-item.active i{color:#fff!important;}';
c+=V+'.nav-item.active .sidebar-text{color:#fff!important;}';
c+=V+'.nav-item.active .nav-badge{background:rgba(255,255,255,0.25)!important;color:#fff!important;}';
c+=V+'.nav-item.active .nav-group-chevron{color:#fff!important;}';
c+=V+'.nav-item.active:hover{background:var(--accent-dark)!important;color:#fff!important;}';
c+=V+'.nav-item.active:hover i{color:#fff!important;}';
c+=V+'.nav-item.active:hover .sidebar-text{color:#fff!important;}';
c+=V+'.nav-item.active:hover .nav-badge{background:rgba(255,255,255,0.25)!important;color:#fff!important;}';
c+=V+'.nav-item.active:hover .nav-group-chevron{color:#fff!important;}';
c+=V+'.nav-badge{background:rgba(52,211,153,0.18)!important;color:#6ee7b7!important;}';
c+=V+'.sidebar-toggle:hover{background:rgba(5,25,10,0.5)!important;color:var(--accent)!important;}';
c+=V+'.sidebar-profile:hover{background:rgba(5,25,10,0.4)!important;border-color:rgba(52,211,153,0.2)!important;}';
c+=V+'.profile-avatar-link:hover .profile-avatar{border-color:var(--accent)!important;box-shadow:0 0 0 0.1875rem rgba(52,211,153,0.2)!important;}';
c+=V+'.sidebar-footer{border-top:0.0625rem solid rgba(120,200,130,0.12)!important;}';
/* Topbar */
c+=V+'.win-topbar{background:rgba(12,28,14,0.85)!important;border-color:rgba(120,200,130,0.15)!important;color:#d4f0d8!important;}';
/* Cards */
c+=V+'.glass-card{background:rgba(22,42,24,0.82)!important;border-color:rgba(120,200,130,0.18)!important;color:#d4f0d8!important;}';
c+=V+'.glass-card:hover{border-color:rgba(52,211,153,0.35)!important;box-shadow:0 0.75rem 2.5rem rgba(0,0,0,0.3),0 0 0 0.0625rem rgba(52,211,153,0.18)!important;background:rgba(18,38,20,0.9)!important;transform:translateY(-0.125rem)!important;}';
c+=V+'.card-content h5{color:#d4f0d8!important;}';
c+=V+'.card-content h5 i{color:var(--accent)!important;}';
c+=V+'.card-content p{color:#8ec99a!important;}';
/* Links */
c+=V+'a{color:var(--accent)!important;}';
c+=V+'a:hover{color:var(--accent-dark)!important;}';
/* Buttons */
c+=V+'.btn-win-primary{background:linear-gradient(135deg,var(--accent),var(--accent-dark))!important;border:none!important;color:#fff!important;}';
c+=V+'.btn-win-primary:hover{filter:brightness(0.85)!important;transform:translateY(-0.0625rem)!important;}';
c+=V+'.btn-win:not(.btn-win-primary){border-color:rgba(120,200,130,0.3)!important;color:#6ee7b7!important;}';
c+=V+'.btn-win:hover{background:rgba(5,25,10,0.5)!important;border-color:var(--accent)!important;color:var(--accent)!important;transform:translateY(-0.0625rem)!important;}';
c+=V+'.btn-win:active{transform:translateY(0)!important;}';
c+=V+'.btn-win-sm:hover{background:rgba(5,25,10,0.4)!important;color:#6ee7b7!important;}';
c+=V+'.btn-icon:hover{box-shadow:0 0.125rem 0.5rem rgba(var(--accent-rgb),0.3)!important;}';
/* Badges */
c+=V+'.badge{background:rgba(52,211,153,0.2)!important;color:#6ee7b7!important;}';
c+=V+'.badge.bg-success{background:var(--color-success)!important;color:#fff!important;}';
c+=V+'.badge-codigo{background:rgba(52,211,153,0.2)!important;color:#6ee7b7!important;}';
c+=V+'.dropdown-item-text .badge{background:linear-gradient(135deg,var(--accent),var(--accent-light))!important;color:#fff!important;border:none!important;}';
/* DataTables */
c+=V+'table.dataTable thead th{background:rgba(52,211,153,0.15)!important;color:#6ee7b7!important;border-bottom:0.0625rem solid rgba(52,211,153,0.25)!important;}';
c+=V+'table.dataTable tbody tr:hover{background:rgba(5,25,10,0.5)!important;}';
c+=V+'.dataTables_wrapper .dataTables_paginate .paginate_button.current{background:linear-gradient(135deg,var(--accent),var(--accent-dark))!important;border-color:var(--accent)!important;color:#fff!important;}';
c+=V+'.dataTables_wrapper .dataTables_paginate .paginate_button:hover{background:rgba(5,25,10,0.5)!important;border-color:var(--accent)!important;}';
c+=V+'.dataTables_wrapper .dataTables_filter input:focus{border-color:var(--accent)!important;box-shadow:0 0 0 0.125rem rgba(52,211,153,0.25)!important;}';
/* Forms */
c+=V+'.form-select:focus,'+V+'.form-control:focus{border-color:var(--accent)!important;box-shadow:0 0 0 0.125rem rgba(52,211,153,0.25)!important;}';
c+=V+'.form-select:hover,'+V+'.form-control:hover{border-color:rgba(52,211,153,0.4)!important;}';
c+=V+'.input-group-text{background:rgba(52,211,153,0.1)!important;border-color:rgba(52,211,153,0.2)!important;color:#6ee7b7!important;}';
/* Dropdowns */
c+=V+'.dropdown-menu-win{background:rgba(16,36,18,0.98)!important;border:0.0625rem solid rgba(120,200,130,0.2)!important;backdrop-filter:blur(1.25rem)!important;box-shadow:0 0.5rem 2rem rgba(0,0,0,0.4),0 0 0 0.0625rem rgba(52,211,153,0.1)!important;}';
c+=V+'.dropdown-menu-win .dropdown-item{color:rgba(212,240,216,0.88)!important;border-radius:0.5rem!important;margin:0.125rem 0.25rem!important;padding:0.625rem 1rem!important;transition:all 0.15s!important;}';
c+=V+'.dropdown-menu-win .dropdown-item:hover{background:rgba(5,25,10,0.55)!important;color:var(--accent)!important;}';
c+=V+'.dropdown-menu-win .dropdown-item:active{background:rgba(52,211,153,0.3)!important;color:#fff!important;}';
c+=V+'.dropdown-menu-win .dropdown-divider{border-color:rgba(120,200,130,0.12)!important;margin:0.25rem 0.75rem!important;}';
c+=V+'.dropdown-menu-win .dropdown-item-text{color:rgba(212,240,216,0.88)!important;padding:0.625rem 1rem!important;}';
c+=V+'.dropdown-menu-win .dropdown-item.text-danger:hover{background:rgba(239,68,68,0.18)!important;color:#ef4444!important;}';
/* Tabs */
c+=V+'.nav-tabs-modern .nav-link{color:#8ec99a!important;border:0.0625rem solid transparent!important;}';
c+=V+'.nav-tabs-modern .nav-link:hover{color:var(--accent)!important;background:rgba(5,25,10,0.4)!important;border-color:rgba(52,211,153,0.25)!important;}';
c+=V+'.nav-tabs-modern .nav-link.active{color:var(--accent)!important;background:rgba(52,211,153,0.15)!important;border-color:var(--accent)!important;}';
/* Misc */
c+=V+'.date-badge{color:var(--accent)!important;background:rgba(52,211,153,0.12)!important;}';
c+=V+'.user-avatar{background:linear-gradient(135deg,var(--accent),var(--accent-light))!important;}';
c+=V+'::-webkit-scrollbar-thumb{background:rgba(52,211,153,0.3)!important;border-radius:0.625rem;}';
c+=V+'::-webkit-scrollbar-thumb:hover{background:rgba(52,211,153,0.5)!important;}';
c+=V+'.swal2-confirm{background:var(--accent)!important;}';
c+=V+'.swal2-cancel{background:rgba(52,211,153,0.12)!important;color:var(--accent)!important;border:0.0625rem solid rgba(52,211,153,0.25)!important;}';
c+=V+'.swal2-cancel:hover{background:rgba(5,25,10,0.5)!important;}';
c+=V+'.swal2-title{color:#d4f0d8!important;}';
c+=V+'.swal2-html-container{color:#8ec99a!important;}';
c+=V+'.accordion-button:focus{box-shadow:0 0 0 0.125rem rgba(52,211,153,0.3)!important;}';
c+=V+'.accordion-button:not(.collapsed){color:var(--accent)!important;background:rgba(52,211,153,0.08)!important;}';
c+=V+'.list-group-item{border-color:rgba(120,200,130,0.12)!important;}';
c+=V+'.list-group-item:hover{background:rgba(5,25,10,0.35)!important;border-color:rgba(52,211,153,0.2)!important;}';
c+=V+'.progress-bar{background:linear-gradient(90deg,var(--accent),var(--accent-light))!important;}';
c+=V+'.snc-stat-card{border-color:rgba(120,200,130,0.18)!important;}';
c+=V+'.snc-stat-card:hover{border-color:var(--accent)!important;box-shadow:0 0.25rem 1rem rgba(52,211,153,0.15)!important;}';
c+=V+'#sncFiltrosWrap{border-color:rgba(120,200,130,0.15)!important;}';
c+=V+'#sncFiltrosToggle .fa-filter{color:var(--accent)!important;}';
c+=V+'#sncFiltrosToggle span{color:var(--accent)!important;}';
c+='html[data-theme="verde"] .table-snc td a:hover,html[data-theme="verde"] .table td a:hover{color:var(--accent-dark)!important;text-decoration:underline!important;}';
c+='html[data-theme="verde"] #auto-hide-toolbar{border-top:0.0625rem solid rgba(120,200,130,0.15)!important;}';
c+='html[data-theme="verde"] .sidebar-close-focus{background:rgba(120,200,130,0.1);color:#8ec99a;}';
c+='html[data-theme="verde"] .sidebar-close-focus:hover{background:rgba(5,25,10,0.5)!important;color:var(--accent)!important;}';
/* Body & page title */
c+=V+'body{background:var(--bg)!important;color:#d4f0d8!important;}';
c+=V+'.page-title h1{color:#d4f0d8!important;}';
c+=V+'.page-title h1 i{color:var(--accent)!important;}';
c+=V+'.page-title p{color:#8ec99a!important;}';

/* ===== ORGULLO THEME (púrpura semiclaro) ===== */
var U='html[data-theme="orgullo"] ';
/* Variables base — superficies claras lavanda, texto púrpura oscuro */
c+=U+'{--bg:#f4eefb;--panel:#ede4f8;--panel-2:#e7dcf4;--card:rgba(255,255,255,0.82);--card-hover:rgba(251,248,255,0.95);--border:rgba(139,92,246,0.22);--border-2:rgba(139,92,246,0.34);--txt:#33264d;--muted:#77689c;--faint:#a291c4;--shadow:0 0.75rem 2.125rem rgba(84,52,142,0.18);}';
c+=U+'body{background:var(--bg)!important;color:#33264d!important;}';
/* Sidebar */
c+=U+'.win-sidebar{background:rgba(240,232,250,0.97)!important;}';
c+=U+'.sidebar-logo h3{background:linear-gradient(135deg,#7c3aed,#a78bfa);-webkit-background-clip:text;background-clip:text;color:transparent!important;}';
c+=U+'.sidebar-logo small{color:rgba(119,104,156,0.85)!important;}';
c+=U+'.nav-item{color:rgba(62,47,96,0.85)!important;background:rgba(139,92,246,0.05)!important;}';
c+=U+'.nav-item:hover{background:rgba(139,92,246,0.14)!important;color:var(--accent-dark)!important;border-color:rgba(139,92,246,0.28)!important;}';
c+=U+'.nav-item:hover i{color:var(--accent-dark)!important;}';
c+=U+'.nav-item.active{background:var(--accent)!important;color:#fff!important;box-shadow:0 0.125rem 0.5rem rgba(var(--accent-rgb),0.3)!important;}';
c+=U+'.nav-item.active i{color:#fff!important;}';
c+=U+'.nav-item.active .sidebar-text{color:#fff!important;}';
c+=U+'.nav-item.active .nav-badge{background:rgba(255,255,255,0.25)!important;color:#fff!important;}';
c+=U+'.nav-item.active .nav-group-chevron{color:#fff!important;}';
c+=U+'.nav-item.active:hover{background:var(--accent-dark)!important;color:#fff!important;}';
c+=U+'.nav-item.active:hover i{color:#fff!important;}';
c+=U+'.nav-item.active:hover .sidebar-text{color:#fff!important;}';
c+=U+'.nav-item.active:hover .nav-badge{background:rgba(255,255,255,0.25)!important;color:#fff!important;}';
c+=U+'.nav-item.active:hover .nav-group-chevron{color:#fff!important;}';
c+=U+'.nav-badge{background:rgba(139,92,246,0.16)!important;color:#6d28d9!important;}';
c+=U+'.sidebar-toggle:hover{background:rgba(139,92,246,0.16)!important;color:var(--accent-dark)!important;}';
c+=U+'.sidebar-profile:hover{background:rgba(139,92,246,0.09)!important;border-color:rgba(139,92,246,0.22)!important;}';
c+=U+'.profile-avatar-link:hover .profile-avatar{border-color:var(--accent)!important;box-shadow:0 0 0 0.1875rem rgba(var(--accent-rgb),0.18)!important;}';
c+=U+'.sidebar-footer{border-top:0.0625rem solid rgba(139,92,246,0.16)!important;}';
/* Topbar */
c+=U+'.win-topbar{background:rgba(249,245,253,0.88)!important;border-color:rgba(139,92,246,0.2)!important;color:#33264d!important;}';
/* Cards */
c+=U+'.glass-card{background:rgba(255,255,255,0.8)!important;border-color:rgba(139,92,246,0.22)!important;color:#33264d!important;}';
c+=U+'.glass-card:hover{border-color:rgba(var(--accent-rgb),0.4)!important;box-shadow:0 0.5rem 1.5rem rgba(84,52,142,0.16),0 0 0 0.0625rem rgba(var(--accent-rgb),0.12)!important;background:rgba(252,249,255,0.92)!important;transform:translateY(-0.125rem)!important;}';
c+=U+'.card-content h5{color:#33264d!important;}';
c+=U+'.card-content h5 i{color:var(--accent-dark)!important;}';
c+=U+'.card-content p{color:#77689c!important;}';
/* Links */
c+=U+'a{color:var(--accent-dark)!important;}';
c+=U+'a:hover{color:var(--accent)!important;}';
/* Buttons */
c+=U+'.btn-win-primary{background:linear-gradient(135deg,var(--accent),var(--accent-dark))!important;border:none!important;color:#fff!important;}';
c+=U+'.btn-win-primary:hover{filter:brightness(1.08)!important;transform:translateY(-0.0625rem)!important;}';
c+=U+'.btn-win:not(.btn-win-primary){border-color:rgba(var(--accent-rgb),0.35)!important;color:var(--accent-dark)!important;}';
c+=U+'.btn-win:hover{background:rgba(var(--accent-rgb),0.12)!important;border-color:var(--accent-dark)!important;color:var(--accent-dark)!important;transform:translateY(-0.0625rem)!important;}';
c+=U+'.btn-win:active{transform:translateY(0)!important;}';
c+=U+'.btn-win-sm:hover{background:rgba(var(--accent-rgb),0.1)!important;color:var(--accent-dark)!important;}';
c+=U+'.btn-icon:hover{box-shadow:0 0.125rem 0.5rem rgba(var(--accent-rgb),0.25)!important;}';
/* Badges */
c+=U+'.badge{background:rgba(var(--accent-rgb),0.14)!important;color:var(--accent-dark)!important;}';
c+=U+'.badge.bg-success{background:var(--color-success)!important;color:#fff!important;}';
c+=U+'.badge-codigo{background:rgba(var(--accent-rgb),0.14)!important;color:var(--accent-dark)!important;}';
c+=U+'.dropdown-item-text .badge{background:linear-gradient(135deg,var(--accent),var(--accent-light))!important;color:#fff!important;border:none!important;}';
/* DataTables */
c+=U+'table.dataTable thead th{background:rgba(139,92,246,0.12)!important;color:#5b21b6!important;border-bottom:0.0625rem solid rgba(139,92,246,0.28)!important;}';
c+=U+'table.dataTable tbody tr:hover{background:rgba(139,92,246,0.1)!important;}';
c+=U+'.dataTables_wrapper .dataTables_paginate .paginate_button.current{background:linear-gradient(135deg,var(--accent),var(--accent-dark))!important;border-color:var(--accent)!important;color:#fff!important;}';
c+=U+'.dataTables_wrapper .dataTables_paginate .paginate_button:hover{background:rgba(139,92,246,0.18)!important;border-color:var(--accent)!important;}';
c+=U+'.dataTables_wrapper .dataTables_filter input:focus{border-color:var(--accent)!important;box-shadow:0 0 0 0.125rem rgba(var(--accent-rgb),0.2)!important;}';
/* Forms */
c+=U+'.form-select:focus,'+U+'.form-control:focus{border-color:var(--accent)!important;box-shadow:0 0 0 0.125rem rgba(var(--accent-rgb),0.18)!important;}';
c+=U+'.form-select:hover,'+U+'.form-control:hover{border-color:rgba(139,92,246,0.45)!important;}';
c+=U+'.input-group-text{background:rgba(139,92,246,0.08)!important;border-color:rgba(139,92,246,0.22)!important;color:var(--accent-dark)!important;}';
/* Dropdowns — fondo claro, texto oscuro */
c+=U+'.dropdown-menu-win{background:rgba(252,250,255,0.98)!important;border:0.0625rem solid rgba(139,92,246,0.25)!important;backdrop-filter:blur(1.25rem)!important;box-shadow:0 0.5rem 2rem rgba(84,52,142,0.22),0 0 0 0.0625rem rgba(var(--accent-rgb),0.08)!important;}';
c+=U+'.dropdown-menu-win .dropdown-item{color:#3a2d55!important;border-radius:0.5rem!important;margin:0.125rem 0.25rem!important;padding:0.625rem 1rem!important;transition:all 0.15s!important;}';
c+=U+'.dropdown-menu-win .dropdown-item:hover{background:rgba(139,92,246,0.16)!important;color:var(--accent-dark)!important;}';
c+=U+'.dropdown-menu-win .dropdown-item:active{background:rgba(139,92,246,0.28)!important;color:#fff!important;}';
c+=U+'.dropdown-menu-win .dropdown-divider{border-color:rgba(139,92,246,0.16)!important;margin:0.25rem 0.75rem!important;}';
c+=U+'.dropdown-menu-win .dropdown-item-text{color:#3a2d55!important;padding:0.625rem 1rem!important;}';
c+=U+'.dropdown-menu-win .dropdown-item.text-danger:hover{background:rgba(239,68,68,0.12)!important;color:#dc2626!important;}';
/* Tabs */
c+=U+'.nav-tabs-modern .nav-link{color:#77689c!important;border:0.0625rem solid transparent!important;}';
c+=U+'.nav-tabs-modern .nav-link:hover{color:var(--accent-dark)!important;background:rgba(139,92,246,0.12)!important;border-color:rgba(139,92,246,0.28)!important;}';
c+=U+'.nav-tabs-modern .nav-link.active{color:var(--accent-dark)!important;background:rgba(139,92,246,0.18)!important;border-color:var(--accent-dark)!important;}';
/* Misc */
c+=U+'.date-badge{color:var(--accent-dark)!important;background:rgba(var(--accent-rgb),0.12)!important;}';
c+=U+'.user-avatar{background:linear-gradient(135deg,var(--accent),var(--accent-light))!important;}';
c+=U+'::-webkit-scrollbar-thumb{background:rgba(139,92,246,0.35)!important;border-radius:0.625rem;}';
c+=U+'::-webkit-scrollbar-thumb:hover{background:rgba(139,92,246,0.55)!important;}';
c+=U+'.swal2-confirm{background:var(--accent)!important;}';
c+=U+'.swal2-cancel{background:rgba(139,92,246,0.1)!important;color:var(--accent-dark)!important;border:0.0625rem solid rgba(139,92,246,0.25)!important;}';
c+=U+'.swal2-cancel:hover{background:rgba(139,92,246,0.2)!important;}';
c+=U+'.swal2-title{color:#33264d!important;}';
c+=U+'.swal2-html-container{color:#77689c!important;}';
c+=U+'.accordion-button:focus{box-shadow:0 0 0 0.125rem rgba(var(--accent-rgb),0.25)!important;}';
c+=U+'.accordion-button:not(.collapsed){color:var(--accent-dark)!important;background:rgba(139,92,246,0.08)!important;}';
c+=U+'.list-group-item{border-color:rgba(139,92,246,0.14)!important;}';
c+=U+'.list-group-item:hover{background:rgba(139,92,246,0.06)!important;border-color:rgba(139,92,246,0.22)!important;}';
c+=U+'.progress-bar{background:linear-gradient(90deg,var(--accent),var(--accent-light))!important;}';
c+=U+'.snc-stat-card{border-color:rgba(139,92,246,0.2)!important;}';
c+=U+'.snc-stat-card:hover{border-color:var(--accent)!important;box-shadow:0 0.25rem 1rem rgba(var(--accent-rgb),0.14)!important;}';
c+=U+'#sncFiltrosWrap{border-color:rgba(139,92,246,0.18)!important;}';
c+=U+'#sncFiltrosToggle .fa-filter{color:var(--accent-dark)!important;}';
c+=U+'#sncFiltrosToggle span{color:var(--accent-dark)!important;}';
c+='html[data-theme="orgullo"] .table-snc td a:hover,html[data-theme="orgullo"] .table td a:hover{color:var(--accent-dark)!important;text-decoration:underline!important;}';
c+='html[data-theme="orgullo"] #auto-hide-toolbar{border-top:0.0625rem solid rgba(139,92,246,0.18)!important;}';
c+='html[data-theme="orgullo"] input[type="checkbox"]:checked,html[data-theme="orgullo"] input[type="radio"]:checked{accent-color:var(--accent-dark)!important;}';
c+='html[data-theme="orgullo"] .selector-box{border:0.0625rem solid rgba(139,92,246,0.14)!important;}';
c+='html[data-theme="orgullo"] .selector-box:hover{border-color:var(--accent-dark)!important;background:rgba(139,92,246,0.05)!important;}';
c+='html[data-theme="orgullo"] .selector-box.active{border-color:var(--accent-dark)!important;background:rgba(139,92,246,0.1)!important;}';
c+='html[data-theme="orgullo"] #logoutSidebarBtn:hover{background:var(--accent-bg)!important;color:var(--accent)!important;border-color:var(--accent)!important;}';
c+='html[data-theme="orgullo"] .sidebar-close-focus{background:rgba(139,92,246,0.12);color:#6d28d9;}';
c+='html[data-theme="orgullo"] .sidebar-close-focus:hover{background:rgba(139,92,246,0.22)!important;color:var(--accent-dark)!important;}';
c+='html[data-theme="orgullo"] .focus-tab{background:rgba(240,232,250,0.94);color:#6d28d9;box-shadow:0.125rem 0 0.625rem rgba(84,52,142,0.2);}';
c+='html[data-theme="orgullo"] .focus-tab:hover,html[data-theme="orgullo"] .focus-tab:focus-visible{color:#4c1d95;}';
/* Body & page title */
c+=U+'.page-title h1{color:#33264d!important;}';
c+=U+'.page-title h1 i{color:var(--accent-dark)!important;}';
c+=U+'.page-title p{color:#77689c!important;}';

/* Sidebar compact */
if(sidebarCompact==='true'){
  c+='html .win-sidebar{width:4.6875rem!important;}';
  c+='html .win-sidebar .sidebar-text,html .win-sidebar .sidebar-expand-only{display:none!important;}';
  c+='html .win-sidebar .nav-item{justify-content:center;padding:0.75rem!important;}';
  c+='html .win-sidebar .nav-item i{margin:0!important;font-size:1.3rem!important;}';
  c+='html .main-container{margin-left:4.6875rem!important;}';
}

/* Movil (viewport <=768px): sidebar fuera de canvas y contenido a ancho completo.
   Va al FINAL del blob para ganar la cascada frente a las reglas de escritorio. */
c+='@media (max-width:768px){'
 +'html .win-sidebar{transform:translateX(-100%)!important;}'
 +'html .win-sidebar.mobile-open{transform:translateX(0)!important;width:min(16.5625rem,85vw)!important;}'
 +'html .main-container{margin-left:0!important;width:auto!important;}'
 +'html .main-container.expanded{margin-left:0!important;}'
 +'body:has(.win-sidebar.collapsed) .main-container{margin-left:0!important;}'
 +'html.focus-mode .main-container{margin-left:0!important;}'
 +'.main-container>.glass-card,.main-container>.mb-3,.main-container>.fade-in-up,.main-container>.win-topbar{max-width:100%!important;margin-left:0!important;margin-right:0!important;}'
 +'}';

/* ===== EMULACIÓN DE FORMA (selección manual de dispositivo) =====
   Debe añadirse a c ANTES de volcarlo al <style>.
   Móvil usa el mismo mecanismo que el modo enfoque: pestañita + hover + focus-pinned. */
if(!perDev&&manualDev&&manualDev!==((_w<=768)?'movil':((_w<=1024)?'tableta':'pc'))){
    var MD='html[data-device="movil"][data-device-manual]';
    c+=MD+' .win-sidebar{transform:translateX(-100%)!important;}'
     +MD+' .win-sidebar:hover,'+MD+' .win-sidebar:focus-within,'+MD+' body:has(.focus-tab:hover) .win-sidebar,'+MD+' .win-sidebar.focus-pinned,'+MD+' .win-sidebar.mobile-open{transform:translateX(0)!important;}'
     +MD+' .focus-tab{display:flex!important;}'
     +MD+' .win-sidebar.focus-pinned+.focus-tab{opacity:1;color:var(--accent);}'
     +MD+' .win-sidebar.focus-pinned .sidebar-close-focus{display:flex!important;background:rgba(255,255,255,0.08);color:#94a3b8;}'
     +MD+' .win-sidebar.focus-pinned .sidebar-close-focus:hover{background:rgba(var(--accent-rgb),0.18)!important;color:var(--accent)!important;}'
     +MD+' .main-container,'+MD+' .main-container.expanded{margin-left:0!important;width:auto!important;}'
     +MD+' .page-content,'+MD+' .fluid-container{max-width:none!important;}'
     +MD+' .main-container>.glass-card,'+MD+' .main-container>.mb-3,'+MD+' .main-container>.fade-in-up,'+MD+' .main-container>.win-topbar{max-width:100%!important;margin-left:0!important;margin-right:0!important;}';
    var TD='html[data-device="tableta"][data-device-manual]';
    c+=TD+' .win-sidebar{width:5rem!important;}'
     +TD+' .win-sidebar .sidebar-text,'+TD+' .win-sidebar .sidebar-expand-only{display:none!important;}'
     +TD+' .win-sidebar .nav-item{justify-content:center;padding:0.75rem!important;}'
     +TD+' .win-sidebar .nav-item i{margin:0!important;font-size:1.3rem!important;}'
     +TD+' .main-container{margin-left:5rem!important;}';
}

var st=document.createElement('style');
st.id='theme-config-all';
st.textContent=c;
d.appendChild(st);

/* ===== DOM-DEPENDENT: Sidebar compact (needs querySelector) ===== */
if(sidebarCompact==='true'){
  function applyCompact(){
    var sidebar=document.querySelector('.win-sidebar');
    var mainC=document.querySelector('.main-container');
    if(window.innerWidth<=768){
      if(sidebar){sidebar.classList.add('collapsed');sidebar.style.width='';}
      if(mainC)mainC.style.marginLeft='';
      return;
    }
    if(sidebar){sidebar.classList.add('collapsed');sidebar.style.width='4.6875rem';}
    if(mainC)mainC.style.marginLeft='4.6875rem';
  }
  var compactTimer=null;
  window.addEventListener('resize',function(){
    if(compactTimer)clearTimeout(compactTimer);
    compactTimer=setTimeout(applyCompact,120);
  });
  if(document.readyState==='loading'){
    document.addEventListener('DOMContentLoaded',applyCompact);
  }else{
    applyCompact();
  }
}

/* ===== RECARGA AL CAMBIAR DE DISPOSITIVO (solo modo automático) ===== */
if(perDev&&!manualDev){
    var temporizadorResize=null;      /* ID del debounce: solo actuamos cuando los resize dejan de llegar */
    var msArranquePagina=Date.now();  /* Cuándo empezó a cargar esta página (para ignorar el asentamiento) */
    var recargasAutomaticas=0;        /* Recargas auto en la ventana actual de 30 s */
    var msUltimaRecarga=0;            /* Cuándo fue la última recarga automática */
    try{
        /* sessionStorage sobrevive al location.reload(): aqui recordamos cuantas recargas llevamos */
        recargasAutomaticas=parseInt(sessionStorage.getItem('transnubet_recargas_auto')||'0',10)||0;
        msUltimaRecarga=parseInt(sessionStorage.getItem('transnubet_recargas_auto_ms')||'0',10)||0;
        if(Date.now()-msUltimaRecarga>30000){recargasAutomaticas=0;} /* ventana vencida: reiniciar */
    }catch(e){}
    window.addEventListener('resize',function(){
        if(temporizadorResize)clearTimeout(temporizadorResize);
        temporizadorResize=setTimeout(function(){
            /* Guarda 1: ignorar el asentamiento inicial del viewport (evita bucle en moviles:
               el script corre antes del <meta viewport>, mide mal y se recargaria solo) */
            if(Date.now()-msArranquePagina<1500){return;}
            /* Guarda 2: maximo 3 recargas automaticas por cada 30 s (corta cualquier bucle) */
            if(recargasAutomaticas>=3){return;}
            var nuevoDispositivo=clasificarDispositivo();
            if(nuevoDispositivo!==DEV){
                recargasAutomaticas++;msUltimaRecarga=Date.now();
                try{sessionStorage.setItem('transnubet_recargas_auto',String(recargasAutomaticas));
                    sessionStorage.setItem('transnubet_recargas_auto_ms',String(msUltimaRecarga));}catch(e){}
                location.reload();
            }
        },500);
    });
}

}catch(e){}
})();
</script>
