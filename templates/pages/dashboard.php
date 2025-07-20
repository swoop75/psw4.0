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
?>

<div class="dashboard-container">
    <!-- Welcome Header -->
    <div class="dashboard-header">
        <h1>
            <i class="fas fa-tachometer-alt"></i>
            Dashboard
        </h1>
        <p class="dashboard-subtitle">
            Welcome back, <?php echo Auth::getUsername(); ?>! Here's your portfolio overview.
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
                                    <td><?php echo date('M j', strtotime($dividend['date'])); ?></td>
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

            <!-- Performance Chart Placeholder -->
            <div class="dashboard-widget">
                <div class="widget-header">
                    <h2><i class="fas fa-chart-area"></i> Portfolio Performance</h2>
                    <div class="widget-controls">
                        <select id="performanceTimeframe" class="form-control-sm">
                            <option value="1M">1 Month</option>
                            <option value="3M">3 Months</option>
                            <option value="6M">6 Months</option>
                            <option value="1Y" selected>1 Year</option>
                        </select>
                    </div>
                </div>
                <div class="widget-content">
                    <canvas id="performanceChart" width="400" height="200"></canvas>
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
    user: {
        isAdmin: <?php echo Auth::isAdmin() ? 'true' : 'false'; ?>,
        username: '<?php echo Auth::getUsername(); ?>'
    }
};
</script>