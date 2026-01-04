<?php
// Report 1: Student ID, Student Name, NIC Number, Route
// Filter by payment_reference (month)

// Check for export FIRST - handle it before any includes
$export = isset($_GET['export']) ? trim($_GET['export']) : '';

// If export requested, handle it immediately
if ($export === 'excel' || $export === 'csv' || $export === 'docx') {
    // Start session and get basic config for database
    if (session_status() === PHP_SESSION_NONE) { 
        session_start(); 
    }
    
    // Minimal database connection
    require_once(__DIR__ . '/../config.php');
    require_once(__DIR__ . '/../auth.php');
    
    // Check roles for export
    $allowed = ['HOD', 'ADM', 'FIN', 'SAO'];
    if (!is_any($allowed)) {
        http_response_code(403);
        header('Content-Type: text/plain');
        die('Access denied.');
    }
    
    // Get filter
    $filter_month = isset($_GET['month']) ? trim($_GET['month']) : '';
    if (!empty($filter_month) && !preg_match('/^\d{4}-\d{2}$/', $filter_month)) {
        $filter_month = '';
    }
    
    // Get HOD department if needed
    $user_id = isset($_SESSION['user_name']) ? $_SESSION['user_name'] : '';
    $is_hod = is_role('HOD');
    $dept_code = null;
    if ($is_hod) {
        $dept_code = isset($_SESSION['department_code']) ? trim($_SESSION['department_code']) : '';
        if (empty($dept_code) && !empty($user_id)) {
            $staff_sql = "SELECT department_id FROM staff WHERE staff_id = '".mysqli_real_escape_string($con, $user_id)."' LIMIT 1";
            $staff_result = mysqli_query($con, $staff_sql);
            if ($staff_result && mysqli_num_rows($staff_result) > 0) {
                $staff_row = mysqli_fetch_assoc($staff_result);
                $dept_code = $staff_row['department_id'] ?? '';
            }
        }
    }
    
    // Build WHERE clause
    $where = [];
    $where[] = "sr.status = 'approved'";
    $where[] = "sp.id IS NOT NULL";
    if (!empty($filter_month)) {
        $where[] = "sp.payment_reference = '".mysqli_real_escape_string($con, $filter_month)."'";
    }
    if ($is_hod && !empty($dept_code)) {
        $where[] = "d.department_id = '".mysqli_real_escape_string($con, $dept_code)."'";
    }
    $where_clause = "WHERE " . implode(" AND ", $where);
    
    // Query
    $sql = "SELECT DISTINCT
            sr.student_id,
            s.student_fullname,
            s.student_nic,
            CONCAT(sr.route_from, ' → ', sr.route_to) as route,
            sp.payment_reference
            FROM season_requests sr
            LEFT JOIN season_payments sp ON sr.id = sp.request_id
            LEFT JOIN student s ON sr.student_id = s.student_id
            LEFT JOIN student_enroll se ON se.student_id = s.student_id AND se.student_enroll_status IN ('Following','Active')
            LEFT JOIN course c ON c.course_id = se.course_id
            LEFT JOIN department d ON d.department_id = c.department_id
            $where_clause
            ORDER BY sr.student_id, sp.payment_reference";
    
    $result = mysqli_query($con, $sql);
    if (!$result) {
        http_response_code(500);
        header('Content-Type: text/plain');
        die('Database error: ' . mysqli_error($con));
    }
    
    // Collect data
    $data = [];
    if ($result && mysqli_num_rows($result) > 0) {
        while ($row = mysqli_fetch_assoc($result)) {
            $data[] = [
                'student_id' => trim($row['student_id'] ?? ''),
                'student_name' => trim($row['student_fullname'] ?? ''),
                'nic' => trim($row['student_nic'] ?? ''),
                'route' => trim($row['route'] ?? ''),
                'payment_month' => trim($row['payment_reference'] ?? '')
            ];
        }
        mysqli_free_result($result);
    }
    
    // Generate DOCX file
    $filename = 'season_report1_' . ($filter_month ? $filter_month : 'all') . '_' . date('Ymd_His') . '.docx';
    
    // Clear output buffers
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    
    // Create temporary file
    $temp_dir = sys_get_temp_dir();
    $temp_file = $temp_dir . DIRECTORY_SEPARATOR . 'docx_' . uniqid() . '.zip';
    
    if (!class_exists('ZipArchive')) {
        http_response_code(500);
        die('ZipArchive class not available. Please enable php_zip extension.');
    }
    
    $zip = new ZipArchive();
    $zip_result = $zip->open($temp_file, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    
    if ($zip_result !== TRUE) {
        http_response_code(500);
        die('Cannot create DOCX file. Error code: ' . $zip_result);
    }
    
    // Helper function to escape XML
    function escapeXml($text) {
        return htmlspecialchars($text ?? '', ENT_XML1, 'UTF-8');
    }
    
    // Create [Content_Types].xml
    $content_types = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
<Default Extension="xml" ContentType="application/xml"/>
<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
<Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/>
</Types>';
    $zip->addFromString('[Content_Types].xml', $content_types);
    
    // Create _rels/.rels
    $rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
</Relationships>';
    $zip->addFromString('_rels/.rels', $rels);
    
    // Create word/_rels/document.xml.rels
    $doc_rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>
</Relationships>';
    $zip->addFromString('word/_rels/document.xml.rels', $doc_rels);
    
    // Create word/styles.xml
    $styles = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
<w:style w:type="table" w:styleId="TableGrid">
<w:name w:val="Table Grid"/>
<w:tblPr>
<w:tblBorders>
<w:top w:val="single" w:sz="4" w:space="0" w:color="000000"/>
<w:left w:val="single" w:sz="4" w:space="0" w:color="000000"/>
<w:bottom w:val="single" w:sz="4" w:space="0" w:color="000000"/>
<w:right w:val="single" w:sz="4" w:space="0" w:color="000000"/>
<w:insideH w:val="single" w:sz="4" w:space="0" w:color="000000"/>
<w:insideV w:val="single" w:sz="4" w:space="0" w:color="000000"/>
</w:tblBorders>
</w:tblPr>
</w:style>
</w:styles>';
    $zip->addFromString('word/styles.xml', $styles);
    
    // Build table rows
    $table_rows = '';
    if (!empty($data)) {
        // Header row with bold text
        $table_rows .= '<w:tr>
<w:tc><w:tcPr><w:shd w:val="clear" w:color="auto" w:fill="D9D9D9"/></w:tcPr><w:p><w:r><w:rPr><w:b/></w:rPr><w:t>Student ID</w:t></w:r></w:p></w:tc>
<w:tc><w:tcPr><w:shd w:val="clear" w:color="auto" w:fill="D9D9D9"/></w:tcPr><w:p><w:r><w:rPr><w:b/></w:rPr><w:t>Student Name</w:t></w:r></w:p></w:tc>
<w:tc><w:tcPr><w:shd w:val="clear" w:color="auto" w:fill="D9D9D9"/></w:tcPr><w:p><w:r><w:rPr><w:b/></w:rPr><w:t>NIC Number</w:t></w:r></w:p></w:tc>
<w:tc><w:tcPr><w:shd w:val="clear" w:color="auto" w:fill="D9D9D9"/></w:tcPr><w:p><w:r><w:rPr><w:b/></w:rPr><w:t>Route</w:t></w:r></w:p></w:tc>
<w:tc><w:tcPr><w:shd w:val="clear" w:color="auto" w:fill="D9D9D9"/></w:tcPr><w:p><w:r><w:rPr><w:b/></w:rPr><w:t>Payment Month</w:t></w:r></w:p></w:tc>
</w:tr>';
        
        // Data rows
        foreach ($data as $row) {
            $table_rows .= '<w:tr>
<w:tc><w:p><w:r><w:t>' . escapeXml($row['student_id']) . '</w:t></w:r></w:p></w:tc>
<w:tc><w:p><w:r><w:t>' . escapeXml($row['student_name']) . '</w:t></w:r></w:p></w:tc>
<w:tc><w:p><w:r><w:t>' . escapeXml($row['nic']) . '</w:t></w:r></w:p></w:tc>
<w:tc><w:p><w:r><w:t>' . escapeXml($row['route']) . '</w:t></w:r></w:p></w:tc>
<w:tc><w:p><w:r><w:t>' . escapeXml($row['payment_month']) . '</w:t></w:r></w:p></w:tc>
</w:tr>';
        }
    } else {
        // Empty data row spanning all columns
        $table_rows = '<w:tr>
<w:tc><w:tcPr><w:gridSpan w:val="5"/></w:tcPr><w:p><w:r><w:t>No data found for the selected criteria.</w:t></w:r></w:p></w:tc>
</w:tr>';
    }
    
    // Create word/document.xml
    $document = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
<w:body>
<w:p><w:pPr><w:jc w:val="center"/></w:pPr><w:r><w:rPr><w:b/><w:sz w:val="28"/></w:rPr><w:t>Season Report 1 - Student Details</w:t></w:r></w:p>
<w:p><w:r><w:t>Generated: ' . escapeXml(date('Y-m-d H:i:s')) . '</w:t></w:r></w:p>';
    
    if (!empty($filter_month)) {
        $document .= '<w:p><w:r><w:t>Payment Month: ' . escapeXml($filter_month) . '</w:t></w:r></w:p>';
    }
    
    $document .= '<w:p></w:p>
<w:tbl>
<w:tblPr>
<w:tblStyle w:val="TableGrid"/>
<w:tblW w:w="0" w:type="auto"/>
<w:tblLook w:val="04A0"/>
</w:tblPr>
<w:tblGrid>
<w:gridCol w:w="2000"/>
<w:gridCol w:w="3000"/>
<w:gridCol w:w="2000"/>
<w:gridCol w:w="3000"/>
<w:gridCol w:w="2000"/>
</w:tblGrid>
' . $table_rows . '
</w:tbl>
<w:p></w:p>
</w:body>
</w:document>';
    
    $zip->addFromString('word/document.xml', $document);
    
    // Close zip
    if (!$zip->close()) {
        http_response_code(500);
        die('Error closing DOCX file');
    }
    
    // Check if file was created
    if (!file_exists($temp_file) || filesize($temp_file) == 0) {
        http_response_code(500);
        die('DOCX file creation failed');
    }
    
    // Output file
    header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . filesize($temp_file));
    header('Pragma: no-cache');
    header('Expires: 0');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    
    readfile($temp_file);
    @unlink($temp_file);
    exit;
}

// Normal page load continues here
$title = "Season Report 1 - Student Details | SLGTI";
include_once("../config.php");
require_once("../auth.php");
require_roles(['HOD', 'ADM', 'FIN', 'SAO']);

$user_id = isset($_SESSION['user_name']) ? $_SESSION['user_name'] : '';
$is_hod = is_role('HOD');

// Get HOD's department code
$dept_code = null;
if ($is_hod) {
    $dept_code = isset($_SESSION['department_code']) ? trim($_SESSION['department_code']) : '';
    if (empty($dept_code) && !empty($user_id)) {
        $staff_sql = "SELECT department_id FROM staff WHERE staff_id = '".mysqli_real_escape_string($con, $user_id)."' LIMIT 1";
        $staff_result = mysqli_query($con, $staff_sql);
        if ($staff_result && mysqli_num_rows($staff_result) > 0) {
            $staff_row = mysqli_fetch_assoc($staff_result);
            $dept_code = $staff_row['department_id'] ?? '';
        }
    }
}

// Get filter parameters
$filter_month = isset($_GET['month']) ? trim($_GET['month']) : '';

// Validate month format
if (!empty($filter_month) && !preg_match('/^\d{4}-\d{2}$/', $filter_month)) {
    $filter_month = '';
}

// Build WHERE clause
$where = [];
$where[] = "sr.status = 'approved'";
$where[] = "sp.id IS NOT NULL";

// Filter by payment_reference (month)
if (!empty($filter_month)) {
    $where[] = "sp.payment_reference = '".mysqli_real_escape_string($con, $filter_month)."'";
}

// Filter by HOD's department
if ($is_hod && !empty($dept_code)) {
    $where[] = "d.department_id = '".mysqli_real_escape_string($con, $dept_code)."'";
}

$where_clause = "WHERE " . implode(" AND ", $where);

// Query for report data
$sql = "SELECT DISTINCT
        sr.student_id,
        s.student_fullname,
        s.student_nic,
        CONCAT(sr.route_from, ' → ', sr.route_to) as route,
        sp.payment_reference
        FROM season_requests sr
        LEFT JOIN season_payments sp ON sr.id = sp.request_id
        LEFT JOIN student s ON sr.student_id = s.student_id
        LEFT JOIN student_enroll se ON se.student_id = s.student_id AND se.student_enroll_status IN ('Following','Active')
        LEFT JOIN course c ON c.course_id = se.course_id
        LEFT JOIN department d ON d.department_id = c.department_id
        $where_clause
        ORDER BY sr.student_id, sp.payment_reference";

// Fetch data for display
$result = mysqli_query($con, $sql);
$report_data = [];
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $report_data[] = $row;
    }
    if ($result) mysqli_free_result($result);
}

include_once("../head.php");
include_once("../menu.php");
?>

<div class="container-fluid mt-3">
    <div class="card shadow">
        <div class="card-header bg-primary text-white">
            <h3 class="mb-0"><i class="fas fa-file-word"></i> Season Report 1 - Student Details</h3>
        </div>
        <div class="card-body">
            <!-- Filter Form -->
            <div class="card mb-3">
                <div class="card-header bg-light">
                    <h5 class="mb-0"><i class="fas fa-filter"></i> Filter</h5>
                </div>
                <div class="card-body">
                    <form method="GET" class="row">
                        <div class="col-md-4">
                            <label>Payment Month (YYYY-MM)</label>
                            <input type="month" name="month" class="form-control" value="<?= htmlspecialchars($filter_month) ?>">
                        </div>
                        <div class="col-md-4">
                            <label>&nbsp;</label><br>
                            <button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> Apply Filter</button>
                            <a href="<?php echo defined('APP_BASE') ? APP_BASE : ''; ?>/season/SeasonReport1.php" class="btn btn-secondary">Reset</a>
                        </div>
                        <div class="col-md-4">
                            <label>&nbsp;</label><br>
                            <?php 
                            $exportQuery = $_GET;
                            $exportQuery['export'] = 'docx';
                            ?>
                            <a href="?<?= http_build_query($exportQuery) ?>" class="btn btn-success">
                                <i class="fas fa-file-word"></i> Download DOCX
                            </a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Report Table -->
            <div class="table-responsive">
                <table class="table table-bordered table-striped">
                    <thead class="thead-dark">
                        <tr>
                            <th>Student ID</th>
                            <th>Student Name</th>
                            <th>NIC Number</th>
                            <th>Route</th>
                            <th>Payment Month</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($report_data)): ?>
                            <tr>
                                <td colspan="5" class="text-center">No data found.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($report_data as $row): ?>
                                <tr>
                                    <td><?= htmlspecialchars($row['student_id']) ?></td>
                                    <td><?= htmlspecialchars($row['student_fullname'] ?? '-') ?></td>
                                    <td><?= htmlspecialchars($row['student_nic'] ?? '-') ?></td>
                                    <td><?= htmlspecialchars($row['route'] ?? '-') ?></td>
                                    <td><?= htmlspecialchars($row['payment_reference'] ?? '-') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include_once("../footer.php"); ?>
