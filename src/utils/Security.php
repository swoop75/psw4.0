<?php
/**
 * File: src/utils/Security.php
 * Path: C:\Users\laoan\Documents\GitHub\psw\psw4.0\src\utils\Security.php
 * Description: Security utility class for PSW 4.0 - handles password hashing, CSRF protection, input sanitization
 */

class Security {
    
    /**
     * Hash password using PHP password_hash
     * @param string $password Plain text password
     * @return string Hashed password
     */
    public static function hashPassword($password) {
        return password_hash($password, PASSWORD_DEFAULT);
    }
    
    /**
     * Verify password against hash
     * @param string $password Plain text password
     * @param string $hash Hashed password from database
     * @return bool True if password matches
     */
    public static function verifyPassword($password, $hash) {
        return password_verify($password, $hash);
    }
    
    /**
     * Generate CSRF token
     * @return string CSRF token
     */
    public static function generateCSRFToken() {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
    
    /**
     * Verify CSRF token
     * @param string $token Token to verify
     * @return bool True if token is valid
     */
    public static function verifyCSRFToken($token) {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
    
    /**
     * Validate CSRF token (alias for verifyCSRFToken)
     * @param string $token Token to validate
     * @return bool True if token is valid
     */
    public static function validateCsrfToken($token) {
        return self::verifyCSRFToken($token);
    }
    
    /**
     * Sanitize input data
     * @param string $input Input string to sanitize
     * @return string Sanitized string
     */
    public static function sanitizeInput($input) {
        return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
    }
    
    /**
     * Validate email address
     * @param string $email Email to validate
     * @return bool True if valid email
     */
    public static function validateEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
    
    /**
     * Validate password strength
     * @param string $password Password to validate
     * @return array Array with 'valid' boolean and 'message' string
     */
    public static function validatePassword($password) {
        $minLength = PASSWORD_MIN_LENGTH;
        $result = ['valid' => true, 'message' => ''];
        
        if (strlen($password) < $minLength) {
            $result['valid'] = false;
            $result['message'] = "Password must be at least {$minLength} characters long";
        } elseif (!preg_match('/[A-Z]/', $password)) {
            $result['valid'] = false;
            $result['message'] = 'Password must contain at least one uppercase letter';
        } elseif (!preg_match('/[a-z]/', $password)) {
            $result['valid'] = false;
            $result['message'] = 'Password must contain at least one lowercase letter';
        } elseif (!preg_match('/[0-9]/', $password)) {
            $result['valid'] = false;
            $result['message'] = 'Password must contain at least one number';
        }
        
        return $result;
    }
    
    /**
     * Generate secure random token
     * @param int $length Token length
     * @return string Random token
     */
    public static function generateToken($length = 32) {
        return bin2hex(random_bytes($length));
    }
    
    /**
     * Check if user has required role
     * @param int $requiredRole Required role level
     * @param int $userRole User's current role
     * @return bool True if user has required access
     */
    public static function hasAccess($requiredRole, $userRole) {
        return $userRole >= $requiredRole;
    }
    
    /**
     * Check if current user is administrator
     * @return bool True if user is administrator
     */
    public static function isAdmin() {
        return isset($_SESSION['user_role']) && $_SESSION['user_role'] == ROLE_ADMINISTRATOR;
    }
    
    /**
     * Rate limiting for login attempts
     * @param string $identifier Usually IP address or username
     * @return bool True if under rate limit
     */
    public static function checkRateLimit($identifier) {
        $key = 'login_attempts_' . $identifier;
        if (!isset($_SESSION[$key])) {
            $_SESSION[$key] = ['count' => 0, 'time' => time()];
        }
        
        $attempts = $_SESSION[$key];
        
        // Reset counter if more than 15 minutes have passed
        if (time() - $attempts['time'] > 900) {
            $_SESSION[$key] = ['count' => 0, 'time' => time()];
            return true;
        }
        
        return $attempts['count'] < MAX_LOGIN_ATTEMPTS;
    }
    
    /**
     * Record login attempt
     * @param string $identifier Usually IP address or username
     */
    public static function recordLoginAttempt($identifier) {
        $key = 'login_attempts_' . $identifier;
        if (!isset($_SESSION[$key])) {
            $_SESSION[$key] = ['count' => 0, 'time' => time()];
        }
        $_SESSION[$key]['count']++;
    }
    
    /**
     * Reset login attempts for identifier
     * @param string $identifier Usually IP address or username
     */
    public static function resetLoginAttempts($identifier) {
        $key = 'login_attempts_' . $identifier;
        unset($_SESSION[$key]);
    }
}