  
	<!-- DELETE MODEL - Bootstrap 4 -->
	<!-- Modal -->
	<div class="modal fade" id="confirm-delete" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
	    <div class="modal-dialog">
	        <div class="modal-content">
	            <div class="modal-body text-center">
	               <h1 class="display-4 text-danger"> <i class="fas fa-trash"></i> </h1>
	                <h1 class="font-weight-lighter">Are you sure?</h1>
	                <h4 class="font-weight-lighter"> Do you really want to delete these records? This process cannot be
                      undone. </h4>       
                <p class="debug-url"></p>
	            </div>
	            <div class="modal-footer">
                  <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                  <a class="btn btn-danger btn-ok">Delete</a>
	            </div>
	        </div>
	    </div>
	</div>
	<!-- END DELETE MODEL -->

  </div>
  <!-- Close container-fluid opened in menu.php -->
</main>
<!-- Close page-content opened in menu.php -->
</div>
<!-- Close page-wrapper opened in head.php -->

  <?php /* Footer removed globally as requested. Keeping scripts below intact. */ ?>
  <!-- Optional JavaScript -->
  <!-- jQuery (prefer local), then Bootstrap JS; if local jQuery fails, dynamic fallback below will load from CDN -->
  <script src="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/js/jquery.min.js"></script>
  <!-- Use only Bootstrap bundle (includes Popper) to avoid conflicts -->
  <script src="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/js/bootstrap.bundle.min.js"></script>
  <script src="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/js/bootstrap-select.min.js"></script>
  <script src="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/js/Chart.min.js"></script>
  <!-- Menu Toggle Script -->
  <script>
// $("#menu-toggle").click(function(e) {
//     e.preventDefault();
//     $("#wrapper").toggleClass("toggled");
// });

// Ensure jQuery is available even if the primary CDN is blocked (e.g., on some NGINX setups)
(function initWhenjQueryReady(init){
  // If jQuery already present, run immediately
  if (window.jQuery) return init(window.jQuery);
  // Try a secondary CDN
  var s = document.createElement('script');
  s.src = 'https://code.jquery.com/jquery-3.6.0.min.js';
  s.async = true;
  s.onload = function(){ init(window.jQuery); };
  s.onerror = function(){
    // As a last resort, wait until it appears (e.g., if injected elsewhere)
    var tries = 0; var t = setInterval(function(){
      if (window.jQuery || ++tries > 20) { clearInterval(t); if (window.jQuery) init(window.jQuery); }
    }, 200);
  };
  document.head.appendChild(s);
})(function ($) {

// Restore active submenus - Accordion behavior: only open dropdown matching current page
function restoreActiveSubmenus() {
  try {
    var sidebar = document.getElementById('sidebar');
    if (!sidebar) return;
    
    var all = $(".sidebar-dropdown");
    if (!all || all.length === 0) return;
    
    // Get current page path
    var currentPath = window.location.pathname;
    var currentHref = window.location.href;
    var currentFile = currentPath.split('/').pop() || '';
    
    // Normalize paths for comparison
    function normalizePath(path) {
      if (!path) return '';
      return path.replace(/^\//, '').split('?')[0].toLowerCase();
    }
    
    var normalizedCurrent = normalizePath(currentPath);
    
    // First, close all dropdowns
    all.each(function() {
      var $dropdown = $(this);
      $dropdown.removeClass('active');
      var $submenu = $dropdown.children('.sidebar-submenu');
      if ($submenu.length) {
        $submenu.hide();
      }
    });
    
    // Check if current page is Dashboard - if so, don't open any dropdown
    var isDashboard = normalizedCurrent.includes('dashboard/index') || 
                      normalizedCurrent.includes('dashboard/index.php') ||
                      (normalizedCurrent === '' && currentHref.includes('dashboard')) ||
                      normalizedCurrent === 'dashboard' ||
                      normalizedCurrent === 'dashboard/';
    
    if (isDashboard) {
      // Dashboard page - keep all dropdowns closed, only Dashboard link is active
      return;
    }
    
    // Find and open only the dropdown that contains the current page
    var activeFound = false;
    all.each(function(index) {
      if (activeFound) return; // Already found active menu
      
      var $dropdown = $(this);
      var $submenuLinks = $dropdown.find('.sidebar-submenu a');
      var isActive = false;
      
      // Check each submenu link
      $submenuLinks.each(function() {
        var subHref = $(this).attr('href') || '';
        if (!subHref || subHref === '#' || subHref === 'javascript:void(0)') return;
        
        var normalizedSub = normalizePath(subHref);
        
        // Check if current path matches this submenu link
        if (normalizedSub && 
            (normalizedCurrent.includes(normalizedSub) || 
             normalizedSub.includes(normalizedCurrent) ||
             (normalizedCurrent.includes(currentFile) && normalizedSub.includes(currentFile)) ||
             currentHref.includes(normalizedSub))) {
          isActive = true;
          return false; // break
        }
      });
      
      if (isActive) {
        $dropdown.addClass('active');
        var $submenu = $dropdown.children('.sidebar-submenu');
        if ($submenu.length) {
          $submenu.show();
        }
        activeFound = true;
        try {
          localStorage.setItem('slgti_active_menu_index', index.toString());
        } catch(e) {}
      }
    });
  } catch(e) {
    console.error('Error in restoreActiveSubmenus:', e);
  }
}

function saveActiveSubmenus() {
  try {
    var activeIndex = -1;
    $(".sidebar-dropdown").each(function(i, el){
      try {
        if ($(el).hasClass('active')) {
          activeIndex = i;
          return false; // break
        }
      } catch(e) {
        console.warn('Error checking dropdown state:', e);
      }
    });
    if (activeIndex >= 0) {
      localStorage.setItem('slgti_active_menu_index', activeIndex.toString());
    } else {
      localStorage.removeItem('slgti_active_menu_index');
    }
  } catch(e) {
    console.warn('Error saving active submenus:', e);
  }
}

// If the sidebar markup is not present, skip initializing
if (document.getElementById('sidebar')) {
  restoreActiveSubmenus();

  // Use delegated event handler to work with dynamically added elements
  // Remove any existing handlers first to prevent duplicates
  $(document).off('click', '.sidebar-dropdown > a');
  $(document).on('click', '.sidebar-dropdown > a', function(e) {
    try {
      var $link = $(this);
      if (!$link || !$link.length) return true;
      
      var $dropdown = $link.parent('.sidebar-dropdown');
      if (!$dropdown || !$dropdown.length) return true;
      
      var $submenu = $link.next('.sidebar-submenu');
      var href = ($link.attr('href') || '').trim();
      
      // Only handle dropdown toggles (links with href="#" or empty)
      if (href && href !== '#' && href !== 'javascript:void(0)' && href !== '') {
        return true; // Allow normal navigation - don't prevent default
      }
      
      // Prevent default and stop propagation for dropdown toggles
      e.preventDefault();
      e.stopPropagation();
      e.stopImmediatePropagation();
      
      // ACCORDION BEHAVIOR: Close all other dropdowns first
      var isCurrentlyActive = $dropdown.hasClass('active');
      
      // Close all dropdowns
      $(".sidebar-dropdown").each(function() {
        var $otherDropdown = $(this);
        if ($otherDropdown[0] !== $dropdown[0]) {
          $otherDropdown.removeClass('active');
          var $otherSubmenu = $otherDropdown.children('.sidebar-submenu');
          if ($otherSubmenu.length) {
            $otherSubmenu.slideUp(200);
          }
        }
      });
      
      // Toggle the clicked dropdown
      if (isCurrentlyActive) {
        // Close this dropdown
        $dropdown.removeClass('active');
        if ($submenu.length) {
          $submenu.slideUp(200, function() {
            try {
              saveActiveSubmenus();
            } catch(err) {
              console.warn('Error saving submenus:', err);
            }
          });
        } else {
          saveActiveSubmenus();
        }
      } else {
        // Open this dropdown
        $dropdown.addClass('active');
        if ($submenu.length) {
          $submenu.slideDown(200, function() {
            try {
              saveActiveSubmenus();
            } catch(err) {
              console.warn('Error saving submenus:', err);
            }
          });
        } else {
          saveActiveSubmenus();
        }
      }
      
      return false; // Prevent any further event handling
    } catch(err) {
      console.error('Error in dropdown click handler:', err);
      return true; // Allow default behavior on error
    }
  });

function saveSidebarOpenState(isOpen){
  try { localStorage.setItem('slgti_sidebar_open', isOpen ? '1' : '0'); } catch(e){}
}

  // Use delegated handlers to be resilient to markup timing and support touch
  $(document).on('click touchstart', "#close-sidebar", function(e) {
    e.preventDefault();
    e.stopPropagation();
    $(".page-wrapper").removeClass("toggled");
    saveSidebarOpenState(false);
    // Trigger resize to ensure proper layout recalculation
    setTimeout(function() {
      $(window).trigger('resize');
    }, 100);
  });
  $(document).on('click touchstart', "#show-sidebar", function(e) {
    e.preventDefault();
    e.stopPropagation();
    $(".page-wrapper").addClass("toggled");
    saveSidebarOpenState(true);
    // Trigger resize to ensure proper layout recalculation
    setTimeout(function() {
      $(window).trigger('resize');
    }, 100);
  });
  
  // Close sidebar when clicking overlay on mobile
  $(document).on('click touchstart', ".sidebar-overlay", function(e) {
    if ($(window).width() < 992) {
      $(".page-wrapper").removeClass("toggled");
      saveSidebarOpenState(false);
    }
  });
  
  // Update overlay visibility when sidebar toggles
  function updateOverlay() {
    if ($(window).width() < 992) {
      if ($(".page-wrapper").hasClass("toggled")) {
        $(".sidebar-overlay").fadeIn(300);
      } else {
        $(".sidebar-overlay").fadeOut(300);
      }
    } else {
      $(".sidebar-overlay").hide();
    }
  }
  
  $(document).on('click touchstart', "#close-sidebar, #show-sidebar", function() {
    setTimeout(updateOverlay, 100);
  });
  
  // Update overlay on window resize
  $(window).on('resize', function() {
    updateOverlay();
  });
  
  // Initial overlay state
  setTimeout(updateOverlay, 100);
  
  // Better overlay click handler for mobile - close sidebar when clicking outside
  $(document).on('click touchstart', function(e) {
    if ($(window).width() < 992 && $(".page-wrapper").hasClass("toggled")) {
      // Check if click is outside sidebar and not on toggle buttons
      if (!$(e.target).closest('.sidebar-wrapper').length && 
          !$(e.target).closest('#show-sidebar').length &&
          !$(e.target).closest('#close-sidebar').length) {
        $(".page-wrapper").removeClass("toggled");
        $(".sidebar-overlay").hide();
        saveSidebarOpenState(false);
      }
    }
  });

  // Responsive behavior: on mobile, start collapsed and auto-close after navigation
  function isMobile() {
    return window.matchMedia('(max-width: 991.98px)').matches; // Bootstrap lg breakpoint
  }

  // Initial state based on saved preference; if none, default: desktop open, mobile closed
  (function(){
    var saved = null; try { saved = localStorage.getItem('slgti_sidebar_open'); } catch(e){}
    if (saved === '1') {
      $(".page-wrapper").addClass("toggled");
    } else if (saved === '0') {
      $(".page-wrapper").removeClass("toggled");
    } else {
      if (isMobile()) {
        $(".page-wrapper").removeClass("toggled");
      } else {
        $(".page-wrapper").addClass("toggled");
      }
    }
  })();

  // Update on resize, but respect saved preference if present
  $(window).on('resize', function() {
    var saved = null; try { saved = localStorage.getItem('slgti_sidebar_open'); } catch(e){}
    if (saved === '1') { $(".page-wrapper").addClass("toggled"); return; }
    if (saved === '0') { $(".page-wrapper").removeClass("toggled"); return; }
    if (isMobile()) { $(".page-wrapper").removeClass("toggled"); } else { $(".page-wrapper").addClass("toggled"); }
  });

  // After clicking any real navigation link on mobile, hide the sidebar
  // Do NOT close when clicking dropdown togglers or placeholder links (#)
  // Use a lower priority handler that runs after dropdown handlers
  $(document).on('click touchstart', '#sidebar a', function(e) {
    try {
      var $a = $(this);
      var href = ($a.attr('href') || '').trim();
      var isDropdownToggler = $a.parent().is('.sidebar-dropdown') && $a.is('.sidebar-dropdown > a');
      var isPlaceholder = href === '' || href === '#' || href === 'javascript:void(0)';
      
      // Skip if this is a dropdown toggle (handled by dropdown handler)
      if (isDropdownToggler && isPlaceholder) {
        return; // Let dropdown handler process this
      }
      
      // Only close sidebar for actual navigation links on mobile
      if (!isPlaceholder && isMobile()) {
        setTimeout(function() {
          $(".page-wrapper").removeClass("toggled");
          saveSidebarOpenState(false);
        }, 100);
      }
    } catch(_){}
  });
}

// Hook delete confirmation modal to display and set the target URL safely (Bootstrap 4)
$('#confirm-delete').on('show.bs.modal', function (e) {
  try {
    var trigger = e.relatedTarget ? $(e.relatedTarget) : null;
    var href = trigger ? (trigger.data('href') || trigger.attr('href') || '#') : '#';
    $(this).find('.btn-ok').attr('href', href);
    $(this).find('.debug-url').html('Delete URL: <strong>' + href + '</strong>');
  } catch(err) { /* noop */ }
});

}); // end initWhenjQueryReady

// Admin Page Content Alignment Handler - Proper Script Structure
(function() {
  'use strict';
  
  var resizeTimer = null;
  
  function ensureProperAlignment() {
    try {
      var pageContent = document.querySelector('.page-content');
      var containerFluid = document.querySelector('.page-content .container-fluid');
      
      if (!pageContent || !containerFluid) return;
      
      // Ensure container is properly centered
      var computedStyle = window.getComputedStyle(containerFluid);
      var maxWidth = computedStyle.maxWidth;
      
      // If max-width is set, ensure margin auto for centering
      if (maxWidth && maxWidth !== 'none' && maxWidth !== '100%') {
        var marginLeft = computedStyle.marginLeft;
        var marginRight = computedStyle.marginRight;
        if (marginLeft === '0px' || marginRight === '0px') {
          containerFluid.style.marginLeft = 'auto';
          containerFluid.style.marginRight = 'auto';
        }
      }
      
      // Ensure proper padding on resize
      var windowWidth = window.innerWidth || document.documentElement.clientWidth;
      var currentPaddingLeft = parseInt(computedStyle.paddingLeft) || 0;
      
      if (windowWidth >= 992) {
        // Desktop
        if (currentPaddingLeft < 15) {
          containerFluid.style.paddingLeft = '20px';
          containerFluid.style.paddingRight = '20px';
        }
      } else if (windowWidth >= 576) {
        // Tablet
        containerFluid.style.paddingLeft = '15px';
        containerFluid.style.paddingRight = '15px';
      }
      // Mobile - handled by CSS
    } catch(e) {
      console.warn('Error ensuring proper alignment:', e);
    }
  }
  
  function handleResize() {
    if (resizeTimer) {
      clearTimeout(resizeTimer);
    }
    resizeTimer = setTimeout(ensureProperAlignment, 150);
  }
  
  // Run on DOM ready
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function() {
      ensureProperAlignment();
      window.addEventListener('resize', handleResize, { passive: true });
    });
  } else {
    ensureProperAlignment();
    window.addEventListener('resize', handleResize, { passive: true });
  }
  
  // Run when sidebar toggles (if jQuery is available)
  if (window.jQuery) {
    jQuery(document).off('click', '#show-sidebar, #close-sidebar').on('click', '#show-sidebar, #close-sidebar', function() {
      setTimeout(ensureProperAlignment, 300);
    });
  }
})();

var timeDisplay = document.getElementById("timestamp");

function refreshTime() {
    var dateString = new Date().toLocaleString("en-US", {
        timeZone: "Asia/Colombo"
    });
    var formattedString = dateString.replace(", ", " - ");
    timeDisplay.innerHTML = formattedString;
}

// setInterval(refreshTime, 60000);

// $(document).ready(function() {
//     setInterval(timestamp, 1000);
// });

// function timestamp() {
//   var xmlhttp = new XMLHttpRequest();
//         xmlhttp.onreadystatechange = function() {
//             if (this.readyState == 4 && this.status == 200) {
//                 document.getElementById("timestamp").innerHTML = this.responseText;
//             }
//         };
//         xmlhttp.open("GET", "controller/timestamp.php", true);
//         xmlhttp.send();
// }

// //notification sample number
// var x = document.getElementById("notificationx")
// x.innerHTML = Math.floor((Math.random() * 1000) + 1);

// //message sample number
// var x = document.getElementById("messengerx")
// x.innerHTML = Math.floor((Math.random() * 2000) + 1);
</script>

</body>
</html>