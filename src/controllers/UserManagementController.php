<?php
/**
 * File: src/controllers/UserManagementController.php
 * Path: C:\Users\laoan\Documents\GitHub\psw\psw4.0\src\controllers\UserManagementController.php
 * Description: User management controller for PSW 4.0 - handles user profile and settings
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../middleware/Auth.php';
require_once __DIR__ . '/../utils/Logger.php';
require_once __DIR__ . '/../utils/Security.php';

class UserManagementController {
    private $userModel;
    private $foundationDb;
    
    public function __construct() {
        $this->userModel = new User();
        $this->foundationDb = Database::getConnection('foundation');
    }
    
    /**
     * Get user by ID - wrapper method
     * @param int $userId User ID
     * @return array|false User data or false if not found
     */
    public function getUserById($userId) {
        return $this->userModel->findById($userId);
    }
    
    /**
     * Update user data
     * @param int $userId User ID
     * @param array $data Data to update
     * @return bool Success status
     */
    public function updateUser($userId, $data) {
        try {
            $updateFields = [];
            $params = [':user_id' => $userId];
            
            if (isset($data['email'])) {
                $updateFields[] = 'email = :email';
                $params[':email'] = $data['email'];
            }
            
            if (isset($data['full_name'])) {
                $updateFields[] = 'full_name = :full_name';
                $params[':full_name'] = $data['full_name'];
            }
            
            if (isset($data['password_hash'])) {
                $updateFields[] = 'password_hash = :password_hash';
                $params[':password_hash'] = $data['password_hash'];
            }
            
            if (empty($updateFields)) {
                return false;
            }
            
            $sql = "UPDATE users SET " . implode(', ', $updateFields) . ", updated_at = NOW() WHERE user_id = :user_id";
            $stmt = $this->foundationDb->prepare($sql);
            
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            
            return $stmt->execute();
            
        } catch (Exception $e) {
            Logger::error('Update user error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get user profile data
     * @return array User profile information
     */
    public function getUserProfile() {
        try {
            $userId = Auth::getUserId();
            if (!$userId) {
                throw new Exception('User not logged in');
            }
            
            $user = $this->getUserById($userId);
            if (!$user) {
                throw new Exception('User not found');
            }
            
            // Get additional profile data
            $profileData = $this->getProfileExtendedData($userId);
            
            return [
                'user' => [
                    'id' => $user['user_id'],
                    'user_id' => $user['user_id'],
                    'username' => $user['username'],
                    'email' => $user['email'],
                    'full_name' => $user['full_name'] ?? '',
                    'role_id' => $user['role_id'],
                    'role_name' => $user['role_name'] ?? ($user['role_id'] == 1 ? 'Administrator' : 'User'),
                    'created_at' => $user['created_at'],
                    'last_login' => $user['last_login'] ?? null,
                    'is_active' => $user['active'] ?? 1,
                    'password_hash' => $user['password_hash'] ?? ''
                ],
                'profile_stats' => $profileData['stats'],
                'preferences' => $profileData['preferences'],
                'activity_log' => $profileData['activity_log']
            ];
            
        } catch (Exception $e) {
            Logger::error('Get user profile error: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Update user profile information
     * @param array $data Profile update data
     * @return bool Success status
     */
    public function updateProfile($data) {
        try {
            $userId = Auth::getUserId();
            if (!$userId) {
                throw new Exception('User not logged in');
            }
            
            // Validate and sanitize input
            $updateData = [];
            
            if (isset($data['full_name'])) {
                $fullName = Security::sanitizeInput($data['full_name']);
                if (strlen($fullName) > 100) {
                    throw new Exception('Full name too long');
                }
                $updateData['full_name'] = $fullName;
            }
            
            if (isset($data['email'])) {
                $email = Security::sanitizeInput($data['email']);
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    throw new Exception('Invalid email format');
                }
                
                // Check if email is already taken by another user
                if ($this->isEmailTaken($email, $userId)) {
                    throw new Exception('Email address is already in use');
                }
                
                $updateData['email'] = $email;
            }
            
            if (empty($updateData)) {
                throw new Exception('No valid data to update');
            }
            
            // Update user profile
            $success = $this->updateUser($userId, $updateData);
            
            if ($success) {
                Logger::logUserAction('profile_updated', 'User profile updated', array_keys($updateData));
                return true;
            }
            
            return false;
            
        } catch (Exception $e) {
            Logger::error('Update profile error: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Change user password
     * @param array $data Password change data
     * @return bool Success status
     */
    public function changePassword($data) {
        try {
            $userId = Auth::getUserId();
            if (!$userId) {
                throw new Exception('User not logged in');
            }
            
            $currentPassword = $data['current_password'] ?? '';
            $newPassword = $data['new_password'] ?? '';
            $confirmPassword = $data['confirm_password'] ?? '';
            
            // Get current password hash from database
            $stmt = $this->foundationDb->prepare("SELECT password_hash FROM users WHERE user_id = :user_id");
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $stmt->execute();
            $userRecord = $stmt->fetch();
            
            if (!$userRecord || !Security::verifyPassword($currentPassword, $userRecord['password_hash'])) {
                throw new Exception('Current password is incorrect');
            }
            
            // Validate new password
            if (strlen($newPassword) < 8) {
                throw new Exception('New password must be at least 8 characters long');
            }
            
            if ($newPassword !== $confirmPassword) {
                throw new Exception('New passwords do not match');
            }
            
            // Check password strength
            if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)/', $newPassword)) {
                throw new Exception('Password must contain at least one lowercase letter, one uppercase letter, and one number');
            }
            
            // Update password
            $passwordHash = Security::hashPassword($newPassword);
            $success = $this->updateUser($userId, ['password_hash' => $passwordHash]);
            
            if ($success) {
                Logger::logUserAction('password_changed', 'User password changed');
                return true;
            }
            
            return false;
            
        } catch (Exception $e) {
            Logger::error('Change password error: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Update user preferences
     * @param array $preferences Preferences data
     * @return bool Success status
     */
    public function updatePreferences($preferences) {
        try {
            $userId = Auth::getUserId();
            if (!$userId) {
                throw new Exception('User not logged in');
            }
            
            // Validate preferences
            $validPreferences = [
                'theme' => ['light', 'dark', 'auto'],
                'language' => ['en', 'sv'],
                'currency_display' => ['SEK', 'USD', 'EUR'],
                'date_format' => ['Y-m-d', 'd/m/Y', 'm/d/Y'],
                'decimal_places' => [0, 1, 2, 3, 4],
                'notifications_email' => [true, false],
                'dashboard_refresh' => [30, 60, 300, 600, 0], // seconds, 0 = manual
                'table_page_size' => [25, 50, 100, 200]
            ];
            
            $cleanPreferences = [];
            foreach ($preferences as $key => $value) {
                if (isset($validPreferences[$key])) {
                    if (in_array($value, $validPreferences[$key], true)) {
                        $cleanPreferences[$key] = $value;
                    }
                }
            }
            
            if (empty($cleanPreferences)) {
                throw new Exception('No valid preferences to update');
            }
            
            // Save preferences
            $success = $this->saveUserPreferences($userId, $cleanPreferences);
            
            if ($success) {
                Logger::logUserAction('preferences_updated', 'User preferences updated', array_keys($cleanPreferences));
                return true;
            }
            
            return false;
            
        } catch (Exception $e) {
            Logger::error('Update preferences error: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Get extended profile data
     * @param int $userId User ID
     * @return array Extended profile data
     */
    private function getProfileExtendedData($userId) {
        try {
            // Get basic stats
            $stats = [
                'login_count' => $this->getLoginCount($userId),
                'last_activity' => $this->getLastActivity($userId),
                'account_age_days' => $this->getAccountAgeDays($userId),
                'dividend_payments_count' => $this->getDividendPaymentsCount(),
                'total_dividend_amount' => $this->getTotalDividendAmount()
            ];
            
            // Get user preferences
            $preferences = $this->getUserPreferences($userId);
            
            // Get recent activity log
            $activityLog = $this->getRecentActivityLog($userId);
            
            return [
                'stats' => $stats,
                'preferences' => $preferences,
                'activity_log' => $activityLog
            ];
            
        } catch (Exception $e) {
            Logger::error('Get profile extended data error: ' . $e->getMessage());
            return [
                'stats' => [],
                'preferences' => [],
                'activity_log' => []
            ];
        }
    }
    
    /**
     * Check if email is already taken by another user
     * @param string $email Email to check
     * @param int $excludeUserId User ID to exclude from check
     * @return bool True if email is taken
     */
    private function isEmailTaken($email, $excludeUserId) {
        try {
            $sql = "SELECT user_id FROM users WHERE email = :email AND user_id != :user_id";
            $stmt = $this->foundationDb->prepare($sql);
            $stmt->bindValue(':email', $email);
            $stmt->bindValue(':user_id', $excludeUserId, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->rowCount() > 0;
            
        } catch (Exception $e) {
            Logger::error('Check email taken error: ' . $e->getMessage());
            return true; // Err on the side of caution
        }
    }
    
    /**
     * Get user login count
     * @param int $userId User ID
     * @return int Login count
     */
    private function getLoginCount($userId) {
        try {
            // First try to get from user_stats table if it exists
            $sql = "SELECT login_count FROM user_stats WHERE user_id = :user_id";
            $stmt = $this->foundationDb->prepare($sql);
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $stmt->execute();
            
            $result = $stmt->fetch();
            if ($result) {
                return (int) ($result['login_count'] ?? 0);
            }
            
            // Fallback: Estimate based on when user was created and last login
            // This is a simple approximation - could be improved with actual login tracking
            $sql = "SELECT DATEDIFF(COALESCE(last_login, NOW()), created_at) as days_active FROM users WHERE user_id = :user_id";
            $stmt = $this->foundationDb->prepare($sql);
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $stmt->execute();
            
            $result = $stmt->fetch();
            $daysActive = (int) ($result['days_active'] ?? 0);
            
            // Rough estimate: if user has been active for more than 7 days, estimate ~1 login per 3 days
            return $daysActive > 7 ? max(1, intval($daysActive / 3)) : 1;
            
        } catch (Exception $e) {
            // If user_stats table doesn't exist, return a default value of 1 (at least one login to be here)
            return 1;
        }
    }
    
    /**
     * Get last activity timestamp
     * @param int $userId User ID
     * @return string|null Last activity timestamp
     */
    private function getLastActivity($userId) {
        try {
            $sql = "SELECT last_activity FROM user_stats WHERE user_id = :user_id";
            $stmt = $this->foundationDb->prepare($sql);
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $stmt->execute();
            
            $result = $stmt->fetch();
            return $result['last_activity'] ?? null;
            
        } catch (Exception $e) {
            return null;
        }
    }
    
    /**
     * Get account age in days
     * @param int $userId User ID
     * @return int Account age in days
     */
    private function getAccountAgeDays($userId) {
        try {
            $sql = "SELECT DATEDIFF(NOW(), created_at) as age_days FROM users WHERE user_id = :user_id";
            $stmt = $this->foundationDb->prepare($sql);
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $stmt->execute();
            
            $result = $stmt->fetch();
            return (int) ($result['age_days'] ?? 0);
            
        } catch (Exception $e) {
            return 0;
        }
    }
    
    /**
     * Get dividend payments count from portfolio database
     * @return int Dividend payments count
     */
    private function getDividendPaymentsCount() {
        try {
            $portfolioDb = Database::getConnection('portfolio');
            $sql = "SELECT COUNT(*) as count FROM log_dividends WHERE dividend_amount_sek > 0";
            $stmt = $portfolioDb->prepare($sql);
            $stmt->execute();
            
            $result = $stmt->fetch();
            return (int) ($result['count'] ?? 0);
            
        } catch (Exception $e) {
            return 0;
        }
    }
    
    /**
     * Get total dividend amount from portfolio database
     * @return float Total dividend amount
     */
    private function getTotalDividendAmount() {
        try {
            $portfolioDb = Database::getConnection('portfolio');
            $sql = "SELECT SUM(dividend_amount_sek) as total FROM log_dividends WHERE dividend_amount_sek > 0";
            $stmt = $portfolioDb->prepare($sql);
            $stmt->execute();
            
            $result = $stmt->fetch();
            return (float) ($result['total'] ?? 0);
            
        } catch (Exception $e) {
            return 0.0;
        }
    }
    
    /**
     * Get user preferences
     * @param int $userId User ID
     * @return array User preferences
     */
    private function getUserPreferences($userId) {
        try {
            $sql = "SELECT preference_key, preference_value FROM user_preferences WHERE user_id = :user_id";
            $stmt = $this->foundationDb->prepare($sql);
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $stmt->execute();
            
            $preferences = [];
            while ($row = $stmt->fetch()) {
                $preferences[$row['preference_key']] = json_decode($row['preference_value'], true) ?? $row['preference_value'];
            }
            
            // Set defaults for missing preferences
            $defaults = [
                'theme' => 'light',
                'language' => 'en',
                'currency_display' => 'SEK',
                'date_format' => 'Y-m-d',
                'decimal_places' => 2,
                'notifications_email' => true,
                'dashboard_refresh' => 300,
                'table_page_size' => 50
            ];
            
            return array_merge($defaults, $preferences);
            
        } catch (Exception $e) {
            Logger::error('Get user preferences error: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Save user preferences
     * @param int $userId User ID
     * @param array $preferences Preferences to save
     * @return bool Success status
     */
    private function saveUserPreferences($userId, $preferences) {
        try {
            $this->foundationDb->beginTransaction();
            
            foreach ($preferences as $key => $value) {
                $sql = "INSERT INTO user_preferences (user_id, preference_key, preference_value) 
                        VALUES (:user_id, :key, :value) 
                        ON DUPLICATE KEY UPDATE preference_value = :value";
                
                $stmt = $this->foundationDb->prepare($sql);
                $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
                $stmt->bindValue(':key', $key);
                $stmt->bindValue(':value', is_array($value) ? json_encode($value) : $value);
                $stmt->execute();
            }
            
            $this->foundationDb->commit();
            return true;
            
        } catch (Exception $e) {
            $this->foundationDb->rollBack();
            Logger::error('Save user preferences error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get recent activity log
     * @param int $userId User ID
     * @return array Recent activity log entries
     */
    private function getRecentActivityLog($userId) {
        try {
            // First try to get from database table if it exists
            $sql = "SELECT action_type, description, created_at 
                    FROM user_activity_log 
                    WHERE user_id = :user_id 
                    ORDER BY created_at DESC 
                    LIMIT 20";
            
            $stmt = $this->foundationDb->prepare($sql);
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $stmt->execute();
            
            $dbResults = $stmt->fetchAll();
            if (!empty($dbResults)) {
                return $dbResults;
            }
            
            // Fallback: Read from log files
            return $this->getActivityFromLogFiles($userId);
            
        } catch (Exception $e) {
            // If database table doesn't exist, fallback to log files
            return $this->getActivityFromLogFiles($userId);
        }
    }
    
    /**
     * Get user activity from log files
     * @param int $userId User ID
     * @return array Activity log entries
     */
    private function getActivityFromLogFiles($userId) {
        try {
            $activities = [];
            $today = date('Y-m-d');
            
            // Check today's log and previous days (up to 7 days)
            for ($i = 0; $i < 7; $i++) {
                $date = date('Y-m-d', strtotime("-{$i} days"));
                $logFile = LOG_PATH . "/psw_{$date}.log";
                
                if (file_exists($logFile)) {
                    $activities = array_merge($activities, $this->parseLogFile($logFile, $userId));
                }
            }
            
            // Sort by timestamp descending and limit to 20
            usort($activities, function($a, $b) {
                return strtotime($b['created_at']) - strtotime($a['created_at']);
            });
            
            return array_slice($activities, 0, 20);
            
        } catch (Exception $e) {
            Logger::error('Get activity from log files error: ' . $e->getMessage());
            return $this->getDefaultActivityEntries($userId);
        }
    }
    
    /**
     * Parse log file for user activities
     * @param string $logFile Path to log file
     * @param int $userId User ID
     * @return array Activity entries
     */
    private function parseLogFile($logFile, $userId) {
        $activities = [];
        
        try {
            $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            
            foreach ($lines as $line) {
                $logEntry = json_decode($line, true);
                
                if ($logEntry && 
                    isset($logEntry['user_id']) && 
                    $logEntry['user_id'] == $userId &&
                    isset($logEntry['context']['action'])) {
                    
                    $activities[] = [
                        'action_type' => $logEntry['context']['action'],
                        'description' => $logEntry['context']['details'] ?? $logEntry['message'],
                        'created_at' => $logEntry['timestamp']
                    ];
                }
            }
        } catch (Exception $e) {
            // Continue silently if file can't be read
        }
        
        return $activities;
    }
    
    /**
     * Get default activity entries when no logs are available
     * @param int $userId User ID
     * @return array Default activity entries
     */
    private function getDefaultActivityEntries($userId) {
        // Get user creation date and last login to show some basic activity
        try {
            $sql = "SELECT created_at, last_login FROM users WHERE user_id = :user_id";
            $stmt = $this->foundationDb->prepare($sql);
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $stmt->execute();
            
            $user = $stmt->fetch();
            $activities = [];
            
            if ($user) {
                if ($user['last_login']) {
                    $activities[] = [
                        'action_type' => 'login',
                        'description' => 'Last login to the system',
                        'created_at' => $user['last_login']
                    ];
                }
                
                $activities[] = [
                    'action_type' => 'account_created',
                    'description' => 'Account created',
                    'created_at' => $user['created_at']
                ];
            }
            
            return $activities;
            
        } catch (Exception $e) {
            return [];
        }
    }
    
    /**
     * Generate random password and update user
     * @return array Result with success status and new password
     */
    public function generateRandomPassword() {
        try {
            $userId = Auth::getUserId();
            if (!$userId) {
                throw new Exception('User not logged in');
            }
            
            // Generate secure random password
            $newPassword = $this->generateSecurePassword();
            
            // Update password
            $passwordHash = Security::hashPassword($newPassword);
            $success = $this->updateUser($userId, ['password_hash' => $passwordHash]);
            
            if ($success) {
                Logger::logUserAction('password_generated', 'Random password generated for user');
                return [
                    'success' => true,
                    'password' => $newPassword,
                    'message' => 'Password generated successfully'
                ];
            }
            
            return ['success' => false, 'message' => 'Failed to update password'];
            
        } catch (Exception $e) {
            Logger::error('Generate password error: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Generate a secure random password
     * @return string Secure random password
     */
    private function generateSecurePassword() {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
        $password = '';
        $length = 12;
        
        // Ensure at least one of each type
        $password .= substr('abcdefghijklmnopqrstuvwxyz', rand(0, 25), 1); // lowercase
        $password .= substr('ABCDEFGHIJKLMNOPQRSTUVWXYZ', rand(0, 25), 1); // uppercase
        $password .= substr('0123456789', rand(0, 9), 1); // number
        $password .= substr('!@#$%^&*', rand(0, 7), 1); // special
        
        // Fill the rest randomly
        for ($i = 4; $i < $length; $i++) {
            $password .= $chars[rand(0, strlen($chars) - 1)];
        }
        
        // Shuffle the password
        return str_shuffle($password);
    }
    
    /**
     * Get admin data including all users and statistics
     * @return array Admin data
     */
    public function getAdminData() {
        try {
            // Get all users with their roles
            $sql = "SELECT u.user_id, u.username, u.email, u.full_name, u.role_id, r.role_name, 
                          u.active, u.created_at, u.last_login, u.updated_at
                    FROM users u 
                    LEFT JOIN roles r ON u.role_id = r.role_id 
                    ORDER BY u.created_at DESC";
            
            $stmt = $this->foundationDb->prepare($sql);
            $stmt->execute();
            $users = $stmt->fetchAll();
            
            // Calculate statistics
            $totalUsers = count($users);
            $activeUsers = count(array_filter($users, function($user) {
                return $user['active'] == 1;
            }));
            
            // Get most popular page (excluding dashboard)
            $mostPopularPage = $this->getMostPopularPage();
            
            return [
                'users' => $users,
                'stats' => [
                    'total_users' => $totalUsers,
                    'active_users' => $activeUsers,
                    'most_popular_page' => $mostPopularPage
                ]
            ];
            
        } catch (Exception $e) {
            Logger::error('Get admin data error: ' . $e->getMessage());
            return [
                'users' => [],
                'stats' => [
                    'total_users' => 0,
                    'active_users' => 0,
                    'most_popular_page' => 'N/A'
                ]
            ];
        }
    }
    
    /**
     * Get most popular page from access logs (excluding dashboard)
     * @return string Most popular page
     */
    private function getMostPopularPage() {
        try {
            // This is a placeholder - you might need to implement page tracking
            // For now, return a default value
            return 'New Companies';
        } catch (Exception $e) {
            Logger::error('Get most popular page error: ' . $e->getMessage());
            return 'N/A';
        }
    }
    
    /**
     * Edit user data (Admin only)
     * @param array $data User data to update
     * @return array Result with success status and message
     */
    public function editUser($data) {
        try {
            $userId = (int) ($data['user_id'] ?? 0);
            $fullName = Security::sanitizeInput($data['full_name'] ?? '');
            $email = Security::sanitizeInput($data['email'] ?? '');
            $roleId = (int) ($data['role_id'] ?? 2);
            $active = isset($data['active']) ? 1 : 0;
            
            if (!$userId) {
                throw new Exception('Invalid user ID');
            }
            
            // Validate email
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception('Invalid email format');
            }
            
            // Check if email is already taken by another user
            if ($this->isEmailTaken($email, $userId)) {
                throw new Exception('Email address is already in use by another user');
            }
            
            // Validate role ID
            if (!in_array($roleId, [1, 2])) {
                throw new Exception('Invalid role selected');
            }
            
            // Get current user to check if we're trying to deactivate ourselves
            $currentUserId = Auth::getUserId();
            if ($userId == $currentUserId && !$active) {
                throw new Exception('You cannot deactivate your own account');
            }
            
            // Update user data
            $updateData = [
                'full_name' => $fullName,
                'email' => $email,
                'role_id' => $roleId,
                'active' => $active
            ];
            
            $sql = "UPDATE users SET 
                    full_name = :full_name, 
                    email = :email, 
                    role_id = :role_id, 
                    active = :active, 
                    updated_at = NOW() 
                    WHERE user_id = :user_id";
                    
            $stmt = $this->foundationDb->prepare($sql);
            $stmt->bindValue(':full_name', $fullName);
            $stmt->bindValue(':email', $email);
            $stmt->bindValue(':role_id', $roleId, PDO::PARAM_INT);
            $stmt->bindValue(':active', $active, PDO::PARAM_INT);
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            
            $success = $stmt->execute();
            
            if ($success) {
                // Get username for logging
                $userStmt = $this->foundationDb->prepare("SELECT username FROM users WHERE user_id = :user_id");
                $userStmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
                $userStmt->execute();
                $userInfo = $userStmt->fetch();
                $username = $userInfo['username'] ?? 'Unknown';
                
                Logger::logUserAction('user_edited', "User {$username} was edited by admin");
                
                return [
                    'success' => true,
                    'message' => 'User updated successfully'
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Failed to update user'
                ];
            }
            
        } catch (Exception $e) {
            Logger::error('Edit user error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Toggle user active/inactive status (Admin only)
     * @param int $userId User ID
     * @param int $active Active status (1 or 0)
     * @return array Result with success status and message
     */
    public function toggleUserStatus($userId, $active) {
        try {
            $userId = (int) $userId;
            $active = (int) $active;
            
            if (!$userId) {
                throw new Exception('Invalid user ID');
            }
            
            // Get current user to check if we're trying to deactivate ourselves
            $currentUserId = Auth::getUserId();
            if ($userId == $currentUserId && !$active) {
                throw new Exception('You cannot deactivate your own account');
            }
            
            // Update user status
            $sql = "UPDATE users SET active = :active, updated_at = NOW() WHERE user_id = :user_id";
            $stmt = $this->foundationDb->prepare($sql);
            $stmt->bindValue(':active', $active, PDO::PARAM_INT);
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            
            $success = $stmt->execute();
            
            if ($success) {
                // Get username for logging
                $userStmt = $this->foundationDb->prepare("SELECT username FROM users WHERE user_id = :user_id");
                $userStmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
                $userStmt->execute();
                $userInfo = $userStmt->fetch();
                $username = $userInfo['username'] ?? 'Unknown';
                
                $action = $active ? 'activated' : 'deactivated';
                Logger::logUserAction('user_status_changed', "User {$username} was {$action} by admin");
                
                return [
                    'success' => true,
                    'message' => "User {$action} successfully"
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Failed to update user status'
                ];
            }
            
        } catch (Exception $e) {
            Logger::error('Toggle user status error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
}