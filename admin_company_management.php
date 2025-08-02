<?php
/**
 * File: admin_company_management.php
 * Description: Admin interface for managing non-Börsdata company data
 */

session_start();

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/src/middleware/Auth.php';
require_once __DIR__ . '/src/utils/Localization.php';
require_once __DIR__ . '/src/utils/DataValidator.php';
require_once __DIR__ . '/src/utils/SimpleDuplicateChecker.php';

// Require admin authentication
Auth::requireAuth();
if (!Auth::isAdmin()) {
    header('Location: ' . BASE_URL . '/dashboard.php');
    exit();
}

$message = '';
$messageType = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $foundationDb = Database::getConnection('foundation');
        
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'add_company':
                    // Sanitize input data
                    $companyData = DataValidator::sanitizeManualCompanyData($_POST);
                    
                    // Validate all data
                    $validation = DataValidator::validateManualCompanyData($companyData);
                    
                    if (!$validation['valid']) {
                        $errorMessages = array_values($validation['errors']);
                        $message = "Validation failed: " . implode('; ', $errorMessages);
                        $messageType = "error";
                        break;
                    }
                    
                    // Check for duplicates using simple checker to avoid collation issues
                    $duplicateCheck = SimpleDuplicateChecker::checkDuplicate($companyData['isin'], $foundationDb);
                    if ($duplicateCheck['duplicate']) {
                        $message = "Company already exists in " . $duplicateCheck['source'] . ": " . $companyData['isin'];
                        $messageType = "error";
                        break;
                    }
                    
                    $sql = "INSERT INTO manual_company_data 
                            (isin, ticker, company_name, country, sector, branch, market_exchange, 
                             currency, company_type, dividend_frequency, notes, created_by) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    
                    $stmt = $foundationDb->prepare($sql);
                    $stmt->execute([
                        $companyData['isin'],
                        $companyData['ticker'] ?: null,
                        $companyData['company_name'],
                        $companyData['country'],
                        $companyData['sector'] ?: null,
                        $companyData['branch'] ?: null,
                        $companyData['market_exchange'] ?: null,
                        $companyData['currency'] ?: null,
                        $companyData['company_type'],
                        $companyData['dividend_frequency'],
                        $companyData['notes'] ?: null,
                        Auth::getUsername() ?: 'admin'
                    ]);
                    
                    $message = "Company added successfully!";
                    $messageType = "success";
                    break;
                    
                case 'update_company':
                    $sql = "UPDATE manual_company_data 
                            SET ticker = ?, company_name = ?, country = ?, sector = ?, 
                                branch = ?, market_exchange = ?, currency = ?, company_type = ?, 
                                dividend_frequency = ?, notes = ?, updated_at = NOW()
                            WHERE manual_id = ?";
                    
                    $stmt = $foundationDb->prepare($sql);
                    $stmt->execute([
                        $_POST['ticker'] ?: null,
                        $_POST['company_name'],
                        $_POST['country'],
                        $_POST['sector'] ?: null,
                        $_POST['branch'] ?: null,
                        $_POST['market_exchange'] ?: null,
                        $_POST['currency'] ?: null,
                        $_POST['company_type'],
                        $_POST['dividend_frequency'],
                        $_POST['notes'] ?: null,
                        $_POST['manual_id']
                    ]);
                    
                    $message = "Company updated successfully!";
                    $messageType = "success";
                    break;
                    
                case 'delete_company':
                    $sql = "DELETE FROM manual_company_data WHERE manual_id = ?";
                    $stmt = $foundationDb->prepare($sql);
                    $stmt->execute([$_POST['manual_id']]);
                    
                    $message = "Company deleted successfully!";
                    $messageType = "success";
                    break;
                    
                case 'check_unsupported':
                    // Simplified approach to avoid collation issues
                    $analysisResults = [];
                    $unsupported = [];
                    $delisted = [];
                    
                    try {
                        // Get all ISINs from portfolio/trades/dividends
                        $portfolioDb = Database::getConnection('portfolio');
                        $stmt = $portfolioDb->query("
                            SELECT DISTINCT isin, ticker FROM portfolio WHERE isin IS NOT NULL
                            UNION
                            SELECT DISTINCT isin, ticker FROM log_trades WHERE isin IS NOT NULL  
                            UNION
                            SELECT DISTINCT isin, ticker FROM log_dividends WHERE isin IS NOT NULL
                        ");
                        $allPortfolioISINs = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        
                        // Check each ISIN individually to avoid collation issues
                        foreach ($allPortfolioISINs as $portfolioItem) {
                            $isin = $portfolioItem['isin'];
                            $ticker = $portfolioItem['ticker'];
                            
                            $foundInBorsdata = false;
                            $foundInMasterlist = false;
                            $foundInManual = false;
                            
                            // Check if in manual data (safe - same database)
                            $stmt = $foundationDb->prepare("SELECT COUNT(*) as count FROM manual_company_data WHERE isin = ?");
                            $stmt->execute([$isin]);
                            if ($stmt->fetch(PDO::FETCH_ASSOC)['count'] > 0) {
                                $foundInManual = true;
                                continue; // Skip if already in manual data
                            }
                            
                            // Check masterlist (safe - same database)
                            $stmt = $foundationDb->prepare("SELECT name, country, delisted, delisted_date FROM masterlist WHERE isin = ?");
                            $stmt->execute([$isin]);
                            $masterlistData = $stmt->fetch(PDO::FETCH_ASSOC);
                            if ($masterlistData) {
                                $foundInMasterlist = true;
                                if ($masterlistData['delisted'] == 1) {
                                    $delisted[] = [
                                        'isin' => $isin,
                                        'ticker' => $ticker,
                                        'category' => 'delisted',
                                        'likely_country' => $masterlistData['country'],
                                        'company_name' => $masterlistData['name'],
                                        'delisted_date' => $masterlistData['delisted_date']
                                    ];
                                    continue;
                                }
                            }
                            
                            // Try to check Börsdata (with error handling for collation issues)
                            try {
                                $marketDataDb = Database::getConnection('marketdata');
                                
                                // Check Nordic
                                $stmt = $marketDataDb->prepare("SELECT COUNT(*) as count FROM nordic_instruments WHERE isin = ?");
                                $stmt->execute([$isin]);
                                if ($stmt->fetch(PDO::FETCH_ASSOC)['count'] > 0) {
                                    $foundInBorsdata = true;
                                    continue;
                                }
                                
                                // Check Global
                                $stmt = $marketDataDb->prepare("SELECT COUNT(*) as count FROM global_instruments WHERE isin = ?");
                                $stmt->execute([$isin]);
                                if ($stmt->fetch(PDO::FETCH_ASSOC)['count'] > 0) {
                                    $foundInBorsdata = true;
                                    continue;
                                }
                            } catch (Exception $e) {
                                // If Börsdata check fails, assume it's not found and continue
                                error_log("Börsdata check failed for $isin: " . $e->getMessage());
                            }
                            
                            // If not found anywhere, it's unsupported
                            if (!$foundInBorsdata && !$foundInMasterlist && !$foundInManual) {
                                $country = 'Unknown Country';
                                if (preg_match('/^AT/', $isin)) $country = 'Austria';
                                elseif (preg_match('/^CA/', $isin)) $country = 'Canada';
                                elseif (preg_match('/^GB/', $isin)) $country = 'United Kingdom';
                                elseif (preg_match('/^US/', $isin)) $country = 'United States';
                                elseif (preg_match('/^CZ/', $isin)) $country = 'Czech Republic';
                                elseif (preg_match('/^IE/', $isin)) $country = 'Ireland';
                                
                                $unsupported[] = [
                                    'isin' => $isin,
                                    'ticker' => $ticker,
                                    'category' => 'unsupported',
                                    'likely_country' => $country,
                                    'company_name' => null,
                                    'delisted_date' => null
                                ];
                            }
                        }
                        
                        $unsupportedCount = count($unsupported);
                        $delistedCount = count($delisted);
                        
                        $message = "Analysis complete: $unsupportedCount truly unsupported companies";
                        if ($delistedCount > 0) {
                            $message .= ", $delistedCount delisted companies found";
                        }
                        $messageType = "success";
                        
                        // Store results for display
                        $_SESSION['analysis_results'] = [
                            'unsupported' => $unsupported,
                            'delisted' => $delisted
                        ];
                        
                    } catch (Exception $e) {
                        $message = "Analysis failed: " . $e->getMessage();
                        $messageType = "error";
                        $_SESSION['analysis_results'] = ['unsupported' => [], 'delisted' => []];
                    }
                    break;
                    
                case 'check_missing':
                    // Check for companies that were previously available but now missing from Börsdata
                    try {
                        $missingCompanies = [];
                        
                        // Get companies from masterlist that are not delisted
                        $stmt = $foundationDb->prepare("
                            SELECT isin, name, country 
                            FROM masterlist 
                            WHERE delisted = 0 OR delisted IS NULL
                        ");
                        $stmt->execute();
                        $activeCompanies = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        
                        foreach ($activeCompanies as $company) {
                            $isin = $company['isin'];
                            $foundInBorsdata = false;
                            
                            // Check if still in Börsdata
                            try {
                                $marketDataDb = Database::getConnection('marketdata');
                                
                                // Check Nordic
                                $stmt = $marketDataDb->prepare("SELECT COUNT(*) as count FROM nordic_instruments WHERE isin = ?");
                                $stmt->execute([$isin]);
                                if ($stmt->fetch(PDO::FETCH_ASSOC)['count'] > 0) {
                                    $foundInBorsdata = true;
                                }
                                
                                // Check Global if not found in Nordic
                                if (!$foundInBorsdata) {
                                    $stmt = $marketDataDb->prepare("SELECT COUNT(*) as count FROM global_instruments WHERE isin = ?");
                                    $stmt->execute([$isin]);
                                    if ($stmt->fetch(PDO::FETCH_ASSOC)['count'] > 0) {
                                        $foundInBorsdata = true;
                                    }
                                }
                            } catch (Exception $e) {
                                error_log("Missing check failed for $isin: " . $e->getMessage());
                            }
                            
                            // If not found in Börsdata but was previously active, it's missing
                            if (!$foundInBorsdata) {
                                $missingCompanies[] = $company;
                            }
                        }
                        
                        $missingCount = count($missingCompanies);
                        $message = "Missing companies check completed: $missingCount companies previously in Börsdata are now missing";
                        $messageType = $missingCount > 0 ? "warning" : "success";
                        
                        // Store missing companies for display
                        $_SESSION['missing_companies'] = $missingCompanies;
                        
                    } catch (Exception $e) {
                        $message = "Missing companies check failed: " . $e->getMessage();
                        $messageType = "error";
                        $_SESSION['missing_companies'] = [];
                    }
                    break;
            }
        }
    } catch (Exception $e) {
        $message = "Error: " . $e->getMessage();
        $messageType = "error";
    }
}

try {
    $foundationDb = Database::getConnection('foundation');
    
    // Get manual companies
    $manualCompaniesStmt = $foundationDb->query("
        SELECT * FROM manual_company_data 
        ORDER BY company_name ASC
    ");
    $manualCompanies = $manualCompaniesStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get recent notifications
    $notificationsStmt = $foundationDb->query("
        SELECT * FROM notification_queue 
        WHERE notification_status = 'pending'
        ORDER BY created_at DESC 
        LIMIT 10
    ");
    $notifications = $notificationsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get sync log
    $syncLogStmt = $foundationDb->query("
        SELECT * FROM data_sync_log 
        ORDER BY sync_started DESC 
        LIMIT 5
    ");
    $syncLogs = $syncLogStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get analysis results from session if available
    $analysisResults = $_SESSION['analysis_results'] ?? ['unsupported' => [], 'delisted' => []];
    
    // Get missing companies results
    $missingCompanies = $_SESSION['missing_companies'] ?? [];
    
    // Get data source summary (simplified for now)
    $sourceSummary = [
        ['data_source' => 'manual', 'total' => count($manualCompanies), 'active' => count($manualCompanies), 'missing' => 0]
    ];
    
} catch (Exception $e) {
    $error = "Error loading data: " . $e->getMessage();
    $manualCompanies = [];
    $notifications = [];
    $syncLogs = [];
    $sourceSummary = [];
}

// Initialize variables for template
$pageTitle = 'Company Data Management - PSW 4.0 Admin';
$pageDescription = 'Manage non-Börsdata company data and monitoring';
$additionalCSS = [];
$additionalJS = [];

// Add CSS to fix text visibility
$additionalCSS[] = '
<style>
.psw-form-input, .psw-form-input:focus, .psw-form-input:active {
    color: #333 !important;
    background-color: white !important;
    border: 1px solid #ddd !important;
}
.psw-form-input option {
    color: #333 !important;
    background-color: white !important;
}
.psw-modal .psw-form-input {
    color: #333 !important;
    background-color: white !important;
}
</style>';

// Prepare content
ob_start();
?>

<div class="psw-container" style="max-width: 1400px;">
    <div class="psw-page-header" style="margin-bottom: 2rem;">
        <h1 class="psw-page-title">
            <i class="fas fa-building" style="margin-right: 0.5rem; color: var(--primary-accent);"></i>
            Company Data Management
        </h1>
        <p class="psw-page-description">
            Manage companies not supported by Börsdata and monitor data source status
        </p>
    </div>

    <?php if ($message): ?>
        <div class="psw-alert psw-alert-<?php echo $messageType; ?>" style="margin-bottom: 1.5rem;">
            <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : ($messageType === 'error' ? 'exclamation-triangle' : 'info-circle'); ?>"></i>
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <!-- Action Buttons -->
    <div class="psw-card" style="margin-bottom: 1.5rem;">
        <div class="psw-card-header">
            <div class="psw-card-title">
                <i class="fas fa-cogs psw-card-title-icon"></i>
                System Actions
            </div>
        </div>
        <div class="psw-card-content">
            <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
                <form method="POST" style="display: inline;">
                    <input type="hidden" name="action" value="check_unsupported">
                    <button type="submit" class="psw-btn psw-btn-primary">
                        <i class="fas fa-search psw-btn-icon"></i>
                        Find Unsupported Companies
                    </button>
                </form>
                
                <form method="POST" style="display: inline;">
                    <input type="hidden" name="action" value="check_missing">
                    <button type="submit" class="psw-btn psw-btn-warning">
                        <i class="fas fa-exclamation-triangle psw-btn-icon"></i>
                        Check Missing Companies
                    </button>
                </form>
                
                <button type="button" class="psw-btn psw-btn-success" onclick="showAddCompanyModal()">
                    <i class="fas fa-plus psw-btn-icon"></i>
                    Add New Company
                </button>
                
                <button type="button" class="psw-btn psw-btn-info" onclick="testFormFill()">
                    <i class="fas fa-flask psw-btn-icon"></i>
                    Test GB Company
                </button>
            </div>
        </div>
    </div>

    <!-- Data Source Summary -->
    <div class="psw-card" style="margin-bottom: 1.5rem;">
        <div class="psw-card-header">
            <div class="psw-card-title">
                <i class="fas fa-chart-bar psw-card-title-icon"></i>
                Data Source Summary
            </div>
        </div>
        <div class="psw-card-content">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1rem;">
                <?php foreach ($sourceSummary as $source): ?>
                    <div class="psw-metric-card">
                        <div class="psw-metric-value"><?php echo number_format($source['total']); ?></div>
                        <div class="psw-metric-label"><?php echo ucwords(str_replace('_', ' ', $source['data_source'])); ?></div>
                        <div style="font-size: 0.875rem; color: var(--text-secondary); margin-top: 0.25rem;">
                            Active: <?php echo $source['active']; ?> | Missing: <?php echo $source['missing']; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Manual Companies Table -->
    <div class="psw-card" style="margin-bottom: 1.5rem;">
        <div class="psw-card-header">
            <div class="psw-card-title">
                <i class="fas fa-table psw-card-title-icon"></i>
                Manual Company Data (<?php echo count($manualCompanies); ?>)
            </div>
        </div>
        <div class="psw-card-content" style="padding: 0;">
            <?php if (!empty($manualCompanies)): ?>
                <table class="psw-table">
                    <thead>
                        <tr>
                            <th>ISIN</th>
                            <th>Company Name</th>
                            <th>Ticker</th>
                            <th>Country</th>
                            <th>Sector</th>
                            <th>Type</th>
                            <th>Currency</th>
                            <th>Updated</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($manualCompanies as $company): ?>
                            <tr>
                                <td style="font-family: var(--font-family-mono); font-size: 0.875rem;">
                                    <?php echo htmlspecialchars($company['isin']); ?>
                                </td>
                                <td style="font-weight: 600;">
                                    <?php echo htmlspecialchars($company['company_name']); ?>
                                </td>
                                <td style="font-family: var(--font-family-mono);">
                                    <?php echo htmlspecialchars($company['ticker'] ?? '-'); ?>
                                </td>
                                <td><?php echo htmlspecialchars($company['country']); ?></td>
                                <td><?php echo htmlspecialchars($company['sector'] ?? '-'); ?></td>
                                <td>
                                    <span class="psw-badge psw-badge-primary">
                                        <?php echo ucwords(str_replace('_', ' ', $company['company_type'])); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($company['currency'] ?? '-'); ?></td>
                                <td style="font-size: 0.875rem; color: var(--text-secondary);">
                                    <?php echo date('Y-m-d H:i', strtotime($company['updated_at'])); ?>
                                </td>
                                <td>
                                    <div style="display: flex; gap: 0.5rem;">
                                        <button type="button" class="psw-btn psw-btn-sm psw-btn-secondary" 
                                                onclick="editCompany(<?php echo htmlspecialchars(json_encode($company)); ?>)">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button type="button" class="psw-btn psw-btn-sm psw-btn-danger" 
                                                onclick="deleteCompany(<?php echo $company['manual_id']; ?>, '<?php echo htmlspecialchars($company['company_name']); ?>')">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div style="padding: 2rem; text-align: center; color: var(--text-secondary);">
                    <i class="fas fa-info-circle" style="font-size: 2rem; margin-bottom: 1rem; opacity: 0.5;"></i>
                    <p>No manual company data found. Click "Add New Company" to get started.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Analysis Results -->
    <?php if (!empty($analysisResults['unsupported']) || !empty($analysisResults['delisted'])): ?>
        <div class="psw-card" style="margin-bottom: 1.5rem;">
            <div class="psw-card-header">
                <div class="psw-card-title">
                    <i class="fas fa-search psw-card-title-icon"></i>
                    Company Analysis Results
                </div>
            </div>
            <div class="psw-card-content">
                <!-- Truly Unsupported Companies -->
                <?php if (!empty($analysisResults['unsupported'])): ?>
                    <div style="margin-bottom: 2rem;">
                        <h4 style="color: var(--warning-color); margin-bottom: 1rem;">
                            <i class="fas fa-exclamation-triangle"></i>
                            Unsupported Companies (<?php echo count($analysisResults['unsupported']); ?>) - Need Manual Entry
                        </h4>
                        <table class="psw-table">
                            <thead>
                                <tr>
                                    <th>ISIN</th>
                                    <th>Ticker</th>
                                    <th>Likely Country</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($analysisResults['unsupported'] as $company): ?>
                                    <tr>
                                        <td style="font-family: var(--font-family-mono);">
                                            <?php echo htmlspecialchars($company['isin']); ?>
                                        </td>
                                        <td style="font-family: var(--font-family-mono);">
                                            <?php echo htmlspecialchars($company['ticker'] ?? '-'); ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($company['likely_country']); ?></td>
                                        <td>
                                            <button type="button" class="psw-btn psw-btn-sm psw-btn-primary" 
                                                    onclick="addCompanyFromAnalysis('<?php echo $company['isin']; ?>', '<?php echo $company['ticker'] ?? ''; ?>', '<?php echo $company['likely_country']; ?>')">
                                                <i class="fas fa-plus"></i> Add Manual Entry
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>

                <!-- Delisted Companies -->
                <?php if (!empty($analysisResults['delisted'])): ?>
                    <div style="margin-bottom: 1rem;">
                        <h4 style="color: var(--text-secondary); margin-bottom: 1rem;">
                            <i class="fas fa-info-circle"></i>
                            Delisted Companies (<?php echo count($analysisResults['delisted']); ?>) - Already in System
                        </h4>
                        <table class="psw-table">
                            <thead>
                                <tr>
                                    <th>ISIN</th>
                                    <th>Company Name</th>
                                    <th>Ticker</th>
                                    <th>Country</th>
                                    <th>Delisted Date</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($analysisResults['delisted'] as $company): ?>
                                    <tr style="opacity: 0.7;">
                                        <td style="font-family: var(--font-family-mono);">
                                            <?php echo htmlspecialchars($company['isin']); ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($company['company_name']); ?></td>
                                        <td style="font-family: var(--font-family-mono);">
                                            <?php echo htmlspecialchars($company['ticker'] ?? '-'); ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($company['likely_country']); ?></td>
                                        <td style="font-size: 0.875rem;">
                                            <?php echo $company['delisted_date'] ? date('Y-m-d', strtotime($company['delisted_date'])) : '-'; ?>
                                        </td>
                                        <td>
                                            <span class="psw-badge" style="background-color: var(--text-secondary); color: white;">
                                                Delisted
                                            </span>
                                            <button type="button" class="psw-btn psw-btn-sm psw-btn-primary" style="margin-left: 0.5rem;"
                                                    onclick="addCompanyFromDelisted('<?php echo htmlspecialchars($company['isin']); ?>', '<?php echo htmlspecialchars($company['ticker'] ?? ''); ?>', '<?php echo htmlspecialchars($company['company_name']); ?>', '<?php echo htmlspecialchars($company['likely_country']); ?>')">
                                                <i class="fas fa-plus"></i> Add Manual Entry
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- Missing Companies Results -->
    <?php if (!empty($missingCompanies)): ?>
        <div class="psw-card" style="margin-bottom: 1.5rem;">
            <div class="psw-card-header">
                <div class="psw-card-title">
                    <i class="fas fa-exclamation-triangle psw-card-title-icon" style="color: var(--warning-color);"></i>
                    Missing Companies (<?php echo count($missingCompanies); ?>) - Previously in Börsdata
                </div>
            </div>
            <div class="psw-card-content">
                <div style="margin-bottom: 1rem; padding: 1rem; background-color: var(--warning-bg); border-left: 4px solid var(--warning-color); border-radius: 4px;">
                    <p style="margin: 0; color: var(--warning-text);">
                        <strong>⚠️ Alert:</strong> These companies were previously available in Börsdata but are now missing. 
                        They may have been delisted, moved to a different exchange, or temporarily unavailable.
                    </p>
                </div>
                <table class="psw-table">
                    <thead>
                        <tr>
                            <th>ISIN</th>
                            <th>Company Name</th>
                            <th>Country</th>
                            <th>Action Needed</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($missingCompanies as $company): ?>
                            <tr>
                                <td style="font-family: var(--font-family-mono);">
                                    <?php echo htmlspecialchars($company['isin']); ?>
                                </td>
                                <td><?php echo htmlspecialchars($company['name']); ?></td>
                                <td><?php echo htmlspecialchars($company['country']); ?></td>
                                <td>
                                    <span class="psw-badge" style="background-color: var(--warning-color); color: white;">
                                        Investigation Required
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>

    <!-- Notifications -->
    <?php if (!empty($notifications)): ?>
        <div class="psw-card" style="margin-bottom: 1.5rem;">
            <div class="psw-card-header">
                <div class="psw-card-title">
                    <i class="fas fa-bell psw-card-title-icon"></i>
                    Pending Notifications (<?php echo count($notifications); ?>)
                </div>
            </div>
            <div class="psw-card-content">
                <?php foreach ($notifications as $notification): ?>
                    <div class="psw-alert psw-alert-<?php echo $notification['priority'] === 'high' ? 'warning' : 'info'; ?>" 
                         style="margin-bottom: 0.5rem;">
                        <div style="display: flex; justify-content: space-between; align-items: start;">
                            <div>
                                <strong><?php echo ucwords(str_replace('_', ' ', $notification['notification_type'])); ?></strong>
                                <?php if ($notification['isin']): ?>
                                    <span style="font-family: var(--font-family-mono); margin-left: 0.5rem;">
                                        (<?php echo $notification['isin']; ?>)
                                    </span>
                                <?php endif; ?>
                                <p style="margin: 0.5rem 0 0 0;">
                                    <?php echo htmlspecialchars($notification['message']); ?>
                                </p>
                            </div>
                            <span style="font-size: 0.75rem; color: var(--text-secondary); white-space: nowrap;">
                                <?php echo date('M j, H:i', strtotime($notification['created_at'])); ?>
                            </span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Add/Edit Company Modal -->
<div id="companyModal" class="psw-modal" style="display: none;">
    <div class="psw-modal-content" style="max-width: 800px;">
        <div class="psw-modal-header">
            <h3 id="modalTitle">Add New Company</h3>
            <button type="button" class="psw-modal-close" onclick="closeCompanyModal()">&times;</button>
        </div>
        <form id="companyForm" method="POST">
            <div class="psw-modal-body">
                <input type="hidden" name="action" id="formAction" value="add_company">
                <input type="hidden" name="manual_id" id="manualId" value="">
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="psw-form-group">
                        <label class="psw-form-label">ISIN *</label>
                        <input type="text" name="isin" id="isin" class="psw-form-input" required 
                               pattern="[A-Z]{2}[A-Z0-9]{9}[0-9]" title="Must be a valid ISIN format"
                               style="color: #333 !important; background-color: white !important;">
                    </div>
                    
                    <div class="psw-form-group">
                        <label class="psw-form-label">Ticker</label>
                        <input type="text" name="ticker" id="ticker" class="psw-form-input"
                               style="color: #333 !important; background-color: white !important;">
                    </div>
                </div>
                
                <div class="psw-form-group">
                    <label class="psw-form-label">Company Name *</label>
                    <input type="text" name="company_name" id="companyName" class="psw-form-input" required
                           style="color: #333 !important; background-color: white !important;">
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="psw-form-group">
                        <label class="psw-form-label">Country *</label>
                        <select name="country" id="country" class="psw-form-input" required
                                style="color: #333 !important; background-color: white !important;">
                            <option value="">Select country...</option>
                            <option value="Austria">Austria</option>
                            <option value="Belgium">Belgium</option>
                            <option value="Canada">Canada</option>
                            <option value="Czech Republic">Czech Republic</option>
                            <option value="Denmark">Denmark</option>
                            <option value="Finland">Finland</option>
                            <option value="France">France</option>
                            <option value="Germany">Germany</option>
                            <option value="Ireland">Ireland</option>
                            <option value="Italy">Italy</option>
                            <option value="Netherlands">Netherlands</option>
                            <option value="Norway">Norway</option>
                            <option value="Poland">Poland</option>
                            <option value="Spain">Spain</option>
                            <option value="Sweden">Sweden</option>
                            <option value="Switzerland">Switzerland</option>
                            <option value="United Kingdom">United Kingdom</option>
                            <option value="United States">United States</option>
                            <option value="Australia">Australia</option>
                            <option value="Japan">Japan</option>
                            <option value="South Korea">South Korea</option>
                            <option value="Singapore">Singapore</option>
                            <option value="Hong Kong">Hong Kong</option>
                        </select>
                    </div>
                    
                    <div class="psw-form-group">
                        <label class="psw-form-label">Currency</label>
                        <select name="currency" id="currency" class="psw-form-input"
                                style="color: #333 !important; background-color: white !important;">
                            <option value="">Select currency...</option>
                            <option value="EUR">EUR</option>
                            <option value="GBP">GBP</option>
                            <option value="USD">USD</option>
                            <option value="CAD">CAD</option>
                            <option value="SEK">SEK</option>
                            <option value="NOK">NOK</option>
                            <option value="DKK">DKK</option>
                            <option value="CZK">CZK</option>
                        </select>
                    </div>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="psw-form-group">
                        <label class="psw-form-label">Sector</label>
                        <input type="text" name="sector" id="sector" class="psw-form-input"
                               style="color: #333 !important; background-color: white !important;">
                    </div>
                    
                    <div class="psw-form-group">
                        <label class="psw-form-label">Branch</label>
                        <input type="text" name="branch" id="branch" class="psw-form-input"
                               style="color: #333 !important; background-color: white !important;">
                    </div>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="psw-form-group">
                        <label class="psw-form-label">Company Type</label>
                        <select name="company_type" id="companyType" class="psw-form-input"
                                style="color: #333 !important; background-color: white !important;">
                            <option value="stock">Stock</option>
                            <option value="etf">ETF</option>
                            <option value="closed_end_fund">Closed End Fund</option>
                            <option value="reit">REIT</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    
                    <div class="psw-form-group">
                        <label class="psw-form-label">Dividend Frequency</label>
                        <select name="dividend_frequency" id="dividendFrequency" class="psw-form-input"
                                style="color: #333 !important; background-color: white !important;">
                            <option value="quarterly">Quarterly</option>
                            <option value="annual">Annual</option>
                            <option value="semi_annual">Semi Annual</option>
                            <option value="monthly">Monthly</option>
                            <option value="irregular">Irregular</option>
                            <option value="none">None</option>
                        </select>
                    </div>
                </div>
                
                <div class="psw-form-group">
                    <label class="psw-form-label">Market/Exchange</label>
                    <input type="text" name="market_exchange" id="marketExchange" class="psw-form-input"
                           style="color: #333 !important; background-color: white !important;">
                </div>
                
                <div class="psw-form-group">
                    <label class="psw-form-label">Notes</label>
                    <textarea name="notes" id="notes" class="psw-form-input" rows="3"
                              style="color: #333 !important; background-color: white !important;"></textarea>
                </div>
            </div>
            <div class="psw-modal-footer">
                <button type="button" class="psw-btn psw-btn-secondary" onclick="closeCompanyModal()">
                    Cancel
                </button>
                <button type="submit" class="psw-btn psw-btn-primary" onclick="return validateForm()">
                    <i class="fas fa-save psw-btn-icon"></i>
                    Save Company
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// Client-side validation functions
function validateISIN(isin) {
    isin = isin.toUpperCase().trim();
    
    if (isin.length !== 12) {
        return { valid: false, error: 'ISIN must be exactly 12 characters' };
    }
    
    if (!/^[A-Z]{2}[A-Z0-9]{9}[0-9]$/.test(isin)) {
        return { valid: false, error: 'Invalid ISIN format. Must be 2 letters + 9 alphanumeric + 1 digit' };
    }
    
    // Validate checksum
    let numString = '';
    for (let i = 0; i < 11; i++) {
        const char = isin[i];
        if (/[0-9]/.test(char)) {
            numString += char;
        } else {
            numString += (char.charCodeAt(0) - 55);
        }
    }
    
    let sum = 0;
    let pos = 1;
    for (let i = numString.length - 1; i >= 0; i--) {
        let digit = parseInt(numString[i]);
        if (pos % 2 === 0) {
            digit *= 2;
            if (digit > 9) {
                digit -= 9;
            }
        }
        sum += digit;
        pos++;
    }
    
    const checkDigit = (10 - (sum % 10)) % 10;
    const actualCheckDigit = parseInt(isin[11]);
    
    if (checkDigit !== actualCheckDigit) {
        console.warn('ISIN checksum validation failed for: ' + isin + ' (expected: ' + checkDigit + ', got: ' + actualCheckDigit + ')');
        // Allow invalid checksums but warn - broker data might use different formats
    }
    
    return { valid: true, error: null };
}

function validateCompanyName(name) {
    name = name.trim();
    if (name.length < 2) {
        return { valid: false, error: 'Company name must be at least 2 characters' };
    }
    if (name.length > 255) {
        return { valid: false, error: 'Company name must not exceed 255 characters' };
    }
    if (/^[0-9]+$/.test(name)) {
        return { valid: false, error: 'Company name cannot be only numbers' };
    }
    return { valid: true, error: null };
}

function validateCountry(country) {
    const validCountries = [
        'Austria', 'Belgium', 'Canada', 'Czech Republic', 'Denmark', 'Finland',
        'France', 'Germany', 'Ireland', 'Italy', 'Netherlands', 'Norway',
        'Poland', 'Spain', 'Sweden', 'Switzerland', 'United Kingdom', 'United States',
        'Australia', 'Japan', 'South Korea', 'Singapore', 'Hong Kong'
    ];
    
    if (!country.trim()) {
        return { valid: false, error: 'Country is required' };
    }
    
    if (!validCountries.includes(country)) {
        return { valid: false, error: 'Unsupported country. Please contact admin to add new countries.' };
    }
    
    return { valid: true, error: null };
}

function showValidationError(fieldId, errorMessage) {
    const field = document.getElementById(fieldId);
    const existingError = field.parentNode.querySelector('.validation-error');
    
    if (existingError) {
        existingError.remove();
    }
    
    if (errorMessage) {
        const errorDiv = document.createElement('div');
        errorDiv.className = 'validation-error';
        errorDiv.style.cssText = 'color: var(--danger-color); font-size: 0.875rem; margin-top: 0.25rem;';
        errorDiv.textContent = errorMessage;
        field.parentNode.appendChild(errorDiv);
        field.style.borderColor = 'var(--danger-color)';
    } else {
        field.style.borderColor = '';
    }
}

function validateForm() {
    let isValid = true;
    
    // Validate ISIN
    const isin = document.getElementById('isin').value;
    const isinValidation = validateISIN(isin);
    if (!isinValidation.valid) {
        showValidationError('isin', isinValidation.error);
        isValid = false;
    } else {
        showValidationError('isin', null);
    }
    
    // Validate Company Name
    const companyName = document.getElementById('companyName').value;
    const nameValidation = validateCompanyName(companyName);
    if (!nameValidation.valid) {
        showValidationError('companyName', nameValidation.error);
        isValid = false;
    } else {
        showValidationError('companyName', null);
    }
    
    // Validate Country
    const country = document.getElementById('country').value;
    const countryValidation = validateCountry(country);
    if (!countryValidation.valid) {
        showValidationError('country', countryValidation.error);
        isValid = false;
    } else {
        showValidationError('country', null);
    }
    
    return isValid;
}

function showAddCompanyModal() {
    document.getElementById('modalTitle').textContent = 'Add New Company';
    document.getElementById('formAction').value = 'add_company';
    document.getElementById('companyForm').reset();
    document.getElementById('manualId').value = '';
    document.getElementById('isin').readOnly = false;
    document.getElementById('companyModal').style.display = 'flex';
    
    // Clear any validation errors
    document.querySelectorAll('.validation-error').forEach(error => error.remove());
    document.querySelectorAll('.psw-form-input').forEach(input => input.style.borderColor = '');
}

function addCompanyFromAnalysis(isin, ticker, country) {
    document.getElementById('modalTitle').textContent = 'Add Unsupported Company';
    document.getElementById('formAction').value = 'add_company';
    document.getElementById('companyForm').reset();
    document.getElementById('manualId').value = '';
    
    // Pre-fill known data
    document.getElementById('isin').value = isin;
    document.getElementById('isin').readOnly = true;
    document.getElementById('ticker').value = ticker;
    document.getElementById('country').value = country;
    
    document.getElementById('companyModal').style.display = 'flex';
}

function addCompanyFromDelisted(isin, ticker, companyName, country) {
    console.log('Adding from delisted:', {isin, ticker, companyName, country}); // Debug log
    
    document.getElementById('modalTitle').textContent = 'Add Company from Masterlist';
    document.getElementById('formAction').value = 'add_company';
    document.getElementById('companyForm').reset();
    document.getElementById('manualId').value = '';
    
    // Wait a moment for form to reset, then populate
    setTimeout(function() {
        // Pre-fill known data from masterlist
        if (isin) {
            document.getElementById('isin').value = isin;
            document.getElementById('isin').readOnly = true;
        }
        if (ticker) {
            document.getElementById('ticker').value = ticker;
        }
        if (companyName) {
            document.getElementById('companyName').value = companyName;
        }
        if (country) {
            document.getElementById('country').value = country;
        }
        
        // Set appropriate currency based on country
        if (country === 'United Kingdom') {
            document.getElementById('currency').value = 'GBP';
        } else if (country === 'Canada') {
            document.getElementById('currency').value = 'CAD';
        } else if (country === 'United States') {
            document.getElementById('currency').value = 'USD';
        } else if (country === 'Czech Republic') {
            document.getElementById('currency').value = 'CZK';
        } else if (country === 'Ireland') {
            document.getElementById('currency').value = 'EUR';
        }
        
        // Add note about source
        document.getElementById('notes').value = 'Added from masterlist - previously delisted company';
        
        console.log('Form populated'); // Debug log
    }, 100);
    
    document.getElementById('companyModal').style.display = 'flex';
}

function testFormFill() {
    // Test function to manually fill GB0001990497
    addCompanyFromDelisted('GB0001990497', 'TEST', 'Test UK Company', 'United Kingdom');
}

function editCompany(company) {
    document.getElementById('modalTitle').textContent = 'Edit Company';
    document.getElementById('formAction').value = 'update_company';
    document.getElementById('manualId').value = company.manual_id;
    
    // Populate form fields
    document.getElementById('isin').value = company.isin;
    document.getElementById('isin').readOnly = true;
    document.getElementById('ticker').value = company.ticker || '';
    document.getElementById('companyName').value = company.company_name;
    document.getElementById('country').value = company.country;
    document.getElementById('currency').value = company.currency || '';
    document.getElementById('sector').value = company.sector || '';
    document.getElementById('branch').value = company.branch || '';
    document.getElementById('companyType').value = company.company_type;
    document.getElementById('dividendFrequency').value = company.dividend_frequency;
    document.getElementById('marketExchange').value = company.market_exchange || '';
    document.getElementById('notes').value = company.notes || '';
    
    document.getElementById('companyModal').style.display = 'flex';
}

function deleteCompany(manualId, companyName) {
    if (confirm('Are you sure you want to delete "' + companyName + '"? This action cannot be undone.')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="delete_company">
            <input type="hidden" name="manual_id" value="${manualId}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}

function closeCompanyModal() {
    document.getElementById('companyModal').style.display = 'none';
}

// Close modal when clicking outside
document.getElementById('companyModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeCompanyModal();
    }
});

// Add real-time validation event listeners
document.addEventListener('DOMContentLoaded', function() {
    // ISIN field validation
    document.getElementById('isin').addEventListener('blur', function() {
        if (this.value) {
            const validation = validateISIN(this.value);
            showValidationError('isin', validation.valid ? null : validation.error);
        }
    });
    
    // Company name validation
    document.getElementById('companyName').addEventListener('blur', function() {
        if (this.value) {
            const validation = validateCompanyName(this.value);
            showValidationError('companyName', validation.valid ? null : validation.error);
        }
    });
    
    // Country validation
    document.getElementById('country').addEventListener('blur', function() {
        if (this.value) {
            const validation = validateCountry(this.value);
            showValidationError('country', validation.valid ? null : validation.error);
        }
    });
    
    // Clear validation errors on input
    document.querySelectorAll('.psw-form-input').forEach(input => {
        input.addEventListener('input', function() {
            const errorDiv = this.parentNode.querySelector('.validation-error');
            if (errorDiv && this.value.trim()) {
                // Re-validate on input for immediate feedback
                let validation = { valid: true };
                if (this.id === 'isin') {
                    validation = validateISIN(this.value);
                } else if (this.id === 'companyName') {
                    validation = validateCompanyName(this.value);
                } else if (this.id === 'country') {
                    validation = validateCountry(this.value);
                }
                
                if (validation.valid) {
                    showValidationError(this.id, null);
                }
            }
        });
    });
});
</script>

<?php
$content = ob_get_clean();

// Include base layout
include __DIR__ . '/templates/layouts/base-redesign.php';
?>