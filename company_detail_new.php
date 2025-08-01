<?php
/**
 * File: company_detail_new.php
 * Description: Company detail page with strategy group management for PSW 4.0
 */

session_start();

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/src/middleware/Auth.php';
require_once __DIR__ . '/src/utils/Localization.php';

// Require authentication
Auth::requireAuth();

// Get ISIN from URL
$isin = $_GET['isin'] ?? '';

if (!$isin) {
    header('Location: ' . BASE_URL . '/portfolio_overview.php');
    exit;
}

// Handle strategy group update
if ($_POST['action'] ?? '' === 'update_strategy_group') {
    $newStrategyGroupId = (int)($_POST['strategy_group_id'] ?? 0);
    
    try {
        $portfolioDb = Database::getConnection('portfolio');
        
        // Check if portfolio table has strategy_group_id column, if not add it
        $checkColumnSql = "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS 
                          WHERE TABLE_SCHEMA = 'psw_portfolio' 
                          AND TABLE_NAME = 'portfolio' 
                          AND COLUMN_NAME = 'strategy_group_id'";
        $checkStmt = $portfolioDb->prepare($checkColumnSql);
        $checkStmt->execute();
        
        if ($checkStmt->rowCount() === 0) {
            // Add strategy_group_id column to portfolio table
            $addColumnSql = "ALTER TABLE psw_portfolio.portfolio 
                            ADD COLUMN strategy_group_id INT NULL,
                            ADD CONSTRAINT fk_portfolio_strategy_group 
                            FOREIGN KEY (strategy_group_id) 
                            REFERENCES psw_foundation.portfolio_strategy_groups(strategy_group_id)";
            $portfolioDb->exec($addColumnSql);
        }
        
        // Update strategy group
        $updateSql = "UPDATE psw_portfolio.portfolio 
                     SET strategy_group_id = ?, updated_at = CURRENT_TIMESTAMP 
                     WHERE isin = ? AND is_active = 1";
        $updateStmt = $portfolioDb->prepare($updateSql);
        $updateStmt->execute([$newStrategyGroupId, $isin]);
        
        $successMessage = "Strategy group updated successfully!";
        
    } catch (Exception $e) {
        $errorMessage = "Error updating strategy group: " . $e->getMessage();
    }
}

try {
    $portfolioDb = Database::getConnection('portfolio');
    $foundationDb = Database::getConnection('foundation');
    $marketDb = Database::getConnection('marketdata');
    
    // Get company details from portfolio
    $companySql = "SELECT 
                    p.portfolio_id,
                    p.isin,
                    p.ticker,
                    p.company_name,
                    p.shares_held,
                    p.average_cost_price_sek,
                    p.total_cost_sek,
                    p.latest_price_local as latest_price,
                    p.currency_local as price_currency,
                    p.current_value_sek,
                    p.strategy_group_id,
                    p.updated_at as price_updated,
                    COALESCE(s1.nameEn, s2.nameEn, 'Unknown') as sector,
                    COALESCE(s1.nameSv, s2.nameSv) as sector_sv,
                    'Sweden' as country
                   FROM psw_portfolio.portfolio p
                   LEFT JOIN psw_marketdata.nordic_instruments ni ON p.isin COLLATE utf8mb4_unicode_ci = ni.isin COLLATE utf8mb4_unicode_ci
                   LEFT JOIN psw_marketdata.global_instruments gi ON p.isin COLLATE utf8mb4_unicode_ci = gi.isin COLLATE utf8mb4_unicode_ci
                   LEFT JOIN psw_marketdata.sectors s1 ON ni.sectorID = s1.id
                   LEFT JOIN psw_marketdata.sectors s2 ON gi.sectorId = s2.id
                   WHERE p.isin = ? AND p.is_active = 1";
    
    $companyStmt = $portfolioDb->prepare($companySql);
    $companyStmt->execute([$isin]);
    $company = $companyStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$company) {
        throw new Exception("Company not found in portfolio");
    }
    
    // Get available strategy groups
    $strategyGroupsSql = "SELECT strategy_group_id, strategy_name, strategy_description 
                         FROM psw_foundation.portfolio_strategy_groups 
                         ORDER BY strategy_group_id";
    $strategyGroupsStmt = $foundationDb->prepare($strategyGroupsSql);
    $strategyGroupsStmt->execute();
    $strategyGroups = $strategyGroupsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get company's trade history
    $tradeHistorySql = "SELECT 
                         trade_date,
                         transaction_type,
                         shares_traded,
                         price_per_share_sek,
                         total_amount_sek,
                         broker_id,
                         created_at
                        FROM psw_portfolio.log_trades 
                        WHERE isin = ?
                        ORDER BY trade_date DESC, created_at DESC
                        LIMIT 20";
    $tradeHistoryStmt = $portfolioDb->prepare($tradeHistorySql);
    $tradeHistoryStmt->execute([$isin]);
    $tradeHistory = $tradeHistoryStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get company's dividend history
    $dividendHistorySql = "SELECT 
                            payment_date,
                            dividend_amount_local,
                            currency,
                            dividend_amount_sek,
                            shares_held,
                            tax_amount_sek,
                            net_dividend_sek
                           FROM psw_portfolio.log_dividends 
                           WHERE isin = ?
                           ORDER BY payment_date DESC
                           LIMIT 20";
    $dividendHistoryStmt = $portfolioDb->prepare($dividendHistorySql);
    $dividendHistoryStmt->execute([$isin]);
    $dividendHistory = $dividendHistoryStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate metrics
    $unrealizedGainLoss = $company['current_value_sek'] - $company['total_cost_sek'];
    $unrealizedPercent = $company['total_cost_sek'] > 0 ? ($unrealizedGainLoss / $company['total_cost_sek']) * 100 : 0;
    
    // Get current strategy group name
    $currentStrategyGroup = null;
    foreach ($strategyGroups as $group) {
        if ($group['strategy_group_id'] == $company['strategy_group_id']) {
            $currentStrategyGroup = $group;
            break;
        }
    }
    
} catch (Exception $e) {
    $error = "Error loading company details: " . $e->getMessage();
    $company = null;
    $strategyGroups = [];
    $tradeHistory = [];
    $dividendHistory = [];
    $unrealizedGainLoss = 0;
    $unrealizedPercent = 0;
    $currentStrategyGroup = null;
}

// Initialize variables for template
$pageTitle = ($company ? htmlspecialchars($company['company_name']) : 'Company') . ' - PSW 4.0';
$pageDescription = 'Company details and strategy group management';
$additionalCSS = [];
$additionalJS = [];

// Prepare content
ob_start();
?>
<div class="psw-content">
    <!-- Page Header -->
    <div class="psw-card psw-mb-4">
        <div class="psw-card-header" style="display: flex; justify-content: space-between; align-items: flex-start;">
            <div>
                <h1 class="psw-card-title">
                    <i class="fas fa-building psw-card-title-icon"></i>
                    <?php echo $company ? htmlspecialchars($company['company_name']) : 'Company Details'; ?>
                </h1>
                <p class="psw-card-subtitle">
                    <?php if ($company): ?>
                        <?php echo htmlspecialchars($company['ticker']); ?> (<?php echo htmlspecialchars($company['isin']); ?>)
                    <?php else: ?>
                        Company information and portfolio management
                    <?php endif; ?>
                </p>
            </div>
            <div style="display: flex; gap: 0.5rem; align-items: center;">
                <a href="<?php echo BASE_URL; ?>/portfolio_overview.php" class="psw-btn psw-btn-secondary">
                    <i class="fas fa-arrow-left psw-btn-icon"></i>Back to Portfolio
                </a>
            </div>
        </div>
    </div>

    <?php if (isset($successMessage)): ?>
        <div class="psw-alert psw-alert-success psw-mb-4">
            <i class="fas fa-check-circle"></i>
            <?php echo htmlspecialchars($successMessage); ?>
        </div>
    <?php endif; ?>

    <?php if (isset($errorMessage)): ?>
        <div class="psw-alert psw-alert-error psw-mb-4">
            <i class="fas fa-exclamation-triangle"></i>
            <?php echo htmlspecialchars($errorMessage); ?>
        </div>
    <?php endif; ?>

    <?php if ($company): ?>
        <!-- Company Overview Cards -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
            <div class="psw-card">
                <div class="psw-card-content" style="display: flex; align-items: center; gap: 1rem;">
                    <div style="width: 48px; height: 48px; background: linear-gradient(135deg, var(--primary-accent), var(--primary-accent-hover)); border-radius: var(--radius-lg); display: flex; align-items: center; justify-content: center;">
                        <i class="fas fa-chart-bar" style="color: var(--text-inverse); font-size: 1.25rem;"></i>
                    </div>
                    <div>
                        <div style="font-size: 1.875rem; font-weight: 700; color: var(--text-primary);">
                            <?php echo Localization::formatCurrency($company['current_value_sek'], 0, 'SEK'); ?>
                        </div>
                        <div style="color: var(--text-secondary); font-size: 0.875rem;">Current Value</div>
                    </div>
                </div>
            </div>
            
            <div class="psw-card">
                <div class="psw-card-content" style="display: flex; align-items: center; gap: 1rem;">
                    <div style="width: 48px; height: 48px; background: linear-gradient(135deg, <?php echo $unrealizedGainLoss >= 0 ? '#10B981, #059669' : '#EF4444, #DC2626'; ?>); border-radius: var(--radius-lg); display: flex; align-items: center; justify-content: center;">
                        <i class="fas fa-<?php echo $unrealizedGainLoss >= 0 ? 'arrow-up' : 'arrow-down'; ?>" style="color: white; font-size: 1.25rem;"></i>
                    </div>
                    <div>
                        <div style="font-size: 1.875rem; font-weight: 700; color: <?php echo $unrealizedGainLoss >= 0 ? 'var(--success-color)' : 'var(--error-color)'; ?>;">
                            <?php echo ($unrealizedGainLoss >= 0 ? '+' : '') . Localization::formatCurrency($unrealizedGainLoss, 0, 'SEK'); ?>
                        </div>
                        <div style="color: var(--text-secondary); font-size: 0.875rem;">
                            P&L (<?php echo number_format($unrealizedPercent, 1); ?>%)
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="psw-card">
                <div class="psw-card-content" style="display: flex; align-items: center; gap: 1rem;">
                    <div style="width: 48px; height: 48px; background: linear-gradient(135deg, #3B82F6, #2563EB); border-radius: var(--radius-lg); display: flex; align-items: center; justify-content: center;">
                        <i class="fas fa-layer-group" style="color: white; font-size: 1.25rem;"></i>
                    </div>
                    <div>
                        <div style="font-size: 1.875rem; font-weight: 700; color: var(--text-primary);">
                            <?php echo Localization::formatNumber($company['shares_held'], 0); ?>
                        </div>
                        <div style="color: var(--text-secondary); font-size: 0.875rem;">Shares Held</div>
                    </div>
                </div>
            </div>
            
            <div class="psw-card">
                <div class="psw-card-content" style="display: flex; align-items: center; gap: 1rem;">
                    <div style="width: 48px; height: 48px; background: linear-gradient(135deg, #F59E0B, #D97706); border-radius: var(--radius-lg); display: flex; align-items: center; justify-content: center;">
                        <i class="fas fa-coins" style="color: white; font-size: 1.25rem;"></i>
                    </div>
                    <div>
                        <div style="font-size: 1.875rem; font-weight: 700; color: var(--text-primary);">
                            <?php echo Localization::formatNumber($company['average_cost_price_sek'], 2); ?>
                        </div>
                        <div style="color: var(--text-secondary); font-size: 0.875rem;">Avg Cost (SEK)</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content Grid -->
        <div style="display: grid; grid-template-columns: 1fr 400px; gap: 2rem; margin-bottom: 2rem;">
            <!-- Company Details -->
            <div class="psw-card">
                <div class="psw-card-header">
                    <div class="psw-card-title">
                        <i class="fas fa-info-circle psw-card-title-icon"></i>
                        Company Information
                    </div>
                </div>
                <div class="psw-card-content">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
                        <div>
                            <label style="font-weight: 600; color: var(--text-secondary); font-size: 0.875rem; margin-bottom: 0.5rem; display: block;">ISIN</label>
                            <div style="font-family: var(--font-family-mono); color: var(--text-primary);"><?php echo htmlspecialchars($company['isin']); ?></div>
                        </div>
                        <div>
                            <label style="font-weight: 600; color: var(--text-secondary); font-size: 0.875rem; margin-bottom: 0.5rem; display: block;">Ticker</label>
                            <div style="font-family: var(--font-family-mono); color: var(--text-primary);"><?php echo htmlspecialchars($company['ticker']); ?></div>
                        </div>
                        <div>
                            <label style="font-weight: 600; color: var(--text-secondary); font-size: 0.875rem; margin-bottom: 0.5rem; display: block;">Sector</label>
                            <div style="color: var(--text-primary);"><?php echo htmlspecialchars($company['sector']); ?></div>
                        </div>
                        <div>
                            <label style="font-weight: 600; color: var(--text-secondary); font-size: 0.875rem; margin-bottom: 0.5rem; display: block;">Country</label>
                            <div style="color: var(--text-primary);"><?php echo htmlspecialchars($company['country']); ?></div>
                        </div>
                        <div>
                            <label style="font-weight: 600; color: var(--text-secondary); font-size: 0.875rem; margin-bottom: 0.5rem; display: block;">Latest Price</label>
                            <div style="color: var(--text-primary);">
                                <?php echo $company['latest_price'] ? Localization::formatNumber($company['latest_price'], 2) . ' ' . htmlspecialchars($company['price_currency']) : 'N/A'; ?>
                            </div>
                        </div>
                        <div>
                            <label style="font-weight: 600; color: var(--text-secondary); font-size: 0.875rem; margin-bottom: 0.5rem; display: block;">Total Cost</label>
                            <div style="color: var(--text-primary);"><?php echo Localization::formatCurrency($company['total_cost_sek'], 0, 'SEK'); ?></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Strategy Group Management -->
            <div class="psw-card">
                <div class="psw-card-header">
                    <div class="psw-card-title">
                        <i class="fas fa-cogs psw-card-title-icon"></i>
                        Strategy Group
                    </div>
                </div>
                <div class="psw-card-content">
                    <form method="POST" style="display: flex; flex-direction: column; gap: 1rem;">
                        <input type="hidden" name="action" value="update_strategy_group">
                        
                        <div>
                            <label style="font-weight: 600; color: var(--text-secondary); font-size: 0.875rem; margin-bottom: 0.5rem; display: block;">Current Strategy Group</label>
                            <div style="padding: 0.75rem; background: var(--bg-secondary); border-radius: var(--radius-md); margin-bottom: 1rem;">
                                <?php if ($currentStrategyGroup): ?>
                                    <div style="font-weight: 600; color: var(--text-primary);">
                                        <?php echo htmlspecialchars($currentStrategyGroup['strategy_name']); ?>
                                    </div>
                                    <div style="color: var(--text-secondary); font-size: 0.875rem; margin-top: 0.25rem;">
                                        <?php echo htmlspecialchars($currentStrategyGroup['strategy_description']); ?>
                                    </div>
                                <?php else: ?>
                                    <div style="color: var(--text-muted); font-style: italic;">No strategy group assigned</div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div>
                            <label for="strategy_group_id" style="font-weight: 600; color: var(--text-secondary); font-size: 0.875rem; margin-bottom: 0.5rem; display: block;">Change Strategy Group</label>
                            <select name="strategy_group_id" id="strategy_group_id" class="psw-form-input" style="width: 100%;">
                                <option value="">Select Strategy Group</option>
                                <?php foreach ($strategyGroups as $group): ?>
                                    <option value="<?php echo $group['strategy_group_id']; ?>"
                                            <?php echo $group['strategy_group_id'] == $company['strategy_group_id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($group['strategy_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <button type="submit" class="psw-btn psw-btn-primary" style="width: 100%;">
                            <i class="fas fa-save psw-btn-icon"></i>Update Strategy Group
                        </button>
                    </form>
                    
                    <?php if (!empty($strategyGroups)): ?>
                        <div style="margin-top: 1.5rem;">
                            <label style="font-weight: 600; color: var(--text-secondary); font-size: 0.875rem; margin-bottom: 0.75rem; display: block;">Available Strategy Groups</label>
                            <?php foreach ($strategyGroups as $group): ?>
                                <div style="padding: 0.5rem; margin-bottom: 0.5rem; border: 1px solid var(--border-primary); border-radius: var(--radius-sm); background: <?php echo $group['strategy_group_id'] == $company['strategy_group_id'] ? 'var(--primary-accent-light)' : 'var(--bg-card)'; ?>;">
                                    <div style="font-weight: 600; color: var(--text-primary); font-size: 0.875rem;">
                                        <?php echo htmlspecialchars($group['strategy_name']); ?>
                                    </div>
                                    <div style="color: var(--text-secondary); font-size: 0.75rem;">
                                        <?php echo htmlspecialchars($group['strategy_description']); ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Trade History -->
        <div class="psw-card psw-mb-4">
            <div class="psw-card-header">
                <div class="psw-card-title">
                    <i class="fas fa-exchange-alt psw-card-title-icon"></i>
                    Recent Trade History
                </div>
            </div>
            <div class="psw-card-content" style="padding: 0;">
                <?php if (!empty($tradeHistory)): ?>
                    <table class="psw-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Type</th>
                                <th style="text-align: right;">Shares</th>
                                <th style="text-align: right;">Price (SEK)</th>
                                <th style="text-align: right;">Total (SEK)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tradeHistory as $trade): ?>
                                <tr>
                                    <td><?php echo date('Y-m-d', strtotime($trade['trade_date'])); ?></td>
                                    <td>
                                        <span class="psw-badge <?php echo strtolower($trade['transaction_type']) === 'buy' ? 'psw-badge-success' : 'psw-badge-error'; ?>">
                                            <?php echo ucfirst(htmlspecialchars($trade['transaction_type'])); ?>
                                        </span>
                                    </td>
                                    <td style="text-align: right;"><?php echo Localization::formatNumber($trade['shares_traded'], 0); ?></td>
                                    <td style="text-align: right;"><?php echo Localization::formatNumber($trade['price_per_share_sek'], 2); ?></td>
                                    <td style="text-align: right; font-weight: 600;"><?php echo Localization::formatCurrency($trade['total_amount_sek'], 0, 'SEK'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div style="text-align: center; padding: 3rem; color: var(--text-muted);">
                        <i class="fas fa-exchange-alt" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.5;"></i>
                        <h3 style="margin-bottom: 0.5rem;">No Trade History</h3>
                        <p>No trades found for this company.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Dividend History -->
        <div class="psw-card">
            <div class="psw-card-header">
                <div class="psw-card-title">
                    <i class="fas fa-coins psw-card-title-icon"></i>
                    Recent Dividend History
                </div>
            </div>
            <div class="psw-card-content" style="padding: 0;">
                <?php if (!empty($dividendHistory)): ?>
                    <table class="psw-table">
                        <thead>
                            <tr>
                                <th>Payment Date</th>
                                <th style="text-align: right;">Shares</th>
                                <th style="text-align: right;">Dividend (Local)</th>
                                <th style="text-align: right;">Dividend (SEK)</th>
                                <th style="text-align: right;">Tax (SEK)</th>
                                <th style="text-align: right;">Net (SEK)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($dividendHistory as $dividend): ?>
                                <tr>
                                    <td><?php echo date('Y-m-d', strtotime($dividend['payment_date'])); ?></td>
                                    <td style="text-align: right;"><?php echo Localization::formatNumber($dividend['shares_held'], 0); ?></td>
                                    <td style="text-align: right;">
                                        <?php echo Localization::formatNumber($dividend['dividend_amount_local'], 2) . ' ' . htmlspecialchars($dividend['currency']); ?>
                                    </td>
                                    <td style="text-align: right;"><?php echo Localization::formatCurrency($dividend['dividend_amount_sek'], 2, 'SEK'); ?></td>
                                    <td style="text-align: right; color: var(--error-color);"><?php echo Localization::formatCurrency($dividend['tax_amount_sek'], 2, 'SEK'); ?></td>
                                    <td style="text-align: right; font-weight: 600; color: var(--success-color);"><?php echo Localization::formatCurrency($dividend['net_dividend_sek'], 2, 'SEK'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div style="text-align: center; padding: 3rem; color: var(--text-muted);">
                        <i class="fas fa-coins" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.5;"></i>
                        <h3 style="margin-bottom: 0.5rem;">No Dividend History</h3>
                        <p>No dividend payments found for this company.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    <?php elseif (isset($error)): ?>
        <div style="text-align: center; padding: 3rem; color: var(--error-color);">
            <i class="fas fa-exclamation-triangle" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.5;"></i>
            <h3 style="margin-bottom: 0.5rem;">Error Loading Company</h3>
            <p><?php echo htmlspecialchars($error); ?></p>
        </div>
    <?php endif; ?>
</div>

<?php
$content = ob_get_clean();

// Include base layout
include __DIR__ . '/templates/layouts/base-redesign.php';
?>