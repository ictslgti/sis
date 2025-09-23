<!--  bLOCK#1 start don't change the order-->
<?php 
$title =" notice | SLGTI";
include_once("../config.php");
include_once("../head.php");
include_once("../menu.php");
?>
<!-- end don't change the order-->



<!-- bLOCK#2 start your code here & u can change -->
<style>
  /* Page-scoped tweaks for better spacing and clickable cards */
  .card-hover { transition: transform .12s ease-in-out, box-shadow .12s ease-in-out; }
  .card-hover:hover { transform: translateY(-2px); box-shadow: 0 .5rem 1rem rgba(0,0,0,.15); }
  .card .stretched-link { font-weight: 600; }
</style>

<div class="container-fluid py-3">
  <div class="row">
    <div class="col-12">
      <div class="alert bg-dark text-white text-center shadow-sm mb-4" role="alert">
        <h3 class="mb-0">Add New Notice</h3>
      </div>
    </div>

    <div class="col-md-6 mb-4">
      <div class="card h-100 shadow-sm card-hover">
        <div class="card-header bg-dark text-white">
          <h5 class="mb-0">Result</h5>
        </div>
        <div class="card-body">
          <p class="card-text text-muted mb-3">Create and publish a new exam result notice.</p>
          <a class="stretched-link" href="NoticeAddResult.php">Add New Result</a>
        </div>
      </div>
    </div>

    <div class="col-md-6 mb-4">
      <div class="card h-100 shadow-sm card-hover">
        <div class="card-header bg-dark text-white">
          <h5 class="mb-0">Events</h5>
        </div>
        <div class="card-body">
          <p class="card-text text-muted mb-3">Announce upcoming institute events.</p>
          <a class="stretched-link" href="NoticeEventUpload.php">Add New Events</a>
        </div>
      </div>
    </div>
  </div>
</div>
   <!-- end your code here-->



<!--bLOCK#3  start don't change the order-->
    <?php include_once("../footer.php");?>
<!-- end don't change the order-->
   