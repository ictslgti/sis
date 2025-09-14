<?php
/**
 * Access Control Functions
 * 
 * This file contains additional functions for handling user authentication and authorization
 * that don't conflict with auth.php
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Only define functions if they don't already exist
if (!function_exists('has_role')) {
    /**
     * Check if user has required role/permission
     * @param string|array $required_roles Role or array of roles to check against
     * @return bool True if user has required role, false otherwise
     */
    function has_role($required_roles) {
        if (!isset($_SESSION['user_type'])) {
            return false;
        }
        
        if (!is_array($required_roles)) {
            $required_roles = [$required_roles];
        }
        
        return in_array($_SESSION['user_type'], $required_roles);
    }
}

if (!function_exists('require_role')) {
    /**
     * Require user to have specific role
     * @param string|array $required_roles Role or array of roles
     * @param string $redirect_url URL to redirect to if access is denied
     */
    function require_role($required_roles, $redirect_url = null) {
        if (!function_exists('require_login')) {
            // If require_login doesn't exist, create a simple version
            if (!isset($_SESSION['user_type'])) {
                $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
                header('Location: ' . (defined('APP_BASE') ? APP_BASE : '') . '/index.php');
                exit();
            }
        } else {
            require_login();
        }
        
        if (!has_role($required_roles)) {
            if ($redirect_url === null) {
                $redirect_url = (defined('BASE_URL') ? BASE_URL : '') . '/home/access_denied.php';
            }
            
            $_SESSION['error'] = 'You do not have permission to access this page.';
            header('Location: ' . $redirect_url);
            exit();
        }
    }
}

if (!function_exists('verify_csrf_token')) {
    /**
     * Check CSRF token
     * @param string $token Token to verify
     * @return bool True if token is valid, false otherwise
     */
    function verify_csrf_token($token) {
        if (!isset($_SESSION['csrf_token'])) {
            return false;
        }
        return hash_equals($_SESSION['csrf_token'], $token);
    }
}

if (!function_exists('generate_csrf_token')) {
    /**
     * Generate CSRF token
     * @return string Generated token
     */
    function generate_csrf_token() {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
}

if (!function_exists('csrf_field')) {
    /**
     * Get CSRF token input field HTML
     * @return string HTML input field with CSRF token
     */
    function csrf_field() {
        return '<input type="hidden" name="csrf_token" value="' . generate_csrf_token() . '">';
    }
}

if (!function_exists('is_method')) {
    /**
     * Verify request method
     * @param string $method Expected request method (GET, POST, etc.)
     * @return bool True if request method matches, false otherwise
     */
    function is_method($method) {
        return $_SERVER['REQUEST_METHOD'] === strtoupper($method);
    }
}

if (!function_exists('require_method')) {
    /**
     * Require specific request method
     * @param string $method Required request method
     * @param string $redirect_url URL to redirect to if method doesn't match
     */
    function require_method($method, $redirect_url = null) {
        if (!is_method($method)) {
            if ($redirect_url === null) {
                $redirect_url = $_SERVER['HTTP_REFERER'] ?? (defined('BASE_URL') ? BASE_URL : '') . '/index.php';
            }
            
            $_SESSION['error'] = 'Invalid request method.';
            header('Location: ' . $redirect_url);
            exit();
        }
    }
}

// User type helper functions
if (!function_exists('is_admin')) {
    function is_admin() {
        return isset($_SESSION['user_type']) && strtoupper($_SESSION['user_type']) === 'ADMIN';
    }
}

if (!function_exists('is_hod')) {
    function is_hod() {
        return isset($_SESSION['user_type']) && strtoupper($_SESSION['user_type']) === 'HOD';
    }
}

if (!function_exists('is_staff')) {
    function is_staff() {
        return isset($_SESSION['user_type']) && strtoupper($_SESSION['user_type']) === 'STAFF';
    }
}

if (!function_exists('is_student')) {
    function is_student() {
        return isset($_SESSION['user_type']) && strtoupper($_SESSION['user_type']) === 'STUDENT';
    }
}

if (!function_exists('get_user_id')) {
    function get_user_id() {
        return $_SESSION['user_id'] ?? null;
    }
}

if (!function_exists('get_user_type')) {
    function get_user_type() {
        return $_SESSION['user_type'] ?? null;
    }
}

if (!function_exists('user')) {
    function user($key = null) {
        if (!isset($_SESSION['user_data'])) {
            return null;
        }
        
        if ($key === null) {
            return $_SESSION['user_data'];
        }
        
        return $_SESSION['user_data'][$key] ?? null;
    }
}

// Flash message functions
if (!function_exists('set_flash')) {
    function set_flash($type, $message) {
        $_SESSION['flash'] = [
            'type' => $type,
            'message' => $message
        ];
    }
}

if (!function_exists('get_flash')) {
    function get_flash() {
        if (empty($_SESSION['flash'])) {
            return null;
        }
        
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
}

if (!function_exists('display_flash')) {
    function display_flash($type = null) {
        $flash = get_flash();
        
        if ($flash === null) {
            return '';
        }
        
        if ($type !== null && $flash['type'] !== $type) {
            return '';
        }
        
        $class = 'alert';
        switch ($flash['type']) {
            case 'success':
                $class .= ' alert-success';
                break;
            case 'error':
                $class .= ' alert-danger';
                break;
            case 'warning':
                $class .= ' alert-warning';
                break;
            case 'info':
            default:
                $class .= ' alert-info';
        }
        
        return '<div class="' . htmlspecialchars($class) . ' alert-dismissible fade show" role="alert">' . 
               htmlspecialchars($flash['message']) . 
               '<button type="button" class="close" data-dismiss="alert" aria-label="Close">' . 
               '<span aria-hidden="true">&times;</span></button></div>';
    }
}

// Initialize CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    generate_csrf_token();
}
?>
