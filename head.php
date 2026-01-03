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
      /* ============================================
         LIGHT THEME - MODERN & CLEAN DESIGN
         ============================================ */
      
      /* Color Variables - Light Theme */
      :root {
        --bg-body: #f8f9fa;
        --bg-card: #ffffff;
        --bg-sidebar: #ffffff;
        --text-primary: #1e293b;
        --text-secondary: #475569;
        --text-muted: #64748b;
        --border-color: #e2e8f0;
        --border-light: #f1f5f9;
        --primary-color: #2563eb;
        --primary-hover: #1d4ed8;
        --primary-light: #dbeafe;
        --success-color: #10b981;
        --warning-color: #f59e0b;
        --danger-color: #ef4444;
        --info-color: #06b6d4;
        --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
        --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
      }
      
      /* Base Body Styles */
      body { 
        background-color: var(--bg-body); 
        color: var(--text-primary);
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
        line-height: 1.6;
      }
      
      /* Card Styles */
      .bg-white { 
        background-color: var(--bg-card) !important; 
      }
      
      .card { 
        background-color: var(--bg-card); 
        border: 1px solid var(--border-color);
        border-radius: 12px;
        box-shadow: var(--shadow-md);
        transition: all 0.3s ease;
      }
      
      .card:hover {
        box-shadow: var(--shadow-lg);
        transform: translateY(-2px);
      }
      
      .card-header { 
        background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-hover) 100%);
        color: #ffffff;
        border: none;
        padding: 1rem 1.5rem;
        font-weight: 600;
        border-radius: 12px 12px 0 0 !important;
      }
      
      .card-header.bg-light {
        background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%) !important;
        color: var(--text-primary) !important;
        border-bottom: 2px solid var(--border-light);
      }
      
      .card-header.bg-info {
        background: linear-gradient(135deg, var(--info-color) 0%, #0891b2 100%) !important;
        color: #ffffff !important;
      }
      
      .card-body {
        color: var(--text-primary);
        padding: 1.5rem;
      }
      
      /* Text Colors */
      .text-muted { 
        color: var(--text-muted) !important; 
      }
      
      .text-primary {
        color: var(--primary-color) !important;
      }
      
      /* Borders */
      .border, .border-bottom, .border-top { 
        border-color: var(--border-color) !important; 
      }
      
      /* Navigation Tabs */
      .nav-tabs .nav-link { 
        color: var(--text-primary);
        border-color: var(--border-color);
      }
      
      .nav-tabs .nav-link.active { 
        background-color: var(--bg-card); 
        border-color: var(--border-color) var(--border-color) transparent;
        color: var(--primary-color);
        font-weight: 600;
      }
      
      /* Form Controls - Light Theme */
      input[type="text"], 
      input[type="email"], 
      input[type="password"], 
      input[type="number"], 
      input[type="date"], 
      input[type="time"],
      select, 
      textarea {
        border: 1.5px solid var(--border-color);
        border-radius: 8px;
        padding: 0.65rem 0.95rem;
        transition: all 0.3s ease;
        font-size: 0.95rem;
        color: var(--text-primary) !important;
        background-color: #ffffff;
      }
      
      input:focus, 
      select:focus, 
      textarea:focus {
        border-color: var(--primary-color);
        box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        outline: none;
        color: var(--text-primary) !important;
        background-color: #ffffff;
      }
      
      input::placeholder, 
      textarea::placeholder {
        color: var(--text-muted) !important;
      }
      
      input:disabled, 
      select:disabled, 
      textarea:disabled {
        background-color: #f8fafc;
        color: var(--text-muted) !important;
        cursor: not-allowed;
      }
      
      /* Buttons - Light Theme */
      .btn {
        border-radius: 8px;
        padding: 0.6rem 1.5rem;
        font-weight: 600;
        transition: all 0.3s ease;
        letter-spacing: 0.5px;
        border: none;
      }
      
      .btn-primary {
        background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-hover) 100%);
        color: #ffffff;
        box-shadow: var(--shadow-sm);
      }
      
      .btn-primary:hover {
        background: linear-gradient(135deg, #3b82f6 0%, var(--primary-color) 100%);
        box-shadow: var(--shadow-md);
        transform: translateY(-2px);
        color: #ffffff;
      }
      
      .btn-success {
        background: linear-gradient(135deg, var(--success-color) 0%, #059669 100%);
        color: #ffffff;
        box-shadow: var(--shadow-sm);
      }
      
      .btn-success:hover {
        background: linear-gradient(135deg, #34d399 0%, var(--success-color) 100%);
        box-shadow: var(--shadow-md);
        transform: translateY(-2px);
        color: #ffffff;
      }
      
      .btn-outline-primary {
        border: 2px solid var(--primary-color);
        color: var(--primary-color);
        background: transparent;
      }
      
      .btn-outline-primary:hover {
        background: var(--primary-color);
        color: #ffffff;
        transform: translateY(-2px);
      }
      
      /* Tables - Light Theme */
      .table {
        color: var(--text-primary) !important;
        background-color: #ffffff;
      }
      
      .table thead th {
        background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
        color: #ffffff !important;
        font-weight: 600;
        text-transform: uppercase;
        font-size: 0.85rem;
        letter-spacing: 0.5px;
        border: none;
        padding: 1rem;
      }
      
      .table thead th * {
        color: #ffffff !important;
      }
      
      .table tbody {
        background-color: #ffffff;
        color: var(--text-primary) !important;
      }
      
      .table tbody tr {
        transition: all 0.2s ease;
        color: var(--text-primary) !important;
      }
      
      .table tbody tr:hover {
        background-color: #f8fafc;
      }
      
      .table tbody td {
        color: var(--text-primary) !important;
        border-color: var(--border-light);
      }
      
      .table-striped tbody tr:nth-of-type(odd) {
        background-color: #f8fafc;
      }
      
      .table tbody tr.table-info {
        background-color: var(--primary-light);
        color: #1e40af !important;
      }
      
      .table tbody tr.table-info td {
        color: #1e40af !important;
      }
      
      /* Labels - Light Theme */
      label, 
      .form-label {
        color: var(--text-secondary) !important;
        font-weight: 600;
        margin-bottom: 0.5rem;
      }
      
      .col-form-label {
        color: var(--text-secondary) !important;
      }
      
      .form-check-label {
        color: var(--text-primary) !important;
      }
      
      label small,
      .form-label small {
        color: var(--text-muted) !important;
      }
      
      /* Card Body Text */
      .card-body h1, 
      .card-body h2, 
      .card-body h3, 
      .card-body h4, 
      .card-body h5, 
      .card-body h6 {
        color: #0f172a !important;
        font-weight: 700;
      }
      
      .card-body p, 
      .card-body span, 
      .card-body div {
        color: var(--text-primary) !important;
      }
      
      .card-body .text-muted {
        color: var(--text-muted) !important;
      }
      
      /* Mobile Dropdown */
      @media (max-width: 576px) {
        .dropdown-menu-mobile {
          position: static !important;
          float: none !important;
          width: 100% !important;
          margin-top: .5rem !important;
          border-radius: .5rem !important;
        }
        
        .navbar .dropdown-menu-mobile .dropdown-item {
          padding-top: .75rem;
          padding-bottom: .75rem;
        }
        
        #sidebar { 
          position: fixed; 
          top: 0; 
          bottom: 0; 
          z-index: 1040; 
        }
        
        #sidebar .sidebar-content, 
        #sidebar .sidebar-menu a { 
          position: relative; 
          z-index: 1041; 
        }
        
        .page-wrapper .page-content { 
          position: relative; 
          z-index: 1; 
        }
      }
      
      /* Utility Classes */
      .hod-desktop-offset {
        margin-left: 0;
      }
      
      @media (min-width: 992px) {
        .hod-desktop-offset { 
          margin-left: 0; 
        }
      }
      
      .center-card-form .card-body {
        display: flex;
        justify-content: center;
      }
      
      .center-card-form .card-body > form,
      .center-card-form .card-body > .form-container {
        width: 100%;
        max-width: 760px;
      }
    </style>
    
    <?php 
    $__is_debug = (isset($_GET['debug']) && $_GET['debug'] === '1'); 
    $__is_admin_like = (isset($_SESSION['user_type']) && in_array($_SESSION['user_type'], ['ADM','SAO'], true)); 
    ?>
    
    <?php if ($__is_debug && $__is_admin_like) { ?>
    <style>
      #slgti-debug-overlay { 
        position: fixed; 
        z-index: 2147483647; 
        bottom: 0; 
        left: 0; 
        right: 0; 
        max-height: 40vh; 
        overflow: auto; 
        background: rgba(0,0,0,.85); 
        color: #ffe08a; 
        font: 12px/1.4 monospace; 
        padding: 8px; 
        border-top: 2px solid #ffc107; 
      }
      #slgti-debug-overlay .dbg { 
        margin: 4px 0; 
        white-space: pre-wrap; 
        word-break: break-word; 
      }
      #slgti-debug-overlay .dbg b { 
        color: #fff; 
      }
    </style>
    <script>
      (function(){
        var box;
        function ensure(){
          if (!box){
            box = document.createElement('div'); 
            box.id = 'slgti-debug-overlay';
            box.innerHTML = '<div class="dbg"><b>Debug enabled</b> — JS errors will appear here.</div>';
            document.addEventListener('DOMContentLoaded', function(){ 
              document.body.appendChild(box); 
            });
          }
          return box;
        }
        function log(msg){ 
          try { 
            ensure().appendChild(document.createElement('div')).className='dbg'; 
            ensure().lastChild.innerHTML = msg; 
          } catch(e){} 
        }
        window.addEventListener('error', function(e){
          try{
            var m = '<b>ERROR:</b> ' + (e.message||'') + ' at ' + (e.filename||'') + ':' + (e.lineno||'') + ':' + (e.colno||'');
            log(m);
          }catch(_){ }
        });
        window.addEventListener('unhandledrejection', function(e){
          try{
            var r = e.reason; 
            var msg = (r && (r.stack||r.message)) ? (r.stack||r.message) : String(r);
            log('<b>UNHANDLED PROMISE:</b> ' + msg);
          }catch(_){ }
        });
        setTimeout(function(){ 
          if(!window.jQuery){ 
            log('<b>WARNING:</b> jQuery not loaded before Bootstrap. Some UI may not work.'); 
          } 
        }, 1500);
      })();
    </script>
    <script>
      // Heartbeat to record activity and keep last_seen fresh
      (function(){
        if (!window.fetch) return;
        var base = (typeof document.querySelector('base') !== 'undefined' && document.querySelector('base')) ? document.querySelector('base').getAttribute('href') || '' : '';
        function url(p){ try { return (base||'') + p; } catch(e){ return p; } }
        function ping(){
          try { 
            fetch(url('controller/Heartbeat.php'), { 
              method:'POST', 
              credentials:'include', 
              cache:'no-cache' 
            }); 
          } catch(_){ }
        }
        document.addEventListener('DOMContentLoaded', function(){
          ping();
          try { 
            window.__slgtiHeartbeat = setInterval(ping, 60000); 
          } catch(_){ }
        });
        function bye(reason){
          try {
            var data = new Blob([], {type: 'text/plain'});
            if (navigator.sendBeacon) { 
              navigator.sendBeacon(url('controller/LogoutBeacon.php?r=' + encodeURIComponent(reason||'close')), data); 
            } else { 
              fetch(url('controller/LogoutBeacon.php?r=' + encodeURIComponent(reason||'close')), { 
                method:'POST', 
                credentials:'include', 
                keepalive:true 
              }); 
            }
          } catch(_){ }
        }
        window.addEventListener('beforeunload', function(){ bye('close'); });
        document.addEventListener('visibilitychange', function(){ 
          if (document.visibilityState === 'hidden') { 
            bye('hidden'); 
          } 
        });
      })();
    </script>
    <?php } ?>
    
    <script>
      // Mobile: close sidebar after clicking a menu link
      document.addEventListener('DOMContentLoaded', function(){
        try {
          var wrapper = document.querySelector('.page-wrapper');
          document.addEventListener('click', function(e) {
            var link = e.target.closest('#sidebar a[href]');
            if (!link) return;
            
            var href = link.getAttribute('href');
            var isDropdownToggle = link.parentElement && link.parentElement.classList && 
                                   link.parentElement.classList.contains('sidebar-dropdown') &&
                                   (href === '#' || href === 'javascript:void(0)' || !href);
            
            if (!isDropdownToggle && href && href !== '#' && href !== 'javascript:void(0)') {
              if (window.innerWidth < 992 && wrapper) {
                setTimeout(function(){ 
                  wrapper.classList.remove('toggled'); 
                }, 100);
              }
            }
          }, true);
        } catch(e) { /* no-op */ }
      });
    </script>
    
    <?php if (isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'STU') { ?>
    <script>
      // Remove menu sections for students
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
          removeMenuBySectionTitle('Departments');
          removeMenuBySectionTitle('Canteen');
          removeMenuBySectionTitle('Blood Donations');
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
  <!-- Sidebar overlay for mobile -->
  <div class="sidebar-overlay" style="display: none;"></div>
  <a id="show-sidebar" class="btn btn-sm btn-dark" href="#">
    <i class="fas fa-bars"></i>
  </a>
