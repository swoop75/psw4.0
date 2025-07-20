<?php
/**
 * File: src/utils/DatabaseSetup.php
 * Description: Database setup utility for PSW 4.0 user management tables
 */

require_once __DIR__ . '/../../config/database.php';

class DatabaseSetup {
    
    /**
     * Create user management tables if they don't exist
     */
    public static function createUserManagementTables() {
        try {
            $db = Database::getConnection('foundation');
            
            // Create user_preferences table
            $sql = "CREATE TABLE IF NOT EXISTS user_preferences (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                preference_key VARCHAR(50) NOT NULL,
                preference_value TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY unique_user_preference (user_id, preference_key),
                FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
            )";
            $db->exec($sql);
            
            // Create user_stats table
            $sql = "CREATE TABLE IF NOT EXISTS user_stats (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL UNIQUE,
                login_count INT DEFAULT 0,
                last_activity TIMESTAMP NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
            )";
            $db->exec($sql);
            
            // Create user_activity_log table
            $sql = "CREATE TABLE IF NOT EXISTS user_activity_log (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                action_type VARCHAR(50) NOT NULL,
                description TEXT,
                ip_address VARCHAR(45),
                user_agent TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_user_activity (user_id, created_at),
                FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE
            )";
            $db->exec($sql);
            
            // Add missing columns to users table if they don't exist
            try {
                $db->exec("ALTER TABLE users ADD COLUMN full_name VARCHAR(100) NULL AFTER email");
            } catch (Exception $e) {
                // Column probably already exists
            }
            
            try {
                $db->exec("ALTER TABLE users ADD COLUMN last_login TIMESTAMP NULL AFTER full_name");
            } catch (Exception $e) {
                // Column probably already exists
            }
            
            // Note: users table already has 'active' column, no need to add 'is_active'
            
            return true;
            
        } catch (Exception $e) {
            error_log('Database setup error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Initialize user stats for existing users
     */
    public static function initializeUserStats() {
        try {
            $db = Database::getConnection('foundation');
            
            $sql = "INSERT IGNORE INTO user_stats (user_id, login_count, last_activity) 
                    SELECT user_id, 0, NOW() FROM users";
            $db->exec($sql);
            
            return true;
            
        } catch (Exception $e) {
            error_log('User stats initialization error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Set up default preferences for existing users
     */
    public static function initializeDefaultPreferences() {
        try {
            $db = Database::getConnection('foundation');
            
            // Get all user IDs
            $stmt = $db->query("SELECT user_id FROM users");
            $userIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            $defaults = [
                'theme' => 'light',
                'language' => 'en',
                'currency_display' => 'SEK',
                'date_format' => 'Y-m-d',
                'decimal_places' => '2',
                'notifications_email' => '1',
                'dashboard_refresh' => '300',
                'table_page_size' => '50'
            ];
            
            foreach ($userIds as $userId) {
                foreach ($defaults as $key => $value) {
                    $sql = "INSERT IGNORE INTO user_preferences (user_id, preference_key, preference_value) 
                            VALUES (:user_id, :key, :value)";
                    $stmt = $db->prepare($sql);
                    $stmt->execute([
                        ':user_id' => $userId,
                        ':key' => $key,
                        ':value' => $value
                    ]);
                }
            }
            
            return true;
            
        } catch (Exception $e) {
            error_log('Default preferences initialization error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Run complete setup
     */
    public static function runSetup() {
        $success = true;
        
        if (!self::createUserManagementTables()) {
            $success = false;
        }
        
        if (!self::initializeUserStats()) {
            $success = false;
        }
        
        if (!self::initializeDefaultPreferences()) {
            $success = false;
        }
        
        return $success;
    }
}