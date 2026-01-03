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
    <!-- Bootstrap 4 CSS -->
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
      body { 
        background-color: var(--bg-body); 
        color: var(--text-body);
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
      }
      .bg-white { background-color: var(--bg-card) !important; }
      .card { 
        background-color: var(--bg-card); 
        border-color: var(--border-color);
        border-radius: 0.75rem;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        transition: all 0.3s ease;
      }
      .card:hover {
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        transform: translateY(-2px);
      }
      .card-header { 
        background: linear-gradient(135deg, #2563eb 0%, #1e40af 100%);
        color: #ffffff;
        border: none;
        padding: 1rem 1.5rem;
        font-weight: 600;
        border-radius: 0.75rem 0.75rem 0 0 !important;
      }
      .card-header.bg-light {
        background: linear-gradient(135deg, #f1f5f9 0%, #e2e8f0 100%) !important;
        color: #1e293b !important;
      }
      .card-header.bg-info {
        background: linear-gradient(135deg, #06b6d4 0%, #0891b2 100%) !important;
        color: #ffffff !important;
      }
      .text-muted { color: var(--text-muted) !important; }
      .border, .border-bottom, .border-top { border-color: var(--border-color) !important; }
      .nav-tabs .nav-link { color: var(--text-body); }
      .nav-tabs .nav-link.active { 
        background-color: var(--bg-card); 
        border-color: var(--border-color) var(--border-color) transparent;
        color: #2563eb;
        font-weight: 600;
      }
      
      /* Enhanced Form Inputs */
      input[type="text"], input[type="email"], input[type="password"], 
      input[type="number"], input[type="date"], input[type="time"],
      select, textarea {
        border: 1.5px solid #e2e8f0;
        border-radius: 8px;
        padding: 0.65rem 0.95rem;
        transition: all 0.3s ease;
        font-size: 0.95rem;
        color: #1e293b !important; /* Dark text on light background */
        background-color: #ffffff;
      }
      input:focus, select:focus, textarea:focus {
        border-color: #2563eb;
        box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15);
        outline: none;
        color: #1e293b !important; /* Dark text on focus */
        background-color: #ffffff;
      }
      input::placeholder, textarea::placeholder {
        color: #94a3b8 !important; /* Light gray placeholder */
      }
      input:disabled, select:disabled, textarea:disabled {
        background-color: #f1f5f9;
        color: #64748b !important; /* Muted dark text for disabled */
      }
      
      /* Enhanced Buttons */
      .btn {
        border-radius: 8px;
        padding: 0.6rem 1.5rem;
        font-weight: 600;
        transition: all 0.3s ease;
        letter-spacing: 0.5px;
      }
      .btn-primary {
        background: linear-gradient(135deg, #2563eb 0%, #1e40af 100%);
        border: none;
        box-shadow: 0 4px 6px rgba(37, 99, 235, 0.3);
      }
      .btn-primary:hover {
        background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
        box-shadow: 0 6px 12px rgba(37, 99, 235, 0.4);
        transform: translateY(-2px);
      }
      .btn-success {
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        border: none;
        box-shadow: 0 4px 6px rgba(16, 185, 129, 0.3);
      }
      .btn-success:hover {
        background: linear-gradient(135deg, #34d399 0%, #10b981 100%);
        box-shadow: 0 6px 12px rgba(16, 185, 129, 0.4);
        transform: translateY(-2px);
      }
      
      /* Enhanced Tables */
      .table {
        color: #1e293b !important; /* Dark text for table content */
      }
      .table thead th {
        background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
        color: #ffffff !important; /* White text on dark header */
        font-weight: 600;
        text-transform: uppercase;
        font-size: 0.85rem;
        letter-spacing: 0.5px;
        border: none;
        padding: 1rem;
      }
      .table thead th * {
        color: #ffffff !important; /* All text in dark header should be white */
      }
      .table tbody {
        background-color: #ffffff;
        color: #1e293b !important; /* Dark text on white background */
      }
      .table tbody tr {
        transition: all 0.2s ease;
        color: #1e293b !important;
      }
      .table tbody tr:hover {
        background-color: #f8fafc;
      }
      .table tbody td {
        color: #1e293b !important; /* Dark text in table cells */
      }
      .table tbody tr.table-info {
        background-color: #e0f2fe;
        color: #0c4a6e !important; /* Dark blue text on light blue background */
      }
      .table tbody tr.table-info td {
        color: #0c4a6e !important;
      }
      
      /* Card body text colors */
      .card-body {
        color: #1e293b !important; /* Dark text on white card body */
      }
      .card-body h1, .card-body h2, .card-body h3, 
      .card-body h4, .card-body h5, .card-body h6 {
        color: #0f172a !important; /* Very dark for headings */
      }
      .card-body p, .card-body span, .card-body div {
        color: #1e293b !important; /* Dark text for content */
      }
      .card-body .text-muted {
        color: #64748b !important; /* Muted dark gray */
      }
      
      /* Labels - always dark and visible on light backgrounds */
      label, .form-label {
        color: #475569 !important; /* Dark gray - always visible on light backgrounds */
        font-weight: 600;
      }
      
      /* Labels in dark card headers - white */
      .card-header:not(.bg-light) label,
      .card-header:not(.bg-light) .form-label {
        color: #ffffff !important; /* White labels only in dark headers */
      }
      
      /* Labels in card body and forms - always dark */
      .card-body label,
      .card-body .form-label,
      .form-group label,
      .form-group .form-label,
      .form-row label,
      .form-row .form-label,
      .page-content label,
      .page-content .form-label {
        color: #475569 !important; /* Dark gray - always visible */
      }
      
      .col-form-label {
        color: #475569 !important; /* Dark gray */
      }
      
      .form-check-label {
        color: #1e293b !important; /* Dark text for checkboxes/radios */
      }
      
      label small,
      .form-label small {
        color: #64748b !important; /* Muted dark gray for small label text */
      }
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
        /* Ensure sidebar links are clickable above content */
        #sidebar { position: fixed; top: 0; bottom: 0; z-index: 1040; }
        #sidebar .sidebar-content, #sidebar .sidebar-menu a { position: relative; z-index: 1041; }
        .page-wrapper .page-content { position: relative; z-index: 1; }
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
    <script>
      // Heartbeat to record activity and keep last_seen fresh; send beacon on unload to auto-logout
      (function(){
        if (!window.fetch) return; // minimal guard
        var base = (typeof document.querySelector('base') !== 'undefined' && document.querySelector('base')) ? document.querySelector('base').getAttribute('href') || '' : '';
        function url(p){ try { return (base||'') + p; } catch(e){ return p; } }
        function ping(){
          try { fetch(url('controller/Heartbeat.php'), { method:'POST', credentials:'include', cache:'no-cache' }); } catch(_){ }
        }
        // Ping right after load and every 60s
        document.addEventListener('DOMContentLoaded', function(){
          ping();
          try { window.__slgtiHeartbeat = setInterval(ping, 60000); } catch(_){ }
        });
        // Use sendBeacon on page unload to mark logout due to close/navigation
        function bye(reason){
          try {
            var data = new Blob([], {type: 'text/plain'});
            if (navigator.sendBeacon) { navigator.sendBeacon(url('controller/LogoutBeacon.php?r=' + encodeURIComponent(reason||'close')), data); }
            else { fetch(url('controller/LogoutBeacon.php?r=' + encodeURIComponent(reason||'close')), { method:'POST', credentials:'include', keepalive:true }); }
          } catch(_){ }
        }
        window.addEventListener('beforeunload', function(){ bye('close'); });
        document.addEventListener('visibilitychange', function(){ if (document.visibilityState === 'hidden') { bye('hidden'); } });
      })();
    </script>
    <?php } ?>
    <script>
      // Improve mobile: close sidebar after clicking a menu link
      // This runs after jQuery handlers, so we use a small delay
      document.addEventListener('DOMContentLoaded', function(){
        try {
          var wrapper = document.querySelector('.page-wrapper');
          // Use event delegation to handle dynamically added links
          document.addEventListener('click', function(e) {
            var link = e.target.closest('#sidebar a[href]');
            if (!link) return;
            
            var href = link.getAttribute('href');
            // Do not close for dropdown toggles or non-navigating links
            var isDropdownToggle = link.parentElement && link.parentElement.classList && 
                                   link.parentElement.classList.contains('sidebar-dropdown') &&
                                   (href === '#' || href === 'javascript:void(0)' || !href);
            
            if (!isDropdownToggle && href && href !== '#' && href !== 'javascript:void(0)') {
              // Only close on mobile
              if (window.innerWidth < 992 && wrapper) {
                setTimeout(function(){ 
                  wrapper.classList.remove('toggled'); 
                }, 100);
              }
            }
          }, true); // Use capture phase to run before other handlers
        } catch(e) { /* no-op */ }
      });
    </script>
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
    <style>
    /* Desktop-only offset for HOD and similar pages */
    @media (min-width: 992px) { /* lg and up */
      .hod-desktop-offset { margin-left: -120px; }
    }
    @media (max-width: 991.98px) { /* below lg */
      .hod-desktop-offset { margin-left: 0 !important; }
    }
    /* Utility: Center forms within card bodies (use on cards via class 'center-card-form') */
    .center-card-form .card-body {
      display: flex;
      justify-content: center;
    }
    .center-card-form .card-body > form,
    .center-card-form .card-body > .form-container {
      width: 100%;
      max-width: 760px; /* nice readable width */
    }
  </style>
</head>
  <body>
  <div class="page-wrapper chiller-theme toggled">
  <!-- Sidebar overlay for mobile -->
  <div class="sidebar-overlay" style="display: none;"></div>
  <a id="show-sidebar" class="btn btn-sm btn-dark" href="#">
    <i class="fas fa-bars"></i>
  </a>
