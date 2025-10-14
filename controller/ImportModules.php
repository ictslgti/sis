<?php
// controller/ImportModules.php
// Roles: ADM, HOD
// Provides: CSV template (GET action=template), CSV import (POST)
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../auth.php';
require_roles(['ADM','HOD']);

// Serve CSV template
if (isset($_GET['action']) && $_GET['action'] === 'template') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="modules_template.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, [
        'module_id',
        'module_name',
        'course_id',
        'semester_id',
        'module_relative_unit',
        'module_lecture_hours',
        'module_practical_hours',
        'module_self_study_hours',
        'module_learning_hours', // optional; if empty we compute sum of hours
        'module_aim',
        'module_learning_outcomes',
        'module_resources',
        'module_reference'
    ]);
    fputcsv($out, ['ICT101', 'Intro to IT', 'ICT', '1', 'U01', '30', '30', '60', '', 'Aim text...', 'Outcomes...', 'Resources...', 'References...']);
    fclose($out);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../module/ImportModules.php');
    exit;
}

// Handle CSV upload
if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
    header('Location: ../module/ImportModules.php?errors=1&msg=' . urlencode('File upload failed'));
    exit;
}

$fname = $_FILES['csv_file']['name'];
$tmp   = $_FILES['csv_file']['tmp_name'];
$size  = $_FILES['csv_file']['size'];
$ext   = strtolower(pathinfo($fname, PATHINFO_EXTENSION));

if ($ext !== 'csv') {
    header('Location: ../module/ImportModules.php?errors=1&msg=' . urlencode('Only CSV files are supported'));
    exit;
}
if ($size <= 0 || $size > 5 * 1024 * 1024) {
    header('Location: ../module/ImportModules.php?errors=1&msg=' . urlencode('File is empty or too large'));
    exit;
}

include_once __DIR__ . '/../config.php';
$con = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if (mysqli_connect_errno()) {
    header('Location: ../module/ImportModules.php?errors=1&msg=' . urlencode('DB connect failed: ' . mysqli_connect_error()));
    exit;
}
mysqli_set_charset($con, 'utf8');

$dryRun = isset($_POST['dry_run']) && $_POST['dry_run'] === '1';

$inserted = 0; $updated = 0; $skipped = 0; $errors = 0; $errMsgs = [];

$handle = fopen($tmp, 'r');
if ($handle === false) {
    header('Location: ../module/ImportModules.php?errors=1&msg=' . urlencode('Unable to read uploaded CSV'));
    exit;
}
// Detect delimiter
$delim = ',';
$sample = fgets($handle);
if ($sample === false) {
    fclose($handle);
    header('Location: ../module/ImportModules.php?errors=1&msg=' . urlencode('CSV is empty'));
    exit;
}
$comma = substr_count($sample, ',');
$semi  = substr_count($sample, ';');
$tab   = substr_count($sample, "\t");
if ($semi > $comma && $semi >= $tab) { $delim = ';'; }
elseif ($tab > $comma && $tab > $semi) { $delim = "\t"; }
rewind($handle);

$header = fgetcsv($handle, 0, $delim);
if ($header === false) {
    fclose($handle);
    header('Location: ../module/ImportModules.php?errors=1&msg=' . urlencode('CSV is empty'));
    exit;
}
if (isset($header[0])) { $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]); }

$normalize = function($name) {
    $name = strtolower(trim((string)$name));
    return preg_replace('/[^a-z0-9]+/', '', $name);
};
$alias = function($norm) {
    $map = [
        'moduleid' => 'module_id',
        'id' => 'module_id',
        'modulename' => 'module_name',
        'coursename' => 'course_id', // tolerate mistaken header
        'courseid' => 'course_id',
        'semester' => 'semester_id',
        'semesterid' => 'semester_id',
        'modulerelativeunit' => 'module_relative_unit',
        'relativeunit' => 'module_relative_unit',
        'modulelecturehours' => 'module_lecture_hours',
        'lecturehours' => 'module_lecture_hours',
        'modulepracticalhours' => 'module_practical_hours',
        'practicalhours' => 'module_practical_hours',
        'moduleselfstudyhours' => 'module_self_study_hours',
        'selfstudyhours' => 'module_self_study_hours',
        'modulelearninghours' => 'module_learning_hours',
        'learninghours' => 'module_learning_hours',
        'moduleaim' => 'module_aim',
        'aim' => 'module_aim',
        'modulelearningoutcomes' => 'module_learning_outcomes',
        'learningoutcomes' => 'module_learning_outcomes',
        'moduleresources' => 'module_resources',
        'resources' => 'module_resources',
        'modulereference' => 'module_reference',
        'reference' => 'module_reference',
    ];
    return $map[$norm] ?? $norm;
};

$map = [];
foreach ($header as $i => $col) {
    $norm = $normalize($col);
    $canon = $alias($norm);
    $map[$canon] = $i;
}

$required = ['module_id','module_name','course_id','semester_id'];
foreach ($required as $c) {
    if (!array_key_exists($c, $map)) {
        fclose($handle);
        header('Location: ../module/ImportModules.php?errors=1&msg=' . urlencode('Missing required column: ' . $c));
        exit;
    }
}

// Prepare statements
$selModule = mysqli_prepare($con, 'SELECT 1 FROM module WHERE module_id = ? AND course_id = ?');
$insModule = mysqli_prepare($con, 'INSERT INTO module (
    module_id, module_name, module_aim, module_learning_hours, module_resources, module_learning_outcomes,
    semester_id, module_reference, module_relative_unit, module_lecture_hours, module_practical_hours, module_self_study_hours, course_id
) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)');
$updModule = mysqli_prepare($con, "UPDATE module SET 
    module_name = COALESCE(NULLIF(?, ''), module_name),
    module_aim = COALESCE(NULLIF(?, ''), module_aim),
    module_learning_hours = ?,
    module_resources = COALESCE(NULLIF(?, ''), module_resources),
    module_learning_outcomes = COALESCE(NULLIF(?, ''), module_learning_outcomes),
    semester_id = ?,
    module_reference = COALESCE(NULLIF(?, ''), module_reference),
    module_relative_unit = COALESCE(NULLIF(?, ''), module_relative_unit),
    module_lecture_hours = ?,
    module_practical_hours = ?,
    module_self_study_hours = ?
    WHERE module_id = ? AND course_id = ?");

if (!$dryRun) { mysqli_begin_transaction($con); }

$line = 1; // header read
while (($row = fgetcsv($handle, 0, $delim)) !== false) {
    $line++;
    $get = function($name) use ($map, $row) {
        if (!isset($map[$name])) return '';
        $idx = $map[$name];
        return isset($row[$idx]) ? trim((string)$row[$idx]) : '';
    };
    $allEmpty = true;
    foreach ($row as $cell) { if (trim((string)$cell) !== '') { $allEmpty = false; break; } }
    if ($allEmpty) { continue; }

    $module_id = $get('module_id');
    $module_name = $get('module_name');
    $course_id = $get('course_id');
    $semester_id = $get('semester_id');
    $module_relative_unit = $get('module_relative_unit');
    $lec = $get('module_lecture_hours');
    $prac = $get('module_practical_hours');
    $self = $get('module_self_study_hours');
    $learn = $get('module_learning_hours');
    $aim = $get('module_aim');
    $outcomes = $get('module_learning_outcomes');
    $resources = $get('module_resources');
    $reference = $get('module_reference');

    if ($module_id === '' || $module_name === '' || $course_id === '' || $semester_id === '') {
        $errors++; $skipped++; $errMsgs[] = "Line $line: Missing required values (module_id, module_name, course_id, semester_id)"; continue;
    }

    $lec_i = ($lec === '' ? 0 : (int)$lec);
    $prac_i = ($prac === '' ? 0 : (int)$prac);
    $self_i = ($self === '' ? 0 : (int)$self);
    $sum_i = $lec_i + $prac_i + $self_i;
    $learn_i = ($learn === '' ? $sum_i : (int)$learn);

    // Exists?
    mysqli_stmt_bind_param($selModule, 'ss', $module_id, $course_id);
    mysqli_stmt_execute($selModule);
    mysqli_stmt_store_result($selModule);
    $exists = (mysqli_stmt_num_rows($selModule) > 0);

    if ($exists) {
        if ($dryRun) { $updated++; continue; }
        mysqli_stmt_bind_param($updModule, 'ssisssssiiiss',
            $module_name, $aim, $learn_i, $resources, $outcomes,
            $semester_id, $reference, $module_relative_unit,
            $lec_i, $prac_i, $self_i,
            $module_id, $course_id
        );
        if (!mysqli_stmt_execute($updModule)) {
            $errors++; $skipped++; $errMsgs[] = "Line $line: Update failed - " . mysqli_error($con);
        } else {
            $updated += (mysqli_stmt_affected_rows($updModule) >= 0 ? 1 : 0);
        }
    } else {
        if ($dryRun) { $inserted++; continue; }
        mysqli_stmt_bind_param($insModule, 'sssisssssiiis',
            $module_id, $module_name, $aim, $learn_i, $resources, $outcomes,
            $semester_id, $reference, $module_relative_unit,
            $lec_i, $prac_i, $self_i, $course_id
        );
        if (!mysqli_stmt_execute($insModule)) {
            $errors++; $skipped++; $errMsgs[] = "Line $line: Insert failed - " . mysqli_error($con);
        } else {
            $inserted += (mysqli_stmt_affected_rows($insModule) > 0 ? 1 : 0);
        }
    }
}

fclose($handle);
foreach ([$selModule,$insModule,$updModule] as $st) { if ($st) mysqli_stmt_close($st); }

if (!$dryRun) {
    if ($errors > 0) { mysqli_rollback($con); }
    else { mysqli_commit($con); }
}
mysqli_close($con);

$_SESSION['import_flash'] = [
    'inserted' => $inserted,
    'updated'  => $updated,
    'skipped'  => $skipped,
    'errors'   => $errors,
    'messages' => $errMsgs,
];

$q = http_build_query([
    'inserted' => $inserted,
    'updated'  => $updated,
    'skipped'  => $skipped,
    'errors'   => $errors,
    'msg'      => ($errors ? ($errMsgs[0] ?? 'Import completed with errors') : 'Import completed'),
]);
header('Location: ../module/ImportModules.php?' . $q);
exit;