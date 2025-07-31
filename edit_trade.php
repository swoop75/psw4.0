<?php
/**
 * File: edit_trade.php
 * Description: Edit existing trade interface for PSW 4.0
 */

session_start();

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/src/middleware/Auth.php';
require_once __DIR__ . '/src/utils/Localization.php';

// Require authentication
Auth::requireAuth();

// Get trade ID from URL
$tradeId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$tradeId) {
    header('Location: ' . BASE_URL . '/trade_logs.php');
    exit();
}

// Load existing trade data
$existingTrade = null;
try {
    $portfolioDb = Database::getConnection('portfolio');
    $sql = "SELECT * FROM log_trades WHERE trade_id = :trade_id";
    $stmt = $portfolioDb->prepare($sql);
    $stmt->execute([':trade_id' => $tradeId]);
    $existingTrade = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$existingTrade) {
        $_SESSION['flash_error'] = 'Trade not found.';
        header('Location: ' . BASE_URL . '/trade_logs.php');
        exit();
    }
} catch (Exception $e) {
    $_SESSION['flash_error'] = 'Error loading trade: ' . $e->getMessage();
    header('Location: ' . BASE_URL . '/trade_logs.php');
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $portfolioDb = Database::getConnection('portfolio');
        $foundationDb = Database::getConnection('foundation');
        
        // Validate required fields
        $requiredFields = ['trade_date', 'trade_type_id', 'isin', 'shares_traded', 'price_per_share_local', 'currency_local', 'price_per_share_sek', 'total_amount_sek', 'net_amount_sek'];
        $errors = [];
        
        foreach ($requiredFields as $field) {
            if (empty($_POST[$field])) {
                $errors[] = "Field '{$field}' is required";
            }
        }
        
        if (empty($errors)) {
            // Prepare data for update
            $tradeData = [
                'trade_id' => $tradeId,
                'trade_date' => $_POST['trade_date'],
                'settlement_date' => !empty($_POST['settlement_date']) ? $_POST['settlement_date'] : null,
                'trade_type_id' => (int)$_POST['trade_type_id'],
                'isin' => trim($_POST['isin']),
                'ticker' => !empty($_POST['ticker']) ? trim($_POST['ticker']) : null,
                'shares_traded' => (float)$_POST['shares_traded'],
                'price_per_share_local' => !empty($_POST['price_per_share_local']) ? (float)$_POST['price_per_share_local'] : null,
                'total_amount_local' => !empty($_POST['total_amount_local']) ? (float)$_POST['total_amount_local'] : null,
                'currency_local' => !empty($_POST['currency_local']) ? trim($_POST['currency_local']) : null,
                'price_per_share_sek' => (float)$_POST['price_per_share_sek'],
                'total_amount_sek' => (float)$_POST['total_amount_sek'],
                'exchange_rate_used' => !empty($_POST['exchange_rate_used']) ? (float)$_POST['exchange_rate_used'] : null,
                'broker_fees_local' => !empty($_POST['broker_fees_local']) ? (float)$_POST['broker_fees_local'] : 0,
                'broker_fees_sek' => !empty($_POST['broker_fees_sek']) ? (float)$_POST['broker_fees_sek'] : 0,
                'tft_tax_local' => !empty($_POST['tft_tax_local']) ? (float)$_POST['tft_tax_local'] : 0,
                'tft_tax_sek' => !empty($_POST['tft_tax_sek']) ? (float)$_POST['tft_tax_sek'] : 0,
                'tft_rate_percent' => !empty($_POST['tft_rate_percent']) ? (float)$_POST['tft_rate_percent'] : null,
                'net_amount_local' => !empty($_POST['net_amount_local']) ? (float)$_POST['net_amount_local'] : null,
                'net_amount_sek' => (float)$_POST['net_amount_sek'],
                'broker_id' => !empty($_POST['broker_id']) ? (int)$_POST['broker_id'] : null,
                'portfolio_account_group_id' => !empty($_POST['portfolio_account_group_id']) ? (int)$_POST['portfolio_account_group_id'] : null,
                'broker_transaction_id' => !empty($_POST['broker_transaction_id']) ? trim($_POST['broker_transaction_id']) : null,
                'order_type' => !empty($_POST['order_type']) ? $_POST['order_type'] : null,
                'execution_status' => $_POST['execution_status'] ?? 'EXECUTED',
                'notes' => !empty($_POST['notes']) ? trim($_POST['notes']) : null
            ];
            
            // Calculate broker fees percentage
            $totalAmountSek = $tradeData['total_amount_sek'];
            $brokerFeesSek = $tradeData['broker_fees_sek'];
            $tradeData['broker_fees_percent'] = 0;
            
            if ($totalAmountSek > 0) {
                $tradeData['broker_fees_percent'] = ($brokerFeesSek / $totalAmountSek) * 100;
            }
            
            // Update trade
            $sql = "UPDATE log_trades SET 
                trade_date = :trade_date,
                settlement_date = :settlement_date, 
                trade_type_id = :trade_type_id,
                isin = :isin,
                ticker = :ticker,
                shares_traded = :shares_traded,
                price_per_share_local = :price_per_share_local,
                total_amount_local = :total_amount_local,
                currency_local = :currency_local,
                price_per_share_sek = :price_per_share_sek,
                total_amount_sek = :total_amount_sek,
                exchange_rate_used = :exchange_rate_used,
                broker_fees_local = :broker_fees_local,
                broker_fees_sek = :broker_fees_sek,
                broker_fees_percent = :broker_fees_percent,
                tft_tax_local = :tft_tax_local,
                tft_tax_sek = :tft_tax_sek,
                tft_rate_percent = :tft_rate_percent,
                net_amount_local = :net_amount_local,
                net_amount_sek = :net_amount_sek,
                broker_id = :broker_id,
                portfolio_account_group_id = :portfolio_account_group_id,
                broker_transaction_id = :broker_transaction_id,
                order_type = :order_type,
                execution_status = :execution_status,
                notes = :notes,
                updated_at = NOW()
            WHERE trade_id = :trade_id";
            
            $stmt = $portfolioDb->prepare($sql);
            $stmt->execute($tradeData);
            
            $success = "Trade updated successfully!";
            
            // Redirect back to trade logs after successful update
            $_SESSION['flash_success'] = $success;
            header('Location: ' . BASE_URL . '/trade_logs.php');
            exit();
        }
        
    } catch (Exception $e) {
        $error = "Error updating trade: " . $e->getMessage();
    }
}

try {
    $foundationDb = Database::getConnection('foundation');
    
    // Get dropdown data
    $tradeTypes = $foundationDb->query("SELECT trade_type_id, type_code, type_name FROM trade_types WHERE is_active = 1 ORDER BY type_name")->fetchAll(PDO::FETCH_ASSOC);
    $brokers = $foundationDb->query("SELECT broker_id, broker_name FROM brokers ORDER BY broker_name")->fetchAll(PDO::FETCH_ASSOC);
    $accountGroups = $foundationDb->query("SELECT portfolio_account_group_id, portfolio_group_name FROM portfolio_account_groups ORDER BY portfolio_group_name")->fetchAll(PDO::FETCH_ASSOC);
    
    // Get currencies from existing trades and add common ones
    $portfolioDb = Database::getConnection('portfolio');
    $existingCurrencies = $portfolioDb->query("SELECT DISTINCT currency_local FROM log_trades WHERE currency_local IS NOT NULL ORDER BY currency_local")->fetchAll(PDO::FETCH_COLUMN);
    
    // Common currencies
    $commonCurrencies = ['SEK', 'USD', 'EUR', 'GBP', 'NOK', 'DKK', 'CHF', 'CAD', 'AUD', 'JPY'];
    $currencies = array_unique(array_merge($commonCurrencies, $existingCurrencies));
    sort($currencies);
    
} catch (Exception $e) {
    $tradeTypes = [];
    $brokers = [];
    $accountGroups = [];
    $currencies = ['SEK', 'USD', 'EUR', 'GBP'];
    $dbError = $e->getMessage();
}

// Helper function to get field value (POST data takes precedence for validation errors)
function getFieldValue($fieldName, $existingTrade) {
    return $_POST[$fieldName] ?? $existingTrade[$fieldName] ?? '';
}

// Initialize variables for template
$pageTitle = 'Edit Trade - PSW 4.0';
$pageDescription = 'Edit trade execution record';
$additionalCSS = [];
$additionalJS = [];

// Prepare content
ob_start();
?>
<div class="psw-content">
    <!-- Page Header -->
    <div class="psw-card psw-mb-6">
        <div class="psw-card-header">
            <h1 class="psw-card-title">
                <i class="fas fa-edit psw-card-title-icon"></i>
                Edit Trade
            </h1>
            <p class="psw-card-subtitle">Update trade execution details for <?php echo htmlspecialchars($existingTrade['isin']); ?></p>
        </div>
    </div>

    <?php if (isset($success)): ?>
        <div class="psw-alert psw-alert-success psw-mb-4">
            <i class="fas fa-check-circle"></i>
            <?php echo htmlspecialchars($success); ?>
            <a href="<?php echo BASE_URL; ?>/trade_logs.php" class="psw-btn psw-btn-primary psw-btn-sm" style="margin-left: 1rem;">
                <i class="fas fa-eye"></i> View Trade Logs
            </a>
        </div>
    <?php endif; ?>

    <?php if (isset($error)): ?>
        <div class="psw-alert psw-alert-error psw-mb-4">
            <i class="fas fa-exclamation-triangle"></i>
            <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <div class="psw-alert psw-alert-error psw-mb-4">
            <i class="fas fa-exclamation-triangle"></i>
            <strong>Please fix the following errors:</strong>
            <ul style="margin: 0.5rem 0 0 1.5rem;">
                <?php foreach ($errors as $err): ?>
                    <li><?php echo htmlspecialchars($err); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <!-- Trade Form -->
    <form method="POST" class="psw-form">
        <div class="psw-card">
            <div class="psw-card-header">
                <h2 class="psw-card-title">
                    <i class="fas fa-info-circle psw-card-title-icon"></i>
                    Trade Details
                </h2>
            </div>
            <div class="psw-card-content">
                <!-- Basic Trade Information -->
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
                    <div class="psw-form-group">
                        <label class="psw-form-label" for="trade_date">
                            <i class="fas fa-calendar-alt"></i>
                            Trade Date *
                        </label>
                        <input type="date" id="trade_date" name="trade_date" class="psw-form-input" 
                               value="<?php echo getFieldValue('trade_date', $existingTrade); ?>" required>
                    </div>
                    
                    <div class="psw-form-group">
                        <label class="psw-form-label" for="settlement_date">
                            <i class="fas fa-calendar-check"></i>
                            Settlement Date (T+2 default)
                        </label>
                        <input type="date" id="settlement_date" name="settlement_date" class="psw-form-input" 
                               value="<?php echo getFieldValue('settlement_date', $existingTrade); ?>" 
                               placeholder="Auto-calculated as T+2">
                    </div>
                    
                    <div class="psw-form-group">
                        <label class="psw-form-label" for="trade_type_id">Trade Type *</label>
                        <select id="trade_type_id" name="trade_type_id" class="psw-form-input" required>
                            <option value="">Select trade type...</option>
                            <?php foreach ($tradeTypes as $type): ?>
                                <option value="<?php echo $type['trade_type_id']; ?>" 
                                        <?php echo getFieldValue('trade_type_id', $existingTrade) == $type['trade_type_id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($type['type_name'] . ' (' . $type['type_code'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- Security Information -->
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
                    <div class="psw-form-group">
                        <label class="psw-form-label" for="isin">
                            <i class="fas fa-search"></i>
                            ISIN / Company Search *
                        </label>
                        <div class="autocomplete-container">
                            <input type="text" id="isin" name="isin" class="psw-form-input" 
                                   placeholder="Type ISIN, company name, or ticker..." maxlength="50"
                                   value="<?php echo htmlspecialchars(getFieldValue('isin', $existingTrade)); ?>" 
                                   autocomplete="off" required>
                            <div id="isin-suggestions" class="autocomplete-suggestions"></div>
                        </div>
                        <small class="psw-form-help">Search by ISIN (e.g., SE0022726485), company name (e.g., Betsson), or ticker (e.g., BETS B)</small>
                    </div>
                    
                    <div class="psw-form-group">
                        <label class="psw-form-label" for="ticker">Ticker</label>
                        <input type="text" id="ticker" name="ticker" class="psw-form-input" 
                               placeholder="e.g., AAPL" maxlength="20"
                               value="<?php echo htmlspecialchars($_POST['ticker'] ?? ''); ?>">
                    </div>
                    
                    <div class="psw-form-group">
                        <label class="psw-form-label" for="shares_traded">Shares *</label>
                        <input type="number" id="shares_traded" name="shares_traded" class="psw-form-input" 
                               step="0.0001" min="0.0001" placeholder="100"
                               value="<?php echo $_POST['shares_traded'] ?? ''; ?>" required>
                    </div>
                </div>

                <!-- Pricing - Local Currency -->
                <fieldset style="border: 1px solid var(--border-primary); border-radius: var(--border-radius); padding: 1.5rem; margin-bottom: 2rem;">
                    <legend style="padding: 0 0.5rem; color: var(--primary-accent); font-weight: 600;">Local Currency Pricing</legend>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem;">
                        <div class="psw-form-group">
                            <label class="psw-form-label" for="currency_local">Currency *</label>
                            <select id="currency_local" name="currency_local" class="psw-form-input" required>
                                <option value="">Select currency...</option>
                                <?php foreach ($currencies as $currency): ?>
                                    <option value="<?php echo $currency; ?>" 
                                            <?php echo ($_POST['currency_local'] ?? '') == $currency ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($currency); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="psw-form-group">
                            <label class="psw-form-label" for="price_per_share_local">Price per Share</label>
                            <input type="number" id="price_per_share_local" name="price_per_share_local" class="psw-form-input" 
                                   step="0.01" min="0" placeholder="227.50"
                                   value="<?php echo $_POST['price_per_share_local'] ?? ''; ?>">
                        </div>
                        
                        <div class="psw-form-group">
                            <label class="psw-form-label" for="total_amount_local">Total Amount</label>
                            <input type="number" id="total_amount_local" name="total_amount_local" class="psw-form-input" 
                                   step="0.01" min="0" placeholder="22750.00"
                                   value="<?php echo $_POST['total_amount_local'] ?? ''; ?>">
                        </div>
                    </div>
                </fieldset>

                <!-- Pricing - SEK -->
                <fieldset style="border: 1px solid var(--border-primary); border-radius: var(--border-radius); padding: 1.5rem; margin-bottom: 2rem;">
                    <legend style="padding: 0 0.5rem; color: var(--primary-accent); font-weight: 600;">SEK Pricing *</legend>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem;">
                        <div class="psw-form-group">
                            <label class="psw-form-label" for="price_per_share_sek">Price per Share (SEK) *</label>
                            <input type="number" id="price_per_share_sek" name="price_per_share_sek" class="psw-form-input" 
                                   step="0.01" min="0" placeholder="2475.00"
                                   value="<?php echo $_POST['price_per_share_sek'] ?? ''; ?>" required>
                        </div>
                        
                        <div class="psw-form-group">
                            <label class="psw-form-label" for="total_amount_sek">Total Amount (SEK) *</label>
                            <input type="number" id="total_amount_sek" name="total_amount_sek" class="psw-form-input" 
                                   step="0.01" min="0" placeholder="247500.00"
                                   value="<?php echo $_POST['total_amount_sek'] ?? ''; ?>" required>
                        </div>
                        
                        <div class="psw-form-group">
                            <label class="psw-form-label" for="exchange_rate_used">Exchange Rate</label>
                            <input type="number" id="exchange_rate_used" name="exchange_rate_used" class="psw-form-input" 
                                   step="0.000001" min="0" placeholder="10.8830"
                                   value="<?php echo $_POST['exchange_rate_used'] ?? ''; ?>">
                        </div>
                    </div>
                </fieldset>

                <!-- Fees and Taxes -->
                <fieldset style="border: 1px solid var(--border-primary); border-radius: var(--border-radius); padding: 1.5rem; margin-bottom: 2rem;">
                    <legend style="padding: 0 0.5rem; color: var(--primary-accent); font-weight: 600;">Fees & Taxes</legend>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem;">
                        <div class="psw-form-group">
                            <label class="psw-form-label" for="broker_fees_local">Broker Fees (Local)</label>
                            <input type="number" id="broker_fees_local" name="broker_fees_local" class="psw-form-input" 
                                   step="0.01" min="0" placeholder="14.95"
                                   value="<?php echo $_POST['broker_fees_local'] ?? '0'; ?>">
                        </div>
                        
                        <div class="psw-form-group">
                            <label class="psw-form-label" for="broker_fees_sek">Broker Fees (SEK)</label>
                            <input type="number" id="broker_fees_sek" name="broker_fees_sek" class="psw-form-input" 
                                   step="0.01" min="0" placeholder="162.74"
                                   value="<?php echo $_POST['broker_fees_sek'] ?? '0'; ?>">
                        </div>
                        
                        <div class="psw-form-group">
                            <label class="psw-form-label" for="tft_tax_local">Transaction Tax (Local)</label>
                            <input type="number" id="tft_tax_local" name="tft_tax_local" class="psw-form-input" 
                                   step="0.01" min="0" placeholder="0.00"
                                   value="<?php echo $_POST['tft_tax_local'] ?? '0'; ?>">
                        </div>
                        
                        <div class="psw-form-group">
                            <label class="psw-form-label" for="tft_tax_sek">Transaction Tax (SEK)</label>
                            <input type="number" id="tft_tax_sek" name="tft_tax_sek" class="psw-form-input" 
                                   step="0.01" min="0" placeholder="0.00"
                                   value="<?php echo $_POST['tft_tax_sek'] ?? '0'; ?>">
                        </div>
                        
                        <div class="psw-form-group">
                            <label class="psw-form-label" for="tft_rate_percent">Tax Rate (%)</label>
                            <input type="number" id="tft_rate_percent" name="tft_rate_percent" class="psw-form-input" 
                                   step="0.01" min="0" max="100" placeholder="0.50"
                                   value="<?php echo $_POST['tft_rate_percent'] ?? ''; ?>">
                        </div>
                    </div>
                </fieldset>

                <!-- Net Amounts -->
                <fieldset style="border: 1px solid var(--border-primary); border-radius: var(--border-radius); padding: 1.5rem; margin-bottom: 2rem;">
                    <legend style="padding: 0 0.5rem; color: var(--primary-accent); font-weight: 600;">Net Amounts</legend>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem;">
                        <div class="psw-form-group">
                            <label class="psw-form-label" for="net_amount_local">Net Amount (Local)</label>
                            <input type="number" id="net_amount_local" name="net_amount_local" class="psw-form-input" 
                                   step="0.01" placeholder="22735.05"
                                   value="<?php echo $_POST['net_amount_local'] ?? ''; ?>">
                        </div>
                        
                        <div class="psw-form-group">
                            <label class="psw-form-label" for="net_amount_sek">Net Amount (SEK) *</label>
                            <input type="number" id="net_amount_sek" name="net_amount_sek" class="psw-form-input" 
                                   step="0.01" min="0" placeholder="247337.26"
                                   value="<?php echo $_POST['net_amount_sek'] ?? ''; ?>" required>
                        </div>
                    </div>
                </fieldset>

                <!-- Account Information -->
                <fieldset style="border: 1px solid var(--border-primary); border-radius: var(--border-radius); padding: 1.5rem; margin-bottom: 2rem;">
                    <legend style="padding: 0 0.5rem; color: var(--primary-accent); font-weight: 600;">Account Information</legend>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem;">
                        <div class="psw-form-group">
                            <label class="psw-form-label" for="broker_id">Broker</label>
                            <select id="broker_id" name="broker_id" class="psw-form-input">
                                <option value="">Select broker...</option>
                                <?php foreach ($brokers as $broker): ?>
                                    <option value="<?php echo $broker['broker_id']; ?>" 
                                            <?php echo ($_POST['broker_id'] ?? '') == $broker['broker_id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($broker['broker_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="psw-form-group">
                            <label class="psw-form-label" for="portfolio_account_group_id">Account Group</label>
                            <select id="portfolio_account_group_id" name="portfolio_account_group_id" class="psw-form-input">
                                <option value="">Select account group...</option>
                                <?php foreach ($accountGroups as $group): ?>
                                    <option value="<?php echo $group['portfolio_account_group_id']; ?>" 
                                            <?php echo ($_POST['portfolio_account_group_id'] ?? '') == $group['portfolio_account_group_id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($group['portfolio_group_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </fieldset>

                <!-- Additional Details -->
                <fieldset style="border: 1px solid var(--border-primary); border-radius: var(--border-radius); padding: 1.5rem; margin-bottom: 2rem;">
                    <legend style="padding: 0 0.5rem; color: var(--primary-accent); font-weight: 600;">Additional Details</legend>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem; margin-bottom: 1.5rem;">
                        <div class="psw-form-group">
                            <label class="psw-form-label" for="broker_transaction_id">Transaction ID</label>
                            <input type="text" id="broker_transaction_id" name="broker_transaction_id" class="psw-form-input" 
                                   placeholder="TXN123456" maxlength="100"
                                   value="<?php echo htmlspecialchars($_POST['broker_transaction_id'] ?? ''); ?>">
                        </div>
                        
                        <div class="psw-form-group">
                            <label class="psw-form-label" for="order_type">Order Type</label>
                            <select id="order_type" name="order_type" class="psw-form-input">
                                <option value="">Select order type...</option>
                                <option value="MARKET" <?php echo ($_POST['order_type'] ?? '') == 'MARKET' ? 'selected' : ''; ?>>Market</option>
                                <option value="LIMIT" <?php echo ($_POST['order_type'] ?? '') == 'LIMIT' ? 'selected' : ''; ?>>Limit</option>
                                <option value="STOP" <?php echo ($_POST['order_type'] ?? '') == 'STOP' ? 'selected' : ''; ?>>Stop</option>
                                <option value="OTHER" <?php echo ($_POST['order_type'] ?? '') == 'OTHER' ? 'selected' : ''; ?>>Other</option>
                            </select>
                        </div>
                        
                        <div class="psw-form-group">
                            <label class="psw-form-label" for="execution_status">Status</label>
                            <select id="execution_status" name="execution_status" class="psw-form-input">
                                <option value="EXECUTED" <?php echo ($_POST['execution_status'] ?? 'EXECUTED') == 'EXECUTED' ? 'selected' : ''; ?>>Executed</option>
                                <option value="PARTIAL" <?php echo ($_POST['execution_status'] ?? '') == 'PARTIAL' ? 'selected' : ''; ?>>Partial</option>
                                <option value="CANCELLED" <?php echo ($_POST['execution_status'] ?? '') == 'CANCELLED' ? 'selected' : ''; ?>>Cancelled</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="psw-form-group">
                        <label class="psw-form-label" for="notes">Notes</label>
                        <textarea id="notes" name="notes" class="psw-form-input" rows="3" 
                                  placeholder="Optional notes about this trade..."><?php echo htmlspecialchars($_POST['notes'] ?? ''); ?></textarea>
                    </div>
                </fieldset>

                <!-- Form Actions -->
                <div style="display: flex; gap: 1rem; justify-content: flex-end; margin-top: 2rem;">
                    <a href="<?php echo BASE_URL; ?>/trade_logs.php" class="psw-btn psw-btn-secondary">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                    <button type="submit" class="psw-btn psw-btn-primary">
                        <i class="fas fa-save"></i> Update Trade
                    </button>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
// ISIN Autocomplete functionality
let searchTimeout;
let selectedSecurity = null;
let currentSearchResults = [];

document.addEventListener('DOMContentLoaded', function() {
    setupIsinAutocomplete();
    setupSekAutoCopy(); // Set up SEK auto-copy functionality immediately
    
    // Auto-calculate functionality
    const sharesInput = document.getElementById('shares_traded');
    const priceLocalInput = document.getElementById('price_per_share_local');
    const totalLocalInput = document.getElementById('total_amount_local');
    const priceSekInput = document.getElementById('price_per_share_sek');
    const totalSekInput = document.getElementById('total_amount_sek');
    const exchangeRateInput = document.getElementById('exchange_rate_used');
    
    function setupIsinAutocomplete() {
        const isinInput = document.getElementById('isin');
        const suggestionsDiv = document.getElementById('isin-suggestions');
        
        if (!isinInput || !suggestionsDiv) {
            console.error('ISIN input or suggestions div not found!');
            return;
        }
        
        isinInput.addEventListener('input', function() {
            const query = this.value.trim();
            
            if (query.length < 2) {
                hideSuggestions();
                return;
            }
            
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                searchIsin(query);
            }, 300);
        });
        
        isinInput.addEventListener('blur', function() {
            // Delay hiding to allow for click on suggestion
            setTimeout(() => {
                hideSuggestions();
            }, 200);
        });
        
        isinInput.addEventListener('keydown', function(e) {
            const suggestions = suggestionsDiv.querySelectorAll('.suggestion-item');
            const activeSuggestion = suggestionsDiv.querySelector('.suggestion-item.active');
            
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                let nextItem = activeSuggestion ? activeSuggestion.nextElementSibling : suggestions[0];
                if (nextItem) {
                    if (activeSuggestion) activeSuggestion.classList.remove('active');
                    nextItem.classList.add('active');
                }
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                let prevItem = activeSuggestion ? activeSuggestion.previousElementSibling : suggestions[suggestions.length - 1];
                if (prevItem) {
                    if (activeSuggestion) activeSuggestion.classList.remove('active');
                    prevItem.classList.add('active');
                }
            } else if (e.key === 'Enter') {
                e.preventDefault();
                if (activeSuggestion) {
                    selectSecurity(JSON.parse(activeSuggestion.dataset.security));
                }
            } else if (e.key === 'Escape') {
                hideSuggestions();
            }
        });
    }
    
    async function searchIsin(query) {
        try {
            const url = `<?php echo BASE_URL; ?>/api/search_isin.php?q=${encodeURIComponent(query)}`;
            const response = await fetch(url);
            const results = await response.json();
            
            if (response.ok) {
                showSuggestions(results);
            } else {
                console.error('Search error:', results.error || results);
                hideSuggestions();
            }
        } catch (error) {
            console.error('Search error:', error);
            hideSuggestions();
        }
    }
    
    function showSuggestions(results) {
        const suggestionsDiv = document.getElementById('isin-suggestions');
        
        if (results.length === 0) {
            hideSuggestions();
            return;
        }
        
        const html = results.map(security => `
            <div class="suggestion-item" data-security='${JSON.stringify(security)}' onclick="selectSecurity(${JSON.stringify(security).replace(/'/g, '&apos;')})">
                <div class="suggestion-main">
                    <strong>${security.isin}</strong> - ${security.company_name}
                </div>
                <div class="suggestion-details">
                    ${security.ticker ? `Ticker: ${security.ticker} | ` : ''}
                    ${security.country ? `Country: ${security.country} | ` : ''}
                    ${security.currency ? `Currency: ${security.currency}` : ''}
                </div>
            </div>
        `).join('');
        
        suggestionsDiv.innerHTML = html;
        suggestionsDiv.style.display = 'block';
    }
    
    function hideSuggestions() {
        const suggestionsDiv = document.getElementById('isin-suggestions');
        suggestionsDiv.style.display = 'none';
        suggestionsDiv.innerHTML = '';
    }
    
    window.selectSecurity = function(security) {
        selectedSecurity = security;
        
        console.log('Selected security:', security); // Debug log
        
        // Populate basic security fields
        document.getElementById('isin').value = security.isin;
        
        // Handle ticker field - check for different possible ticker fields
        const tickerInput = document.getElementById('ticker');
        if (security.ticker && security.ticker.trim() !== '') {
            tickerInput.value = security.ticker.trim();
            console.log('Ticker set to:', security.ticker); // Debug log
        } else {
            console.log('No ticker available or ticker is empty'); // Debug log
            tickerInput.value = ''; // Clear if no ticker
        }
        
        // Set currency
        if (security.currency) {
            const currencySelect = document.getElementById('currency_local');
            for (let option of currencySelect.options) {
                if (option.value === security.currency) {
                    option.selected = true;
                    break;
                }
            }
        }
        
        // Auto-set settlement date (T+2 business days from today if trade_date is today)
        const tradeDateInput = document.getElementById('trade_date');
        const settlementDateInput = document.getElementById('settlement_date');
        
        if (tradeDateInput.value === getCurrentDate() && !settlementDateInput.value) {
            const settlementDate = calculateSettlementDate(new Date(), 2);
            settlementDateInput.value = formatDate(settlementDate);
        }
        
        // Set execution status to EXECUTED (most common)
        const executionStatusSelect = document.getElementById('execution_status');
        if (!executionStatusSelect.value) {
            executionStatusSelect.value = 'EXECUTED';
        }
        
        // Auto-set some defaults based on security type/country
        if (security.country) {
            // For Swedish securities, pre-fill some common values
            if (security.country === 'Sweden' || security.country === 'SE' || security.currency === 'SEK') {
                // Swedish securities often have no transaction tax
                if (!document.getElementById('tft_tax_local').value) {
                    document.getElementById('tft_tax_local').value = '0.00';
                }
                if (!document.getElementById('tft_tax_sek').value) {
                    document.getElementById('tft_tax_sek').value = '0.00';
                }
                if (!document.getElementById('tft_rate_percent').value) {
                    document.getElementById('tft_rate_percent').value = '0.00';
                }
            }
            
            // For UK securities, set stamp duty rate
            if (security.country === 'United Kingdom' || security.country === 'UK' || security.country === 'GB') {
                if (!document.getElementById('tft_rate_percent').value) {
                    document.getElementById('tft_rate_percent').value = '0.50'; // UK stamp duty
                }
            }
        }
        
        // Set order type to MARKET as default (most common)
        const orderTypeSelect = document.getElementById('order_type');
        if (!orderTypeSelect.value) {
            orderTypeSelect.value = 'MARKET';
        }
        
        // If currency is SEK, set exchange rate to 1.0 and trigger auto-copy
        if (security.currency === 'SEK') {
            const exchangeRateInput = document.getElementById('exchange_rate_used');
            if (!exchangeRateInput.value) {
                exchangeRateInput.value = '1.000000';
            }
            
            // Trigger the currency change event to activate SEK auto-copy
            const currencySelect = document.getElementById('currency_local');
            currencySelect.dispatchEvent(new Event('change'));
        }
        
        hideSuggestions();
        
        // Show success indicator with more details
        const isinInput = document.getElementById('isin');
        isinInput.style.borderColor = 'var(--success-color)';
        isinInput.style.boxShadow = '0 0 0 2px var(--success-color-light)';
        
        // Show a brief success message
        showSuccessMessage(`Security selected: ${security.company_name}${security.ticker ? ' (' + security.ticker + ')' : ''}`);
        
        setTimeout(() => {
            isinInput.style.borderColor = '';
            isinInput.style.boxShadow = '';
        }, 2000);
        
        // Focus next field
        document.getElementById('shares_traded').focus();
    }
    
    function getCurrentDate() {
        const today = new Date();
        return formatDate(today);
    }
    
    function calculateSettlementDate(tradeDate, businessDays) {
        const settlement = new Date(tradeDate);
        let daysAdded = 0;
        
        while (daysAdded < businessDays) {
            settlement.setDate(settlement.getDate() + 1);
            // Skip weekends (Saturday = 6, Sunday = 0)
            if (settlement.getDay() !== 0 && settlement.getDay() !== 6) {
                daysAdded++;
            }
        }
        
        return settlement;
    }
    
    function formatDate(date) {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    }
    
    function setupSekAutoCopy() {
        const priceLocalInput = document.getElementById('price_per_share_local');
        const totalLocalInput = document.getElementById('total_amount_local');
        const feesLocalInput = document.getElementById('broker_fees_local');
        const taxLocalInput = document.getElementById('tft_tax_local');
        const netLocalInput = document.getElementById('net_amount_local');
        
        const priceSekInput = document.getElementById('price_per_share_sek');
        const totalSekInput = document.getElementById('total_amount_sek');
        const feesSekInput = document.getElementById('broker_fees_sek');
        const taxSekInput = document.getElementById('tft_tax_sek');
        const netSekInput = document.getElementById('net_amount_sek');
        const currencySelect = document.getElementById('currency_local');
        
        // Function to check if currency is SEK and copy values accordingly
        function handleSekAutoCopy() {
            if (currencySelect.value === 'SEK') {
                // Copy price per share
                if (priceLocalInput.value && priceLocalInput.value !== priceSekInput.value) {
                    priceSekInput.value = priceLocalInput.value;
                }
                
                // Copy total amount
                if (totalLocalInput.value && totalLocalInput.value !== totalSekInput.value) {
                    totalSekInput.value = totalLocalInput.value;
                }
                
                // Copy broker fees
                if (feesLocalInput.value && feesLocalInput.value !== feesSekInput.value) {
                    feesSekInput.value = feesLocalInput.value;
                }
                
                // Copy transaction tax
                if (taxLocalInput.value && taxLocalInput.value !== taxSekInput.value) {
                    taxSekInput.value = taxLocalInput.value;
                }
                
                // Copy net amount
                if (netLocalInput.value && netLocalInput.value !== netSekInput.value) {
                    netSekInput.value = netLocalInput.value;
                }
                
                // Set exchange rate to 1.0 for SEK
                const exchangeRateInput = document.getElementById('exchange_rate_used');
                if (!exchangeRateInput.value || exchangeRateInput.value === '0') {
                    exchangeRateInput.value = '1.000000';
                }
            }
        }
        
        // Add event listeners for real-time copying when currency is SEK
        priceLocalInput.addEventListener('input', function() {
            if (currencySelect.value === 'SEK' && this.value) {
                priceSekInput.value = this.value;
                calculateTotals();
            }
        });
        
        totalLocalInput.addEventListener('input', function() {
            if (currencySelect.value === 'SEK' && this.value) {
                totalSekInput.value = this.value;
                calculateNetAmounts();
            }
        });
        
        feesLocalInput.addEventListener('input', function() {
            if (currencySelect.value === 'SEK' && this.value) {
                feesSekInput.value = this.value;
                calculateNetAmounts();
            }
        });
        
        taxLocalInput.addEventListener('input', function() {
            if (currencySelect.value === 'SEK' && this.value) {
                taxSekInput.value = this.value;
                calculateNetAmounts();
            }
        });
        
        netLocalInput.addEventListener('input', function() {
            if (currencySelect.value === 'SEK' && this.value) {
                netSekInput.value = this.value;
            }
        });
        
        // Handle currency change - if changed to SEK, copy all existing values
        currencySelect.addEventListener('change', function() {
            if (this.value === 'SEK') {
                handleSekAutoCopy();
                calculateTotals();
            }
        });
        
        // Initial copy if currency is already SEK
        if (currencySelect.value === 'SEK') {
            handleSekAutoCopy();
        }
    }
    
    function showSuccessMessage(message) {
        // Create success message element
        const successDiv = document.createElement('div');
        successDiv.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            background: var(--success-color);
            color: white;
            padding: 12px 20px;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow-lg);
            z-index: 10000;
            font-size: var(--font-size-sm);
            font-weight: 500;
            max-width: 300px;
        `;
        successDiv.innerHTML = `<i class="fas fa-check-circle" style="margin-right: 8px;"></i>${message}`;
        
        document.body.appendChild(successDiv);
        
        // Remove after 3 seconds
        setTimeout(() => {
            if (successDiv.parentNode) {
                successDiv.parentNode.removeChild(successDiv);
            }
        }, 3000);
    }
    
    function calculateTotals() {
        const shares = parseFloat(sharesInput.value) || 0;
        const priceLocal = parseFloat(priceLocalInput.value) || 0;
        const priceSek = parseFloat(priceSekInput.value) || 0;
        
        if (shares > 0) {
            if (priceLocal > 0) {
                totalLocalInput.value = (shares * priceLocal).toFixed(2);
            }
            if (priceSek > 0) {
                totalSekInput.value = (shares * priceSek).toFixed(2);
            }
        }
        
        // Calculate exchange rate if both local and SEK prices are available
        if (priceLocal > 0 && priceSek > 0) {
            const rate = priceSek / priceLocal;
            exchangeRateInput.value = rate.toFixed(6);
        }
        
        calculateNetAmounts();
    }
    
    function calculateNetAmounts() {
        const totalLocal = parseFloat(totalLocalInput.value) || 0;
        const totalSek = parseFloat(totalSekInput.value) || 0;
        const feesLocal = parseFloat(document.getElementById('broker_fees_local').value) || 0;
        const feesSek = parseFloat(document.getElementById('broker_fees_sek').value) || 0;
        const taxLocal = parseFloat(document.getElementById('tft_tax_local').value) || 0;
        const taxSek = parseFloat(document.getElementById('tft_tax_sek').value) || 0;
        
        const tradeType = document.getElementById('trade_type_id');
        const selectedOption = tradeType.options[tradeType.selectedIndex];
        const isBuy = selectedOption.text.includes('BUY') || selectedOption.text.includes('Buy');
        
        if (totalLocal > 0) {
            const netLocal = isBuy ? totalLocal + feesLocal + taxLocal : totalLocal - feesLocal - taxLocal;
            document.getElementById('net_amount_local').value = netLocal.toFixed(2);
        }
        
        if (totalSek > 0) {
            const netSek = isBuy ? totalSek + feesSek + taxSek : totalSek - feesSek - taxSek;
            document.getElementById('net_amount_sek').value = netSek.toFixed(2);
        }
    }
    
    // Add event listeners
    sharesInput.addEventListener('input', calculateTotals);
    priceLocalInput.addEventListener('input', calculateTotals);
    priceSekInput.addEventListener('input', calculateTotals);
    document.getElementById('broker_fees_local').addEventListener('input', calculateNetAmounts);
    document.getElementById('broker_fees_sek').addEventListener('input', calculateNetAmounts);
    document.getElementById('tft_tax_local').addEventListener('input', calculateNetAmounts);
    document.getElementById('tft_tax_sek').addEventListener('input', calculateNetAmounts);
    document.getElementById('trade_type_id').addEventListener('change', calculateNetAmounts);
    
    // Auto-calculate settlement date (T+2) whenever trade date changes
    const tradeDate = document.getElementById('trade_date');
    const settlementDate = document.getElementById('settlement_date');
    
    // Calculate settlement date on page load if trade date exists
    if (tradeDate.value) {
        const date = new Date(tradeDate.value);
        const settlement = calculateSettlementDate(date, 2);
        if (!settlementDate.value) {
            settlementDate.value = formatDate(settlement);
        }
    }
    
    // Auto-calculate settlement whenever trade date changes
    tradeDate.addEventListener('change', function() {
        if (this.value) {
            const date = new Date(this.value);
            const settlement = calculateSettlementDate(date, 2);
            settlementDate.value = formatDate(settlement);
            
            // Show brief visual feedback
            settlementDate.style.borderColor = 'var(--success-color)';
            setTimeout(() => {
                settlementDate.style.borderColor = '';
            }, 1000);
        }
    });
});
</script>

<style>
.psw-alert {
    padding: var(--spacing-4);
    border-radius: var(--border-radius);
    margin-bottom: var(--spacing-4);
    display: flex;
    align-items: center;
    gap: var(--spacing-3);
}

.psw-alert-success {
    background: var(--success-color-light);
    color: var(--success-color);
    border: 1px solid var(--success-color);
}

.psw-alert-error {
    background: var(--error-color-light);
    color: var(--error-color);
    border: 1px solid var(--error-color);
}

fieldset {
    border: 1px solid var(--border-primary);
    border-radius: var(--border-radius);
    margin-bottom: var(--spacing-4);
}

legend {
    font-weight: 600;
    color: var(--primary-accent);
    padding: 0 var(--spacing-2);
}

.psw-form-group textarea {
    resize: vertical;
    min-height: 80px;
}

.psw-form-label i {
    margin-right: 0.5rem;
    color: var(--primary-accent);
}

.psw-form-help {
    display: block;
    margin-top: 0.25rem;
    font-size: var(--font-size-xs);
    color: var(--text-secondary);
    font-style: italic;
}

/* ISIN Autocomplete Styles */
.autocomplete-container {
    position: relative;
}

.autocomplete-suggestions {
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    background: var(--bg-card);
    border: 1px solid var(--border-primary);
    border-top: none;
    border-radius: 0 0 var(--border-radius) var(--border-radius);
    box-shadow: var(--shadow-lg);
    max-height: 300px;
    overflow-y: auto;
    z-index: 1000;
    display: none;
}

.suggestion-item {
    padding: var(--spacing-3);
    cursor: pointer;
    border-bottom: 1px solid var(--border-secondary);
    transition: background-color 0.2s ease;
}

.suggestion-item:last-child {
    border-bottom: none;
}

.suggestion-item:hover,
.suggestion-item.active {
    background: var(--primary-accent-light);
}

.suggestion-main {
    font-size: var(--font-size-sm);
    font-weight: 500;
    color: var(--text-primary);
    margin-bottom: 0.25rem;
}

.suggestion-details {
    font-size: var(--font-size-xs);
    color: var(--text-secondary);
}

.autocomplete-suggestions::-webkit-scrollbar {
    width: 6px;
}

.autocomplete-suggestions::-webkit-scrollbar-track {
    background: var(--bg-tertiary);
}

.autocomplete-suggestions::-webkit-scrollbar-thumb {
    background: var(--border-primary);
    border-radius: 3px;
}

.autocomplete-suggestions::-webkit-scrollbar-thumb:hover {
    background: var(--text-secondary);
}
</style>
<?php
$content = ob_get_clean();

// Include base layout
include __DIR__ . '/templates/layouts/base-redesign.php';
?>