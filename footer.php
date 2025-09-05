  
	<!-- DELETE MODEL -->
	<!-- Modal -->
	<div class="modal fade" id="confirm-delete" tabindex="-1" role="dialog" aria-labelledby="myModalLabel" aria-hidden="true">
	    <div class="modal-dialog" >
	        <div class="modal-content">
	            <div class="modal-body text-center">
	               <h1 class="display-4 text-danger"> <i class="fas fa-trash"></i> </h1>
	                <h1 class="font-weight-lighter">Are you sure?</h1>
	                <h4 class="font-weight-lighter"> Do you really want to delete these records? This process cannot be
                      undone. </h4>       
                <p class="debug-url"></p>
	            </div>
	            <div class="modal-footer">
                  <button type="button btn-primary" class="btn btn-secondary" data-dismiss="modal">Close</button>
                  <a class="btn btn-danger btn-ok">Delete</a>
	            </div>
	        </div>
	    </div>
	</div>
	<!-- END DELETE MODEL -->

</div>
</main>
</div>

  <?php /* Footer removed globally as requested. Keeping scripts below intact. */ ?>
  <!-- Optional JavaScript -->
  <!-- jQuery first, then Popper.js, then Bootstrap JS -->
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.3.1/jquery.min.js"></script>
  <!-- <script src="https://code.jquery.com/jquery-3.3.1.slim.min.js"
      integrity="sha384-q8i/X+965DzO0rT7abK41JStQIAqVgRVzpbzo5smXKp4YfRvH+8abtTE1Pi6jizo" crossorigin="anonymous">
  </script> -->
  <!-- Use only Bootstrap bundle (includes Popper) to avoid conflicts -->
  <script src="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/js/bootstrap.bundle.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap-select@1.13.9/dist/js/bootstrap-select.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/2.8.0/Chart.min.js"></script>
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

// Restore active submenus from saved state before binding handlers
function restoreActiveSubmenus() {
  try {
    var saved = localStorage.getItem('slgti_sidebar_active_idx');
    if (!saved) return;
    var idxs = JSON.parse(saved);
    if (!Array.isArray(idxs)) return;
    var all = $(".sidebar-dropdown");
    idxs.forEach(function(i){
      var li = all.eq(i);
      if (li && li.length) {
        li.addClass('active');
        li.children('.sidebar-submenu').show();
      }
    });
  } catch(e){}
}

function saveActiveSubmenus() {
  try {
    var idxs = [];
    $(".sidebar-dropdown").each(function(i, el){
      if ($(el).hasClass('active')) idxs.push(i);
    });
    localStorage.setItem('slgti_sidebar_active_idx', JSON.stringify(idxs));
  } catch(e){}
}

// If the sidebar markup is not present, skip initializing
if (document.getElementById('sidebar')) {
  restoreActiveSubmenus();

  $(".sidebar-dropdown > a").on('click', function(e) {
e.preventDefault();
$(".sidebar-submenu").slideUp(200);
if (
$(this)
  .parent()
  .hasClass("active")
) {
$(".sidebar-dropdown").removeClass("active");
$(this)
  .parent()
  .removeClass("active");
} else {
$(".sidebar-dropdown").removeClass("active");
$(this)
  .next(".sidebar-submenu")
  .slideDown(200);
$(this)
  .parent()
  .addClass("active");
}
 saveActiveSubmenus();
  });

function saveSidebarOpenState(isOpen){
  try { localStorage.setItem('slgti_sidebar_open', isOpen ? '1' : '0'); } catch(e){}
}

  // Use delegated handlers to be resilient to markup timing and support touch
  $(document).on('click touchstart', "#close-sidebar", function(e) {
    e.preventDefault();
    $(".page-wrapper").removeClass("toggled");
    saveSidebarOpenState(false);
  });
  $(document).on('click touchstart', "#show-sidebar", function(e) {
    e.preventDefault();
    $(".page-wrapper").addClass("toggled");
    saveSidebarOpenState(true);
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
  $(document).on('click touchstart', '#sidebar a', function(e) {
    try {
      var $a = $(this);
      var href = ($a.attr('href') || '').trim();
      var isDropdownToggler = $a.parent().is('.sidebar-dropdown') && $a.is('.sidebar-dropdown > a');
      var isPlaceholder = href === '' || href === '#' || href === 'javascript:void(0)';
      if (isDropdownToggler || isPlaceholder) return; // don't auto-close
      if (isMobile()) {
        $(".page-wrapper").removeClass("toggled");
        saveSidebarOpenState(false);
      }
    } catch(_){}
  });
}

// Hook delete confirmation modal to display and set the target URL safely
$('#confirm-delete').on('show.bs.modal', function (e) {
  try {
    var trigger = e.relatedTarget ? $(e.relatedTarget) : null;
    var href = trigger ? (trigger.data('href') || trigger.attr('href') || '#') : '#';
    $(this).find('.btn-ok').attr('href', href);
    $(this).find('.debug-url').html('Delete URL: <strong>' + href + '</strong>');
  } catch(err) { /* noop */ }
});

}); // end initWhenjQueryReady

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