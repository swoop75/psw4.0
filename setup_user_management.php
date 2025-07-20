<?php
/**
 * File: setup_user_management.php
 * Description: Setup script for user management database tables
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/src/utils/DatabaseSetup.php';

echo "Setting up User Management database tables...\n";

if (DatabaseSetup::runSetup()) {
    echo "✅ User Management setup completed successfully!\n";
    echo "\nCreated tables:\n";
    echo "- user_preferences\n";
    echo "- user_stats\n";
    echo "- user_activity_log\n";
    echo "\nAdded columns to users table:\n";
    echo "- full_name\n";
    echo "- last_login\n";
    echo "- is_active\n";
    echo "\nInitialized default data for existing users.\n";
} else {
    echo "❌ User Management setup failed. Check error logs.\n";
}