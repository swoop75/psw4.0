<?php
/**
 * File: src/models/User.php
 * Path: C:\Users\laoan\Documents\GitHub\psw\psw4.0\src\models\User.php
 * Description: User model for PSW 4.0 - handles user data operations
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../utils/Security.php';
require_once __DIR__ . '/../utils/Logger.php';

class User {
    private $db;
    
    public function __construct() {
        $this->db = Database::getConnection('foundation');
    }
    
    /**
     * Authenticate user login
     * @param string $username Username or email
     * @param string $password Plain text password
     * @return array Result array with success status and user data
     */
    public function authenticate($username, $password) {
        try {
            $sql = "SELECT u.user_id, u.username, u.email, u.password_hash, u.role_id, r.role_name 
                    FROM users u 
                    JOIN roles r ON u.role_id = r.role_id 
                    WHERE u.username = :username OR u.email = :username";
            
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':username', $username);
            $stmt->execute();
            
            $user = $stmt->fetch();
            
            if ($user && Security::verifyPassword($password, $user['password_hash'])) {
                Logger::logAuth($username, true);
                return [
                    'success' => true,
                    'user' => [
                        'user_id' => $user['user_id'],
                        'username' => $user['username'],
                        'email' => $user['email'],
                        'role_id' => $user['role_id'],
                        'role_name' => $user['role_name']
                    ]
                ];
            } else {
                Logger::logAuth($username, false, 'Invalid credentials');
                return ['success' => false, 'message' => 'Invalid username or password'];
            }
        } catch (Exception $e) {
            Logger::error('Authentication error: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Authentication failed'];
        }
    }
    
    /**
     * Create new user
     * @param array $userData User data (username, email, password, role_id)
     * @return array Result array
     */
    public function create($userData) {
        try {
            // Validate required fields
            if (empty($userData['username']) || empty($userData['email']) || empty($userData['password'])) {
                return ['success' => false, 'message' => 'All fields are required'];
            }
            
            // Validate email
            if (!Security::validateEmail($userData['email'])) {
                return ['success' => false, 'message' => 'Invalid email address'];
            }
            
            // Validate password
            $passwordValidation = Security::validatePassword($userData['password']);
            if (!$passwordValidation['valid']) {
                return ['success' => false, 'message' => $passwordValidation['message']];
            }
            
            // Check if username or email already exists
            $existingUser = $this->findByUsernameOrEmail($userData['username'], $userData['email']);
            if ($existingUser) {
                return ['success' => false, 'message' => 'Username or email already exists'];
            }
            
            // Hash password
            $hashedPassword = Security::hashPassword($userData['password']);
            
            // Set default role if not specified
            $roleId = $userData['role_id'] ?? ROLE_USER;
            
            $sql = "INSERT INTO users (username, email, password_hash, role_id, created_at) 
                    VALUES (:username, :email, :password_hash, :role_id, NOW())";
            
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':username', $userData['username']);
            $stmt->bindParam(':email', $userData['email']);
            $stmt->bindParam(':password_hash', $hashedPassword);
            $stmt->bindParam(':role_id', $roleId);
            
            if ($stmt->execute()) {
                $userId = $this->db->lastInsertId();
                Logger::logUserAction('user_created', "New user created: {$userData['username']}");
                return ['success' => true, 'user_id' => $userId, 'message' => 'User created successfully'];
            } else {
                return ['success' => false, 'message' => 'Failed to create user'];
            }
        } catch (Exception $e) {
            Logger::error('User creation error: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to create user'];
        }
    }
    
    /**
     * Update user password
     * @param int $userId User ID
     * @param string $newPassword New password
     * @return array Result array
     */
    public function updatePassword($userId, $newPassword) {
        try {
            $passwordValidation = Security::validatePassword($newPassword);
            if (!$passwordValidation['valid']) {
                return ['success' => false, 'message' => $passwordValidation['message']];
            }
            
            $hashedPassword = Security::hashPassword($newPassword);
            
            $sql = "UPDATE users SET password_hash = :password_hash, updated_at = NOW() WHERE user_id = :user_id";
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':password_hash', $hashedPassword);
            $stmt->bindParam(':user_id', $userId);
            
            if ($stmt->execute()) {
                Logger::logUserAction('password_updated', "Password updated for user ID: {$userId}");
                return ['success' => true, 'message' => 'Password updated successfully'];
            } else {
                return ['success' => false, 'message' => 'Failed to update password'];
            }
        } catch (Exception $e) {
            Logger::error('Password update error: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to update password'];
        }
    }
    
    /**
     * Update user email
     * @param int $userId User ID
     * @param string $newEmail New email
     * @return array Result array
     */
    public function updateEmail($userId, $newEmail) {
        try {
            if (!Security::validateEmail($newEmail)) {
                return ['success' => false, 'message' => 'Invalid email address'];
            }
            
            // Check if email already exists for another user
            $sql = "SELECT user_id FROM users WHERE email = :email AND user_id != :user_id";
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':email', $newEmail);
            $stmt->bindParam(':user_id', $userId);
            $stmt->execute();
            
            if ($stmt->fetch()) {
                return ['success' => false, 'message' => 'Email address already in use'];
            }
            
            $sql = "UPDATE users SET email = :email, updated_at = NOW() WHERE user_id = :user_id";
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':email', $newEmail);
            $stmt->bindParam(':user_id', $userId);
            
            if ($stmt->execute()) {
                Logger::logUserAction('email_updated', "Email updated for user ID: {$userId}");
                return ['success' => true, 'message' => 'Email updated successfully'];
            } else {
                return ['success' => false, 'message' => 'Failed to update email'];
            }
        } catch (Exception $e) {
            Logger::error('Email update error: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to update email'];
        }
    }
    
    /**
     * Get user by ID
     * @param int $userId User ID
     * @return array|false User data or false if not found
     */
    public function findById($userId) {
        try {
            $sql = "SELECT u.user_id, u.username, u.email, u.role_id, r.role_name, u.weekly_report, u.created_at 
                    FROM users u 
                    JOIN roles r ON u.role_id = r.role_id 
                    WHERE u.user_id = :user_id";
            
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':user_id', $userId);
            $stmt->execute();
            
            return $stmt->fetch();
        } catch (Exception $e) {
            Logger::error('Find user by ID error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Find user by username or email
     * @param string $username Username
     * @param string $email Email
     * @return array|false User data or false if not found
     */
    private function findByUsernameOrEmail($username, $email) {
        try {
            $sql = "SELECT user_id, username, email FROM users WHERE username = :username OR email = :email";
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':username', $username);
            $stmt->bindParam(':email', $email);
            $stmt->execute();
            
            return $stmt->fetch();
        } catch (Exception $e) {
            Logger::error('Find user by username/email error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get all users (admin function)
     * @return array Array of users
     */
    public function getAllUsers() {
        try {
            $sql = "SELECT u.user_id, u.username, u.email, u.role_id, r.role_name, u.weekly_report, u.created_at 
                    FROM users u 
                    JOIN roles r ON u.role_id = r.role_id 
                    ORDER BY u.created_at DESC";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            
            return $stmt->fetchAll();
        } catch (Exception $e) {
            Logger::error('Get all users error: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Delete user (admin function)
     * @param int $userId User ID to delete
     * @return array Result array
     */
    public function delete($userId) {
        try {
            // Prevent deletion of the last administrator
            $sql = "SELECT COUNT(*) as admin_count FROM users WHERE role_id = :admin_role";
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':admin_role', $adminRole = ROLE_ADMINISTRATOR);
            $stmt->execute();
            $adminCount = $stmt->fetch()['admin_count'];
            
            // Check if this user is an admin
            $user = $this->findById($userId);
            if ($user['role_id'] == ROLE_ADMINISTRATOR && $adminCount <= 1) {
                return ['success' => false, 'message' => 'Cannot delete the last administrator'];
            }
            
            $sql = "DELETE FROM users WHERE user_id = :user_id";
            $stmt = $this->db->prepare($sql);
            $stmt->bindParam(':user_id', $userId);
            
            if ($stmt->execute()) {
                Logger::logUserAction('user_deleted', "User deleted: ID {$userId}");
                return ['success' => true, 'message' => 'User deleted successfully'];
            } else {
                return ['success' => false, 'message' => 'Failed to delete user'];
            }
        } catch (Exception $e) {
            Logger::error('Delete user error: ' . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to delete user'];
        }
    }
    }
    
    /**
     * Get user by ID (alias for consistency)
     * @param int $userId User ID
     * @return array < /dev/null | false User data or false if not found
     */
    public function getUserById($userId) {
        return $this->findById($userId);
    }
}
