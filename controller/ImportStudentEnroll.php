<?php
// Ensure session for flash messages
if (session_status() === PHP_SESSION_NONE) { session_start(); }
// Controller: handle CSV import for student_enroll and serve CSV template
// Location: controller/ImportStudentEnroll.php
// Access control: allow only Admin and SAO to use this controller
require_once __DIR__ . '/../auth.php';
require_roles(['ADM','SAO']);

// Serve a CSV template if requested
if (isset($_GET['action']) && $_GET['action'] === 'template') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="student_enrollment_template.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, [
        'student_id',
        'student_fullname',
        'student_nic'
    ]);
    fputcsv($out, ['2025ICT5IT01', 'TEST STUDENT 1', '1996651913V']);
    fclose($out);
    exit;
}

// Import flow: only handle POST here. Any direct GET should go back to the UI silently.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../student/ImportStudentEnroll.php');
    exit;
}

// Manual add flow: process a single enrollment without CSV
if (isset($_POST['manual']) && $_POST['manual'] === '1') {
    include_once('../config.php');
    $con = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if (mysqli_connect_errno()) {
        header('Location: ../student/ImportStudentEnroll.php?errors=1&msg=' . urlencode('DB connect failed: ' . mysqli_connect_error()));
        exit;
    }
    mysqli_set_charset($con, 'utf8');

    $student_id = trim($_POST['student_id'] ?? '');
    $stu_full   = trim($_POST['student_fullname'] ?? '');
    $stu_nic    = trim($_POST['student_nic'] ?? '');
    $course_id  = trim($_POST['course_id'] ?? '');
    $academic_year = trim($_POST['academic_year'] ?? '');
    $enroll_date   = trim($_POST['enroll_date'] ?? '');
    $course_mode   = trim($_POST['course_mode'] ?? 'Full');
    $status        = trim($_POST['status'] ?? 'Following');
    $manual_debug  = isset($_POST['manual_debug']) && $_POST['manual_debug'] === '1';

    $allowedModes = ['Part', 'Full'];
    $allowedStatus = ['Following', 'Dropout', 'Completed', 'Long Absent'];
    $isDate = function($d) {
        if ($d === '') return false;
        $parts = explode('-', $d);
        if (count($parts) !== 3) return false;
        return checkdate((int)$parts[1], (int)$parts[2], (int)$parts[0]);
    };

    $errMsgs = [];
    $inserted = 0; $updated = 0; $skipped = 0; $errors = 0;
    $dbg = [
        'input' => [
            'student_id' => $student_id,
            'student_fullname' => $stu_full,
            'student_nic' => $stu_nic,
            'course_id' => $course_id,
            'academic_year' => $academic_year,
            'enroll_date' => $enroll_date,
            'course_mode' => $course_mode,
            'status' => $status,
        ],
        'checks' => [],
        'actions' => [],
    ];

    if ($student_id === '') { $errors++; $errMsgs[] = 'Student ID is required'; }
    if ($course_id === '')  { $errors++; $errMsgs[] = 'Course is required'; }
    if ($academic_year === '') { $errors++; $errMsgs[] = 'Academic Year is required'; }
    if ($enroll_date === '' || !$isDate($enroll_date)) { $errors++; $errMsgs[] = 'Valid Enroll Date is required (YYYY-MM-DD)'; }
    if (!in_array($course_mode, $allowedModes, true)) { $errors++; $errMsgs[] = 'Invalid Course Mode'; }
    if (!in_array($status, $allowedStatus, true)) { $errors++; $errMsgs[] = 'Invalid Enrollment Status'; }

    if ($errors === 0) {
        // Prepare statements (subset used below)
        $selStudent = mysqli_prepare($con, 'SELECT 1 FROM student WHERE student_id = ?');
        $selCourse  = mysqli_prepare($con, 'SELECT 1 FROM course WHERE course_id = ?');
        $selEnroll  = mysqli_prepare($con, 'SELECT 1 FROM student_enroll WHERE student_id = ? AND course_id = ? AND academic_year = ?');
        $insEnroll  = mysqli_prepare($con, 'INSERT INTO student_enroll (student_id, course_id, course_mode, academic_year, student_enroll_date, student_enroll_exit_date, student_enroll_status) VALUES (?,?,?,?,?,?,?)');
        $updEnroll  = mysqli_prepare($con, 'UPDATE student_enroll SET course_mode = ?, student_enroll_date = ?, student_enroll_exit_date = ?, student_enroll_status = ? WHERE student_id = ? AND course_id = ? AND academic_year = ?');
        $updStudent = mysqli_prepare($con, "UPDATE student 
            SET student_fullname = COALESCE(NULLIF(?, ''), student_fullname),
                student_nic      = COALESCE(NULLIF(?, ''), student_nic)
            WHERE student_id = ?");
        $insStudent = mysqli_prepare($con, 'INSERT INTO student (
            student_id, student_title, student_fullname, student_ininame, student_gender, student_civil, student_email, student_nic, student_profile_img, student_dob, student_phone, student_address, student_zip, student_district, student_divisions, student_provice, student_blood, student_em_name, student_em_address, student_em_phone, student_em_relation, student_status
        ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');

        // Validate course exists
        mysqli_stmt_bind_param($selCourse, 's', $course_id);
        mysqli_stmt_execute($selCourse);
        mysqli_stmt_store_result($selCourse);
        $course_exists = (mysqli_stmt_num_rows($selCourse) > 0);
        $dbg['checks']['course_exists'] = $course_exists;
        if (!$course_exists) { $errors++; $errMsgs[] = 'Unknown course_id: ' . $course_id; }

        // Check student exists or create minimal
        if ($errors === 0) {
            mysqli_stmt_bind_param($selStudent, 's', $student_id);
            mysqli_stmt_execute($selStudent);
            mysqli_stmt_store_result($selStudent);
            $student_exists = (mysqli_stmt_num_rows($selStudent) > 0);
            $dbg['checks']['student_exists'] = $student_exists;
            if (!$student_exists) {
                $def_title = 'Mr';
                $def_email = $student_id . '@slgti.com';
                $def_profile = 'img/user.jpg';
                $def_dob = '0000-00-00';
                $zero = '0';
                $empty = '';
                $ins_full = ($stu_full !== '' ? $stu_full : $student_id);
                $ins_ini  = $ins_full;
                // Bind requires variables by reference (no literals)
                $def_status = 'Active';
                mysqli_stmt_bind_param(
                    $insStudent,
                    'ssssssssssssssssssssss',
                    $student_id, $def_title, $ins_full, $ins_ini, $empty, $empty, $def_email, $stu_nic, $def_profile, $def_dob, $zero, $empty, $zero, $empty, $empty, $empty, $empty, $empty, $empty, $zero, $empty, $def_status
                );
                if (!mysqli_stmt_execute($insStudent)) {
                    $errors++; $errMsgs[] = 'Student create failed: ' . mysqli_error($con);
                    $dbg['actions'][] = ['student_insert' => ['ok' => false, 'error' => mysqli_error($con)]];
                } else {
                    $dbg['actions'][] = ['student_insert' => ['ok' => true, 'affected' => mysqli_stmt_affected_rows($insStudent)]];
                }
            } else {
                if ($stu_full !== '' || $stu_nic !== '') {
                    mysqli_stmt_bind_param($updStudent, 'sss', $stu_full, $stu_nic, $student_id);
                    if (!mysqli_stmt_execute($updStudent)) { $errors++; $errMsgs[] = 'Student update failed: ' . mysqli_error($con); $dbg['actions'][] = ['student_update' => ['ok' => false, 'error' => mysqli_error($con)]]; }
                    else { $dbg['actions'][] = ['student_update' => ['ok' => true, 'affected' => mysqli_stmt_affected_rows($updStudent)]]; }
                }
            }
        }

        // Upsert enrollment
        if ($errors === 0) {
            $exit_date = $enroll_date; // default same day
            mysqli_stmt_bind_param($selEnroll, 'sss', $student_id, $course_id, $academic_year);
            mysqli_stmt_execute($selEnroll);
            mysqli_stmt_store_result($selEnroll);
            $exists = (mysqli_stmt_num_rows($selEnroll) > 0);
            $dbg['checks']['enrollment_exists'] = $exists;
            if ($exists) {
                mysqli_stmt_bind_param($updEnroll, 'sssssss', $course_mode, $enroll_date, $exit_date, $status, $student_id, $course_id, $academic_year);
                if (!mysqli_stmt_execute($updEnroll)) {
                    $errors++; $errMsgs[] = 'Update failed: ' . mysqli_error($con);
                    $dbg['actions'][] = ['enroll_update' => ['ok' => false, 'error' => mysqli_error($con)]];
                } else {
                    $aff = mysqli_stmt_affected_rows($updEnroll);
                    if ($aff > 0) { $updated++; }
                    $dbg['actions'][] = ['enroll_update' => ['ok' => true, 'affected' => $aff]];
                }
            } else {
                mysqli_stmt_bind_param($insEnroll, 'sssssss', $student_id, $course_id, $course_mode, $academic_year, $enroll_date, $exit_date, $status);
                if (!mysqli_stmt_execute($insEnroll)) {
                    $errors++; $errMsgs[] = 'Insert failed: ' . mysqli_error($con);
                    $dbg['actions'][] = ['enroll_insert' => ['ok' => false, 'error' => mysqli_error($con)]];
                } else {
                    $aff = mysqli_stmt_affected_rows($insEnroll);
                    if ($aff > 0) { $inserted++; }
                    $dbg['actions'][] = ['enroll_insert' => ['ok' => true, 'affected' => $aff]];
                }
            }
        }

        foreach ([$selStudent,$selCourse,$selEnroll,$insEnroll,$updEnroll,$updStudent,$insStudent] as $st) { if ($st) mysqli_stmt_close($st); }
    }

    mysqli_close($con);

    // If operation reported success but no rows affected, provide an explicit hint
    if ($errors === 0 && ($inserted + $updated) === 0) {
        $errMsgs[] = 'No changes made: record may already exist with the same values.';
    }

    // Manual debug output
    if ($manual_debug) {
        header('Content-Type: application/json');
        echo json_encode([
            'input' => $dbg['input'],
            'checks' => $dbg['checks'],
            'actions' => $dbg['actions'],
            'counters' => ['inserted' => $inserted, 'updated' => $updated, 'errors' => $errors],
            'messages' => $errMsgs,
        ], JSON_PRETTY_PRINT);
        exit;
    }

    $_SESSION['import_flash'] = [
        'inserted' => $inserted,
        'updated'  => $updated,
        'skipped'  => $skipped,
        'errors'   => $errors,
        'messages' => $errMsgs,
        'hint'     => ''
    ];
    $successMsg = 'Manual add successful';
    if ($errors === 0 && ($inserted + $updated) === 0) { $successMsg = 'No changes made'; }
    $q = http_build_query([
        'inserted' => $inserted,
        'updated'  => $updated,
        'skipped'  => $skipped,
        'errors'   => $errors,
        'msg'      => ($errors? ($errMsgs[0] ?? 'Error'): $successMsg),
    ]);
    header('Location: ../student/ImportStudentEnroll.php?' . $q);
    exit;
}

// CSV import flow
if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
    header('Location: ../student/ImportStudentEnroll.php?errors=1&msg=' . urlencode('File upload failed'));
    exit;
}

$dryRun = isset($_POST['dry_run']) && $_POST['dry_run'] === '1';

// Form-level enrollment values applied to all rows
$formCourseId = isset($_POST['course_id']) ? trim($_POST['course_id']) : '';
$formAcademicYear = isset($_POST['academic_year']) ? trim($_POST['academic_year']) : '';
$formEnrollDate = isset($_POST['enroll_date']) ? trim($_POST['enroll_date']) : '';
$formCourseMode = isset($_POST['course_mode']) ? trim($_POST['course_mode']) : 'Full';
$formStatus = isset($_POST['status']) ? trim($_POST['status']) : 'Following';

$fname = $_FILES['csv_file']['name'];
$tmp   = $_FILES['csv_file']['tmp_name'];
$size  = $_FILES['csv_file']['size'];
$ext   = strtolower(pathinfo($fname, PATHINFO_EXTENSION));

if ($ext !== 'csv') {
    header('Location: ../student/ImportStudentEnroll.php?errors=1&msg=' . urlencode('Only CSV files are supported'));
    exit;
}
if ($size <= 0 || $size > 5 * 1024 * 1024) { // 5MB
    header('Location: ../student/ImportStudentEnroll.php?errors=1&msg=' . urlencode('File is empty or too large'));
    exit;
}

include_once('../config.php');
$con = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if (mysqli_connect_errno()) {
    header('Location: ../student/ImportStudentEnroll.php?errors=1&msg=' . urlencode('DB connect failed: ' . mysqli_connect_error()));
    exit;
}
mysqli_set_charset($con, 'utf8');

// Validate form-level inputs (used when CSV doesn't provide per-row enrollment fields)
$allowedModes = ['Part', 'Full'];
$allowedStatus = ['Following', 'Dropout', 'Completed', 'Long Absent'];

$isDate = function($d) {
    if ($d === '') return false;
    $parts = explode('-', $d);
    if (count($parts) !== 3) return false;
    return checkdate((int)$parts[1], (int)$parts[2], (int)$parts[0]);
};

// We'll only enforce these if the CSV doesn't provide replacements

$inserted = 0; $updated = 0; $skipped = 0; $errors = 0; $errMsgs = [];

// Open CSV
if (($handle = fopen($tmp, 'r')) === false) {
    header('Location: ../student/ImportStudentEnroll.php?errors=1&msg=' . urlencode('Unable to read uploaded CSV'));
    exit;
}
// Detect delimiter by sampling first line
$delim = ',';
$sample = fgets($handle);
if ($sample === false) {
    fclose($handle);
    header('Location: ../student/ImportStudentEnroll.php?errors=1&msg=' . urlencode('CSV is empty'));
    exit;
}
$commaCount = substr_count($sample, ',');
$semiCount  = substr_count($sample, ';');
$tabCount   = substr_count($sample, "\t");
if ($semiCount > $commaCount && $semiCount >= $tabCount) { $delim = ';'; }
elseif ($tabCount > $commaCount && $tabCount > $semiCount) { $delim = "\t"; }
rewind($handle);

// Map header
$header = fgetcsv($handle, 0, $delim);
if ($header === false) {
    fclose($handle);
    header('Location: ../student/ImportStudentEnroll.php?errors=1&msg=' . urlencode('CSV is empty'));
    exit;
}
// Strip UTF-8 BOM if present on first column
if (isset($header[0])) {
    $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]);
}

// Build a normalized header map with aliases
$normalize = function($name) {
    $name = strtolower(trim((string)$name));
    // collapse non-alphanumeric to nothing, then reinsert underscores for known boundaries
    $name = preg_replace('/[^a-z0-9]+/', '', $name);
    return $name;
};
$alias = function($norm) {
    // map common variants to canonical keys
    $map = [
        'studentid' => 'student_id',
        'id' => 'student_id',
        'indexno' => 'student_id',
        'regno' => 'student_id',
        'registrationno' => 'student_id',

        'studentfullname' => 'student_fullname',
        'fullname' => 'student_fullname',
        'name' => 'student_fullname',

        'studentnic' => 'student_nic',
        'nic' => 'student_nic',

        'studentininame' => 'student_ininame',
        'ininame' => 'student_ininame',
        'initials' => 'student_ininame',

        'courseid' => 'course_id',
        'academicyear' => 'academic_year',
        'year' => 'academic_year',

        'studentenrolldate' => 'student_enroll_date',
        'enrolldate' => 'student_enroll_date',
        'admissiondate' => 'student_enroll_date',

        'studentenrollexitdate' => 'student_enroll_exit_date',
        'exitdate' => 'student_enroll_exit_date',

        'coursemode' => 'course_mode',
        'mode' => 'course_mode',

        'studentenrollstatus' => 'student_enroll_status',
        'status' => 'student_enroll_status',
    ];
    return $map[$norm] ?? $norm; // if unknown, keep normalized form
};

$map = [];
foreach ($header as $i => $col) {
    $norm = $normalize($col);
    $canon = $alias($norm);
    $map[$canon] = $i;
}
// Determine availability of per-row enrollment fields
$hasCourseIdCol = array_key_exists('course_id', $map);
$hasYearCol     = array_key_exists('academic_year', $map);
$hasDateCol     = array_key_exists('student_enroll_date', $map);
$hasModeCol     = array_key_exists('course_mode', $map);
$hasStatusCol   = array_key_exists('student_enroll_status', $map);

// Require at minimum student_id (and either form-level enrollment fields or all necessary per-row fields)
$requiredForKey = ['student_id'];
foreach ($requiredForKey as $c) {
    if (!array_key_exists($c, $map)) {
        fclose($handle);
        header('Location: ../student/ImportStudentEnroll.php?errors=1&msg=' . urlencode('Missing required column: ' . $c));
        exit;
    }
}

// If CSV does NOT provide course_id/year/date, enforce form-level selections now
if (!($hasCourseIdCol && $hasYearCol && $hasDateCol)) {
    if ($formCourseId === '' || $formAcademicYear === '' || !$isDate($formEnrollDate)) {
        fclose($handle);
        header('Location: ../student/ImportStudentEnroll.php?errors=1&msg=' . urlencode('Provide Course, Academic Year and Enroll Date via form or CSV columns'));
        exit;
    }
    if (!in_array($formCourseMode, $allowedModes, true)) {
        fclose($handle);
        header('Location: ../student/ImportStudentEnroll.php?errors=1&msg=' . urlencode('Invalid Course Mode'));
        exit;
    }
    if (!in_array($formStatus, $allowedStatus, true)) {
        fclose($handle);
        header('Location: ../student/ImportStudentEnroll.php?errors=1&msg=' . urlencode('Invalid Enrollment Status'));
        exit;
    }
}

// Prepare statements
$selStudent = mysqli_prepare($con, 'SELECT 1 FROM student WHERE student_id = ?');
$selCourse  = mysqli_prepare($con, 'SELECT 1 FROM course WHERE course_id = ?');
$selEnroll  = mysqli_prepare($con, 'SELECT 1 FROM student_enroll WHERE student_id = ? AND course_id = ? AND academic_year = ?');
$insEnroll  = mysqli_prepare($con, 'INSERT INTO student_enroll (student_id, course_id, course_mode, academic_year, student_enroll_date, student_enroll_exit_date, student_enroll_status) VALUES (?,?,?,?,?,?,?)');
$updEnroll  = mysqli_prepare($con, 'UPDATE student_enroll SET course_mode = ?, student_enroll_date = ?, student_enroll_exit_date = ?, student_enroll_status = ? WHERE student_id = ? AND course_id = ? AND academic_year = ?');
// Optional student details update (only non-empty fields)
$updStudent = mysqli_prepare($con, "UPDATE student 
    SET student_fullname = COALESCE(NULLIF(?, ''), student_fullname),
        student_nic      = COALESCE(NULLIF(?, ''), student_nic),
        student_ininame  = COALESCE(NULLIF(?, ''), student_ininame)
    WHERE student_id = ?");
// Insert minimal student record if missing
$insStudent = mysqli_prepare($con, 'INSERT INTO student (
    student_id, student_title, student_fullname, student_ininame, student_gender, student_civil, student_email, student_nic, student_profile_img, student_dob, student_phone, student_address, student_zip, student_district, student_divisions, student_provice, student_blood, student_em_name, student_em_address, student_em_phone, student_em_relation, student_status
) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');

// Validate course exists (either global or will validate per-row when CSV provides it)
if (!($hasCourseIdCol)) {
    mysqli_stmt_bind_param($selCourse, 's', $formCourseId);
    mysqli_stmt_execute($selCourse);
    mysqli_stmt_store_result($selCourse);
    if (mysqli_stmt_num_rows($selCourse) === 0) {
        header('Location: ../student/ImportStudentEnroll.php?errors=1&msg=' . urlencode('Unknown course_id: ' . $formCourseId));
        exit;
    }
}

// Begin transaction if not dry run
if (!$dryRun) {
    mysqli_begin_transaction($con);
}

$line = 1; // header already read
$processed = 0;
while (($row = fgetcsv($handle, 0, $delim)) !== false) {
    $line++;
    // Extract with safe index
    $get = function($name) use ($map, $row) {
        if (!isset($map[$name])) return '';
        $idx = $map[$name];
        return isset($row[$idx]) ? trim($row[$idx]) : '';
    };

    // Skip completely empty lines
    $allEmpty = true;
    foreach ($row as $cell) { if (trim((string)$cell) !== '') { $allEmpty = false; break; } }
    if ($allEmpty) { continue; }

    $processed++;

    $student_id = $get('student_id');
    // Prefer per-row values when present, otherwise form-level
    $course_id  = $hasCourseIdCol ? $get('course_id') : $formCourseId;
    $academic_year = $hasYearCol ? $get('academic_year') : $formAcademicYear;

    $course_mode = $hasModeCol ? $get('course_mode') : $formCourseMode;
    $enroll_date = $hasDateCol ? $get('student_enroll_date') : $formEnrollDate;
    $exit_date   = array_key_exists('student_enroll_exit_date', $map) ? $get('student_enroll_exit_date') : '';
    $status      = $hasStatusCol ? $get('student_enroll_status') : $formStatus;
    $stu_full    = $get('student_fullname');
    $stu_nic     = $get('student_nic');
    $stu_ini     = $get('student_ininame');

    // Basic validations
    if ($student_id === '') {
        $errors++; $skipped++; $errMsgs[] = "Line $line: Missing student_id"; continue;
    }

    // Check student exists
    mysqli_stmt_bind_param($selStudent, 's', $student_id);
    mysqli_stmt_execute($selStudent);
    mysqli_stmt_store_result($selStudent);
    $student_exists = (mysqli_stmt_num_rows($selStudent) > 0);
    if (!$student_exists) {
        // Auto-create minimal student using provided fields and safe defaults
        $def_title = 'Mr';
        $def_gender = '';
        $def_civil = '';
        $def_email = $student_id . '@slgti.com';
        $def_profile = 'img/user.jpg';
        $def_dob = '0000-00-00';
        $def_phone = 0;
        $def_addr = '';
        $def_zip = 0;
        $def_district = '';
        $def_div = '';
        $def_prov = '';
        $def_blood = '';
        $def_em_name = '';
        $def_em_addr = '';
        $def_em_phone = 0;
        $def_em_rel = '';
        $def_status = 'Active';

        $ins_full = ($stu_full !== '' ? $stu_full : $student_id);
        $ins_ini  = ($stu_ini  !== '' ? $stu_ini  : $ins_full);
        $ins_nic  = $stu_nic;

        if ($dryRun) {
            // Assume student will be inserted
        } else {
            // Bind requires variables by reference, not expressions
            $v_student_id = $student_id;
            $v_title = $def_title;
            $v_full = $ins_full;
            $v_ini = $ins_ini;
            $v_gender = $def_gender;
            $v_civil = $def_civil;
            $v_email = $def_email;
            $v_nic = $ins_nic;
            $v_profile = $def_profile;
            $v_dob = $def_dob;
            $v_phone = (string)$def_phone;
            $v_addr = $def_addr;
            $v_zip = (string)$def_zip;
            $v_district = $def_district;
            $v_div = $def_div;
            $v_prov = $def_prov;
            $v_blood = $def_blood;
            $v_em_name = $def_em_name;
            $v_em_addr = $def_em_addr;
            $v_em_phone = (string)$def_em_phone;
            $v_em_rel = $def_em_rel;
            $v_status = $def_status;

            mysqli_stmt_bind_param(
                $insStudent,
                'ssssssssssssssssssssss',
                $v_student_id, $v_title, $v_full, $v_ini, $v_gender, $v_civil, $v_email, $v_nic, $v_profile, $v_dob, $v_phone, $v_addr, $v_zip, $v_district, $v_div, $v_prov, $v_blood, $v_em_name, $v_em_addr, $v_em_phone, $v_em_rel, $v_status
            );
            if (!mysqli_stmt_execute($insStudent)) {
                $errors++; $skipped++; $errMsgs[] = "Line $line: Student create failed - " . mysqli_error($con); continue;
            }
        }
    }

    // Validate course existence (globally or per-row)
    if ($hasCourseIdCol) {
        if ($course_id === '') { $errors++; $skipped++; $errMsgs[] = "Line $line: Missing course_id"; continue; }
        mysqli_stmt_bind_param($selCourse, 's', $course_id);
        mysqli_stmt_execute($selCourse);
        mysqli_stmt_store_result($selCourse);
        if (mysqli_stmt_num_rows($selCourse) === 0) {
            $errors++; $skipped++; $errMsgs[] = "Line $line: Unknown course_id $course_id"; continue;
        }
    }

    // Normalize and validate values
    if ($course_mode === '') { $course_mode = 'Full'; }
    if (!in_array($course_mode, $allowedModes, true)) {
        $errors++; $skipped++; $errMsgs[] = "Line $line: Invalid course_mode '$course_mode'"; continue;
    }
    if ($status === '') { $status = 'Following'; }
    if (!in_array($status, $allowedStatus, true)) {
        $errors++; $skipped++; $errMsgs[] = "Line $line: Invalid status '$status'"; continue;
    }
    // Dates: allow empty exit date
    if ($enroll_date === '' || !$isDate($enroll_date) || ($exit_date !== '' && !$isDate($exit_date))) {
        $errors++; $skipped++; $errMsgs[] = "Line $line: Invalid date format (expect YYYY-MM-DD)"; continue;
    }
    // Default exit date to enroll date if missing to satisfy NOT NULL
    if ($exit_date === '') { $exit_date = $enroll_date; }

    // Update basic student details if provided (only for existing student)
    if ($stu_full !== '' || $stu_nic !== '' || $stu_ini !== '') {
        mysqli_stmt_bind_param($updStudent, 'ssss', $stu_full, $stu_nic, $stu_ini, $student_id);
        if (!$dryRun) {
            if (!mysqli_stmt_execute($updStudent)) {
                $errors++; $errMsgs[] = "Line $line: Student update failed - " . mysqli_error($con);
            }
        }
    }

    // Exists?
    mysqli_stmt_bind_param($selEnroll, 'sss', $student_id, $course_id, $academic_year);
    mysqli_stmt_execute($selEnroll);
    mysqli_stmt_store_result($selEnroll);
    $exists = (mysqli_stmt_num_rows($selEnroll) > 0);

    if ($dryRun) {
        if ($exists) { $updated++; } else { $inserted++; }
        continue;
    }

    if ($exists) {
        mysqli_stmt_bind_param($updEnroll, 'sssssss', $course_mode, $enroll_date, $exit_date, $status, $student_id, $course_id, $academic_year);
        if (!mysqli_stmt_execute($updEnroll)) {
            $errors++; $errMsgs[] = "Line $line: Update failed - " . mysqli_error($con);
        } else {
            $updated++;
        }
    } else {
        mysqli_stmt_bind_param($insEnroll, 'sssssss', $student_id, $course_id, $course_mode, $academic_year, $enroll_date, $exit_date, $status);
        if (!mysqli_stmt_execute($insEnroll)) {
            $errors++; $errMsgs[] = "Line $line: Insert failed - " . mysqli_error($con);
        } else {
            $inserted++;
        }
    }
}

fclose($handle);

if (!$dryRun) {
    if ($errors > 0) {
        mysqli_rollback($con);
    } else {
        mysqli_commit($con);
    }
}

// Cleanup statements
foreach ([$selStudent,$selCourse,$selEnroll,$insEnroll,$updEnroll,$updStudent,$insStudent] as $st) {
    if ($st) { mysqli_stmt_close($st); }
}
mysqli_close($con);

$summaryMsg = '';
if (!empty($errMsgs)) {
    // Limit length for URL
    $summaryMsg = implode('; ', array_slice($errMsgs, 0, 5));
    if (count($errMsgs) > 5) { $summaryMsg .= ' ...'; }
}
// Friendly hint if nothing was processed and no errors
if ($processed === 0 && $errors === 0 && $inserted === 0 && $updated === 0 && $summaryMsg === '') {
    $summaryMsg = 'No data rows found under the header. Ensure your CSV has at least one row with student_id.';
}

// Store full details in session flash
$_SESSION['import_flash'] = [
    'inserted' => $inserted,
    'updated'  => $updated,
    'skipped'  => $skipped,
    'errors'   => $errors,
    'messages' => $errMsgs,
    'hint'     => ($processed === 0 && empty($errMsgs)) ? $summaryMsg : ''
];

// Keep compact summary in URL for quick view
$q = http_build_query([
    'inserted' => $inserted,
    'updated'  => $updated,
    'skipped'  => $skipped,
    'errors'   => $errors,
    'msg'      => $summaryMsg,
]);
header('Location: ../student/ImportStudentEnroll.php?' . $q);
exit;
