<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include configuration and access control
require_once('../config.php');
require_once('../library/access_control.php');

// Set page variables
$page_title = "Group Timetable Management";
$nav_active = 'timetable';

// Initialize variables
$group_id = $_GET['group_id'] ?? 0;
$academic_year = $_GET['academic_year'] ?? date('Y');

// Check permissions (support 'ADM' role code used elsewhere)
if (!isset($_SESSION['user_type']) || !in_array($_SESSION['user_type'], ['ADM', 'HOD', 'ADMIN'])) {
    $_SESSION['error'] = 'You do not have permission to access this page.';
    header('Location: ' . (defined('APP_BASE') ? APP_BASE : '') . '/home/home.php');
    exit();
    exit;
}

// Get group details if group_id is provided
$group_id = isset($_GET['group_id']) ? intval($_GET['group_id']) : 0;
$academic_year = isset($_GET['academic_year']) ? trim($_GET['academic_year']) : '';

// If no group selected, redirect to group selection with a more specific message
if ($group_id <= 0) {
    $_SESSION['info'] = 'Please select a group to view its timetable';
    header('Location: ' . (defined('APP_BASE') ? APP_BASE : '') . '/group/Groups.php?redirect=group_timetable');
    exit;
}

// Get group details
$group = [];
$stmt = $con->prepare("
    SELECT g.*, c.course_name, c.department_id, d.department_name 
    FROM `groups` g 
    LEFT JOIN course c ON g.course_id = c.course_id
    LEFT JOIN department d ON c.department_id = d.department_id
    WHERE g.id = ? AND g.status = 'active'
");
if ($stmt) {
    $stmt->bind_param('i', $group_id);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    error_log("SQL Error: " . $con->error);
    $_SESSION['error'] = 'Database error. Please try again.';
    header('Location: ' . (defined('APP_BASE') ? APP_BASE : '') . '/group/Groups.php');
    exit;
}

if ($result->num_rows === 0) {
    $_SESSION['error'] = "Group not found";
    header('Location: ' . (defined('APP_BASE') ? APP_BASE : '') . '/group/Groups.php?redirect=group_timetable');
    exit;
}

$group = $result->fetch_assoc();

// Verify HOD has access to this group's department
if ($_SESSION['user_type'] === 'HOD' && $group['department_id'] != $_SESSION['department_id']) {
    $_SESSION['error'] = "You don't have permission to access this group";
    header('Location: ' . (defined('APP_BASE') ? APP_BASE : '') . '/group/Groups.php?redirect=group_timetable');
    exit;
}

// Get academic years for dropdown
$academic_years = [];
$current_year = date('Y');
for ($i = $current_year - 2; $i <= $current_year + 2; $i++) {
    $year = $i . '-' . ($i + 1);
    $academic_years[] = [
        'value' => $year,
        'label' => $year,
        'selected' => $academic_year === $year
    ];
}

// If no academic year selected, use current academic year
if (empty($academic_year)) {
    $current_month = date('n');
    $year = $current_month >= 8 ? $current_year : $current_year - 1;
    $academic_year = $year . '-' . ($year + 1);
    header("Location: ?group_id=$group_id&academic_year=" . urlencode($academic_year));
    exit;
}

include('../head.php');
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="page-header d-flex justify-content-between align-items-center mb-4">
                <h1 class="h3 mb-0">
                    <i class="fas fa-calendar-alt mr-2"></i>Group Timetable
                </h1>
                <div>
                    <a href="../group/Groups.php?redirect=group_timetable" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left mr-1"></i> Back to Groups
                    </a>
                </div>
            </div>

            <!-- Group Info Card -->
            <div class="card mb-4">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4">
                            <h5 class="mb-1"><?= htmlspecialchars($group['group_name'] ?? $group['name'] ?? 'Group') ?></h5>
                            <p class="text-muted mb-1"><?= htmlspecialchars($group['group_code'] ?? 'N/A') ?></p>
                        </div>
                        <div class="col-md-4">
                            <p class="mb-1"><strong>Course:</strong> <?= htmlspecialchars($group['course_name'] ?? 'N/A') ?></p>
                            <p class="mb-1"><strong>Department:</strong> <?= htmlspecialchars($group['department_name'] ?? 'N/A') ?></p>
                        </div>
                        <div class="col-md-4">
                            <form id="academicYearForm" class="form-inline justify-content-end">
                                <input type="hidden" name="group_id" value="<?= $group_id ?>">
                                <div class="form-group mb-0">
                                    <label for="academic_year" class="mr-2">Academic Year:</label>
                                    <select name="academic_year" id="academic_year" class="form-control form-control-sm">
                                        <?php foreach ($academic_years as $year): ?>
                                            <option value="<?= htmlspecialchars($year['value']) ?>" <?= $year['selected'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($year['label']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Timetable Actions -->
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Timetable Entries</h5>
                    <div>
                        <button type="button" class="btn btn-sm btn-primary" data-toggle="modal" data-target="#addTimetableModal">
                            <i class="fas fa-plus mr-1"></i> Add Entry
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="printTimetable">
                            <i class="fas fa-print mr-1"></i> Print
                        </button>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover mb-0" id="timetableTable">
                            <thead class="thead-light">
                                <tr>
                                    <th width="10%">Day/Session</th>
                                    <th width="22.5%" class="text-center">Session 1<br>08:30 - 10:00</th>
                                    <th width="22.5%" class="text-center">Session 2<br>10:30 - 12:00</th>
                                    <th width="22.5%" class="text-center">Session 3<br>13:00 - 14:30</th>
                                    <th width="22.5%" class="text-center">Session 4<br>14:45 - 16:15</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $weekdays = [
                                    1 => 'Monday',
                                    2 => 'Tuesday',
                                    3 => 'Wednesday',
                                    4 => 'Thursday',
                                    5 => 'Friday',
                                    6 => 'Saturday',
                                    7 => 'Sunday'
                                ];

                                foreach ($weekdays as $dayNum => $dayName):
                                    echo "<tr>";
                                    echo "<th class='align-middle'>{$dayName}</th>";
                                    
                                    foreach (['P1', 'P2', 'P3', 'P4'] as $period) {
                                        echo "<td class='timetable-slot' data-day='{$dayNum}' data-period='{$period}'>";
                                        echo "<div class='timetable-content' id='slot-{$dayNum}-{$period}'>";
                                        echo "<div class='text-center py-3 text-muted'>";
                                        echo "<i class='fas fa-plus-circle'></i> Add";
                                        echo "</div>";
                                        echo "</div>";
                                        echo "</td>";
                                    }
                                    
                                    echo "</tr>";
                                endforeach;
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add/Edit Timetable Modal -->
<div class="modal fade" id="timetableModal" tabindex="-1" role="dialog" aria-labelledby="timetableModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <form id="timetableForm">
                <input type="hidden" id="timetable_id" name="timetable_id" value="0">
                <input type="hidden" id="group_id" name="group_id" value="<?= $group_id ?>">
                
                <div class="modal-header">
                    <h5 class="modal-title" id="timetableModalLabel">Add Timetable Entry</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="module_id">Module <span class="text-danger">*</span></label>
                                <select class="form-control selectpicker" id="module_id" name="module_id" required 
                                        data-live-search="true" title="Select module">
                                    <!-- Loaded via AJAX -->
                                </select>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="staff_id">Staff <span class="text-danger">*</span></label>
                                <select class="form-control selectpicker" id="staff_id" name="staff_id" required 
                                        data-live-search="true" title="Select staff">
                                    <!-- Loaded via AJAX -->
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="weekday">Weekday <span class="text-danger">*</span></label>
                                <select class="form-control" id="weekday" name="weekday" required>
                                    <option value="1">Monday</option>
                                    <option value="2">Tuesday</option>
                                    <option value="3">Wednesday</option>
                                    <option value="4">Thursday</option>
                                    <option value="5">Friday</option>
                                    <option value="6">Saturday</option>
                                    <option value="7">Sunday</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="period">Session <span class="text-danger">*</span></label>
                                <select class="form-control" id="period" name="period" required>
                                    <option value="P1">Session 1 (08:30 - 10:00)</option>
                                    <option value="P2">Session 2 (10:30 - 12:00)</option>
                                    <option value="P3">Session 3 (13:00 - 14:30)</option>
                                    <option value="P4">Session 4 (14:45 - 16:15)</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="classroom">Classroom <span class="text-danger">*</span></label>
                                <select class="form-control" id="classroom" name="classroom" required>
                                    <option value="LAP-01">LAP-01</option>
                                    <option value="LAP-02">LAP-02</option>
                                    <option value="LAP-03">LAP-03</option>
                                    <option value="LAP-04">LAP-04</option>
                                    <option value="LAP-05">LAP-05</option>
                                    <option value="THEORY-01">THEORY-01</option>
                                    <option value="THEORY-02">THEORY-02</option>
                                    <option value="LAB-01">LAB-01</option>
                                    <option value="LAB-02">LAB-02</option>
                                    <option value="SEMINAR">SEMINAR HALL</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="start_date">Start Date <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="start_date" name="start_date" required>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="end_date">End Date <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="end_date" name="end_date" required>
                            </div>
                        </div>
                    </div>
                    
                    <div id="formError" class="alert alert-danger d-none"></div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirm Delete</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete this timetable entry?</p>
                <p class="text-muted">This action cannot be undone.</p>
                <input type="hidden" id="delete_id" value="">
                <div class="custom-control custom-checkbox mt-2">
                    <input type="checkbox" class="custom-control-input" id="hardDelete" name="hard_delete">
                    <label class="custom-control-label text-danger" for="hardDelete">
                        Permanently delete (cannot be recovered)
                    </label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirmDelete">Delete</button>
            </div>
        </div>
    </div>
</div>

<?php include('../footer.php'); ?>

<!-- Include required CSS/JS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-select@1.13.14/dist/css/bootstrap-select.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">

<script src="https://cdn.jsdelivr.net/npm/bootstrap-select@1.13.14/dist/js/bootstrap-select.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/print-js/1.6.0/print.min.js"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/print-js/1.6.0/print.min.css">

<style>
.timetable-slot {
    min-height: 100px;
    vertical-align: top;
    cursor: pointer;
    transition: background-color 0.2s;
}

.timetable-slot:hover {
    background-color: #f8f9fa;
}

.timetable-slot .timetable-content {
    height: 100%;
    position: relative;
}

.timetable-entry {
    border-radius: 4px;
    padding: 8px;
    margin: 2px 0;
    color: white;
    font-size: 0.85rem;
    position: relative;
    overflow: hidden;
    border-left: 4px solid rgba(0,0,0,0.2);
}

.timetable-entry .module-code {
    font-weight: bold;
    margin-bottom: 2px;
    display: block;
}

.timetable-entry .staff-name {
    font-size: 0.8em;
    opacity: 0.9;
    display: block;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.timetable-entry .classroom {
    position: absolute;
    bottom: 4px;
    right: 8px;
    font-size: 0.75em;
    background: rgba(0,0,0,0.15);
    padding: 0 4px;
    border-radius: 3px;
}

.timetable-actions {
    position: absolute;
    top: 2px;
    right: 2px;
    opacity: 0;
    transition: opacity 0.2s;
    background: rgba(255,255,255,0.2);
    border-radius: 3px;
}

.timetable-entry:hover .timetable-actions {
    opacity: 1;
}

.timetable-actions .btn {
    padding: 0 4px;
    font-size: 0.7rem;
    line-height: 1.2;
}

/* Responsive adjustments */
@media (max-width: 768px) {
    .table-responsive {
        font-size: 0.8rem;
    }
    
    .timetable-slot {
        min-height: 80px;
    }
    
    .timetable-entry {
        padding: 4px;
        font-size: 0.7rem;
    }
    
    .timetable-entry .module-code {
        font-size: 0.75rem;
    }
    
    .timetable-entry .staff-name {
        font-size: 0.7rem;
    }
    
    .timetable-entry .classroom {
        font-size: 0.65rem;
    }
}
</style>

<script>
$(document).ready(function() {
    const groupId = <?= $group_id ?>;
    const academicYear = '<?= $academic_year ?>';
    let timetableData = {};
    
    // Initialize select pickers
    $('.selectpicker').selectpicker({
        style: 'btn-light',
        size: 8
    });
    
    // Set default dates for the academic year
    const [startYear] = academicYear.split('-').map(Number);
    const defaultStartDate = `${startYear}-08-01`;
    const defaultEndDate = `${startYear + 1}-05-31`;
    
    $('#start_date').val(defaultStartDate);
    $('#end_date').val(defaultEndDate);
    
    // Load modules and staff for the group's course
    function loadFormData() {
        // Load modules
        $.get('../controller/ajax/get_course_modules.php', { 
            course_id: <?= $group['course_id'] ?>
        }, function(modules) {
            const $moduleSelect = $('#module_id');
            $moduleSelect.empty();
            
            if (modules.length > 0) {
                $.each(modules, function(i, module) {
                    $moduleSelect.append($('<option>', {
                        value: module.module_id,
                        text: module.module_code + ' - ' + module.module_name
                    }));
                });
                $moduleSelect.selectpicker('refresh');
                
                // After loading modules, load staff
                loadStaff();
            } else {
                $moduleSelect.append($('<option>', {
                    value: '',
                    text: 'No modules found for this course',
                    disabled: true,
                    selected: true
                }));
                $moduleSelect.selectpicker('refresh');
            }
        }, 'json');
    }
    
    // Load staff members
    function loadStaff() {
        $.get('../controller/ajax/get_staff.php', function(staff) {
            const $staffSelect = $('#staff_id');
            $staffSelect.empty();
            
            if (staff.length > 0) {
                $.each(staff, function(i, person) {
                    $staffSelect.append($('<option>', {
                        value: person.staff_id,
                        text: person.staff_name + ' (' + person.staff_id + ')'
                    }));
                });
                $staffSelect.selectpicker('refresh');
            } else {
                $staffSelect.append($('<option>', {
                    value: '',
                    text: 'No staff found',
                    disabled: true,
                    selected: true
                }));
                $staffSelect.selectpicker('refresh');
            }
        }, 'json');
    }
    
    // Load timetable data
    function loadTimetable() {
        $.ajax({
            url: '../controller/GroupTimetableController.php',
            type: 'GET',
            dataType: 'json',
            data: {
                action: 'list',
                group_id: groupId,
                academic_year: academicYear
            },
            success: function(response) {
                if (response.success && response.data) {
                    timetableData = {};
                    
                    // Clear all slots
                    $('.timetable-content').html(`
                        <div class="text-center py-3 text-muted">
                            <i class="fas fa-plus-circle"></i> Add
                        </div>
                    `);
                    
                    // Group entries by day and period
                    response.data.forEach(function(entry) {
                        const day = entry.weekday;
                        const period = entry.period;
                        const slotId = `#slot-${day}-${period}`;
                        
                        if (!timetableData[day]) {
                            timetableData[day] = {};
                        }
                        
                        if (!timetableData[day][period]) {
                            timetableData[day][period] = [];
                        }
                        
                        timetableData[day][period].push(entry);
                        
                        // Store the entry data on the slot
                        $(slotId).data('entry', entry);
                        
                        // Update the slot UI
                        updateTimetableSlot(day, period);
                    });
                }
            },
            error: function() {
                showAlert('Error loading timetable data', 'danger');
            }
        });
    }
    
    // Update a single timetable slot UI
    function updateTimetableSlot(day, period) {
        const slotId = `#slot-${day}-${period}`;
        const entries = timetableData[day]?.[period] || [];
        const $slot = $(slotId);
        
        if (entries.length === 0) {
            $slot.html(`
                <div class="text-center py-3 text-muted">
                    <i class="fas fa-plus-circle"></i> Add
                </div>
            `);
            return;
        }
        
        let html = '<div class="timetable-entries">';
        
        entries.forEach(function(entry) {
            const startDate = new Date(entry.start_date);
            const endDate = new Date(entry.end_date);
            const dateRange = `${formatDate(startDate)} to ${formatDate(endDate)}`;
            
            // Generate a consistent color based on module ID
            const colorIndex = Math.abs(hashCode(entry.module_id)) % 10;
            const colors = [
                '#3498db', '#2ecc71', '#e74c3c', '#f39c12', '#9b59b6',
                '#1abc9c', '#d35400', '#34495e', '#7f8c8d', '#27ae60'
            ];
            const bgColor = colors[colorIndex];
            
            html += `
                <div class="timetable-entry" style="background-color: ${bgColor}" 
                     data-id="${entry.timetable_id}" 
                     data-module="${entry.module_id}">
                    <div class="timetable-actions">
                        <button class="btn btn-xs btn-light btn-edit" title="Edit">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="btn btn-xs btn-light btn-delete" title="Delete">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                    <span class="module-code">${entry.module_code || 'N/A'}</span>
                    <span class="staff-name">${entry.staff_name || 'Staff N/A'}</span>
                    <span class="classroom">${entry.classroom || 'N/A'}</span>
                    <div class="date-range small">${dateRange}</div>
                </div>
            `;
        });
        
        html += '</div>';
        $slot.html(html);
    }
    
    // Helper function to format date as DD/MM/YYYY
    function formatDate(date) {
        const day = String(date.getDate()).padStart(2, '0');
        const month = String(date.getMonth() + 1).padStart(2, '0');
        return `${day}/${month}/${date.getFullYear()}`;
    }
    
    // Helper function to generate a hash code for consistent colors
    function hashCode(str) {
        let hash = 0;
        for (let i = 0; i < str.length; i++) {
            const char = str.charCodeAt(i);
            hash = ((hash << 5) - hash) + char;
            hash = hash & hash; // Convert to 32bit integer
        }
        return hash;
    }
    
    // Handle click on timetable slot
    $(document).on('click', '.timetable-slot', function() {
        const day = $(this).data('day');
        const period = $(this).data('period');
        
        // Reset form
        resetTimetableForm();
        
        // Set day and period
        $('#weekday').val(day);
        $('#period').val(period);
        
        // Update modal title
        $('#timetableModalLabel').text('Add Timetable Entry');
        
        // Show modal
        $('#timetableModal').modal('show');
    });
    
    // Handle edit button click
    $(document).on('click', '.btn-edit', function(e) {
        e.stopPropagation();
        
        const $entry = $(this).closest('.timetable-entry');
        const entryId = $entry.data('id');
        
        // Load entry data
        $.ajax({
            url: '../controller/GroupTimetableController.php',
            type: 'GET',
            dataType: 'json',
            data: {
                action: 'get',
                timetable_id: entryId
            },
            success: function(response) {
                if (response.success && response.data) {
                    const entry = response.data;
                    
                    // Populate form
                    $('#timetable_id').val(entry.timetable_id);
                    $('#module_id').val(entry.module_id);
                    $('#staff_id').val(entry.staff_id);
                    $('#weekday').val(entry.weekday);
                    $('#period').val(entry.period);
                    $('#classroom').val(entry.classroom);
                    $('#start_date').val(entry.start_date);
                    $('#end_date').val(entry.end_date);
                    
                    // Update selects
                    $('.selectpicker').selectpicker('refresh');
                    
                    // Update modal title
                    $('#timetableModalLabel').text('Edit Timetable Entry');
                    
                    // Show modal
                    $('#timetableModal').modal('show');
                } else {
                    showAlert('Error loading entry data', 'danger');
                }
            },
            error: function() {
                showAlert('Error loading entry data', 'danger');
            }
        });
    });
    
    // Handle delete button click
    $(document).on('click', '.btn-delete', function(e) {
        e.stopPropagation();
        
        const entryId = $(this).closest('.timetable-entry').data('id');
        $('#delete_id').val(entryId);
        $('#deleteModal').modal('show');
    });
    
    // Confirm delete
    $('#confirmDelete').click(function() {
        const entryId = $('#delete_id').val();
        const hardDelete = $('#hardDelete').is(':checked');
        
        $.ajax({
            url: '../controller/GroupTimetableController.php',
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'delete',
                timetable_id: entryId,
                hard_delete: hardDelete
            },
            success: function(response) {
                if (response.success) {
                    showAlert('Entry deleted successfully', 'success');
                    loadTimetable();
                } else {
                    showAlert(response.message || 'Error deleting entry', 'danger');
                }
                
                $('#deleteModal').modal('hide');
            },
            error: function() {
                showAlert('Error deleting entry', 'danger');
                $('#deleteModal').modal('hide');
            }
        });
    });
    
    // Handle form submission
    $('#timetableForm').submit(function(e) {
        e.preventDefault();
        
        // Validate form
        if (!validateForm()) {
            return false;
        }
        
        const formData = $(this).serialize();
        
        $.ajax({
            url: '../controller/GroupTimetableController.php',
            type: 'POST',
            data: formData + '&action=save',
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    showAlert('Timetable saved successfully', 'success');
                    $('#timetableModal').modal('hide');
                    loadTimetable();
                } else {
                    showFormError(response.message || 'Error saving timetable');
                }
            },
            error: function() {
                showFormError('Error saving timetable');
            }
        });
        
        return false;
    });
    
    // Validate form
    function validateForm() {
        const startDate = new Date($('#start_date').val());
        const endDate = new Date($('#end_date').val());
        
        if (endDate < startDate) {
            showFormError('End date cannot be before start date');
            return false;
        }
        
        // Additional validations can be added here
        
        return true;
    }
    
    // Reset form
    function resetTimetableForm() {
        $('#timetableForm')[0].reset();
        $('#timetable_id').val('0');
        $('#formError').addClass('d-none');
        
        // Set default dates
        $('#start_date').val(defaultStartDate);
        $('#end_date').val(defaultEndDate);
        
        // Refresh select pickers
        $('.selectpicker').selectpicker('refresh');
    }
    
    // Show form error
    function showFormError(message) {
        const $errorDiv = $('#formError');
        $errorDiv.removeClass('d-none').text(message);
        $('html, body').animate({
            scrollTop: $errorDiv.offset().top - 100
        }, 500);
    }
    
    // Show alert
    function showAlert(message, type) {
        const alertHtml = `
            <div class="alert alert-${type} alert-dismissible fade show" role="alert">
                ${message}
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
        `;
        
        // Remove any existing alerts
        $('.alert-dismissible').alert('close');
        
        // Add new alert
        $('.container-fluid').prepend(alertHtml);
    }
    
    // Handle academic year change
    $('#academic_year').change(function() {
        const year = $(this).val();
        window.location.href = `?group_id=${groupId}&academic_year=${year}`;
    });
    
    // Print timetable
    $('#printTimetable').click(function() {
        const printContent = `
            <div class="container">
                <div class="text-center mb-4">
                    <h3>${$('h1').text()}</h3>
                    <h5>${$('.card-body h5').text()} - ${$('.card-body p').eq(0).text()}</h5>
                    <p>${$('.card-body p').eq(1).text()}</p>
                    <p>Academic Year: ${academicYear}</p>
                </div>
                ${$('#timetableTable').parent().html()}
                <div class="text-muted text-center mt-4">
                    Printed on ${new Date().toLocaleString()}
                </div>
            </div>
        `;
        
        const printWindow = window.open('', '_blank');
        printWindow.document.write(`
            <!DOCTYPE html>
            <html>
            <head>
                <title>Timetable Print</title>
                <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
                <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
                <style>
                    @page { size: A4 landscape; margin: 1cm; }
                    body { font-size: 10pt; }
                    .timetable-entry { color: white; padding: 4px; margin: 2px 0; border-radius: 3px; }
                    .module-code { font-weight: bold; display: block; }
                    .staff-name { font-size: 0.8em; opacity: 0.9; }
                    .classroom { font-size: 0.7em; position: absolute; bottom: 2px; right: 4px; }
                </style>
            </head>
            <body>
                ${printContent}
                <script>
                    window.onload = function() {
                        window.print();
                        setTimeout(function() { window.close(); }, 500);
                    };
                <\/script>
            </body>
            </html>
        `);
        printWindow.document.close();
    });
    
    // Initialize the page
    loadFormData();
    loadTimetable();
    
    // Show modal when clicking "Add Entry" button
    $('[data-target="#addTimetableModal"]').click(function() {
        resetTimetableForm();
        $('#timetableModalLabel').text('Add Timetable Entry');
    });
    
    // Reset form when modal is hidden
    $('#timetableModal').on('hidden.bs.modal', function() {
        resetTimetableForm();
    });
});
</script>
