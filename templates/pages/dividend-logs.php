<?php
/**
 * File: templates/pages/dividend-logs.php
 * Path: C:\Users\laoan\Documents\GitHub\psw\psw4.0\templates\pages\dividend-logs.php
 * Description: Dividend logs template for PSW 4.0
 */

$dividends = $logsData['dividends'];
$pagination = $logsData['pagination'];
$filters = $logsData['filters'];
$filterOptions = $logsData['filter_options'];
$summaryStats = $logsData['summary_stats'];
?>

<div class="dividend-logs-container">
    <!-- Page Header -->
    <div class="page-header">
        <div class="header-content">
            <h1>
                <i class="fas fa-list-alt"></i>
                Dividend Transaction History
            </h1>
            <p class="page-subtitle">
                Complete record of all dividend payments with filtering and export capabilities
            </p>
        </div>
        <div class="header-actions">
            <button class="btn btn-outline" onclick="exportDividendLogs()">
                <i class="fas fa-download"></i> Export CSV
            </button>
            <button class="btn btn-outline" onclick="printDividendLogs()">
                <i class="fas fa-print"></i> Print
            </button>
        </div>
    </div>

    <!-- Summary Statistics -->
    <div class="summary-stats">
        <div class="stat-card">
            <div class="stat-value"><?php echo number_format($summaryStats['total_payments']); ?></div>
            <div class="stat-label">Total Payments</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?php echo number_format($summaryStats['total_amount_sek'], 0); ?> SEK</div>
            <div class="stat-label">Total Amount</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?php echo $summaryStats['unique_companies']; ?></div>
            <div class="stat-label">Companies</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?php echo number_format($summaryStats['avg_amount_sek'], 0); ?> SEK</div>
            <div class="stat-label">Average Payment</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?php echo number_format($summaryStats['total_tax_sek'], 0); ?> SEK</div>
            <div class="stat-label">Total Tax Withheld</div>
        </div>
    </div>

    <!-- Filters -->
    <div class="filters-section">
        <form method="GET" action="" class="filters-form">
            <div class="filters-grid">
                <div class="filter-group">
                    <label for="year">Year</label>
                    <select name="year" id="year" class="form-control">
                        <option value="">All Years</option>
                        <?php foreach ($filterOptions['years'] as $year): ?>
                            <option value="<?php echo $year; ?>" <?php echo ($filters['year'] == $year) ? 'selected' : ''; ?>>
                                <?php echo $year; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-group">
                    <label for="currency">Currency</label>
                    <select name="currency" id="currency" class="form-control">
                        <option value="">All Currencies</option>
                        <?php foreach ($filterOptions['currencies'] as $currency): ?>
                            <option value="<?php echo $currency; ?>" <?php echo ($filters['currency'] == $currency) ? 'selected' : ''; ?>>
                                <?php echo $currency; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-group">
                    <label for="company">Company</label>
                    <input type="text" name="company" id="company" class="form-control" 
                           placeholder="Company name or ticker" value="<?php echo htmlspecialchars($filters['company']); ?>">
                </div>

                <div class="filter-group">
                    <label for="date_from">From Date</label>
                    <input type="date" name="date_from" id="date_from" class="form-control" 
                           value="<?php echo htmlspecialchars($filters['date_from']); ?>">
                </div>

                <div class="filter-group">
                    <label for="date_to">To Date</label>
                    <input type="date" name="date_to" id="date_to" class="form-control" 
                           value="<?php echo htmlspecialchars($filters['date_to']); ?>">
                </div>

                <div class="filter-group">
                    <label for="amount_min">Min Amount (SEK)</label>
                    <input type="number" name="amount_min" id="amount_min" class="form-control" 
                           placeholder="0" step="0.01" value="<?php echo htmlspecialchars($filters['amount_min']); ?>">
                </div>

                <div class="filter-group">
                    <label for="amount_max">Max Amount (SEK)</label>
                    <input type="number" name="amount_max" id="amount_max" class="form-control" 
                           placeholder="No limit" step="0.01" value="<?php echo htmlspecialchars($filters['amount_max']); ?>">
                </div>

                <div class="filter-group">
                    <label for="per_page">Per Page</label>
                    <select name="per_page" id="per_page" class="form-control">
                        <option value="25" <?php echo ($pagination['per_page'] == 25) ? 'selected' : ''; ?>>25</option>
                        <option value="50" <?php echo ($pagination['per_page'] == 50) ? 'selected' : ''; ?>>50</option>
                        <option value="100" <?php echo ($pagination['per_page'] == 100) ? 'selected' : ''; ?>>100</option>
                        <option value="200" <?php echo ($pagination['per_page'] == 200) ? 'selected' : ''; ?>>200</option>
                    </select>
                </div>
            </div>

            <div class="filter-actions">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-search"></i> Apply Filters
                </button>
                <a href="<?php echo $_SERVER['PHP_SELF']; ?>" class="btn btn-outline">
                    <i class="fas fa-times"></i> Clear All
                </a>
            </div>
        </form>
    </div>

    <!-- Results Table -->
    <div class="results-section">
        <div class="results-header">
            <h2>
                Dividend Transactions 
                <span class="results-count">
                    (<?php echo number_format($pagination['total_items']); ?> records)
                </span>
            </h2>
            <div class="sort-options">
                <label>Sort by:</label>
                <select id="sortBy" onchange="changeSorting()">
                    <option value="ex_date_DESC" <?php echo ($filters['sort'] == 'ex_date' && $filters['order'] == 'DESC') ? 'selected' : ''; ?>>
                        Ex-Date (Newest)
                    </option>
                    <option value="ex_date_ASC" <?php echo ($filters['sort'] == 'ex_date' && $filters['order'] == 'ASC') ? 'selected' : ''; ?>>
                        Ex-Date (Oldest)
                    </option>
                    <option value="dividend_total_sek_DESC" <?php echo ($filters['sort'] == 'dividend_total_sek' && $filters['order'] == 'DESC') ? 'selected' : ''; ?>>
                        Amount (Highest)
                    </option>
                    <option value="dividend_total_sek_ASC" <?php echo ($filters['sort'] == 'dividend_total_sek' && $filters['order'] == 'ASC') ? 'selected' : ''; ?>>
                        Amount (Lowest)
                    </option>
                    <option value="company_name_ASC" <?php echo ($filters['sort'] == 'company_name' && $filters['order'] == 'ASC') ? 'selected' : ''; ?>>
                        Company (A-Z)
                    </option>
                </select>
            </div>
        </div>

        <?php if (empty($dividends)): ?>
            <div class="no-results">
                <i class="fas fa-search"></i>
                <h3>No dividend records found</h3>
                <p>Try adjusting your filter criteria to see results.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="dividends-table">
                    <thead>
                        <tr>
                            <th>Ex-Date</th>
                            <th>Pay Date</th>
                            <th>Company</th>
                            <th>Country</th>
                            <th class="text-right">Shares</th>
                            <th class="text-right">Per Share</th>
                            <th class="text-right">Total Original</th>
                            <th class="text-right">Total SEK</th>
                            <th class="text-right">Tax SEK</th>
                            <th class="text-right">Net SEK</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($dividends as $dividend): ?>
                            <tr>
                                <td><?php echo date('Y-m-d', strtotime($dividend['ex_date'])); ?></td>
                                <td><?php echo date('Y-m-d', strtotime($dividend['pay_date'])); ?></td>
                                <td>
                                    <div class="company-info">
                                        <strong><?php echo htmlspecialchars($dividend['company_name']); ?></strong>
                                        <div class="company-details">
                                            <span class="ticker"><?php echo htmlspecialchars($dividend['ticker_symbol']); ?></span>
                                            <span class="isin"><?php echo htmlspecialchars($dividend['isin']); ?></span>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="country-flag"><?php echo $dividend['country_code']; ?></span>
                                    <?php echo $dividend['country_name'] ?? 'Unknown'; ?>
                                </td>
                                <td class="text-right"><?php echo number_format($dividend['shares']); ?></td>
                                <td class="text-right">
                                    <?php echo $dividend['original_currency']; ?> 
                                    <?php echo number_format($dividend['dividend_per_share'], 4); ?>
                                </td>
                                <td class="text-right">
                                    <?php echo $dividend['original_currency']; ?> 
                                    <?php echo number_format($dividend['dividend_total_original'], 2); ?>
                                </td>
                                <td class="text-right amount-highlight">
                                    <?php echo number_format($dividend['dividend_total_sek'], 2); ?>
                                </td>
                                <td class="text-right tax-amount">
                                    <?php echo number_format($dividend['withholding_tax_sek'], 2); ?>
                                </td>
                                <td class="text-right net-amount">
                                    <strong><?php echo number_format($dividend['net_dividend_sek'], 2); ?></strong>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($pagination['total_pages'] > 1): ?>
                <div class="pagination-section">
                    <div class="pagination-info">
                        Showing <?php echo number_format(($pagination['current_page'] - 1) * $pagination['per_page'] + 1); ?> 
                        to <?php echo number_format(min($pagination['current_page'] * $pagination['per_page'], $pagination['total_items'])); ?> 
                        of <?php echo number_format($pagination['total_items']); ?> records
                    </div>
                    
                    <div class="pagination-controls">
                        <?php if ($pagination['has_prev']): ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $pagination['prev_page']])); ?>" 
                               class="btn btn-outline btn-sm">
                                <i class="fas fa-chevron-left"></i> Previous
                            </a>
                        <?php endif; ?>
                        
                        <?php
                        $startPage = max(1, $pagination['current_page'] - 2);
                        $endPage = min($pagination['total_pages'], $pagination['current_page'] + 2);
                        
                        for ($i = $startPage; $i <= $endPage; $i++):
                        ?>
                            <?php if ($i == $pagination['current_page']): ?>
                                <span class="btn btn-primary btn-sm current-page"><?php echo $i; ?></span>
                            <?php else: ?>
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>" 
                                   class="btn btn-outline btn-sm"><?php echo $i; ?></a>
                            <?php endif; ?>
                        <?php endfor; ?>
                        
                        <?php if ($pagination['has_next']): ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $pagination['next_page']])); ?>" 
                               class="btn btn-outline btn-sm">
                                Next <i class="fas fa-chevron-right"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Store data for JavaScript -->
<script>
window.dividendLogsData = {
    filters: <?php echo json_encode($filters); ?>,
    pagination: <?php echo json_encode($pagination); ?>,
    summaryStats: <?php echo json_encode($summaryStats); ?>
};
</script>