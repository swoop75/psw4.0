<?php
/**
 * File: templates/pages/dividend-estimate.php
 * Path: C:\Users\laoan\Documents\GitHub\psw\psw4.0\templates\pages\dividend-estimate.php
 * Description: Dividend estimate overview template for PSW 4.0
 */

$annualEstimates = $estimateData['annual_estimates'];
$monthlyBreakdown = $estimateData['monthly_breakdown'];
$quarterlySummary = $estimateData['quarterly_summary'];
$upcomingPayments = $estimateData['upcoming_payments'];
$estimateAccuracy = $estimateData['estimate_accuracy'];
$growthForecast = $estimateData['dividend_growth_forecast'];
$yieldProjections = $estimateData['yield_projections'];
?>

<div class="dividend-estimate-container">
    <!-- Page Header -->
    <div class="page-header">
        <h1>
            <i class="fas fa-chart-line"></i>
            Dividend Estimate Overview
        </h1>
        <p class="page-subtitle">
            Income forecasts and dividend projections based on historical data and current holdings
        </p>
    </div>

    <!-- Annual Summary Cards -->
    <div class="annual-summary">
        <div class="summary-card">
            <div class="summary-header">
                <h3>Current Year Estimate</h3>
                <i class="fas fa-calendar-alt summary-icon"></i>
            </div>
            <div class="summary-value">
                <?php echo number_format($annualEstimates['current_year_estimate'], 2); ?> SEK
            </div>
            <div class="summary-details">
                <div class="detail-item">
                    <span class="detail-label">YTD Actual:</span>
                    <span class="detail-value"><?php echo number_format($annualEstimates['ytd_actual'], 2); ?> SEK</span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Remaining:</span>
                    <span class="detail-value"><?php echo number_format($annualEstimates['remaining_estimate'], 2); ?> SEK</span>
                </div>
            </div>
        </div>

        <div class="summary-card">
            <div class="summary-header">
                <h3>Growth Projection</h3>
                <i class="fas fa-trending-up summary-icon"></i>
            </div>
            <div class="summary-value">
                <?php echo number_format($growthForecast['current_growth_rate'], 1); ?>%
            </div>
            <div class="summary-details">
                <div class="detail-item">
                    <span class="detail-label">Trend:</span>
                    <span class="detail-value"><?php echo $growthForecast['growth_sustainability']; ?></span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Previous Year:</span>
                    <span class="detail-value"><?php echo number_format($annualEstimates['previous_year_actual'], 2); ?> SEK</span>
                </div>
            </div>
        </div>

        <div class="summary-card">
            <div class="summary-header">
                <h3>Current Yield</h3>
                <i class="fas fa-percentage summary-icon"></i>
            </div>
            <div class="summary-value">
                <?php echo number_format($yieldProjections['current_yield'], 2); ?>%
            </div>
            <div class="summary-details">
                <div class="detail-item">
                    <span class="detail-label">Trend:</span>
                    <span class="detail-value"><?php echo $yieldProjections['yield_trend']; ?></span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Accuracy:</span>
                    <span class="detail-value"><?php echo number_format($estimateAccuracy['overall_accuracy'], 1); ?>%</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content Grid -->
    <div class="estimate-content">
        <!-- Left Column -->
        <div class="estimate-left">
            <!-- Quarterly Summary -->
            <div class="estimate-widget">
                <div class="widget-header">
                    <h2><i class="fas fa-calendar-check"></i> Quarterly Summary</h2>
                    <div class="widget-controls">
                        <select id="quarterYear" class="form-control-sm">
                            <option value="<?php echo date('Y'); ?>"><?php echo date('Y'); ?></option>
                            <option value="<?php echo date('Y') - 1; ?>"><?php echo date('Y') - 1; ?></option>
                        </select>
                    </div>
                </div>
                <div class="widget-content">
                    <div class="quarterly-grid">
                        <?php foreach ($quarterlySummary as $quarter): ?>
                        <div class="quarter-card <?php echo $quarter['is_complete'] ? 'completed' : 'estimated'; ?>">
                            <div class="quarter-header">
                                <h4><?php echo $quarter['quarter']; ?></h4>
                                <span class="quarter-status">
                                    <?php echo $quarter['is_complete'] ? 'Actual' : 'Estimate'; ?>
                                </span>
                            </div>
                            <div class="quarter-amount">
                                <?php echo number_format($quarter['is_complete'] ? $quarter['actual_amount'] : $quarter['estimated_amount'], 0); ?> SEK
                            </div>
                            <div class="quarter-details">
                                <span class="payment-count"><?php echo $quarter['payment_count']; ?> payments</span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Monthly Breakdown Chart -->
            <div class="estimate-widget">
                <div class="widget-header">
                    <h2><i class="fas fa-chart-bar"></i> Monthly Breakdown</h2>
                    <div class="widget-controls">
                        <button class="btn btn-sm btn-outline" onclick="toggleChartView()">
                            <i class="fas fa-exchange-alt"></i> Toggle View
                        </button>
                    </div>
                </div>
                <div class="widget-content">
                    <canvas id="monthlyChart" width="400" height="200"></canvas>
                    <div class="chart-legend">
                        <div class="legend-item">
                            <span class="legend-color actual"></span>
                            <span class="legend-label">Actual</span>
                        </div>
                        <div class="legend-item">
                            <span class="legend-color estimated"></span>
                            <span class="legend-label">Estimated</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Column -->
        <div class="estimate-right">
            <!-- Upcoming Payments -->
            <div class="estimate-widget">
                <div class="widget-header">
                    <h2><i class="fas fa-clock"></i> Upcoming Payments</h2>
                    <a href="<?php echo BASE_URL; ?>/dividend_estimate_monthly.php" class="btn btn-sm btn-outline">View Monthly</a>
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
                                <?php foreach (array_slice($upcomingPayments, 0, 10) as $payment): ?>
                                <tr>
                                    <td><?php echo date('M j', strtotime($payment['ex_date'])); ?></td>
                                    <td>
                                        <strong><?php echo $payment['symbol']; ?></strong>
                                        <div class="text-muted small"><?php echo substr($payment['company'], 0, 25) . (strlen($payment['company']) > 25 ? '...' : ''); ?></div>
                                    </td>
                                    <td>
                                        <strong><?php echo number_format($payment['estimated_total'], 0); ?></strong>
                                        <small class="text-muted"><?php echo $payment['currency']; ?></small>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Estimate Accuracy -->
            <div class="estimate-widget">
                <div class="widget-header">
                    <h2><i class="fas fa-target"></i> Estimate Accuracy</h2>
                    <small class="text-muted">Last updated: <?php echo $estimateAccuracy['last_updated'] ? date('M j, Y', strtotime($estimateAccuracy['last_updated'])) : 'N/A'; ?></small>
                </div>
                <div class="widget-content">
                    <div class="accuracy-metrics">
                        <div class="accuracy-item">
                            <div class="accuracy-label">Overall Accuracy</div>
                            <div class="accuracy-bar">
                                <div class="accuracy-fill" style="width: <?php echo $estimateAccuracy['overall_accuracy']; ?>%;"></div>
                            </div>
                            <div class="accuracy-value"><?php echo number_format($estimateAccuracy['overall_accuracy'], 1); ?>%</div>
                        </div>
                        
                        <div class="accuracy-item">
                            <div class="accuracy-label">Monthly Accuracy</div>
                            <div class="accuracy-bar">
                                <div class="accuracy-fill" style="width: <?php echo $estimateAccuracy['monthly_accuracy']; ?>%;"></div>
                            </div>
                            <div class="accuracy-value"><?php echo number_format($estimateAccuracy['monthly_accuracy'], 1); ?>%</div>
                        </div>
                        
                        <div class="accuracy-item">
                            <div class="accuracy-label">Annual Accuracy</div>
                            <div class="accuracy-bar">
                                <div class="accuracy-fill" style="width: <?php echo $estimateAccuracy['annual_accuracy']; ?>%;"></div>
                            </div>
                            <div class="accuracy-value"><?php echo number_format($estimateAccuracy['annual_accuracy'], 1); ?>%</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Growth Forecast -->
            <div class="estimate-widget">
                <div class="widget-header">
                    <h2><i class="fas fa-seedling"></i> Growth Outlook</h2>
                </div>
                <div class="widget-content">
                    <div class="growth-metrics">
                        <div class="growth-item">
                            <div class="growth-label">Current Growth Rate</div>
                            <div class="growth-value positive">
                                +<?php echo number_format($growthForecast['current_growth_rate'], 1); ?>%
                            </div>
                        </div>
                        
                        <div class="growth-item">
                            <div class="growth-label">Sustainability</div>
                            <div class="growth-sustainability <?php echo strtolower($growthForecast['growth_sustainability']); ?>">
                                <?php echo $growthForecast['growth_sustainability']; ?>
                            </div>
                        </div>
                        
                        <div class="growth-item">
                            <div class="growth-label">Yield Trend</div>
                            <div class="yield-trend <?php echo strtolower($yieldProjections['yield_trend']); ?>">
                                <?php echo $yieldProjections['yield_trend']; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Store data for JavaScript -->
<script>
window.dividendEstimateData = {
    monthlyBreakdown: <?php echo json_encode($monthlyBreakdown); ?>,
    quarterlySummary: <?php echo json_encode($quarterlySummary); ?>,
    annualEstimates: <?php echo json_encode($annualEstimates); ?>
};
</script>