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
                    $sql = "INSERT INTO manual_company_data 
                            (isin, ticker, company_name, country, sector, branch, market_exchange, 
                             currency, company_type, dividend_frequency, notes, created_by) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    
                    $stmt = $foundationDb->prepare($sql);
                    $stmt->execute([
                        $_POST['isin'],
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
                    $stmt = $foundationDb->prepare("CALL sp_identify_unsupported_companies()");
                    $stmt->execute();
                    $result = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    $message = $result['result'] ?? "Unsupported companies check completed.";
                    $messageType = "info";
                    break;
                    
                case 'check_missing':
                    $stmt = $foundationDb->prepare("CALL sp_check_missing_companies()");
                    $stmt->execute();
                    $result = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    $message = $result['result'] ?? "Missing companies check completed.";
                    $messageType = "info";
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
    
    // Get data source summary
    $sourceSummaryStmt = $foundationDb->query("
        SELECT 
            data_source,
            COUNT(*) as total,
            SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
            SUM(CASE WHEN status = 'missing' THEN 1 ELSE 0 END) as missing
        FROM company_data_sources 
        GROUP BY data_source
    ");
    $sourceSummary = $sourceSummaryStmt->fetchAll(PDO::FETCH_ASSOC);
    
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
                               pattern="[A-Z]{2}[A-Z0-9]{9}[0-9]" title="Must be a valid ISIN format">
                    </div>
                    
                    <div class="psw-form-group">
                        <label class="psw-form-label">Ticker</label>
                        <input type="text" name="ticker" id="ticker" class="psw-form-input">
                    </div>
                </div>
                
                <div class="psw-form-group">
                    <label class="psw-form-label">Company Name *</label>
                    <input type="text" name="company_name" id="companyName" class="psw-form-input" required>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="psw-form-group">
                        <label class="psw-form-label">Country *</label>
                        <input type="text" name="country" id="country" class="psw-form-input" required>
                    </div>
                    
                    <div class="psw-form-group">
                        <label class="psw-form-label">Currency</label>
                        <select name="currency" id="currency" class="psw-form-input">
                            <option value="">Select currency...</option>
                            <option value="EUR">EUR</option>
                            <option value="GBP">GBP</option>
                            <option value="USD">USD</option>
                            <option value="CAD">CAD</option>
                            <option value="SEK">SEK</option>
                            <option value="NOK">NOK</option>
                            <option value="DKK">DKK</option>
                        </select>
                    </div>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="psw-form-group">
                        <label class="psw-form-label">Sector</label>
                        <input type="text" name="sector" id="sector" class="psw-form-input">
                    </div>
                    
                    <div class="psw-form-group">
                        <label class="psw-form-label">Branch</label>
                        <input type="text" name="branch" id="branch" class="psw-form-input">
                    </div>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="psw-form-group">
                        <label class="psw-form-label">Company Type</label>
                        <select name="company_type" id="companyType" class="psw-form-input">
                            <option value="stock">Stock</option>
                            <option value="etf">ETF</option>
                            <option value="closed_end_fund">Closed End Fund</option>
                            <option value="reit">REIT</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    
                    <div class="psw-form-group">
                        <label class="psw-form-label">Dividend Frequency</label>
                        <select name="dividend_frequency" id="dividendFrequency" class="psw-form-input">
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
                    <input type="text" name="market_exchange" id="marketExchange" class="psw-form-input">
                </div>
                
                <div class="psw-form-group">
                    <label class="psw-form-label">Notes</label>
                    <textarea name="notes" id="notes" class="psw-form-input" rows="3"></textarea>
                </div>
            </div>
            <div class="psw-modal-footer">
                <button type="button" class="psw-btn psw-btn-secondary" onclick="closeCompanyModal()">
                    Cancel
                </button>
                <button type="submit" class="psw-btn psw-btn-primary">
                    <i class="fas fa-save psw-btn-icon"></i>
                    Save Company
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function showAddCompanyModal() {
    document.getElementById('modalTitle').textContent = 'Add New Company';
    document.getElementById('formAction').value = 'add_company';
    document.getElementById('companyForm').reset();
    document.getElementById('manualId').value = '';
    document.getElementById('isin').readOnly = false;
    document.getElementById('companyModal').style.display = 'flex';
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
</script>

<?php
$content = ob_get_clean();

// Include base layout
include __DIR__ . '/templates/layouts/base-redesign.php';
?>