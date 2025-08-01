<?php
/**
 * File: dashboard.php
 * Description: Portfolio dashboard with charts, top positions, and key metrics for PSW 4.0
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
    $marketDb = Database::getConnection('marketdata');
    
    // Get portfolio summary
    $summarySql = "SELECT 
                    COUNT(*) as total_positions,
                    SUM(COALESCE(p.current_value_sek, 0)) as total_value_sek,
                    SUM(COALESCE(p.total_cost_sek, 0)) as total_cost_sek,
                    SUM(COALESCE(p.current_value_sek, 0) - COALESCE(p.total_cost_sek, 0)) as total_unrealized_pnl,
                    COUNT(DISTINCT LEFT(p.isin, 2)) as countries_count
                   FROM psw_portfolio.portfolio p
                   WHERE p.is_active = 1 AND p.shares_held > 0";
    
    $summaryStmt = $portfolioDb->prepare($summarySql);
    $summaryStmt->execute();
    $summary = $summaryStmt->fetch(PDO::FETCH_ASSOC);
    
    // Get top 10 holdings
    $topHoldingsSql = "SELECT 
                        p.isin,
                        p.ticker,
                        p.company_name,
                        p.shares_held,
                        p.current_value_sek,
                        p.total_cost_sek,
                        (p.current_value_sek / NULLIF((SELECT SUM(current_value_sek) FROM psw_portfolio.portfolio WHERE is_active = 1 AND shares_held > 0), 0)) * 100 as portfolio_weight,
                        COALESCE(s1.nameEn, s2.nameEn, 'Unknown') as sector
                       FROM psw_portfolio.portfolio p
                       LEFT JOIN psw_marketdata.nordic_instruments ni ON p.isin COLLATE utf8mb4_unicode_ci = ni.isin COLLATE utf8mb4_unicode_ci
                       LEFT JOIN psw_marketdata.global_instruments gi ON p.isin COLLATE utf8mb4_unicode_ci = gi.isin COLLATE utf8mb4_unicode_ci
                       LEFT JOIN psw_marketdata.sectors s1 ON ni.sectorID = s1.id
                       LEFT JOIN psw_marketdata.sectors s2 ON gi.sectorId = s2.id
                       WHERE p.is_active = 1 AND p.shares_held > 0
                       ORDER BY p.current_value_sek DESC
                       LIMIT 10";
    
    $topHoldingsStmt = $portfolioDb->prepare($topHoldingsSql);
    $topHoldingsStmt->execute();
    $topHoldings = $topHoldingsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get sector allocation
    $sectorSql = "SELECT 
                    COALESCE(s1.nameEn, s2.nameEn, 'Unknown') as sector,
                    COUNT(*) as positions,
                    SUM(COALESCE(p.current_value_sek, 0)) as sector_value_sek,
                    (SUM(COALESCE(p.current_value_sek, 0)) / NULLIF((SELECT SUM(current_value_sek) FROM psw_portfolio.portfolio WHERE is_active = 1 AND shares_held > 0), 0)) * 100 as sector_weight
                  FROM psw_portfolio.portfolio p
                  LEFT JOIN psw_marketdata.nordic_instruments ni ON p.isin COLLATE utf8mb4_unicode_ci = ni.isin COLLATE utf8mb4_unicode_ci
                  LEFT JOIN psw_marketdata.global_instruments gi ON p.isin COLLATE utf8mb4_unicode_ci = gi.isin COLLATE utf8mb4_unicode_ci
                  LEFT JOIN psw_marketdata.sectors s1 ON ni.sectorID = s1.id
                  LEFT JOIN psw_marketdata.sectors s2 ON gi.sectorId = s2.id
                  WHERE p.is_active = 1 AND p.shares_held > 0
                  GROUP BY COALESCE(s1.nameEn, s2.nameEn, 'Unknown')
                  ORDER BY sector_value_sek DESC";
    
    $sectorStmt = $portfolioDb->prepare($sectorSql);
    $sectorStmt->execute();
    $sectorAllocations = $sectorStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get recent dividend activity (last 30 days)
    $recentDividendsSql = "SELECT 
                            ld.isin,
                            ld.company_name,
                            ld.payment_date,
                            ld.dividend_amount_local,
                            ld.currency,
                            ld.dividend_amount_sek,
                            ld.shares_held
                           FROM psw_portfolio.log_dividends ld
                           WHERE ld.payment_date >= DATE_SUB(CURRENT_DATE, INTERVAL 30 DAY)
                           ORDER BY ld.payment_date DESC
                           LIMIT 10";
    
    $recentDividendsStmt = $portfolioDb->prepare($recentDividendsSql);
    $recentDividendsStmt->execute();
    $recentDividends = $recentDividendsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate percentage returns
    $totalUnrealizedPercent = $summary['total_cost_sek'] > 0 ? 
        ($summary['total_unrealized_pnl'] / $summary['total_cost_sek']) * 100 : 0;
    
} catch (Exception $e) {
    $error = "Error loading dashboard data: " . $e->getMessage();
    $summary = ['total_positions' => 0, 'total_value_sek' => 0, 'total_cost_sek' => 0, 'total_unrealized_pnl' => 0, 'countries_count' => 0];
    $topHoldings = [];
    $sectorAllocations = [];
    $recentDividends = [];
    $totalUnrealizedPercent = 0;
}

// Initialize variables for template
$pageTitle = 'Dashboard - PSW 4.0';
$pageDescription = 'Portfolio dashboard with key metrics and insights';
$additionalCSS = [];
$additionalJS = ['https://cdn.jsdelivr.net/npm/chart.js'];

// Prepare content
ob_start();
?>
<div class="psw-content">
    <!-- Page Header -->
    <div class="psw-card psw-mb-4">
        <div class="psw-card-header" style="display: flex; justify-content: space-between; align-items: flex-start;">
            <div>
                <h1 class="psw-card-title">
                    <i class="fas fa-tachometer-alt psw-card-title-icon"></i>
                    Dashboard
                </h1>
                <p class="psw-card-subtitle">Portfolio overview with key metrics and performance insights</p>
            </div>
            <div style="display: flex; gap: 0.5rem; align-items: center;">
                <button type="button" class="psw-btn psw-btn-secondary" onclick="refreshDashboard()">
                    <i class="fas fa-sync psw-btn-icon"></i>Refresh
                </button>
            </div>
        </div>
    </div>

    <!-- Portfolio Summary Cards -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
        <div class="psw-card">
            <div class="psw-card-content" style="display: flex; align-items: center; gap: 1rem;">
                <div style="width: 48px; height: 48px; background: linear-gradient(135deg, var(--primary-accent), var(--primary-accent-hover)); border-radius: var(--radius-lg); display: flex; align-items: center; justify-content: center;">
                    <i class="fas fa-wallet" style="color: var(--text-inverse); font-size: 1.25rem;"></i>
                </div>
                <div>
                    <div style="font-size: 1.875rem; font-weight: 700; color: var(--text-primary);">
                        <?php echo Localization::formatCurrency($summary['total_value_sek'], 0, 'SEK'); ?>
                    </div>
                    <div style="color: var(--text-secondary); font-size: 0.875rem;">Total Portfolio Value</div>
                </div>
            </div>
        </div>
        
        <div class="psw-card">
            <div class="psw-card-content" style="display: flex; align-items: center; gap: 1rem;">
                <div style="width: 48px; height: 48px; background: linear-gradient(135deg, <?php echo $summary['total_unrealized_pnl'] >= 0 ? '#10B981, #059669' : '#EF4444, #DC2626'; ?>); border-radius: var(--radius-lg); display: flex; align-items: center; justify-content: center;">
                    <i class="fas fa-chart-line" style="color: white; font-size: 1.25rem;"></i>
                </div>
                <div>
                    <div style="font-size: 1.875rem; font-weight: 700; color: <?php echo $summary['total_unrealized_pnl'] >= 0 ? 'var(--success-color)' : 'var(--error-color)'; ?>;">
                        <?php echo ($summary['total_unrealized_pnl'] >= 0 ? '+' : '') . Localization::formatCurrency($summary['total_unrealized_pnl'], 0, 'SEK'); ?>
                    </div>
                    <div style="color: var(--text-secondary); font-size: 0.875rem;">
                        Total Return (<?php echo number_format($totalUnrealizedPercent, 1); ?>%)
                    </div>
                </div>
            </div>
        </div>
        
        <div class="psw-card">
            <div class="psw-card-content" style="display: flex; align-items: center; gap: 1rem;">
                <div style="width: 48px; height: 48px; background: linear-gradient(135deg, #3B82F6, #2563EB); border-radius: var(--radius-lg); display: flex; align-items: center; justify-content: center;">
                    <i class="fas fa-building" style="color: white; font-size: 1.25rem;"></i>
                </div>
                <div>
                    <div style="font-size: 1.875rem; font-weight: 700; color: var(--text-primary);">
                        <?php echo Localization::formatNumber($summary['total_positions']); ?>
                    </div>
                    <div style="color: var(--text-secondary); font-size: 0.875rem;">Active Positions</div>
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
                        <?php echo Localization::formatCurrency($summary['total_cost_sek'], 0, 'SEK'); ?>
                    </div>
                    <div style="color: var(--text-secondary); font-size: 0.875rem;">Total Cost Basis</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Dashboard Content -->
    <div style="display: grid; grid-template-columns: 1fr 400px; gap: 2rem; margin-bottom: 2rem;">
        <!-- Top Holdings -->
        <div class="psw-card">
            <div class="psw-card-header">
                <div class="psw-card-title">
                    <i class="fas fa-trophy psw-card-title-icon"></i>
                    Top 10 Holdings
                </div>
            </div>
            <div class="psw-card-content" style="padding: 0;">
                <?php if (!empty($topHoldings) && !isset($error)): ?>
                    <table class="psw-table">
                        <thead>
                            <tr>
                                <th>Company</th>
                                <th>Ticker</th>
                                <th>Sector</th>
                                <th style="text-align: right;">Value (SEK)</th>
                                <th style="text-align: right;">Weight %</th>
                                <th style="text-align: right;">P&L</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($topHoldings as $holding): ?>
                                <tr>
                                    <td style="font-weight: 600;">
                                        <a href="<?php echo BASE_URL; ?>/company_detail_new.php?isin=<?php echo urlencode($holding['isin']); ?>" 
                                           style="color: var(--primary-accent); text-decoration: none;">
                                            <?php echo htmlspecialchars($holding['company_name'] ?? 'Unknown'); ?>
                                        </a>
                                    </td>
                                    <td style="font-family: var(--font-family-mono); font-size: 0.875rem;">
                                        <?php echo htmlspecialchars($holding['ticker'] ?? '-'); ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($holding['sector'] ?? '-'); ?></td>
                                    <td style="text-align: right; font-weight: 600;">
                                        <?php echo Localization::formatCurrency($holding['current_value_sek'], 0, 'SEK'); ?>
                                    </td>
                                    <td style="text-align: right;">
                                        <?php echo number_format($holding['portfolio_weight'], 1); ?>%
                                    </td>
                                    <td style="text-align: right; color: <?php echo ($holding['current_value_sek'] - $holding['total_cost_sek']) >= 0 ? 'var(--success-color)' : 'var(--error-color)'; ?>;">
                                        <?php 
                                        $pnl = $holding['current_value_sek'] - $holding['total_cost_sek'];
                                        echo ($pnl >= 0 ? '+' : '') . Localization::formatCurrency($pnl, 0, 'SEK'); 
                                        ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div style="text-align: center; padding: 3rem; color: var(--text-muted);">
                        <i class="fas fa-chart-bar" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.5;"></i>
                        <h3 style="margin-bottom: 0.5rem;">No Holdings Found</h3>
                        <p>Start building your portfolio to see top holdings here.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Sector Allocation Chart -->
        <div class="psw-card">
            <div class="psw-card-header">
                <div class="psw-card-title">
                    <i class="fas fa-chart-pie psw-card-title-icon"></i>
                    Sector Allocation
                </div>
            </div>
            <div class="psw-card-content">
                <?php if (!empty($sectorAllocations)): ?>
                    <canvas id="sectorChart" width="400" height="300"></canvas>
                <?php else: ?>
                    <div style="text-align: center; padding: 2rem; color: var(--text-muted);">
                        <i class="fas fa-chart-pie" style="font-size: 2rem; margin-bottom: 1rem; opacity: 0.5;"></i>
                        <p>No sector data available</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Recent Dividend Activity -->
    <div class="psw-card">
        <div class="psw-card-header">
            <div class="psw-card-title">
                <i class="fas fa-coins psw-card-title-icon"></i>
                Recent Dividend Activity (Last 30 Days)
            </div>
        </div>
        <div class="psw-card-content" style="padding: 0;">
            <?php if (!empty($recentDividends)): ?>
                <table class="psw-table">
                    <thead>
                        <tr>
                            <th>Payment Date</th>
                            <th>Company</th>
                            <th style="text-align: right;">Shares</th>
                            <th style="text-align: right;">Dividend (Local)</th>
                            <th style="text-align: right;">Dividend (SEK)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentDividends as $dividend): ?>
                            <tr>
                                <td><?php echo date('Y-m-d', strtotime($dividend['payment_date'])); ?></td>
                                <td style="font-weight: 600;">
                                    <?php echo htmlspecialchars($dividend['company_name']); ?>
                                </td>
                                <td style="text-align: right;">
                                    <?php echo Localization::formatNumber($dividend['shares_held'], 0); ?>
                                </td>
                                <td style="text-align: right;">
                                    <?php echo Localization::formatNumber($dividend['dividend_amount_local'], 2) . ' ' . htmlspecialchars($dividend['currency']); ?>
                                </td>
                                <td style="text-align: right; font-weight: 600; color: var(--success-color);">
                                    <?php echo Localization::formatCurrency($dividend['dividend_amount_sek'], 2, 'SEK'); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div style="text-align: center; padding: 3rem; color: var(--text-muted);">
                    <i class="fas fa-coins" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.5;"></i>
                    <h3 style="margin-bottom: 0.5rem;">No Recent Dividends</h3>
                    <p>No dividend payments received in the last 30 days.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
// Sector allocation chart
<?php if (!empty($sectorAllocations)): ?>
const ctx = document.getElementById('sectorChart').getContext('2d');
const sectorChart = new Chart(ctx, {
    type: 'doughnut',
    data: {
        labels: [
            <?php foreach ($sectorAllocations as $sector): ?>
                '<?php echo addslashes($sector['sector']); ?>',
            <?php endforeach; ?>
        ],
        datasets: [{
            data: [
                <?php foreach ($sectorAllocations as $sector): ?>
                    <?php echo $sector['sector_weight']; ?>,
                <?php endforeach; ?>
            ],
            backgroundColor: [
                '#3B82F6', '#10B981', '#F59E0B', '#EF4444', '#8B5CF6', 
                '#06B6D4', '#84CC16', '#F97316', '#EC4899', '#6B7280'
            ],
            borderWidth: 2,
            borderColor: '#ffffff'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom',
                labels: {
                    padding: 20,
                    usePointStyle: true,
                    font: {
                        size: 12
                    }
                }
            },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        return context.label + ': ' + context.parsed.toFixed(1) + '%';
                    }
                }
            }
        }
    }
});
<?php endif; ?>

function refreshDashboard() {
    location.reload();
}
</script>

<?php
$content = ob_get_clean();

// Include base layout
include __DIR__ . '/templates/layouts/base-redesign.php';
?>