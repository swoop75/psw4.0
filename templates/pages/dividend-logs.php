<?php
/**
 * File: templates/pages/dividend-logs.php
 * Path: C:\Users\laoan\Documents\GitHub\psw\psw4.0\templates\pages\dividend-logs.php
 * Description: Dividend logs template for PSW 4.0 - Updated with new date range picker
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

                <!-- NEW: Central Date Range Picker -->
                <div class="filter-group">
                    <label>Date Range</label>
                    <div id="dividend-date-range" class="date-range-picker">
                        <input type="hidden" name="date_from" value="<?php echo htmlspecialchars($filters['date_from']); ?>">
                        <input type="hidden" name="date_to" value="<?php echo htmlspecialchars($filters['date_to']); ?>">
                        
                        <div class="date-range-display" onclick="toggleDateRangePicker()">
                            <i class="fas fa-calendar-alt"></i>
                            <span class="date-range-text" id="dateRangeText">
                                <?php 
                                if ($filters['date_from'] && $filters['date_to']) {
                                    echo $filters['date_from'] . ' - ' . $filters['date_to'];
                                } else {
                                    $defaultFrom = date('Y-m-01', strtotime('-3 months'));
                                    $defaultTo = date('Y-m-t');
                                    echo $defaultFrom . ' - ' . $defaultTo;
                                }
                                ?>
                            </span>
                            <i class="fas fa-chevron-down"></i>
                        </div>
                        
                        <div class="date-range-overlay" id="dateRangeOverlay">
                            <div class="date-range-header">
                                <h4>Date Range Selector</h4>
                                <button type="button" class="close-btn" onclick="closeDateRangePicker()">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                            <div class="date-range-content">
                                <div class="date-range-panels">
                                    <div class="date-panel from-panel">
                                        <h5>From Date</h5>
                                        <input type="text" class="date-input" id="fromDateInput" placeholder="YYYY-MM-DD">
                                    </div>
                                    <div class="date-panel to-panel">
                                        <h5>To Date</h5>
                                        <input type="text" class="date-input" id="toDateInput" placeholder="YYYY-MM-DD">
                                    </div>
                                    <div class="presets-panel">
                                        <h5>Quick Presets</h5>
                                        <div class="presets-grid">
                                            <button type="button" class="preset-btn" onclick="applyPreset('today')">Today</button>
                                            <button type="button" class="preset-btn" onclick="applyPreset('yesterday')">Yesterday</button>
                                            <button type="button" class="preset-btn" onclick="applyPreset('thisWeek')">This Week</button>
                                            <button type="button" class="preset-btn" onclick="applyPreset('prevWeek')">Previous Week</button>
                                            <button type="button" class="preset-btn" onclick="applyPreset('thisMonth')">This Month</button>
                                            <button type="button" class="preset-btn" onclick="applyPreset('prevMonth')">Previous Month</button>
                                            <button type="button" class="preset-btn" onclick="applyPreset('thisYear')">This Year</button>
                                            <button type="button" class="preset-btn" onclick="applyPreset('prevYear')">Previous Year</button>
                                            <button type="button" class="preset-btn" onclick="applyPreset('sinceStart')">Since Start</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="date-range-footer">
                                <button type="button" class="btn-date-range btn-outline-date" onclick="closeDateRangePicker()">Cancel</button>
                                <button type="button" class="btn-date-range btn-primary-date" onclick="applyDateRange()">Apply</button>
                            </div>
                        </div>
                    </div>
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

<!-- Date Range Picker Styles - Inline for immediate loading -->
<style>
/* Date Range Picker Styles */
.date-range-picker {
    position: relative;
    display: inline-block;
    width: 100%;
    max-width: 300px;
}

.date-range-display {
    display: flex;
    align-items: center;
    padding: 8px 12px;
    background: white;
    border: 1px solid #ddd;
    border-radius: 4px;
    cursor: pointer;
    transition: all 0.2s ease;
    min-height: 40px;
}

.date-range-display:hover {
    border-color: #7c3aed;
    box-shadow: 0 0 0 2px rgba(124, 58, 237, 0.1);
}

.date-range-display i.fa-calendar-alt {
    color: #6c757d;
    margin-right: 8px;
}

.date-range-text {
    flex: 1;
    font-size: 14px;
    font-weight: 500;
    color: #333;
}

.date-range-display i.fa-chevron-down {
    color: #6c757d;
    font-size: 12px;
    transition: transform 0.2s ease;
}

.date-range-picker.open .date-range-display i.fa-chevron-down {
    transform: rotate(180deg);
}

.date-range-overlay {
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    background: white;
    border: 1px solid #ddd;
    border-radius: 8px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
    z-index: 1000;
    min-width: 900px;
    margin-top: 4px;
    display: none;
}

.date-range-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 16px 20px;
    border-bottom: 1px solid #eee;
}

.date-range-header h4 {
    margin: 0;
    font-size: 16px;
    font-weight: 600;
    color: #333;
}

.close-btn {
    background: none;
    border: none;
    font-size: 16px;
    color: #6c757d;
    cursor: pointer;
    padding: 4px;
    border-radius: 4px;
}

.close-btn:hover {
    background: #f8f9fa;
}

.date-range-content {
    padding: 20px;
}

.date-range-panels {
    display: grid;
    grid-template-columns: 1fr 1fr 300px;
    gap: 20px;
}

.date-panel {
    background: #f8f9fa;
    border-radius: 8px;
    padding: 16px;
}

.date-panel h5 {
    margin: 0 0 12px 0;
    font-size: 14px;
    font-weight: 600;
    color: #495057;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.date-input {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid #ced4da;
    border-radius: 4px;
    font-size: 14px;
    margin-bottom: 16px;
}

.presets-panel {
    background: #f8f9fa;
    border-radius: 8px;
    padding: 16px;
}

.presets-grid {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.preset-btn {
    padding: 10px 12px;
    background: white;
    border: 1px solid #dee2e6;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s ease;
    text-align: center;
}

.preset-btn:hover {
    background: #7c3aed;
    color: white;
    border-color: #7c3aed;
}

.date-range-footer {
    display: flex;
    justify-content: flex-end;
    gap: 12px;
    padding: 16px 20px;
    border-top: 1px solid #eee;
    background: #f8f9fa;
}

.btn-date-range {
    padding: 8px 16px;
    border-radius: 4px;
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s ease;
    border: 1px solid transparent;
}

.btn-outline-date {
    background: white;
    color: #6c757d;
    border-color: #ced4da;
}

.btn-primary-date {
    background: #7c3aed;
    color: white;
    border-color: #7c3aed;
}

.btn-primary-date:hover {
    background: #6d28d9;
}

@media (max-width: 1024px) {
    .date-range-overlay {
        min-width: 100%;
    }
    
    .date-range-panels {
        grid-template-columns: 1fr;
        gap: 16px;
    }
}
</style>

<script>
// Simple Date Range Picker JavaScript
let tempFromDate = '';
let tempToDate = '';

function toggleDateRangePicker() {
    const overlay = document.getElementById('dateRangeOverlay');
    const picker = document.getElementById('dividend-date-range');
    
    if (overlay.style.display === 'none' || overlay.style.display === '') {
        overlay.style.display = 'block';
        picker.classList.add('open');
        
        // Set current values
        const fromInput = document.querySelector('input[name="date_from"]');
        const toInput = document.querySelector('input[name="date_to"]');
        
        document.getElementById('fromDateInput').value = fromInput.value;
        document.getElementById('toDateInput').value = toInput.value;
        
        tempFromDate = fromInput.value;
        tempToDate = toInput.value;
        
        // Set defaults if empty
        if (!tempFromDate && !tempToDate) {
            applyPreset('defaultRange');
        }
    } else {
        closeDateRangePicker();
    }
}

function closeDateRangePicker() {
    const overlay = document.getElementById('dateRangeOverlay');
    const picker = document.getElementById('dividend-date-range');
    
    overlay.style.display = 'none';
    picker.classList.remove('open');
}

function applyPreset(preset) {
    const today = new Date();
    let fromDate, toDate;

    switch (preset) {
        case 'today':
            fromDate = toDate = formatDate(today);
            break;
        case 'yesterday':
            const yesterday = new Date(today);
            yesterday.setDate(today.getDate() - 1);
            fromDate = toDate = formatDate(yesterday);
            break;
        case 'thisWeek':
            const startOfWeek = new Date(today);
            startOfWeek.setDate(today.getDate() - today.getDay());
            const endOfWeek = new Date(startOfWeek);
            endOfWeek.setDate(startOfWeek.getDate() + 6);
            fromDate = formatDate(startOfWeek);
            toDate = formatDate(endOfWeek);
            break;
        case 'prevWeek':
            const prevWeekEnd = new Date(today);
            prevWeekEnd.setDate(today.getDate() - today.getDay() - 1);
            const prevWeekStart = new Date(prevWeekEnd);
            prevWeekStart.setDate(prevWeekEnd.getDate() - 6);
            fromDate = formatDate(prevWeekStart);
            toDate = formatDate(prevWeekEnd);
            break;
        case 'thisMonth':
            fromDate = formatDate(new Date(today.getFullYear(), today.getMonth(), 1));
            toDate = formatDate(new Date(today.getFullYear(), today.getMonth() + 1, 0));
            break;
        case 'prevMonth':
            fromDate = formatDate(new Date(today.getFullYear(), today.getMonth() - 1, 1));
            toDate = formatDate(new Date(today.getFullYear(), today.getMonth(), 0));
            break;
        case 'thisYear':
            fromDate = formatDate(new Date(today.getFullYear(), 0, 1));
            toDate = formatDate(new Date(today.getFullYear(), 11, 31));
            break;
        case 'prevYear':
            fromDate = formatDate(new Date(today.getFullYear() - 1, 0, 1));
            toDate = formatDate(new Date(today.getFullYear() - 1, 11, 31));
            break;
        case 'sinceStart':
            fromDate = '2020-01-01';
            toDate = formatDate(today);
            break;
        case 'defaultRange':
        default:
            // Current month + 3 months back
            fromDate = formatDate(new Date(today.getFullYear(), today.getMonth() - 3, 1));
            toDate = formatDate(new Date(today.getFullYear(), today.getMonth() + 1, 0));
            break;
    }

    document.getElementById('fromDateInput').value = fromDate;
    document.getElementById('toDateInput').value = toDate;
    tempFromDate = fromDate;
    tempToDate = toDate;
}

function applyDateRange() {
    const fromInput = document.getElementById('fromDateInput');
    const toInput = document.getElementById('toDateInput');
    
    tempFromDate = fromInput.value;
    tempToDate = toInput.value;
    
    // Update hidden inputs
    document.querySelector('input[name="date_from"]').value = tempFromDate;
    document.querySelector('input[name="date_to"]').value = tempToDate;
    
    // Update display
    document.getElementById('dateRangeText').textContent = tempFromDate + ' - ' + tempToDate;
    
    closeDateRangePicker();
}

function formatDate(date) {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
}

// Close on outside click
document.addEventListener('click', function(e) {
    const picker = document.getElementById('dividend-date-range');
    const overlay = document.getElementById('dateRangeOverlay');
    
    if (picker && !picker.contains(e.target) && overlay && overlay.style.display === 'block') {
        closeDateRangePicker();
    }
});

// Initialize default date range if empty
document.addEventListener('DOMContentLoaded', function() {
    const fromInput = document.querySelector('input[name="date_from"]');
    const toInput = document.querySelector('input[name="date_to"]');
    
    if (fromInput && toInput && !fromInput.value && !toInput.value) {
        const today = new Date();
        const defaultFrom = formatDate(new Date(today.getFullYear(), today.getMonth() - 3, 1));
        const defaultTo = formatDate(new Date(today.getFullYear(), today.getMonth() + 1, 0));
        
        fromInput.value = defaultFrom;
        toInput.value = defaultTo;
        const dateRangeText = document.getElementById('dateRangeText');
        if (dateRangeText) {
            dateRangeText.textContent = defaultFrom + ' - ' + defaultTo;
        }
    }
});
</script>