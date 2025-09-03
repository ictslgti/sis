  
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

jQuery(function ($) {

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

$("#close-sidebar").on('click', function(e) {
  e.preventDefault();
  $(".page-wrapper").removeClass("toggled");
  saveSidebarOpenState(false);
});
$("#show-sidebar").on('click', function(e) {
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

  // After clicking any sidebar link on mobile, hide the sidebar to show content
  $('#sidebar a').on('click', function(e) {
    if (isMobile()) {
      $(".page-wrapper").removeClass("toggled");
      saveSidebarOpenState(false);
    }
  });

$('.debug-url').html('Delete URL: <strong>' + $(this).find('.btn-ok').attr('href') + '</strong>');
});

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