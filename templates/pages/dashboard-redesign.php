<?php
/**
 * File: templates/pages/dashboard-redesign.php
 * Path: C:\Users\laoan\Documents\GitHub\psw\psw4.0\templates\pages\dashboard-redesign.php
 * Description: Redesigned dashboard template for PSW 4.0 using new design system
 */

$metrics = $dashboardData['portfolio_metrics'];
$recentDividends = $dashboardData['recent_dividends'];
$upcomingDividends = $dashboardData['upcoming_dividends'];
$allocation = $dashboardData['allocation_data'];
$quickStats = $dashboardData['quick_stats'];
$dividendStats = $dashboardData['dividend_stats'];
?>

<div class="psw-dashboard">
    <!-- Welcome Header -->
    <div class="psw-card psw-mb-6">
        <div class="psw-card-header">
            <h1 class="psw-card-title">
                <i class="fas fa-tachometer-alt psw-card-title-icon"></i>
                Dashboard
            </h1>
            <p class="psw-card-subtitle">
                Welcome back, <?php echo Auth::getUsername(); ?>! Here's your portfolio overview.
            </p>
        </div>
    </div>

    <!-- Key Metrics Cards -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: var(--spacing-4); margin-bottom: var(--spacing-6);">
        <div class="psw-card">
            <div class="psw-card-content">
                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: var(--spacing-3);">
                    <h3 class="psw-text-lg" style="margin: 0; color: var(--text-primary);">Portfolio Value</h3>
                    <i class="fas fa-chart-line" style="color: var(--primary-accent); font-size: var(--font-size-xl);"></i>
                </div>
                <div style="font-size: var(--font-size-2xl); font-weight: 700; color: var(--text-primary); margin-bottom: var(--spacing-2);">
                    <?php echo Localization::formatCurrency($metrics['total_value'], 2, 'SEK'); ?>
                </div>
                <div style="font-size: var(--font-size-sm); color: <?php echo $metrics['daily_change'] >= 0 ? 'var(--success-color)' : 'var(--error-color)'; ?>;">
                    <i class="fas fa-arrow-<?php echo $metrics['daily_change'] >= 0 ? 'up' : 'down'; ?>"></i>
                    <?php echo Localization::formatCurrency($metrics['daily_change'], 2, 'SEK'); ?> 
                    (<?php echo Localization::formatNumber($metrics['daily_change_percent'], 2); ?>%) today
                </div>
            </div>
        </div>

        <div class="psw-card">
            <div class="psw-card-content">
                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: var(--spacing-3);">
                    <h3 class="psw-text-lg" style="margin: 0; color: var(--text-primary);">Dividends YTD</h3>
                    <i class="fas fa-coins" style="color: var(--primary-accent); font-size: var(--font-size-xl);"></i>
                </div>
                <div style="font-size: var(--font-size-2xl); font-weight: 700; color: var(--text-primary); margin-bottom: var(--spacing-2);">
                    <?php echo Localization::formatCurrency($metrics['total_dividends_ytd'], 2, 'SEK'); ?>
                </div>
                <div style="font-size: var(--font-size-sm); color: var(--text-muted);">
                    All-time: <?php echo Localization::formatCurrency($metrics['total_dividends_all_time'], 2, 'SEK'); ?>
                </div>
            </div>
        </div>

        <div class="psw-card">
            <div class="psw-card-content">
                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: var(--spacing-3);">
                    <h3 class="psw-text-lg" style="margin: 0; color: var(--text-primary);">Current Yield</h3>
                    <i class="fas fa-percentage" style="color: var(--primary-accent); font-size: var(--font-size-xl);"></i>
                </div>
                <div style="font-size: var(--font-size-2xl); font-weight: 700; color: var(--text-primary); margin-bottom: var(--spacing-2);">
                    <?php echo Localization::formatNumber($metrics['current_yield'], 2); ?>%
                </div>
                <div style="font-size: var(--font-size-sm); color: var(--text-muted);">
                    Expected monthly: <?php echo Localization::formatCurrency($metrics['expected_monthly_income'], 2, 'SEK'); ?>
                </div>
            </div>
        </div>

        <div class="psw-card">
            <div class="psw-card-content">
                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: var(--spacing-3);">
                    <h3 class="psw-text-lg" style="margin: 0; color: var(--text-primary);">Holdings</h3>
                    <i class="fas fa-building" style="color: var(--primary-accent); font-size: var(--font-size-xl);"></i>
                </div>
                <div style="font-size: var(--font-size-2xl); font-weight: 700; color: var(--text-primary); margin-bottom: var(--spacing-2);">
                    <?php echo $metrics['total_holdings']; ?>
                </div>
                <div style="font-size: var(--font-size-sm); color: var(--text-muted);">
                    <?php echo $metrics['total_companies']; ?> companies
                </div>
            </div>
        </div>
    </div>

    <!-- Main Dashboard Content Grid -->
    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: var(--spacing-6);">
        <!-- Left Column -->
        <div style="display: flex; flex-direction: column; gap: var(--spacing-6);">
            <!-- Portfolio Allocation -->
            <div class="psw-card">
                <div class="psw-card-header">
                    <h2 class="psw-card-title">
                        <i class="fas fa-chart-pie psw-card-title-icon"></i>
                        Portfolio Allocation
                    </h2>
                    <select id="allocationView" class="psw-form-input" style="max-width: 200px;">
                        <option value="sector">By Sector</option>
                        <option value="country">By Country</option>
                        <option value="asset_class">By Asset Class</option>
                    </select>
                </div>
                <div class="psw-card-content">
                    <div style="display: flex; align-items: center; justify-content: center; gap: var(--spacing-6);">
                        <canvas id="allocationChart" width="300" height="300"></canvas>
                        <div id="allocationLegend" style="flex: 1;">
                            <!-- Legend will be populated by JavaScript -->
                        </div>
                    </div>
                </div>
            </div>

            <!-- Top Paying Companies -->
            <div class="psw-card">
                <div class="psw-card-header">
                    <h2 class="psw-card-title">
                        <i class="fas fa-trophy psw-card-title-icon"></i>
                        Top Paying Companies
                    </h2>
                    <span class="psw-card-subtitle">All time data</span>
                </div>
                <div class="psw-card-content">
                    <?php if (!empty($dividendStats['top_paying_companies'])): ?>
                        <div style="display: flex; flex-direction: column; gap: var(--spacing-3);">
                            <?php foreach ($dividendStats['top_paying_companies'] as $index => $company): ?>
                                <div style="display: flex; align-items: center; gap: var(--spacing-3); padding: var(--spacing-3); background-color: var(--bg-secondary); border-radius: var(--radius-lg);">
                                    <div style="display: flex; align-items: center; justify-content: center; width: 32px; height: 32px; background-color: var(--primary-accent); color: var(--text-inverse); border-radius: 50%; font-weight: 700;">
                                        <?php echo $index + 1; ?>
                                    </div>
                                    <div style="flex: 1;">
                                        <div style="font-weight: 600; color: var(--text-primary); margin-bottom: var(--spacing-1);">
                                            <?php echo htmlspecialchars($company['company_name'] ?? 'Unknown Company'); ?>
                                        </div>
                                        <div style="display: flex; gap: var(--spacing-4); font-size: var(--font-size-sm); color: var(--text-muted);">
                                            <span><strong><?php echo number_format($company['total_dividends'], 2); ?> SEK</strong></span>
                                            <span><?php echo $company['payment_count']; ?> payments</span>
                                        </div>
                                    </div>
                                    <div style="width: 100px; height: 6px; background-color: var(--border-primary); border-radius: var(--radius-sm); overflow: hidden;">
                                        <div style="height: 100%; background-color: var(--primary-accent); width: <?php echo $index === 0 ? 100 : ($company['total_dividends'] / $dividendStats['top_paying_companies'][0]['total_dividends'] * 100); ?>%; transition: width var(--transition-base);"></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div style="text-align: center; padding: var(--spacing-8); color: var(--text-muted);">
                            <i class="fas fa-chart-bar" style="font-size: var(--font-size-3xl); margin-bottom: var(--spacing-4);"></i>
                            <p>No dividend data available</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Recent Dividends -->
            <div class="psw-card">
                <div class="psw-card-header">
                    <h2 class="psw-card-title">
                        <i class="fas fa-coins psw-card-title-icon"></i>
                        Recent Dividends
                    </h2>
                    <a href="<?php echo BASE_URL; ?>/dividend_logs.php" class="psw-btn psw-btn-secondary psw-text-sm">View All</a>
                </div>
                <div class="psw-card-content">
                    <div style="overflow-x: auto;">
                        <table class="psw-table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Company</th>
                                    <th>Shares</th>
                                    <th>Per Share</th>
                                    <th>Total (SEK)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentDividends as $dividend): ?>
                                <tr>
                                    <td><?php echo Localization::formatDate($dividend['date']); ?></td>
                                    <td>
                                        <div style="font-weight: 600; color: var(--text-primary);"><?php echo $dividend['symbol']; ?></div>
                                        <div style="font-size: var(--font-size-sm); color: var(--text-muted);"><?php echo $dividend['company']; ?></div>
                                    </td>
                                    <td><?php echo Localization::formatNumber($dividend['shares']); ?></td>
                                    <td><?php echo $dividend['currency']; ?> <?php echo Localization::formatNumber($dividend['dividend_per_share'], 2); ?></td>
                                    <td><strong><?php echo Localization::formatCurrency($dividend['sek_amount'], 2, 'SEK'); ?></strong></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Column -->
        <div style="display: flex; flex-direction: column; gap: var(--spacing-6);">
            <!-- Best Payment Days -->
            <div class="psw-card">
                <div class="psw-card-header">
                    <h2 class="psw-card-title">
                        <i class="fas fa-calendar-day psw-card-title-icon"></i>
                        Best Payment Days
                    </h2>
                    <span class="psw-card-subtitle">By day of week</span>
                </div>
                <div class="psw-card-content">
                    <?php if (!empty($dividendStats['best_payment_days'])): ?>
                        <div style="display: flex; flex-direction: column; gap: var(--spacing-2);">
                            <?php foreach ($dividendStats['best_payment_days'] as $day): ?>
                                <div style="display: flex; align-items: center; justify-content: space-between; padding: var(--spacing-2); background-color: var(--bg-secondary); border-radius: var(--radius-md);">
                                    <div style="font-weight: 600; color: var(--text-primary);"><?php echo $day['day_name']; ?></div>
                                    <div style="text-align: right;">
                                        <div style="font-weight: 600; color: var(--text-primary);"><?php echo Localization::formatCurrency($day['total_amount'], 2, 'SEK'); ?></div>
                                        <div style="font-size: var(--font-size-sm); color: var(--text-muted);"><?php echo $day['payment_count']; ?> payments</div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div style="text-align: center; padding: var(--spacing-6); color: var(--text-muted);">
                            <i class="fas fa-calendar-alt" style="font-size: var(--font-size-2xl); margin-bottom: var(--spacing-2);"></i>
                            <p>No payment day data available</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Quick Stats -->
            <div class="psw-card">
                <div class="psw-card-header">
                    <h2 class="psw-card-title">
                        <i class="fas fa-info-circle psw-card-title-icon"></i>
                        Quick Stats
                    </h2>
                </div>
                <div class="psw-card-content">
                    <div style="display: grid; grid-template-columns: 1fr; gap: var(--spacing-4);">
                        <div style="text-align: center; padding: var(--spacing-3); background-color: var(--bg-secondary); border-radius: var(--radius-lg);">
                            <div style="font-size: var(--font-size-sm); color: var(--text-muted); margin-bottom: var(--spacing-1);">Dividend Streak</div>
                            <div style="font-size: var(--font-size-xl); font-weight: 700; color: var(--text-primary);"><?php echo $quickStats['dividend_streak_months']; ?> months</div>
                        </div>
                        <div style="text-align: center; padding: var(--spacing-3); background-color: var(--bg-secondary); border-radius: var(--radius-lg);">
                            <div style="font-size: var(--font-size-sm); color: var(--text-muted); margin-bottom: var(--spacing-1);">Best Performer</div>
                            <div style="font-size: var(--font-size-lg); font-weight: 600; color: var(--text-primary);">
                                <?php if ($quickStats['best_performing_stock']): ?>
                                    <?php echo $quickStats['best_performing_stock']['symbol']; ?>
                                    <small style="color: var(--success-color);">(+<?php echo number_format($quickStats['best_performing_stock']['gain_percent'], 1); ?>%)</small>
                                <?php else: ?>
                                    N/A
                                <?php endif; ?>
                            </div>
                        </div>
                        <div style="text-align: center; padding: var(--spacing-3); background-color: var(--bg-secondary); border-radius: var(--radius-lg);">
                            <div style="font-size: var(--font-size-sm); color: var(--text-muted); margin-bottom: var(--spacing-1);">Largest Holding</div>
                            <div style="font-size: var(--font-size-lg); font-weight: 600; color: var(--text-primary);">
                                <?php if ($quickStats['largest_holding']): ?>
                                    <?php echo $quickStats['largest_holding']['symbol']; ?>
                                    <small style="color: var(--text-muted);">(<?php echo number_format($quickStats['largest_holding']['percentage'], 1); ?>%)</small>
                                <?php else: ?>
                                    N/A
                                <?php endif; ?>
                            </div>
                        </div>
                        <div style="text-align: center; padding: var(--spacing-3); background-color: var(--bg-secondary); border-radius: var(--radius-lg);">
                            <div style="font-size: var(--font-size-sm); color: var(--text-muted); margin-bottom: var(--spacing-1);">Next Ex-Div</div>
                            <div style="font-size: var(--font-size-lg); font-weight: 600; color: var(--text-primary);">
                                <?php echo $quickStats['next_ex_div_date'] ? date('M j', strtotime($quickStats['next_ex_div_date'])) : 'N/A'; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Dividend Trends Chart -->
            <div class="psw-card">
                <div class="psw-card-header">
                    <h2 class="psw-card-title">
                        <i class="fas fa-chart-line psw-card-title-icon"></i>
                        Dividend Trends
                    </h2>
                    <span class="psw-card-subtitle">Monthly trends</span>
                </div>
                <div class="psw-card-content">
                    <?php if (!empty($dividendStats['monthly_trends'])): ?>
                        <canvas id="dividendTrendsChart" width="400" height="200"></canvas>
                    <?php else: ?>
                        <div style="text-align: center; padding: var(--spacing-6); color: var(--text-muted);">
                            <i class="fas fa-chart-line" style="font-size: var(--font-size-2xl); margin-bottom: var(--spacing-2);"></i>
                            <p>No dividend trend data available</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Dividend Insights -->
            <div class="psw-card">
                <div class="psw-card-header">
                    <h2 class="psw-card-title">
                        <i class="fas fa-lightbulb psw-card-title-icon"></i>
                        Dividend Insights
                    </h2>
                    <span class="psw-card-subtitle">All time data</span>
                </div>
                <div class="psw-card-content">
                    <?php if (!empty($dividendStats['insights']) && $dividendStats['insights']['total_payments'] > 0): ?>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: var(--spacing-3);">
                            <div style="text-align: center; padding: var(--spacing-3); background-color: var(--bg-secondary); border-radius: var(--radius-lg);">
                                <i class="fas fa-hand-holding-usd" style="color: var(--primary-accent); font-size: var(--font-size-lg); margin-bottom: var(--spacing-2);"></i>
                                <div style="font-size: var(--font-size-lg); font-weight: 700; color: var(--text-primary);"><?php echo number_format($dividendStats['insights']['avg_payment'] ?? 0, 2); ?> SEK</div>
                                <div style="font-size: var(--font-size-sm); color: var(--text-muted);">Average Payment</div>
                            </div>
                            
                            <div style="text-align: center; padding: var(--spacing-3); background-color: var(--bg-secondary); border-radius: var(--radius-lg);">
                                <i class="fas fa-star" style="color: var(--primary-accent); font-size: var(--font-size-lg); margin-bottom: var(--spacing-2);"></i>
                                <div style="font-size: var(--font-size-lg); font-weight: 700; color: var(--text-primary);"><?php echo number_format($dividendStats['insights']['largest_payment'] ?? 0, 2); ?> SEK</div>
                                <div style="font-size: var(--font-size-sm); color: var(--text-muted);">Largest Payment</div>
                            </div>
                            
                            <div style="text-align: center; padding: var(--spacing-3); background-color: var(--bg-secondary); border-radius: var(--radius-lg);">
                                <i class="fas fa-building" style="color: var(--primary-accent); font-size: var(--font-size-lg); margin-bottom: var(--spacing-2);"></i>
                                <div style="font-size: var(--font-size-lg); font-weight: 700; color: var(--text-primary);"><?php echo $dividendStats['insights']['total_companies'] ?? 0; ?></div>
                                <div style="font-size: var(--font-size-sm); color: var(--text-muted);">Paying Companies</div>
                            </div>
                            
                            <div style="text-align: center; padding: var(--spacing-3); background-color: var(--bg-secondary); border-radius: var(--radius-lg);">
                                <i class="fas fa-calendar-check" style="color: var(--primary-accent); font-size: var(--font-size-lg); margin-bottom: var(--spacing-2);"></i>
                                <div style="font-size: var(--font-size-lg); font-weight: 700; color: var(--text-primary);"><?php echo $dividendStats['insights']['total_payments'] ?? 0; ?></div>
                                <div style="font-size: var(--font-size-sm); color: var(--text-muted);">Total Payments</div>
                            </div>
                        </div>
                    <?php else: ?>
                        <div style="text-align: center; padding: var(--spacing-6); color: var(--text-muted);">
                            <i class="fas fa-lightbulb" style="font-size: var(--font-size-2xl); margin-bottom: var(--spacing-2);"></i>
                            <p>No dividend data available. <a href="<?php echo BASE_URL; ?>/dividend_import.php" style="color: var(--primary-accent);">Import dividends</a> to see insights.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Upcoming Dividends -->
            <div class="psw-card">
                <div class="psw-card-header">
                    <h2 class="psw-card-title">
                        <i class="fas fa-calendar-alt psw-card-title-icon"></i>
                        Upcoming Ex-Dividend Dates
                    </h2>
                    <a href="<?php echo BASE_URL; ?>/dividend_estimate.php" class="psw-btn psw-btn-secondary psw-text-sm">View All</a>
                </div>
                <div class="psw-card-content">
                    <div style="overflow-x: auto;">
                        <table class="psw-table">
                            <thead>
                                <tr>
                                    <th>Ex-Date</th>
                                    <th>Company</th>
                                    <th>Est. Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($upcomingDividends as $upcoming): ?>
                                <tr>
                                    <td><?php echo date('M j', strtotime($upcoming['ex_date'])); ?></td>
                                    <td>
                                        <div style="font-weight: 600; color: var(--text-primary);"><?php echo $upcoming['symbol']; ?></div>
                                        <div style="font-size: var(--font-size-sm); color: var(--text-muted);"><?php echo substr($upcoming['company'], 0, 20) . (strlen($upcoming['company']) > 20 ? '...' : ''); ?></div>
                                    </td>
                                    <td><?php echo $upcoming['currency']; ?> <?php echo number_format($upcoming['estimated_total'], 2); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Store data for JavaScript -->
<script>
window.dashboardData = {
    allocation: <?php echo json_encode($allocation); ?>,
    performance: <?php echo json_encode($dashboardData['performance_data']); ?>,
    dividendStats: <?php echo json_encode($dividendStats); ?>,
    user: {
        isAdmin: <?php echo Auth::isAdmin() ? 'true' : 'false'; ?>,
        username: '<?php echo Auth::getUsername(); ?>'
    }
};

// Initialize dashboard charts when page loads
document.addEventListener('DOMContentLoaded', function() {
    if (typeof initializeDashboardCharts === 'function') {
        initializeDashboardCharts();
    }
});
</script>