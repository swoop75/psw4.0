<?php
/**
 * File: portfolio_overview.php
 * Description: Portfolio overview interface for PSW 4.0 - displays current holdings with real-time valuations
 */

session_start();

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/src/middleware/Auth.php';
require_once __DIR__ . '/src/utils/Localization.php';

// Require authentication
Auth::requireAuth();

try {
    $portfolioDb = Database::getConnection('portfolio');
    $foundationDb = Database::getConnection('foundation');
    $marketDb = Database::getConnection('marketdata');
    
    // Use exact same query that worked in MySQL directly
    $sql = "SELECT 
                portfolio_id,
                isin, 
                ticker,
                company_name,
                shares_held,
                average_cost_price_sek,
                total_cost_sek,
                latest_price_local as latest_price,
                currency_local as price_currency,
                updated_at as price_updated,
                NULL as fx_rate,
                NULL as fx_updated,
                current_value_local as calculated_value_local,
                current_value_sek as calculated_value_sek,
                currency_local as base_currency,
                'Sweden' as country,
                'Unknown' as sector
            FROM psw_portfolio.portfolio 
            WHERE is_active = 1 AND shares_held > 0
            ORDER BY isin, portfolio_id";
    
    $stmt = $portfolioDb->prepare($sql);
    $stmt->execute();
    $holdings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Debug: Print what we got from database (visible on page)
    echo "<!-- DEBUG: Raw count from DB: " . count($holdings) . " -->\n";
    foreach ($holdings as $i => $holding) {
        echo "<!-- DEBUG [$i]: ID={$holding['portfolio_id']}, ISIN={$holding['isin']}, Ticker={$holding['ticker']}, Company={$holding['company_name']} -->\n";
    }
    
    // Remove the deduplication for now to see raw data
    // $holdings = array_values($uniqueHoldings);
    
    // Calculate portfolio totals
    $totalValueSek = 0;
    $totalCostSek = 0;
    $totalPositions = count($holdings);
    
    echo "<!-- CALC DEBUG: Before calculation loop, count: " . count($holdings) . " -->\n";
    foreach ($holdings as $i => $holding) {
        echo "<!-- CALC [$i]: Processing {$holding['isin']} - {$holding['ticker']} -->\n";
        $currentValueSek = $holding['calculated_value_sek'] ?: $holding['current_value_sek'] ?: 0;
        $costSek = $holding['total_cost_sek'] ?: 0;
        
        // Update the holding with calculated values
        $holdings[$i]['display_value_sek'] = $currentValueSek;
        $holdings[$i]['unrealized_gain_loss_sek'] = $currentValueSek - $costSek;
        $holdings[$i]['unrealized_gain_loss_percent'] = $costSek > 0 ? (($currentValueSek - $costSek) / $costSek) * 100 : 0;
        
        $totalValueSek += $currentValueSek;
        $totalCostSek += $costSek;
    }
    
    // Calculate portfolio weight percentages
    echo "<!-- WEIGHT DEBUG: Before weight loop, count: " . count($holdings) . " -->\n";
    foreach ($holdings as $i => $holding) {
        echo "<!-- WEIGHT [$i]: Processing {$holding['isin']} - {$holding['ticker']} -->\n";
        $holdings[$i]['portfolio_weight_percent'] = $totalValueSek > 0 ? ($holdings[$i]['display_value_sek'] / $totalValueSek) * 100 : 0;
    }
    
    $totalUnrealizedGainLoss = $totalValueSek - $totalCostSek;
    $totalUnrealizedPercent = $totalCostSek > 0 ? ($totalUnrealizedGainLoss / $totalCostSek) * 100 : 0;
    
    // Get sector allocation
    $sectorSql = "SELECT 
                    'Unknown' as sector,
                    COUNT(*) as positions,
                    SUM(COALESCE(p.current_value_sek, 0)) as sector_value_sek
                  FROM psw_portfolio.portfolio p
                  WHERE p.is_active = 1 AND p.shares_held > 0
                  GROUP BY 'Unknown'
                  ORDER BY sector_value_sek DESC";
    
    $sectorStmt = $portfolioDb->prepare($sectorSql);
    $sectorStmt->execute();
    $sectorAllocations = $sectorStmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $error = "Error loading portfolio data: " . $e->getMessage();
    $holdings = [];
    $sectorAllocations = [];
    $totalValueSek = 0;
    $totalCostSek = 0;
    $totalPositions = 0;
    $totalUnrealizedGainLoss = 0;
    $totalUnrealizedPercent = 0;
}

// Initialize variables for template
$pageTitle = 'Portfolio Overview - PSW 4.0';
$pageDescription = 'Current portfolio holdings and performance';
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
                    <i class="fas fa-chart-pie psw-card-title-icon"></i>
                    Portfolio Overview
                </h1>
                <p class="psw-card-subtitle">Current holdings with real-time valuations and performance metrics</p>
            </div>
            <div style="display: flex; gap: 0.5rem; align-items: center;">
                <button type="button" class="psw-btn psw-btn-primary" onclick="refreshPrices()">
                    <i class="fas fa-sync psw-btn-icon"></i>Refresh Prices
                </button>
                <button type="button" class="psw-btn psw-btn-secondary" onclick="exportToCSV()">
                    <i class="fas fa-download psw-btn-icon"></i>Export
                </button>
            </div>
        </div>
    </div>

    <!-- Portfolio Summary Cards -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem; margin-bottom: 1rem;">
        <div class="psw-card">
            <div class="psw-card-content" style="display: flex; align-items: center; gap: 1rem;">
                <div style="width: 48px; height: 48px; background: linear-gradient(135deg, var(--primary-accent), var(--primary-accent-hover)); border-radius: var(--radius-lg); display: flex; align-items: center; justify-content: center;">
                    <i class="fas fa-wallet" style="color: var(--text-inverse); font-size: 1.25rem;"></i>
                </div>
                <div>
                    <div style="font-size: 1.875rem; font-weight: 700; color: var(--text-primary);">
                        <?php echo Localization::formatCurrency($totalValueSek, 0, 'SEK'); ?>
                    </div>
                    <div style="color: var(--text-secondary); font-size: 0.875rem;">Total Value</div>
                </div>
            </div>
        </div>
        
        <div class="psw-card">
            <div class="psw-card-content" style="display: flex; align-items: center; gap: 1rem;">
                <div style="width: 48px; height: 48px; background: linear-gradient(135deg, #10B981, #059669); border-radius: var(--radius-lg); display: flex; align-items: center; justify-content: center;">
                    <i class="fas fa-chart-bar" style="color: white; font-size: 1.25rem;"></i>
                </div>
                <div>
                    <div style="font-size: 1.875rem; font-weight: 700; color: <?php echo $totalUnrealizedGainLoss >= 0 ? 'var(--success-color)' : 'var(--error-color)'; ?>;">
                        <?php echo ($totalUnrealizedGainLoss >= 0 ? '+' : '') . Localization::formatCurrency($totalUnrealizedGainLoss, 0, 'SEK'); ?>
                    </div>
                    <div style="color: var(--text-secondary); font-size: 0.875rem;">
                        Unrealized P&L (<?php echo number_format($totalUnrealizedPercent, 1); ?>%)
                    </div>
                </div>
            </div>
        </div>
        
        <div class="psw-card">
            <div class="psw-card-content" style="display: flex; align-items: center; gap: 1rem;">
                <div style="width: 48px; height: 48px; background: linear-gradient(135deg, #F59E0B, #D97706); border-radius: var(--radius-lg); display: flex; align-items: center; justify-content: center;">
                    <i class="fas fa-building" style="color: white; font-size: 1.25rem;"></i>
                </div>
                <div>
                    <div style="font-size: 1.875rem; font-weight: 700; color: var(--text-primary);">
                        <?php echo Localization::formatNumber($totalPositions); ?>
                    </div>
                    <div style="color: var(--text-secondary); font-size: 0.875rem;">Active Positions</div>
                </div>
            </div>
        </div>
        
        <div class="psw-card">
            <div class="psw-card-content" style="display: flex; align-items: center; gap: 1rem;">
                <div style="width: 48px; height: 48px; background: linear-gradient(135deg, #3B82F6, #2563EB); border-radius: var(--radius-lg); display: flex; align-items: center; justify-content: center;">
                    <i class="fas fa-coins" style="color: white; font-size: 1.25rem;"></i>
                </div>
                <div>
                    <div style="font-size: 1.875rem; font-weight: 700; color: var(--text-primary);">
                        <?php echo Localization::formatCurrency($totalCostSek, 0, 'SEK'); ?>
                    </div>
                    <div style="color: var(--text-secondary); font-size: 0.875rem;">Total Cost Basis</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Holdings Table -->
    <div class="psw-card">
        <div class="psw-card-header">
            <div class="psw-card-title">
                <i class="fas fa-table psw-card-title-icon"></i>
                Current Holdings
            </div>
        </div>
        <div class="psw-card-content" style="padding: 0;">
            <?php if (!empty($holdings) && !isset($error)): ?>
                <table class="psw-table">
                    <thead>
                        <tr>
                            <th>ISIN</th>
                            <th>Company</th>
                            <th>Ticker</th>
                            <th>Country</th>
                            <th>Sector</th>
                            <th style="text-align: right;">Shares</th>
                            <th style="text-align: right;">Avg Cost (SEK)</th>
                            <th style="text-align: right;">Latest Price</th>
                            <th style="text-align: right;">Market Value (SEK)</th>
                            <th style="text-align: right;">Weight %</th>
                            <th style="text-align: right;">Unrealized P&L</th>
                            <th style="text-align: right;">Return %</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        echo "<!-- TABLE DEBUG: About to render " . count($holdings) . " holdings -->\n";
                        // Debug: Show final array state
                        foreach ($holdings as $i => $h) {
                            echo "<!-- FINAL [$i]: {$h['isin']} - {$h['ticker']} - {$h['company_name']} -->\n";
                        }
                        
                        foreach ($holdings as $i => $holding): 
                        echo "<!-- TABLE ROW [$i]: {$holding['isin']} - {$holding['ticker']} -->\n";
                        ?>
                            <tr>
                                <td style="font-family: var(--font-family-mono); font-size: 0.875rem;">
                                    <?php echo htmlspecialchars($holding['isin']); ?>
                                </td>
                                <td><?php echo htmlspecialchars($holding['company_name'] ?? 'Unknown Company'); ?></td>
                                <td style="font-family: var(--font-family-mono); font-size: 0.875rem;">
                                    <?php echo htmlspecialchars($holding['ticker'] ?? '-'); ?>
                                </td>
                                <td><?php echo htmlspecialchars($holding['country'] ?? '-'); ?></td>
                                <td><?php echo htmlspecialchars($holding['sector'] ?? '-'); ?></td>
                                <td style="text-align: right;">
                                    <?php echo Localization::formatNumber($holding['shares_held'], 0); ?>
                                </td>
                                <td style="text-align: right;">
                                    <?php echo $holding['average_cost_price_sek'] ? Localization::formatNumber($holding['average_cost_price_sek'], 2) : '-'; ?>
                                </td>
                                <td style="text-align: right;">
                                    <?php if ($holding['latest_price']): ?>
                                        <?php echo Localization::formatNumber($holding['latest_price'], 2) . ' ' . htmlspecialchars($holding['price_currency']); ?>
                                    <?php else: ?>
                                        <span style="color: var(--text-muted);">N/A</span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align: right; font-weight: 600;">
                                    <?php echo Localization::formatCurrency($holding['display_value_sek'], 0, 'SEK'); ?>
                                </td>
                                <td style="text-align: right;">
                                    <?php echo number_format($holding['portfolio_weight_percent'], 1); ?>%
                                </td>
                                <td style="text-align: right; color: <?php echo $holding['unrealized_gain_loss_sek'] >= 0 ? 'var(--success-color)' : 'var(--error-color)'; ?>;">
                                    <?php echo ($holding['unrealized_gain_loss_sek'] >= 0 ? '+' : '') . Localization::formatCurrency($holding['unrealized_gain_loss_sek'], 0, 'SEK'); ?>
                                </td>
                                <td style="text-align: right; color: <?php echo $holding['unrealized_gain_loss_percent'] >= 0 ? 'var(--success-color)' : 'var(--error-color)'; ?>; font-weight: 600;">
                                    <?php echo ($holding['unrealized_gain_loss_percent'] >= 0 ? '+' : '') . number_format($holding['unrealized_gain_loss_percent'], 1); ?>%
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php elseif (isset($error)): ?>
                <div style="text-align: center; padding: 3rem; color: var(--error-color);">
                    <i class="fas fa-exclamation-triangle" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.5;"></i>
                    <h3 style="margin-bottom: 0.5rem;">Error Loading Portfolio</h3>
                    <p><?php echo htmlspecialchars($error); ?></p>
                </div>
            <?php else: ?>
                <div style="text-align: center; padding: 3rem; color: var(--text-muted);">
                    <i class="fas fa-chart-pie" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.5;"></i>
                    <h3 style="margin-bottom: 0.5rem;">No Holdings Found</h3>
                    <p>Your portfolio appears to be empty. Start by making some trades to build your portfolio.</p>
                    <a href="<?php echo BASE_URL; ?>/add_trade.php" class="psw-btn psw-btn-primary" style="margin-top: 1rem;">
                        <i class="fas fa-plus psw-btn-icon"></i>Add Trade
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function refreshPrices() {
    // TODO: Implement price refresh functionality
    alert('Price refresh functionality will be implemented in a future update.');
}

function exportToCSV() {
    // TODO: Implement CSV export
    alert('CSV export functionality will be implemented in a future update.');
}
</script>

<?php
$content = ob_get_clean();

// Include base layout
include __DIR__ . '/templates/layouts/base-redesign.php';
?>