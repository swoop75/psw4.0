<?php
/**
 * File: public/company_detail.php
 * Description: Company detail view for PSW 4.0
 */

session_start();

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../src/middleware/Auth.php';
require_once __DIR__ . '/../src/controllers/MasterlistController.php';

if (!Auth::isLoggedIn()) {
    header('Location: ' . BASE_URL . '/public/login.php');
    exit;
}

$controller = new MasterlistController();
$errorMessage = '';

// Get ISIN from URL
$isin = $_GET['isin'] ?? '';

if (!$isin) {
    header('Location: ' . BASE_URL . '/public/masterlist_management.php');
    exit;
}

// Get company data
try {
    $company = $controller->getCompanyByIsin($isin);
    
    if (!$company) {
        $errorMessage = 'Company not found';
        $company = [];
    }
} catch (Exception $e) {
    $errorMessage = 'Error loading company: ' . $e->getMessage();
    $company = [];
}

$user = [
    'username' => Auth::getUsername(),
    'user_id' => Auth::getUserId(),
    'role_name' => $_SESSION['role_name'] ?? 'User'
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= !empty($company) ? htmlspecialchars($company['name']) . ' - ' : '' ?>Company Details - PSW 4.0</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/company-detail.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="container">
        <!-- Header -->
        <header class="page-header">
            <div class="header-content">
                <div class="header-left">
                    <div class="breadcrumb">
                        <a href="<?= BASE_URL ?>/public/index.php">
                            <i class="fas fa-home"></i> Dashboard
                        </a>
                        <span class="separator">/</span>
                        <a href="<?= BASE_URL ?>/public/masterlist_management.php">
                            <i class="fas fa-building"></i> Masterlist
                        </a>
                        <span class="separator">/</span>
                        <span class="current">Company Details</span>
                    </div>
                    <?php if (!empty($company)): ?>
                        <h1><?= htmlspecialchars($company['name']) ?></h1>
                        <div class="company-basic-info">
                            <span class="ticker"><?= htmlspecialchars($company['ticker']) ?></span>
                            <span class="isin"><?= htmlspecialchars($company['isin']) ?></span>
                            <?php if ($company['delisted']): ?>
                                <span class="status-badge delisted">
                                    <i class="fas fa-times-circle"></i> Delisted
                                </span>
                            <?php else: ?>
                                <span class="status-badge active">
                                    <i class="fas fa-check-circle"></i> Active
                                </span>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <h1>Company Details</h1>
                    <?php endif; ?>
                </div>
                <div class="header-right">
                    <span class="user-info">
                        <i class="fas fa-user"></i> <?= htmlspecialchars($user['username']) ?>
                    </span>
                    <a href="<?= BASE_URL ?>/public/masterlist_management.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Masterlist
                    </a>
                </div>
            </div>
        </header>

        <?php if ($errorMessage): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-triangle"></i>
                <?= htmlspecialchars($errorMessage) ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($company)): ?>
            <!-- Company Information -->
            <div class="content-grid">
                <!-- Basic Information Card -->
                <div class="info-card">
                    <div class="card-header">
                        <h3><i class="fas fa-info-circle"></i> Basic Information</h3>
                        <button class="btn btn-sm btn-primary" onclick="editCompany('<?= htmlspecialchars($company['isin']) ?>')">
                            <i class="fas fa-edit"></i> Edit
                        </button>
                    </div>
                    <div class="card-content">
                        <div class="info-grid">
                            <div class="info-item">
                                <label>ISIN</label>
                                <value class="mono"><?= htmlspecialchars($company['isin']) ?></value>
                            </div>
                            <div class="info-item">
                                <label>Ticker</label>
                                <value class="mono font-bold"><?= htmlspecialchars($company['ticker']) ?></value>
                            </div>
                            <div class="info-item">
                                <label>Company Name</label>
                                <value><?= htmlspecialchars($company['name']) ?></value>
                            </div>
                            <div class="info-item">
                                <label>Country</label>
                                <value>
                                    <span class="country-flag"><?= getCountryFlag($company['country']) ?></span>
                                    <?= htmlspecialchars($company['country']) ?>
                                </value>
                            </div>
                            <div class="info-item">
                                <label>Market</label>
                                <value><?= htmlspecialchars($company['market'] ?: 'N/A') ?></value>
                            </div>
                            <div class="info-item">
                                <label>Share Type</label>
                                <value>
                                    <span class="share-type-badge">
                                        <?= htmlspecialchars($company['share_type_code'] ?: 'N/A') ?>
                                    </span>
                                    <?= htmlspecialchars($company['share_type_description'] ?: '') ?>
                                </value>
                            </div>
                            <div class="info-item">
                                <label>Status</label>
                                <value>
                                    <?php if ($company['delisted']): ?>
                                        <span class="status-badge delisted">
                                            <i class="fas fa-times-circle"></i> Delisted
                                        </span>
                                    <?php else: ?>
                                        <span class="status-badge active">
                                            <i class="fas fa-check-circle"></i> Active
                                        </span>
                                    <?php endif; ?>
                                </value>
                            </div>
                            <?php if ($company['delisted'] && $company['delisted_date']): ?>
                                <div class="info-item">
                                    <label>Delisted Date</label>
                                    <value><?= date('Y-m-d', strtotime($company['delisted_date'])) ?></value>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Portfolio Information Card -->
                <div class="info-card">
                    <div class="card-header">
                        <h3><i class="fas fa-chart-line"></i> Portfolio Information</h3>
                    </div>
                    <div class="card-content">
                        <div class="portfolio-summary">
                            <div class="summary-item">
                                <div class="summary-number">0</div>
                                <div class="summary-label">Shares Held</div>
                            </div>
                            <div class="summary-item">
                                <div class="summary-number">0 SEK</div>
                                <div class="summary-label">Current Value</div>
                            </div>
                            <div class="summary-item">
                                <div class="summary-number">0 SEK</div>
                                <div class="summary-label">Total Dividends</div>
                            </div>
                            <div class="summary-item">
                                <div class="summary-number">0</div>
                                <div class="summary-label">Transactions</div>
                            </div>
                        </div>
                        <div class="portfolio-note">
                            <i class="fas fa-info-circle"></i>
                            Portfolio integration coming soon
                        </div>
                    </div>
                </div>

                <!-- Market Data Card -->
                <div class="info-card">
                    <div class="card-header">
                        <h3><i class="fas fa-chart-bar"></i> Market Data</h3>
                    </div>
                    <div class="card-content">
                        <div class="market-data">
                            <div class="data-item">
                                <label>Current Price</label>
                                <value>N/A</value>
                            </div>
                            <div class="data-item">
                                <label>Currency</label>
                                <value>SEK</value>
                            </div>
                            <div class="data-item">
                                <label>Market Cap</label>
                                <value>N/A</value>
                            </div>
                            <div class="data-item">
                                <label>P/E Ratio</label>
                                <value>N/A</value>
                            </div>
                            <div class="data-item">
                                <label>Dividend Yield</label>
                                <value>N/A</value>
                            </div>
                            <div class="data-item">
                                <label>Last Updated</label>
                                <value>N/A</value>
                            </div>
                        </div>
                        <div class="market-note">
                            <i class="fas fa-info-circle"></i>
                            Market data integration coming soon
                        </div>
                    </div>
                </div>

                <!-- Actions Card -->
                <div class="info-card">
                    <div class="card-header">
                        <h3><i class="fas fa-cogs"></i> Actions</h3>
                    </div>
                    <div class="card-content">
                        <div class="action-buttons">
                            <button class="btn btn-primary" onclick="editCompany('<?= htmlspecialchars($company['isin']) ?>')">
                                <i class="fas fa-edit"></i> Edit Company
                            </button>
                            <button class="btn btn-secondary" onclick="viewPortfolio('<?= htmlspecialchars($company['isin']) ?>')">
                                <i class="fas fa-chart-pie"></i> View Portfolio
                            </button>
                            <button class="btn btn-secondary" onclick="viewTransactions('<?= htmlspecialchars($company['isin']) ?>')">
                                <i class="fas fa-exchange-alt"></i> View Transactions
                            </button>
                            <button class="btn btn-danger" onclick="deleteCompany('<?= htmlspecialchars($company['isin']) ?>', '<?= htmlspecialchars($company['name']) ?>')">
                                <i class="fas fa-trash"></i> Delete Company
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <div class="empty-icon">
                    <i class="fas fa-building"></i>
                </div>
                <h3>Company Not Found</h3>
                <p>The requested company could not be found in the masterlist.</p>
                <a href="<?= BASE_URL ?>/public/masterlist_management.php" class="btn btn-primary">
                    <i class="fas fa-arrow-left"></i> Back to Masterlist
                </a>
            </div>
        <?php endif; ?>
    </div>

    <script>
        function editCompany(isin) {
            window.location.href = '<?= BASE_URL ?>/public/masterlist_management.php?edit=' + encodeURIComponent(isin);
        }

        function viewPortfolio(isin) {
            alert('Portfolio view coming soon for ' + isin);
        }

        function viewTransactions(isin) {
            alert('Transaction view coming soon for ' + isin);
        }

        function deleteCompany(isin, name) {
            if (confirm('Are you sure you want to delete ' + name + '?')) {
                alert('Delete functionality needs to be implemented');
            }
        }
    </script>
</body>
</html>

<?php
/**
 * Get country flag emoji
 */
function getCountryFlag($countryCode) {
    $flags = [
        'SE' => '🇸🇪',
        'US' => '🇺🇸',
        'FI' => '🇫🇮',
        'NO' => '🇳🇴',
        'DK' => '🇩🇰',
        'DE' => '🇩🇪',
        'GB' => '🇬🇧',
        'FR' => '🇫🇷'
    ];
    
    return $flags[$countryCode] ?? '🏳️';
}
?>