<?php
if(!isset($_SESSION['user_name'])){
    // Redirect to app login using configured base path
    header('Location: ' . (defined('APP_BASE') ? APP_BASE : '') . '/index');
    exit();
}
?>
<!doctype html>
<html lang="en">
  <head>
    <!-- Required meta tags -->
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <?php $__base = (defined('APP_BASE') ? APP_BASE : ''); if ($__base !== '' && substr($__base,-1) !== '/') { $__base .= '/'; } ?>
    <base href="<?php echo $__base === '' ? '/' : $__base; ?>">
    <link rel="shortcut icon" href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/img/favicon.ico" type="image/x-icon">
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/css/bootstrap.min.css">
    <link rel="stylesheet" href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/css/signin.css">
    <link href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/css/bootstrap-select.min.css">
    <title><?php echo htmlspecialchars(isset($title) && $title !== '' ? $title : 'MIS@SLGTI', ENT_QUOTES, 'UTF-8'); ?></title>
    <style>
      :root {
        --bg-body: #f8f9fa;
        --bg-card: #ffffff;
        --text-body: #212529;
        --text-muted: #6c757d;
        --border-color: #dee2e6;
      }
      body.theme-dark {
        --bg-body: #121212;
        --bg-card: #1e1e1e;
        --text-body: #e9ecef;
        --text-muted: #adb5bd;
        --border-color: #343a40;
      }
      body { background-color: var(--bg-body); color: var(--text-body); }
      .bg-white { background-color: var(--bg-card) !important; }
      .card { background-color: var(--bg-card); border-color: var(--border-color); }
      .card-header { background-color: var(--bg-card); border-bottom-color: var(--border-color); }
      .text-muted { color: var(--text-muted) !important; }
      .border, .border-bottom, .border-top { border-color: var(--border-color) !important; }
      .nav-tabs .nav-link { color: var(--text-body); }
      .nav-tabs .nav-link.active { background-color: var(--bg-card); border-color: var(--border-color) var(--border-color) transparent; }
      /* Mobile-friendly dropdown in top nav */
      @media (max-width: 576px) {
        .dropdown-menu-mobile {
          position: static !important;
          float: none !important;
          width: 100% !important;
          margin-top: .5rem !important;
          border-radius: .25rem !important;
        }
        .navbar .dropdown-menu-mobile .dropdown-item {
          padding-top: .75rem;
          padding-bottom: .75rem;
        }
      }
    </style>
    <?php $__is_debug = (isset($_GET['debug']) && $_GET['debug'] === '1'); $__is_admin_like = (isset($_SESSION['user_type']) && in_array($_SESSION['user_type'], ['ADM','SAO'], true)); ?>
    <?php if ($__is_debug && $__is_admin_like) { ?>
    <style>
      #slgti-debug-overlay { position: fixed; z-index: 2147483647; bottom: 0; left: 0; right: 0; max-height: 40vh; overflow:auto; background: rgba(0,0,0,.85); color: #ffe08a; font: 12px/1.4 monospace; padding: 8px; border-top: 2px solid #ffc107; }
      #slgti-debug-overlay .dbg { margin: 4px 0; white-space: pre-wrap; word-break: break-word; }
      #slgti-debug-overlay .dbg b { color: #fff; }
    </style>
    <script>
      (function(){
        var box;
        function ensure(){
          if (!box){
            box = document.createElement('div'); box.id = 'slgti-debug-overlay';
            box.innerHTML = '<div class="dbg"><b>Debug enabled</b> — JS errors will appear here.</div>';
            document.addEventListener('DOMContentLoaded', function(){ document.body.appendChild(box); });
          }
          return box;
        }
        function log(msg){ try { ensure().appendChild(document.createElement('div')).className='dbg'; ensure().lastChild.innerHTML = msg; } catch(e){}
        }
        window.addEventListener('error', function(e){
          try{
            var m = '<b>ERROR:</b> ' + (e.message||'') + ' at ' + (e.filename||'') + ':' + (e.lineno||'') + ':' + (e.colno||'');
            log(m);
          }catch(_){ }
        });
        window.addEventListener('unhandledrejection', function(e){
          try{
            var r = e.reason; var msg = (r && (r.stack||r.message)) ? (r.stack||r.message) : String(r);
            log('<b>UNHANDLED PROMISE:</b> ' + msg);
          }catch(_){ }
        });
        // Warn if jQuery missing (Bootstrap JS may need it)
        setTimeout(function(){ if(!window.jQuery){ log('<b>WARNING:</b> jQuery not loaded before Bootstrap. Some UI may not work.'); } }, 1500);
      })();
    </script>
    <?php } ?>
    <script>
      // Apply saved theme ASAP to avoid flash
      (function(){
        try {
          var t = localStorage.getItem('slgti_theme');
          if (t === 'dark') {
            document.documentElement.classList.add('js');
            document.addEventListener('DOMContentLoaded', function(){ document.body.classList.add('theme-dark'); });
          }
        } catch(e){}
      })();
    </script>
    <?php if (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'STU') { ?>
    <script>
      // Defer execution until DOM is ready
      document.addEventListener('DOMContentLoaded', function () {
        try {
          const removeMenuBySectionTitle = (title) => {
            document.querySelectorAll('#sidebar .sidebar-dropdown > a span').forEach(span => {
              if (span.textContent && span.textContent.trim().toLowerCase() === title.toLowerCase()) {
                const li = span.closest('li.sidebar-dropdown');
                if (li) li.remove();
              }
            });
          };

          // Remove entire sections for students
          removeMenuBySectionTitle('Departments');
          removeMenuBySectionTitle('Canteen');
          removeMenuBySectionTitle('Blood Donations');

          // Remove Calendar (single link under Extra)
          const calendarLink = document.querySelector('#sidebar a[href="Timetable.new"], #sidebar a span');
          // Safer text-based removal if href changes
          document.querySelectorAll('#sidebar a').forEach(a => {
            if ((a.getAttribute('href') && a.getAttribute('href').includes('Timetable.new')) ||
                (a.textContent && a.textContent.trim().toLowerCase() === 'calendar')) {
              const li = a.closest('li');
              if (li) li.remove();
            }
          });
        } catch (e) {
          // Fail silently
        }
      });
    </script>
    <?php } ?>
  </head>
  <body>
  <div class="page-wrapper chiller-theme toggled">
  <a id="show-sidebar" class="btn btn-sm btn-dark" href="#">
    <i class="fas fa-bars"></i>
  </a>
