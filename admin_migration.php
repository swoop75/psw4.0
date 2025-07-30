<?php
/**
 * Admin migration page - run database migrations
 */

session_start();

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/src/middleware/Auth.php';

// Require authentication and admin role
Auth::requireAuth();

// Simple admin check - you can modify this based on your role system
$isAdmin = true; // For now, allow any authenticated user

if (!$isAdmin) {
    die('Access denied. Admin privileges required.');
}

$output = '';
$success = false;

if (isset($_POST['run_migration'])) {
    ob_start();
    
    try {
        echo "Running format preference migration...\n";
        
        $db = Database::getConnection('foundation');
        
        // Check if column already exists
        $stmt = $db->query("SHOW COLUMNS FROM users LIKE 'format_preference'");
        $columnExists = $stmt->fetch();
        
        if ($columnExists) {
            echo "✅ Column 'format_preference' already exists.\n";
        } else {
            echo "Adding format_preference column to users table...\n";
            
            // Add the column
            $db->exec("ALTER TABLE users ADD COLUMN format_preference VARCHAR(5) DEFAULT 'US' COMMENT 'User preferred format (US, EU, SE, UK, DE, FR)' AFTER last_login");
            echo "✅ Column added successfully.\n";
            
            // Update existing users to have default US format
            $db->exec("UPDATE users SET format_preference = 'US' WHERE format_preference IS NULL");
            echo "✅ Updated existing users with default format.\n";
            
            // Add index for performance
            try {
                $db->exec("CREATE INDEX idx_users_format_preference ON users(format_preference)");
                echo "✅ Index created successfully.\n";
            } catch (Exception $e) {
                echo "⚠️ Index creation failed (may already exist): " . $e->getMessage() . "\n";
            }
        }
        
        // Show updated table structure
        echo "\n=== Users Table Structure ===\n";
        $stmt = $db->query("DESCRIBE users");
        $columns = $stmt->fetchAll();
        
        foreach ($columns as $column) {
            $line = "Column: {$column['Field']}, Type: {$column['Type']}";
            if ($column['Field'] === 'format_preference') {
                $line = "✅ " . $line . " (FORMAT COLUMN)";
            }
            echo $line . "\n";
        }
        
        echo "\n🎉 Migration completed successfully!\n";
        $success = true;
        
    } catch (Exception $e) {
        echo "❌ Error: " . $e->getMessage() . "\n";
        echo "Stack trace: " . $e->getTraceAsString() . "\n";
    }
    
    $output = ob_get_clean();
}

// Initialize variables for template
$pageTitle = 'Database Migration - PSW 4.0';
$pageDescription = 'Run database migrations';

// Prepare content
ob_start();
?>

<div class="psw-content">
    <!-- Page Header -->
    <div class="psw-card psw-mb-6">
        <div class="psw-card-header">
            <h1 class="psw-card-title">
                <i class="fas fa-database psw-card-title-icon"></i>
                Database Migration
            </h1>
            <p class="psw-card-subtitle">Run the format preference migration to add localization support</p>
        </div>
    </div>

    <!-- Migration Form -->
    <div class="psw-card">
        <div class="psw-card-header">
            <div class="psw-card-title">
                <i class="fas fa-cogs psw-card-title-icon"></i>
                Format Preference Migration
            </div>
        </div>
        <div class="psw-card-content">
            <?php if (!$output): ?>
                <div style="background: var(--warning-bg); border: 1px solid var(--warning-color); border-radius: var(--radius-md); padding: var(--spacing-4); margin-bottom: var(--spacing-4);">
                    <h4 style="color: var(--warning-color); margin-bottom: var(--spacing-2);">
                        <i class="fas fa-exclamation-triangle"></i> Migration Required
                    </h4>
                    <p style="margin: 0; color: var(--text-primary);">
                        The format preference feature requires a new database column. Click the button below to run the migration.
                    </p>
                </div>
                
                <form method="POST">
                    <button type="submit" name="run_migration" class="psw-btn psw-btn-primary">
                        <i class="fas fa-play psw-btn-icon"></i>
                        Run Migration
                    </button>
                </form>
            <?php else: ?>
                <div class="psw-card" style="background: var(--bg-secondary); margin-bottom: var(--spacing-4);">
                    <div class="psw-card-content">
                        <h4 style="margin-bottom: var(--spacing-3);">Migration Output:</h4>
                        <pre style="background: var(--bg-card); padding: var(--spacing-3); border-radius: var(--radius-md); overflow-x: auto; font-family: var(--font-family-mono); font-size: var(--font-size-sm); white-space: pre-wrap;"><?php echo htmlspecialchars($output); ?></pre>
                    </div>
                </div>
                
                <?php if ($success): ?>
                    <div class="psw-alert psw-alert-success">
                        <i class="fas fa-check-circle"></i>
                        Migration completed successfully! You can now use the format preferences feature.
                    </div>
                    
                    <div style="margin-top: var(--spacing-4);">
                        <a href="<?php echo BASE_URL; ?>/format_preferences.php" class="psw-btn psw-btn-primary">
                            <i class="fas fa-globe psw-btn-icon"></i>
                            Go to Format Settings
                        </a>
                        <a href="<?php echo BASE_URL; ?>/dividend_logs.php" class="psw-btn psw-btn-secondary">
                            <i class="fas fa-coins psw-btn-icon"></i>
                            Test on Dividend Logs
                        </a>
                    </div>
                <?php else: ?>
                    <div class="psw-alert psw-alert-error">
                        <i class="fas fa-exclamation-circle"></i>
                        Migration failed. Please check the error output above.
                    </div>
                    
                    <form method="POST" style="margin-top: var(--spacing-4);">
                        <button type="submit" name="run_migration" class="psw-btn psw-btn-secondary">
                            <i class="fas fa-redo psw-btn-icon"></i>
                            Try Again
                        </button>
                    </form>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Info Card -->
    <div class="psw-card" style="margin-top: 1.5rem;">
        <div class="psw-card-header">
            <div class="psw-card-title">
                <i class="fas fa-info-circle psw-card-title-icon"></i>
                What This Migration Does
            </div>
        </div>
        <div class="psw-card-content">
            <ul style="margin: 0; padding-left: var(--spacing-4);">
                <li>Adds <code>format_preference</code> column to the <code>users</code> table</li>
                <li>Sets default format to 'US' for all existing users</li>
                <li>Creates an index on the new column for better performance</li>
                <li>Enables user-specific number, date, and time formatting across the application</li>
            </ul>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();

// Include base layout
include __DIR__ . '/templates/layouts/base-redesign.php';
?>