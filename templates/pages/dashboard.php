<?php
/**
 * File: templates/pages/dashboard.php
 * Path: C:\Users\laoan\Documents\GitHub\psw\psw4.0\templates\pages\dashboard.php
 * Description: Dashboard template for PSW 4.0 - displays portfolio overview and key metrics
 */

$metrics = $dashboardData['portfolio_metrics'];
$recentDividends = $dashboardData['recent_dividends'];
$upcomingDividends = $dashboardData['upcoming_dividends'];
$allocation = $dashboardData['allocation_data'];
$quickStats = $dashboardData['quick_stats'];
$dividendStats = $dashboardData['dividend_stats'];
?>

<div class="dashboard-container">
    <!-- Welcome Header -->
    <div class="dashboard-header">
        <div class="header-main">
            <h1>
                <i class="fas fa-tachometer-alt"></i>
                Dashboard
            </h1>
            <span class="last-updated">
                Last updated: <?php echo date('M j, Y H:i'); ?>
            </span>
        </div>
        <p class="dashboard-subtitle">
            Welcome back, <?php echo Auth::getUsername(); ?>! Here's portfolio overview.
        </p>
    </div>

    <!-- Key Metrics Cards -->
    <div class="metrics-grid">
        <div class="metric-card">
            <div class="metric-header">
                <h3>Portfolio Value</h3>
                <i class="fas fa-chart-line metric-icon"></i>
            </div>
            <div class="metric-value">
                <?php echo number_format($metrics['total_value'], 2); ?> SEK
            </div>
            <div class="metric-change <?php echo $metrics['daily_change'] >= 0 ? 'positive' : 'negative'; ?>">
                <i class="fas fa-arrow-<?php echo $metrics['daily_change'] >= 0 ? 'up' : 'down'; ?>"></i>
                <?php echo number_format($metrics['daily_change'], 2); ?> SEK 
                (<?php echo number_format($metrics['daily_change_percent'], 2); ?>%) today
            </div>
        </div>

        <div class="metric-card">
            <div class="metric-header">
                <h3>Dividends YTD</h3>
                <i class="fas fa-coins metric-icon"></i>
            </div>
            <div class="metric-value">
                <?php echo number_format($metrics['total_dividends_ytd'], 2); ?> SEK
            </div>
            <div class="metric-info">
                All-time: <?php echo number_format($metrics['total_dividends_all_time'], 2); ?> SEK
            </div>
        </div>

        <div class="metric-card">
            <div class="metric-header">
                <h3>Current Yield</h3>
                <i class="fas fa-percentage metric-icon"></i>
            </div>
            <div class="metric-value">
                <?php echo number_format($metrics['current_yield'], 2); ?>%
            </div>
            <div class="metric-info">
                Expected monthly: <?php echo number_format($metrics['expected_monthly_income'], 2); ?> SEK
            </div>
        </div>

        <div class="metric-card">
            <div class="metric-header">
                <h3>Holdings</h3>
                <i class="fas fa-building metric-icon"></i>
            </div>
            <div class="metric-value">
                <?php echo $metrics['total_holdings']; ?>
            </div>
            <div class="metric-info">
                <?php echo $metrics['total_companies']; ?> companies
            </div>
        </div>
    </div>

    <!-- Main Dashboard Content -->
    <div class="dashboard-content">
        <!-- Left Column -->
        <div class="dashboard-left">
            <!-- Portfolio Allocation -->
            <div class="dashboard-widget">
                <div class="widget-header">
                    <h2><i class="fas fa-chart-pie"></i> Portfolio Allocation</h2>
                    <div class="widget-controls">
                        <select id="allocationView" class="form-control-sm">
                            <option value="sector">By Sector</option>
                            <option value="country">By Country</option>
                            <option value="asset_class">By Asset Class</option>
                        </select>
                    </div>
                </div>
                <div class="widget-content">
                    <div class="allocation-container">
                        <canvas id="allocationChart" width="300" height="300"></canvas>
                        <div class="allocation-legend" id="allocationLegend">
                            <!-- Legend will be populated by JavaScript -->
                        </div>
                    </div>
                </div>
            </div>

            <!-- Top Paying Companies -->
            <div class="dashboard-widget">
                <div class="widget-header">
                    <h2><i class="fas fa-trophy"></i> Top Paying Companies</h2>
                    <span class="widget-subtitle">Last 12 months</span>
                </div>
                <div class="widget-content">
                    <div class="company-rankings">
                        <?php if (!empty($dividendStats['top_paying_companies'])): ?>
                            <?php foreach ($dividendStats['top_paying_companies'] as $index => $company): ?>
                                <div class="company-rank-item">
                                    <div class="rank-badge">
                                        <span class="rank-number"><?php echo $index + 1; ?></span>
                                    </div>
                                    <div class="company-info">
                                        <div class="company-name"><?php echo htmlspecialchars($company['company_name'] ?? 'Unknown Company'); ?></div>
                                        <div class="company-stats">
                                            <span class="total-amount"><?php echo number_format($company['total_dividends'], 2); ?> SEK</span>
                                            <span class="payment-count"><?php echo $company['payment_count']; ?> payments</span>
                                        </div>
                                    </div>
                                    <div class="company-progress">
                                        <div class="progress-bar" style="width: <?php echo $index === 0 ? 100 : ($company['total_dividends'] / $dividendStats['top_paying_companies'][0]['total_dividends'] * 100); ?>%"></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-chart-bar"></i>
                                <p>No dividend data available for the last 12 months</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Best Payment Days -->
            <div class="dashboard-widget">
                <div class="widget-header">
                    <h2><i class="fas fa-calendar-day"></i> Best Payment Days</h2>
                    <span class="widget-subtitle">By day of week</span>
                </div>
                <div class="widget-content">
                    <div class="day-rankings">
                        <?php if (!empty($dividendStats['best_payment_days'])): ?>
                            <?php foreach ($dividendStats['best_payment_days'] as $day): ?>
                                <div class="day-rank-item">
                                    <div class="day-name"><?php echo $day['day_name']; ?></div>
                                    <div class="day-stats">
                                        <div class="day-amount"><?php echo number_format($day['total_amount'], 2); ?> SEK</div>
                                        <div class="day-count"><?php echo $day['payment_count']; ?> payments</div>
                                    </div>
                                    <div class="day-progress">
                                        <div class="progress-bar" style="width: <?php echo ($day['total_amount'] / $dividendStats['best_payment_days'][0]['total_amount'] * 100); ?>%"></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="fas fa-calendar-alt"></i>
                                <p>No payment day data available</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Recent Dividends -->
            <div class="dashboard-widget">
                <div class="widget-header">
                    <h2><i class="fas fa-coins"></i> Recent Dividends</h2>
                    <a href="<?php echo BASE_URL; ?>/logs_dividends.php" class="btn btn-sm btn-outline">View All</a>
                </div>
                <div class="widget-content">
                    <div class="table-responsive">
                        <table class="table table-sm">
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
                                    <td><?php echo date('M j, Y', strtotime($dividend['date'])); ?></td>
                                    <td>
                                        <strong><?php echo $dividend['symbol']; ?></strong>
                                        <div class="text-muted small"><?php echo $dividend['company']; ?></div>
                                    </td>
                                    <td><?php echo number_format($dividend['shares']); ?></td>
                                    <td><?php echo $dividend['currency']; ?> <?php echo number_format($dividend['dividend_per_share'], 2); ?></td>
                                    <td><strong><?php echo number_format($dividend['sek_amount'], 2); ?></strong></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Column -->
        <div class="dashboard-right">
            <!-- Quick Stats -->
            <div class="dashboard-widget">
                <div class="widget-header">
                    <h2><i class="fas fa-info-circle"></i> Quick Stats</h2>
                </div>
                <div class="widget-content">
                    <div class="stats-grid">
                        <div class="stat-item">
                            <div class="stat-label">Dividend Streak</div>
                            <div class="stat-value"><?php echo $quickStats['dividend_streak_months']; ?> months</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-label">Best Performer</div>
                            <div class="stat-value">
                                <?php if ($quickStats['best_performing_stock']): ?>
                                    <?php echo $quickStats['best_performing_stock']['symbol']; ?>
                                    <small>(+<?php echo number_format($quickStats['best_performing_stock']['gain_percent'], 1); ?>%)</small>
                                <?php else: ?>
                                    N/A
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-label">Largest Holding</div>
                            <div class="stat-value">
                                <?php if ($quickStats['largest_holding']): ?>
                                    <?php echo $quickStats['largest_holding']['symbol']; ?>
                                    <small>(<?php echo number_format($quickStats['largest_holding']['percentage'], 1); ?>%)</small>
                                <?php else: ?>
                                    N/A
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-label">Next Ex-Div</div>
                            <div class="stat-value">
                                <?php echo $quickStats['next_ex_div_date'] ? date('M j', strtotime($quickStats['next_ex_div_date'])) : 'N/A'; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="dashboard-widget">
                <div class="widget-header">
                    <h2><i class="fas fa-bolt"></i> Quick Actions</h2>
                </div>
                <div class="widget-content">
                    <div class="quick-actions-grid">
                        <a href="<?php echo BASE_URL; ?>/masterlist_management.php" class="quick-action-card">
                            <div class="action-icon">
                                <i class="fas fa-building"></i>
                            </div>
                            <div class="action-content">
                                <h4>Masterlist</h4>
                                <p>Manage companies</p>
                            </div>
                        </a>
                        <a href="<?php echo BASE_URL; ?>/buylist_management.php" class="quick-action-card">
                            <div class="action-icon">
                                <i class="fas fa-star"></i>
                            </div>
                            <div class="action-content">
                                <h4>Buylist</h4>
                                <p>Watch & targets</p>
                            </div>
                        </a>
                        <a href="<?php echo BASE_URL; ?>/user_management.php" class="quick-action-card">
                            <div class="action-icon">
                                <i class="fas fa-user-cog"></i>
                            </div>
                            <div class="action-content">
                                <h4>User Settings</h4>
                                <p>Update profile</p>
                            </div>
                        </a>
                        <a href="<?php echo BASE_URL; ?>/logs_dividends.php" class="quick-action-card">
                            <div class="action-icon">
                                <i class="fas fa-coins"></i>
                            </div>
                            <div class="action-content">
                                <h4>Dividends</h4>
                                <p>View dividend logs</p>
                            </div>
                        </a>
                        <a href="<?php echo BASE_URL; ?>/dividend_estimate.php" class="quick-action-card">
                            <div class="action-icon">
                                <i class="fas fa-calendar-alt"></i>
                            </div>
                            <div class="action-content">
                                <h4>Estimates</h4>
                                <p>Upcoming dividends</p>
                            </div>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Upcoming Dividends -->
            <div class="dashboard-widget">
                <div class="widget-header">
                    <h2><i class="fas fa-calendar-alt"></i> Upcoming Ex-Dividend Dates</h2>
                    <a href="<?php echo BASE_URL; ?>/dividend_estimate.php" class="btn btn-sm btn-outline">View All</a>
                </div>
                <div class="widget-content">
                    <div class="table-responsive">
                        <table class="table table-sm">
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
                                        <strong><?php echo $upcoming['symbol']; ?></strong>
                                        <div class="text-muted small"><?php echo substr($upcoming['company'], 0, 20) . (strlen($upcoming['company']) > 20 ? '...' : ''); ?></div>
                                    </td>
                                    <td>
                                        <?php echo $upcoming['currency']; ?> <?php echo number_format($upcoming['estimated_total'], 2); ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Dividend Trends Chart -->
            <div class="dashboard-widget">
                <div class="widget-header">
                    <h2><i class="fas fa-chart-line"></i> Dividend Trends</h2>
                    <span class="widget-subtitle">Monthly trends (last 12 months)</span>
                </div>
                <div class="widget-content">
                    <canvas id="dividendTrendsChart" width="400" height="200"></canvas>
                    <?php if (empty($dividendStats['monthly_trends'])): ?>
                        <div class="empty-state">
                            <i class="fas fa-chart-line"></i>
                            <p>No dividend trend data available</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Dividend Insights -->
            <div class="dashboard-widget">
                <div class="widget-header">
                    <h2><i class="fas fa-lightbulb"></i> Dividend Insights</h2>
                    <span class="widget-subtitle">All time data</span>
                </div>
                <div class="widget-content">
                    <?php if (!empty($dividendStats['insights']) && $dividendStats['insights']['total_payments'] > 0): ?>
                        <div class="insights-grid">
                            <div class="insight-item">
                                <div class="insight-icon">
                                    <i class="fas fa-hand-holding-usd"></i>
                                </div>
                                <div class="insight-content">
                                    <div class="insight-value"><?php echo number_format($dividendStats['insights']['avg_payment'] ?? 0, 2); ?> SEK</div>
                                    <div class="insight-label">Average Payment</div>
                                </div>
                            </div>
                            
                            <div class="insight-item">
                                <div class="insight-icon">
                                    <i class="fas fa-star"></i>
                                </div>
                                <div class="insight-content">
                                    <div class="insight-value"><?php echo number_format($dividendStats['insights']['largest_payment'] ?? 0, 2); ?> SEK</div>
                                    <div class="insight-label">Largest Payment</div>
                                </div>
                            </div>
                            
                            <div class="insight-item">
                                <div class="insight-icon">
                                    <i class="fas fa-building"></i>
                                </div>
                                <div class="insight-content">
                                    <div class="insight-value"><?php echo $dividendStats['insights']['total_companies'] ?? 0; ?></div>
                                    <div class="insight-label">Paying Companies</div>
                                </div>
                            </div>
                            
                            <div class="insight-item">
                                <div class="insight-icon">
                                    <i class="fas fa-calendar-check"></i>
                                </div>
                                <div class="insight-content">
                                    <div class="insight-value"><?php echo $dividendStats['insights']['total_payments'] ?? 0; ?></div>
                                    <div class="insight-label">Total Payments</div>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-lightbulb"></i>
                            <p>No dividend data available. <a href="<?php echo BASE_URL; ?>/dividend_import.php">Import dividends</a> to see insights.</p>
                        </div>
                    <?php endif; ?>
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
</script>