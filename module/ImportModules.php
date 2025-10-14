<!--Block#1 start dont change the order-->
<?php 
$title = "Import Modules | SLGTI";    
include_once ("../config.php");
include_once ("../head.php");
include_once ("../menu.php");
?>
<!-- end dont change the order-->

<!-- Block#2 start your code -->
<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
$base = defined('APP_BASE') ? APP_BASE : '';
$flash = isset($_SESSION['import_flash']) ? $_SESSION['import_flash'] : null;
unset($_SESSION['import_flash']);
$inserted = isset($_GET['inserted']) ? (int)$_GET['inserted'] : null;
$updated  = isset($_GET['updated'])  ? (int)$_GET['updated']  : null;
$skipped  = isset($_GET['skipped'])  ? (int)$_GET['skipped']  : null;
$errors   = isset($_GET['errors'])   ? (int)$_GET['errors']   : null;
$msg      = isset($_GET['msg'])      ? (string)$_GET['msg']    : '';
?>

<div class="container-fluid mt-3">
  <div class="card shadow-sm">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
      <div>
        <h5 class="mb-0">Import Modules (CSV)</h5>
        <small class="text-muted">Download template, fill in data, and upload to insert/update modules</small>
      </div>
      <div>
        <a class="btn btn-outline-secondary btn-sm" href="<?php echo $base; ?>/controller/ImportModules.php?action=template">
          <i class="fas fa-file-download mr-1"></i>Template
        </a>
        <a class="btn btn-light btn-sm" href="<?php echo $base; ?>/module/Module.php"><i class="fas fa-list mr-1"></i>Back to Modules</a>
      </div>
    </div>
    <div class="card-body">

      <?php if ($flash): ?>
        <div class="alert <?php echo ($flash['errors'] ?? 0) > 0 ? 'alert-danger' : 'alert-success'; ?> alert-dismissible fade show" role="alert">
          <div><strong>Result:</strong> inserted <?php echo (int)($flash['inserted'] ?? 0); ?>, updated <?php echo (int)($flash['updated'] ?? 0); ?>, skipped <?php echo (int)($flash['skipped'] ?? 0); ?>, errors <?php echo (int)($flash['errors'] ?? 0); ?>.</div>
          <?php if (!empty($flash['messages'])): ?>
            <ul class="mb-0 mt-2">
            <?php foreach ($flash['messages'] as $m): ?>
              <li><?php echo htmlspecialchars($m); ?></li>
            <?php endforeach; ?>
            </ul>
          <?php endif; ?>
          <button type="button" class="close" data-dismiss="alert" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
      <?php elseif ($errors !== null): ?>
        <div class="alert <?php echo ($errors > 0) ? 'alert-danger' : 'alert-success'; ?> alert-dismissible fade show" role="alert">
          <div><strong>Result:</strong> inserted <?php echo (int)$inserted; ?>, updated <?php echo (int)$updated; ?>, skipped <?php echo (int)$skipped; ?>, errors <?php echo (int)$errors; ?>.</div>
          <?php if ($msg): ?><div class="mt-1"><?php echo htmlspecialchars($msg); ?></div><?php endif; ?>
          <button type="button" class="close" data-dismiss="alert" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
      <?php endif; ?>

      <form method="POST" action="<?php echo $base; ?>/controller/ImportModules.php" enctype="multipart/form-data">
        <div class="form-row">
          <div class="form-group col-md-8">
            <label class="small text-muted">CSV File</label>
            <input type="file" class="form-control" name="csv_file" accept=".csv" required>
            <small class="form-text text-muted">Max 5MB. Delimiters supported: comma, semicolon, tab.</small>
          </div>
          <div class="form-group col-md-4 d-flex align-items-end">
            <div class="custom-control custom-checkbox">
              <input type="checkbox" class="custom-control-input" id="dryrun" name="dry_run" value="1">
              <label class="custom-control-label" for="dryrun">Dry run (validate only)</label>
            </div>
          </div>
        </div>
        <div>
          <button type="submit" class="btn btn-primary"><i class="fas fa-upload mr-1"></i>Upload & Import</button>
          <a class="btn btn-outline-secondary" href="<?php echo $base; ?>/controller/ImportModules.php?action=template"><i class="fas fa-file-download mr-1"></i>Download Template</a>
        </div>
      </form>

      <hr>
      <div>
        <h6>CSV Columns</h6>
        <ul class="mb-2">
          <li><strong>module_id</strong> (required)</li>
          <li><strong>module_name</strong> (required)</li>
          <li><strong>course_id</strong> (required)</li>
          <li><strong>semester_id</strong> (required)</li>
          <li>module_relative_unit</li>
          <li>module_lecture_hours</li>
          <li>module_practical_hours</li>
          <li>module_self_study_hours</li>
          <li>module_learning_hours (optional; auto = lecture+practical+self-study)</li>
          <li>module_aim</li>
          <li>module_learning_outcomes</li>
          <li>module_resources</li>
          <li>module_reference</li>
        </ul>
      </div>

    </div>
  </div>
</div>

<style>
  label { font-weight: 600; }
</style>
<!-- end your code -->

<!--Block#3 start dont change the order-->
<?php include_once ("../footer.php"); ?>  
<!--  end dont change the order-->
