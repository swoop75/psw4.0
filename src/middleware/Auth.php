<?php
/**
 * File: src/middleware/Auth.php
 * Path: C:\Users\laoan\Documents\GitHub\psw\psw4.0\src\middleware\Auth.php
 * Description: Authentication middleware for PSW 4.0 - handles session management and access control
 */

require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../utils/Security.php';
require_once __DIR__ . '/../utils/Logger.php';

class Auth {
    
    /**
     * Check if user is logged in
     * @return bool True if user is authenticated
     */
    public static function isLoggedIn() {
        return isset($_SESSION['user_id']) && 
               isset($_SESSION['username']) && 
               isset($_SESSION['user_role']) &&
               self::isSessionValid();
    }
    
    /**
     * Check if session is valid (not expired)
     * @return bool True if session is valid
     */
    public static function isSessionValid() {
        if (!isset($_SESSION['last_activity'])) {
            return false;
        }
        
        $currentTime = time();
        $lastActivity = $_SESSION['last_activity'];
        
        if (($currentTime - $lastActivity) > SESSION_TIMEOUT) {
            self::logout();
            return false;
        }
        
        // Update last activity time
        $_SESSION['last_activity'] = $currentTime;
        return true;
    }
    
    /**
     * Login user and create session
     * @param array $userData User data from database
     */
    public static function login($userData) {
        // Regenerate session ID for security
        session_regenerate_id(true);
        
        $_SESSION['user_id'] = $userData['user_id'];
        $_SESSION['username'] = $userData['username'];
        $_SESSION['email'] = $userData['email'];
        $_SESSION['user_role'] = $userData['role_id'];
        $_SESSION['role_name'] = $userData['role_name'];
        $_SESSION['last_activity'] = time();
        $_SESSION['login_time'] = time();
        
        Logger::info('User session created', [
            'user_id' => $userData['user_id'],
            'username' => $userData['username']
        ]);
    }
    
    /**
     * Logout user and destroy session
     */
    public static function logout() {
        if (isset($_SESSION['user_id'])) {
            Logger::info('User session destroyed', [
                'user_id' => $_SESSION['user_id'],
                'username' => $_SESSION['username']
            ]);
        }
        
        // Clear session data
        $_SESSION = [];
        
        // Destroy session cookie
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        
        // Destroy session
        session_destroy();
    }
    
    /**
     * Require authentication - redirect to login if not authenticated
     */
    public static function requireAuth() {
        if (!self::isLoggedIn()) {
            header('Location: ' . BASE_URL . '/');
            exit;
        }
    }
    
    /**
     * Require admin role
     */
    public static function requireAdmin() {
        self::requireAuth();
        
        if (!self::isAdmin()) {
            Logger::warning('Unauthorized access attempt to admin area', [
                'user_id' => $_SESSION['user_id'] ?? 'unknown',
                'role' => $_SESSION['user_role'] ?? 'unknown'
            ]);
            
            http_response_code(403);
            include __DIR__ . '/../../templates/errors/403.php';
            exit;
        }
    }
    
    /**
     * Check if current user is administrator
     * @return bool True if user is admin
     */
    public static function isAdmin() {
        return isset($_SESSION['user_role']) && $_SESSION['user_role'] == ROLE_ADMINISTRATOR;
    }
    
    /**
     * Get current user ID
     * @return int|null User ID or null if not logged in
     */
    public static function getUserId() {
        return $_SESSION['user_id'] ?? null;
    }
    
    /**
     * Get current username
     * @return string|null Username or null if not logged in
     */
    public static function getUsername() {
        return $_SESSION['username'] ?? null;
    }
    
    /**
     * Get current user role
     * @return int|null Role ID or null if not logged in
     */
    public static function getUserRole() {
        return $_SESSION['user_role'] ?? null;
    }
    
    /**
     * Check if user has access to specific menu item
     * @param string $menuItem Menu item key from MENU_STRUCTURE
     * @return bool True if user has access
     */
    public static function hasMenuAccess($menuItem) {
        if (!isset(MENU_STRUCTURE[$menuItem])) {
            return false;
        }
        
        $menu = MENU_STRUCTURE[$menuItem];
        
        // Check if user is logged in for user-level access
        if ($menu['access'] >= ACCESS_USER && !self::isLoggedIn()) {
            return false;
        }
        
        // Check if admin role is required
        if ($menu['admin_only'] && !self::isAdmin()) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Check if user has access to submenu item
     * @param string $menuItem Parent menu item
     * @param string $submenuItem Submenu item key
     * @return bool True if user has access
     */
    public static function hasSubmenuAccess($menuItem, $submenuItem) {
        if (!self::hasMenuAccess($menuItem)) {
            return false;
        }
        
        $menu = MENU_STRUCTURE[$menuItem];
        if (!isset($menu['submenu'][$submenuItem])) {
            return false;
        }
        
        $submenu = $menu['submenu'][$submenuItem];
        
        // Check if admin role is required for submenu
        if ($submenu['admin_only'] && !self::isAdmin()) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Generate navigation menu based on user permissions
     * @return array Filtered menu structure
     */
    public static function getAccessibleMenu() {
        $accessibleMenu = [];
        
        foreach (MENU_STRUCTURE as $key => $menu) {
            if (self::hasMenuAccess($key)) {
                $menuItem = $menu;
                
                // Filter submenus
                if (isset($menu['submenu'])) {
                    $accessibleSubmenu = [];
                    foreach ($menu['submenu'] as $subKey => $submenu) {
                        if (self::hasSubmenuAccess($key, $subKey)) {
                            $accessibleSubmenu[$subKey] = $submenu;
                        }
                    }
                    $menuItem['submenu'] = $accessibleSubmenu;
                }
                
                $accessibleMenu[$key] = $menuItem;
            }
        }
        
        return $accessibleMenu;
    }
    
    /**
     * Get user session info for debugging
     * @return array Session information
     */
    public static function getSessionInfo() {
        if (!self::isLoggedIn()) {
            return ['status' => 'not_logged_in'];
        }
        
        return [
            'status' => 'logged_in',
            'user_id' => $_SESSION['user_id'],
            'username' => $_SESSION['username'],
            'role' => $_SESSION['role_name'],
            'login_time' => date(DATETIME_FORMAT, $_SESSION['login_time']),
            'last_activity' => date(DATETIME_FORMAT, $_SESSION['last_activity']),
            'session_expires' => date(DATETIME_FORMAT, $_SESSION['last_activity'] + SESSION_TIMEOUT)
        ];
    }
}