<?php
/**
 * File: templates/pages/dividend-logs-redesign.php
 * Description: Redesigned dividend logs template using the new date range picker
 * Compatible with PSW 4.0 Beautiful Redesign Framework
 */

require_once __DIR__ . '/../../src/components/DateRangePicker.php';

$dividends = $logsData['dividends'];
$pagination = $logsData['pagination'];
$filters = $logsData['filters'];
$filterOptions = $logsData['filter_options'];
$summaryStats = $logsData['summary_stats'];

// Create date range picker instance
$dateRangePicker = new DateRangePicker('dividend-date-range', 'date', [
    'defaultMonthsBack' => 3,
    'required' => false,
    'class' => 'psw-form-field'
]);

// Set current values if they exist
if (!empty($filters['date_from']) || !empty($filters['date_to'])) {
    $dateRangePicker->setValues($filters['date_from'], $filters['date_to']);
}
?>

<div class="psw-page-container">
    <!-- Page Header -->
    <div class="psw-page-header">
        <div class="psw-page-header-content">
            <div class="psw-page-title-section">
                <h1 class="psw-page-title">
                    <div class="psw-page-icon">
                        <i class="fas fa-list-alt"></i>
                    </div>
                    Dividend Transaction History
                </h1>
                <p class="psw-page-subtitle">
                    Complete record of all dividend payments with advanced filtering and export capabilities
                </p>
            </div>
            
            <div class="psw-page-actions">
                <button class="psw-btn psw-btn-outline" onclick="exportDividendLogs()">
                    <i class="fas fa-download"></i>
                    <span>Export CSV</span>
                </button>
                <button class="psw-btn psw-btn-outline" onclick="printDividendLogs()">
                    <i class="fas fa-print"></i>
                    <span>Print</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Summary Statistics Cards -->
    <div class="psw-stats-grid">
        <div class="psw-stat-card">
            <div class="psw-stat-icon">
                <i class="fas fa-receipt"></i>
            </div>
            <div class="psw-stat-content">
                <div class="psw-stat-value"><?php echo number_format($summaryStats['total_payments']); ?></div>
                <div class="psw-stat-label">Total Payments</div>
            </div>
        </div>
        
        <div class="psw-stat-card">
            <div class="psw-stat-icon">
                <i class="fas fa-coins"></i>
            </div>
            <div class="psw-stat-content">
                <div class="psw-stat-value"><?php echo number_format($summaryStats['total_amount_sek'], 0); ?> SEK</div>
                <div class="psw-stat-label">Total Amount</div>
            </div>
        </div>
        
        <div class="psw-stat-card">
            <div class="psw-stat-icon">
                <i class="fas fa-building"></i>
            </div>
            <div class="psw-stat-content">
                <div class="psw-stat-value"><?php echo $summaryStats['unique_companies']; ?></div>
                <div class="psw-stat-label">Companies</div>
            </div>
        </div>
        
        <div class="psw-stat-card">
            <div class="psw-stat-icon">
                <i class="fas fa-chart-bar"></i>
            </div>
            <div class="psw-stat-content">
                <div class="psw-stat-value"><?php echo number_format($summaryStats['avg_amount_sek'], 0); ?> SEK</div>
                <div class="psw-stat-label">Average Payment</div>
            </div>
        </div>
        
        <div class="psw-stat-card">
            <div class="psw-stat-icon">
                <i class="fas fa-percentage"></i>
            </div>
            <div class="psw-stat-content">
                <div class="psw-stat-value"><?php echo number_format($summaryStats['total_tax_sek'], 0); ?> SEK</div>
                <div class="psw-stat-label">Total Tax Withheld</div>
            </div>
        </div>
    </div>

    <!-- Advanced Filters Section -->
    <div class="psw-card">
        <div class="psw-card-header">
            <h2 class="psw-card-title">
                <i class="fas fa-filter"></i>
                Filter & Search
            </h2>
            <div class="psw-card-actions">
                <button type="button" class="psw-btn psw-btn-ghost psw-btn-sm" onclick="toggleFilters()">
                    <i class="fas fa-chevron-up" id="filter-toggle-icon"></i>
                </button>
            </div>
        </div>
        
        <div class="psw-card-content" id="filters-content">
            <form method="GET" action="" class="psw-filters-form">
                <div class="psw-filters-grid">
                    <!-- Date Range Picker - The New Central Component -->
                    <div class="psw-form-group">
                        <?php echo $dateRangePicker->renderField('Date Range', ['class' => 'psw-form-group', 'labelClass' => 'psw-form-label']); ?>
                    </div>

                    <!-- Year Filter -->
                    <div class="psw-form-group">
                        <label for="year" class="psw-form-label">Year</label>
                        <select name="year" id="year" class="psw-form-field">
                            <option value="">All Years</option>
                            <?php foreach ($filterOptions['years'] as $year): ?>
                                <option value="<?php echo $year; ?>" <?php echo ($filters['year'] == $year) ? 'selected' : ''; ?>>
                                    <?php echo $year; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Currency Filter -->
                    <div class="psw-form-group">
                        <label for="currency" class="psw-form-label">Currency</label>
                        <select name="currency" id="currency" class="psw-form-field">
                            <option value="">All Currencies</option>
                            <?php foreach ($filterOptions['currencies'] as $currency): ?>
                                <option value="<?php echo $currency; ?>" <?php echo ($filters['currency'] == $currency) ? 'selected' : ''; ?>>
                                    <?php echo $currency; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Company Search -->
                    <div class="psw-form-group">
                        <label for="company" class="psw-form-label">Company</label>
                        <input type="text" name="company" id="company" class="psw-form-field" 
                               placeholder="Company name or ticker" value="<?php echo htmlspecialchars($filters['company']); ?>">
                    </div>

                    <!-- Amount Range -->
                    <div class="psw-form-group">
                        <label for="amount_min" class="psw-form-label">Min Amount (SEK)</label>
                        <input type="number" name="amount_min" id="amount_min" class="psw-form-field" 
                               placeholder="0" step="0.01" value="<?php echo htmlspecialchars($filters['amount_min']); ?>">
                    </div>

                    <div class="psw-form-group">
                        <label for="amount_max" class="psw-form-label">Max Amount (SEK)</label>
                        <input type="number" name="amount_max" id="amount_max" class="psw-form-field" 
                               placeholder="No limit" step="0.01" value="<?php echo htmlspecialchars($filters['amount_max']); ?>">
                    </div>

                    <!-- Results Per Page -->
                    <div class="psw-form-group">
                        <label for="per_page" class="psw-form-label">Per Page</label>
                        <select name="per_page" id="per_page" class="psw-form-field">
                            <option value="25" <?php echo ($pagination['per_page'] == 25) ? 'selected' : ''; ?>>25</option>
                            <option value="50" <?php echo ($pagination['per_page'] == 50) ? 'selected' : ''; ?>>50</option>
                            <option value="100" <?php echo ($pagination['per_page'] == 100) ? 'selected' : ''; ?>>100</option>
                            <option value="200" <?php echo ($pagination['per_page'] == 200) ? 'selected' : ''; ?>>200</option>
                        </select>
                    </div>
                </div>

                <!-- Filter Actions -->
                <div class="psw-filters-actions">
                    <button type="submit" class="psw-btn psw-btn-primary">
                        <i class="fas fa-search"></i>
                        <span>Apply Filters</span>
                    </button>
                    <a href="<?php echo $_SERVER['PHP_SELF']; ?>" class="psw-btn psw-btn-outline">
                        <i class="fas fa-times"></i>
                        <span>Clear All</span>
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Results Section -->
    <div class="psw-card">
        <div class="psw-card-header">
            <h2 class="psw-card-title">
                Dividend Transactions 
                <span class="psw-results-count">
                    (<?php echo number_format($pagination['total_items']); ?> records)
                </span>
            </h2>
            
            <div class="psw-card-actions">
                <div class="psw-sort-options">
                    <label class="psw-form-label">Sort by:</label>
                    <select id="sortBy" class="psw-form-field psw-form-field-sm" onchange="changeSorting()">
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
        </div>

        <div class="psw-card-content">
            <?php if (empty($dividends)): ?>
                <div class="psw-empty-state">
                    <div class="psw-empty-icon">
                        <i class="fas fa-search"></i>
                    </div>
                    <h3 class="psw-empty-title">No dividend records found</h3>
                    <p class="psw-empty-description">Try adjusting your filter criteria to see results.</p>
                    <a href="<?php echo $_SERVER['PHP_SELF']; ?>" class="psw-btn psw-btn-primary">
                        <i class="fas fa-refresh"></i>
                        Reset Filters
                    </a>
                </div>
            <?php else: ?>
                <div class="psw-table-container">
                    <table class="psw-table">
                        <thead>
                            <tr>
                                <th>Ex-Date</th>
                                <th>Pay Date</th>
                                <th>Company</th>
                                <th>Country</th>
                                <th class="psw-text-right">Shares</th>
                                <th class="psw-text-right">Per Share</th>
                                <th class="psw-text-right">Total Original</th>
                                <th class="psw-text-right">Total SEK</th>
                                <th class="psw-text-right">Tax SEK</th>
                                <th class="psw-text-right">Net SEK</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($dividends as $dividend): ?>
                                <tr class="psw-table-row">
                                    <td><?php echo date('Y-m-d', strtotime($dividend['ex_date'])); ?></td>
                                    <td><?php echo date('Y-m-d', strtotime($dividend['pay_date'])); ?></td>
                                    <td>
                                        <div class="psw-company-info">
                                            <div class="psw-company-name"><?php echo htmlspecialchars($dividend['company_name']); ?></div>
                                            <div class="psw-company-details">
                                                <span class="psw-ticker"><?php echo htmlspecialchars($dividend['ticker_symbol']); ?></span>
                                                <span class="psw-isin"><?php echo htmlspecialchars($dividend['isin']); ?></span>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="psw-country">
                                            <span class="psw-country-flag"><?php echo $dividend['country_code']; ?></span>
                                            <span class="psw-country-name"><?php echo $dividend['country_name'] ?? 'Unknown'; ?></span>
                                        </div>
                                    </td>
                                    <td class="psw-text-right"><?php echo number_format($dividend['shares']); ?></td>
                                    <td class="psw-text-right">
                                        <div class="psw-currency-amount">
                                            <span class="psw-currency"><?php echo $dividend['original_currency']; ?></span>
                                            <span class="psw-amount"><?php echo number_format($dividend['dividend_per_share'], 4); ?></span>
                                        </div>
                                    </td>
                                    <td class="psw-text-right">
                                        <div class="psw-currency-amount">
                                            <span class="psw-currency"><?php echo $dividend['original_currency']; ?></span>
                                            <span class="psw-amount"><?php echo number_format($dividend['dividend_total_original'], 2); ?></span>
                                        </div>
                                    </td>
                                    <td class="psw-text-right">
                                        <div class="psw-amount-highlight">
                                            <?php echo number_format($dividend['dividend_total_sek'], 2); ?> SEK
                                        </div>
                                    </td>
                                    <td class="psw-text-right">
                                        <div class="psw-tax-amount">
                                            <?php echo number_format($dividend['withholding_tax_sek'], 2); ?> SEK
                                        </div>
                                    </td>
                                    <td class="psw-text-right">
                                        <div class="psw-net-amount">
                                            <?php echo number_format($dividend['net_dividend_sek'], 2); ?> SEK
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($pagination['total_pages'] > 1): ?>
                    <div class="psw-pagination">
                        <div class="psw-pagination-info">
                            Showing <?php echo number_format(($pagination['current_page'] - 1) * $pagination['per_page'] + 1); ?> 
                            to <?php echo number_format(min($pagination['current_page'] * $pagination['per_page'], $pagination['total_items'])); ?> 
                            of <?php echo number_format($pagination['total_items']); ?> records
                        </div>
                        
                        <div class="psw-pagination-controls">
                            <?php if ($pagination['has_prev']): ?>
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $pagination['prev_page']])); ?>" 
                                   class="psw-btn psw-btn-outline psw-btn-sm">
                                    <i class="fas fa-chevron-left"></i>
                                    Previous
                                </a>
                            <?php endif; ?>
                            
                            <?php
                            $startPage = max(1, $pagination['current_page'] - 2);
                            $endPage = min($pagination['total_pages'], $pagination['current_page'] + 2);
                            
                            for ($i = $startPage; $i <= $endPage; $i++):
                            ?>
                                <?php if ($i == $pagination['current_page']): ?>
                                    <span class="psw-btn psw-btn-primary psw-btn-sm psw-current-page"><?php echo $i; ?></span>
                                <?php else: ?>
                                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>" 
                                       class="psw-btn psw-btn-outline psw-btn-sm"><?php echo $i; ?></a>
                                <?php endif; ?>
                            <?php endfor; ?>
                            
                            <?php if ($pagination['has_next']): ?>
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $pagination['next_page']])); ?>" 
                                   class="psw-btn psw-btn-outline psw-btn-sm">
                                    Next
                                    <i class="fas fa-chevron-right"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Store data for JavaScript -->
<script>
window.dividendLogsData = {
    filters: <?php echo json_encode($filters); ?>,
    pagination: <?php echo json_encode($pagination); ?>,
    summaryStats: <?php echo json_encode($summaryStats); ?>
};

// Initialize filter toggle
function toggleFilters() {
    const content = document.getElementById('filters-content');
    const icon = document.getElementById('filter-toggle-icon');
    
    if (content.style.display === 'none') {
        content.style.display = 'block';
        icon.className = 'fas fa-chevron-up';
    } else {
        content.style.display = 'none';
        icon.className = 'fas fa-chevron-down';
    }
}
</script>

<!-- Additional CSS for dividend logs specific styling -->
<style>
.psw-filters-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: var(--spacing-4);
    margin-bottom: var(--spacing-6);
}

.psw-company-info {
    display: flex;
    flex-direction: column;
    gap: var(--spacing-1);
}

.psw-company-name {
    font-weight: 600;
    color: var(--text-primary);
}

.psw-company-details {
    display: flex;
    gap: var(--spacing-2);
    font-size: var(--font-size-xs);
    color: var(--text-secondary);
}

.psw-ticker {
    font-weight: 500;
}

.psw-isin {
    opacity: 0.8;
}

.psw-country {
    display: flex;
    align-items: center;
    gap: var(--spacing-2);
}

.psw-country-flag {
    font-weight: 600;
    font-size: var(--font-size-xs);
}

.psw-currency-amount {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
}

.psw-currency {
    font-size: var(--font-size-xs);
    color: var(--text-secondary);
    margin-bottom: var(--spacing-1);
}

.psw-amount-highlight {
    font-weight: 600;
    color: var(--success-color);
}

.psw-tax-amount {
    color: var(--warning-color);
}

.psw-net-amount {
    font-weight: 700;
    color: var(--text-primary);
}

.psw-results-count {
    font-size: var(--font-size-sm);
    color: var(--text-secondary);
    font-weight: 400;
}

.psw-sort-options {
    display: flex;
    align-items: center;
    gap: var(--spacing-2);
}

.psw-sort-options .psw-form-label {
    margin: 0;
    font-size: var(--font-size-sm);
}

@media (max-width: 768px) {
    .psw-filters-grid {
        grid-template-columns: 1fr;
    }
    
    .psw-company-details {
        flex-direction: column;
        gap: var(--spacing-1);
    }
}
</style>