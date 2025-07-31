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
                        <label class="psw-form-label">Date Range</label>
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
                                            <input type="text" class="date-input" id="fromDateInput" placeholder="YYYY-MM-DD" onchange="updateCalendar('from')">
                                            <div class="calendar-container" id="fromCalendar"></div>
                                        </div>
                                        <div class="date-panel to-panel">
                                            <h5>To Date</h5>
                                            <input type="text" class="date-input" id="toDateInput" placeholder="YYYY-MM-DD" onchange="updateCalendar('to')">
                                            <div class="calendar-container" id="toCalendar"></div>
                                        </div>
                                        <div class="presets-panel">
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
                                    <button type="button" class="psw-btn psw-btn-outline" onclick="closeDateRangePicker()">Cancel</button>
                                    <button type="button" class="psw-btn psw-btn-primary" onclick="applyDateRange()">Apply</button>
                                </div>
                            </div>
                        </div>
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

// Enhanced Date Range Picker JavaScript with Calendar
let tempFromDate = '';
let tempToDate = '';
let currentFromMonth = new Date();
let currentToMonth = new Date();

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
        } else {
            // Update calendars to show selected dates
            if (tempFromDate) {
                currentFromMonth = new Date(tempFromDate);
                renderCalendar('from', currentFromMonth);
            }
            if (tempToDate) {
                currentToMonth = new Date(tempToDate);
                renderCalendar('to', currentToMonth);
            }
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
            fromDate = toDate = new Date(today);
            break;
        case 'yesterday':
            const yesterday = new Date(today);
            yesterday.setDate(today.getDate() - 1);
            fromDate = toDate = new Date(yesterday);
            break;
        case 'thisWeek':
            const startOfWeek = new Date(today);
            startOfWeek.setDate(today.getDate() - today.getDay());
            const endOfWeek = new Date(startOfWeek);
            endOfWeek.setDate(startOfWeek.getDate() + 6);
            fromDate = new Date(startOfWeek);
            toDate = new Date(endOfWeek);
            break;
        case 'prevWeek':
            const prevWeekEnd = new Date(today);
            prevWeekEnd.setDate(today.getDate() - today.getDay() - 1);
            const prevWeekStart = new Date(prevWeekEnd);
            prevWeekStart.setDate(prevWeekEnd.getDate() - 6);
            fromDate = new Date(prevWeekStart);
            toDate = new Date(prevWeekEnd);
            break;
        case 'thisMonth':
            fromDate = new Date(today.getFullYear(), today.getMonth(), 1);
            toDate = new Date(today.getFullYear(), today.getMonth() + 1, 0);
            break;
        case 'prevMonth':
            fromDate = new Date(today.getFullYear(), today.getMonth() - 1, 1);
            toDate = new Date(today.getFullYear(), today.getMonth(), 0);
            break;
        case 'thisYear':
            fromDate = new Date(today.getFullYear(), 0, 1);
            toDate = new Date(today.getFullYear(), 11, 31);
            break;
        case 'prevYear':
            fromDate = new Date(today.getFullYear() - 1, 0, 1);
            toDate = new Date(today.getFullYear() - 1, 11, 31);
            break;
        case 'sinceStart':
            fromDate = new Date(2020, 0, 1);
            toDate = new Date(today);
            break;
        case 'defaultRange':
        default:
            // Current month + 3 months back
            fromDate = new Date(today.getFullYear(), today.getMonth() - 3, 1);
            toDate = new Date(today.getFullYear(), today.getMonth() + 1, 0);
            break;
    }

    const fromDateStr = formatDate(fromDate);
    const toDateStr = formatDate(toDate);

    document.getElementById('fromDateInput').value = fromDateStr;
    document.getElementById('toDateInput').value = toDateStr;
    tempFromDate = fromDateStr;
    tempToDate = toDateStr;
    
    // Update calendars
    currentFromMonth = new Date(fromDate);
    currentToMonth = new Date(toDate);
    renderCalendar('from', currentFromMonth);
    renderCalendar('to', currentToMonth);
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

function updateCalendar(type) {
    const input = document.getElementById(type + 'DateInput');
    const dateValue = input.value;
    
    if (isValidDate(dateValue)) {
        const newDate = new Date(dateValue);
        if (type === 'from') {
            currentFromMonth = newDate;
            tempFromDate = dateValue;
        } else {
            currentToMonth = newDate;
            tempToDate = dateValue;
        }
        renderCalendar(type, type === 'from' ? currentFromMonth : currentToMonth);
    }
}

function renderCalendar(type, date) {
    const calendarContainer = document.getElementById(type + 'Calendar');
    const year = date.getFullYear();
    const month = date.getMonth();
    
    // Calendar header
    const monthNames = [
        'January', 'February', 'March', 'April', 'May', 'June',
        'July', 'August', 'September', 'October', 'November', 'December'
    ];
    
    let html = `
        <div class="calendar-header">
            <button type="button" class="calendar-nav" onclick="navigateMonth('${type}', -1)">
                <i class="fas fa-chevron-left"></i>
            </button>
            <div class="calendar-month-year">${monthNames[month]} ${year}</div>
            <button type="button" class="calendar-nav" onclick="navigateMonth('${type}', 1)">
                <i class="fas fa-chevron-right"></i>
            </button>
        </div>
        <div class="calendar-grid">
            <div class="calendar-weekdays">
                <div class="calendar-weekday">Su</div>
                <div class="calendar-weekday">Mo</div>
                <div class="calendar-weekday">Tu</div>
                <div class="calendar-weekday">We</div>
                <div class="calendar-weekday">Th</div>
                <div class="calendar-weekday">Fr</div>
                <div class="calendar-weekday">Sa</div>
            </div>
            <div class="calendar-days">
    `;
    
    // Get first day of month and days in month
    const firstDay = new Date(year, month, 1);
    const lastDay = new Date(year, month + 1, 0);
    const daysInMonth = lastDay.getDate();
    const startingDayOfWeek = firstDay.getDay();
    
    // Previous month's trailing days
    const prevMonth = new Date(year, month - 1, 0);
    const prevMonthDays = prevMonth.getDate();
    
    for (let i = prevMonthDays - startingDayOfWeek + 1; i <= prevMonthDays; i++) {
        html += `<div class="calendar-day other-month">${i}</div>`;
    }
    
    // Current month days
    const today = new Date();
    const selectedDate = type === 'from' ? tempFromDate : tempToDate;
    
    for (let day = 1; day <= daysInMonth; day++) {
        const currentDate = new Date(year, month, day);
        const dateStr = formatDate(currentDate);
        const isToday = dateStr === formatDate(today);
        const isSelected = dateStr === selectedDate;
        
        let classes = 'calendar-day';
        if (isToday) classes += ' today';
        if (isSelected) classes += ' selected';
        
        html += `<div class="${classes}" onclick="selectCalendarDate('${type}', '${dateStr}')">${day}</div>`;
    }
    
    // Next month's leading days
    const remainingCells = 42 - (startingDayOfWeek + daysInMonth);
    for (let day = 1; day <= remainingCells && remainingCells < 7; day++) {
        html += `<div class="calendar-day other-month">${day}</div>`;
    }
    
    html += `
            </div>
        </div>
    `;
    
    calendarContainer.innerHTML = html;
}

function navigateMonth(type, direction) {
    if (type === 'from') {
        currentFromMonth.setMonth(currentFromMonth.getMonth() + direction);
        renderCalendar('from', currentFromMonth);
    } else {
        currentToMonth.setMonth(currentToMonth.getMonth() + direction);
        renderCalendar('to', currentToMonth);
    }
}

function selectCalendarDate(type, dateStr) {
    const input = document.getElementById(type + 'DateInput');
    input.value = dateStr;
    
    if (type === 'from') {
        tempFromDate = dateStr;
    } else {
        tempToDate = dateStr;
    }
    
    // Re-render calendar to show selection
    renderCalendar(type, type === 'from' ? currentFromMonth : currentToMonth);
}

function formatDate(date) {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
}

function isValidDate(dateStr) {
    const date = new Date(dateStr);
    return date instanceof Date && !isNaN(date) && dateStr.match(/^\d{4}-\d{2}-\d{2}$/);
}

// Close on outside click
document.addEventListener('click', function(e) {
    const picker = document.getElementById('dividend-date-range');
    const overlay = document.getElementById('dateRangeOverlay');
    
    if (picker && !picker.contains(e.target) && overlay && overlay.style.display === 'block') {
        closeDateRangePicker();
    }
});

// Initialize calendars when overlay opens
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
    
    // Initialize calendar months
    if (fromInput && fromInput.value) {
        currentFromMonth = new Date(fromInput.value);
    }
    if (toInput && toInput.value) {
        currentToMonth = new Date(toInput.value);
    }
});
</script>

<!-- Additional CSS for dividend logs specific styling -->
<style>
.psw-filters-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmod(250px, 1fr));
    gap: var(--spacing-4);
    margin-bottom: var(--spacing-6);
}

/* Enhanced Date Range Picker Styles */
.date-range-picker {
    position: relative;
    display: inline-block;
    width: 100%;
    max-width: 300px;
}

.date-range-display {
    display: flex;
    align-items: center;
    padding: var(--spacing-3) var(--spacing-4);
    background: var(--bg-card);
    border: 1px solid var(--border-primary);
    border-radius: var(--border-radius);
    cursor: pointer;
    transition: all 0.2s ease;
    min-height: 44px;
    font-family: var(--font-family-primary);
    color: var(--text-primary);
}

.date-range-display:hover {
    border-color: var(--primary-accent);
    box-shadow: 0 0 0 2px var(--primary-accent-light);
}

.date-range-display i.fa-calendar-alt {
    color: var(--text-secondary);
    margin-right: var(--spacing-2);
}

.date-range-text {
    flex: 1;
    font-size: var(--font-size-sm);
    font-weight: 500;
    color: var(--text-primary);
}

.date-range-display i.fa-chevron-down {
    color: var(--text-secondary);
    font-size: var(--font-size-xs);
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
    background: var(--bg-card);
    border: 1px solid var(--border-primary);
    border-radius: var(--border-radius-lg);
    box-shadow: var(--shadow-xl);
    z-index: 1000;
    min-width: 900px;
    margin-top: var(--spacing-1);
    display: none;
}

.date-range-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: var(--spacing-4) var(--spacing-5);
    border-bottom: 1px solid var(--border-primary);
    background: var(--bg-secondary);
}

.date-range-header h4 {
    margin: 0;
    font-size: var(--font-size-lg);
    font-weight: 600;
    color: var(--text-primary);
}

.close-btn {
    background: none;
    border: none;
    font-size: var(--font-size-lg);
    color: var(--text-secondary);
    cursor: pointer;
    padding: var(--spacing-1);
    border-radius: var(--border-radius);
    transition: all 0.2s ease;
}

.close-btn:hover {
    background: var(--bg-tertiary);
    color: var(--text-primary);
}

.date-range-content {
    padding: var(--spacing-5);
}

.date-range-panels {
    display: grid;
    grid-template-columns: 1fr 1fr 150px;
    gap: var(--spacing-5);
}

.date-panel {
    background: var(--bg-secondary);
    border-radius: var(--border-radius);
    padding: var(--spacing-4);
}

.date-panel h5 {
    margin: 0 0 var(--spacing-3) 0;
    font-size: var(--font-size-sm);
    font-weight: 600;
    color: var(--text-secondary);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.date-input {
    width: 100%;
    padding: var(--spacing-2) var(--spacing-3);
    border: 1px solid var(--border-primary);
    border-radius: var(--border-radius);
    font-size: var(--font-size-sm);
    margin-bottom: var(--spacing-3);
    background: var(--bg-card);
    color: var(--text-primary);
    transition: border-color 0.2s ease;
}

.date-input:focus {
    outline: none;
    border-color: var(--primary-accent);
    box-shadow: 0 0 0 2px var(--primary-accent-light);
}

.calendar-container {
    background: var(--bg-card);
    border: 1px solid var(--border-primary);
    border-radius: var(--border-radius);
    overflow: hidden;
}

.calendar-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: var(--spacing-2) var(--spacing-3);
    background: var(--bg-tertiary);
    border-bottom: 1px solid var(--border-primary);
}

.calendar-nav {
    background: none;
    border: none;
    color: var(--text-secondary);
    cursor: pointer;
    padding: var(--spacing-1);
    border-radius: var(--border-radius);
    transition: all 0.2s ease;
}

.calendar-nav:hover {
    background: var(--primary-accent-light);
    color: var(--primary-accent);
}

.calendar-month-year {
    font-weight: 600;
    font-size: var(--font-size-sm);
    color: var(--text-primary);
}

.calendar-grid {
    padding: var(--spacing-2);
}

.calendar-weekdays {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 1px;
    margin-bottom: var(--spacing-1);
}

.calendar-weekday {
    text-align: center;
    font-size: var(--font-size-xs);
    font-weight: 600;
    color: var(--text-secondary);
    padding: var(--spacing-1);
}

.calendar-days {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 1px;
}

.calendar-day {
    text-align: center;
    padding: var(--spacing-1);
    cursor: pointer;
    border-radius: var(--border-radius);
    font-size: var(--font-size-xs);
    transition: all 0.2s ease;
    min-height: 28px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.calendar-day:hover {
    background: var(--primary-accent-light);
    color: var(--primary-accent);
}

.calendar-day.selected {
    background: var(--primary-accent);
    color: white;
    font-weight: 600;
}

.calendar-day.other-month {
    color: var(--text-muted);
}

.calendar-day.today {
    background: var(--info-color);
    color: white;
    font-weight: 600;
}

.calendar-day.today.selected {
    background: var(--primary-accent);
}

.presets-panel {
    background: var(--bg-secondary);
    border-radius: var(--border-radius);
    padding: var(--spacing-4);
    min-width: 150px;
}

.presets-grid {
    display: flex;
    flex-direction: column;
    gap: var(--spacing-1);
}

.preset-btn {
    padding: var(--spacing-1) var(--spacing-2);
    background: var(--bg-card);
    border: 1px solid var(--border-primary);
    border-radius: var(--border-radius);
    font-size: var(--font-size-xs);
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s ease;
    text-align: center;
    color: var(--text-primary);
    white-space: nowrap;
    width: 100%;
}

.preset-btn:hover {
    background: var(--primary-accent);
    color: white;
    border-color: var(--primary-accent);
    transform: translateY(-1px);
    box-shadow: 0 2px 4px var(--primary-accent-light);
}

.date-range-footer {
    display: flex;
    justify-content: flex-end;
    gap: var(--spacing-3);
    padding: var(--spacing-4) var(--spacing-5);
    border-top: 1px solid var(--border-primary);
    background: var(--bg-secondary);
}

@media (max-width: 1024px) {
    .date-range-overlay {
        min-width: 100%;
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        margin: 0;
        border-radius: 0;
        height: 100vh;
        overflow-y: auto;
    }
    
    .date-range-panels {
        grid-template-columns: 1fr;
        gap: var(--spacing-4);
    }
    
    .presets-grid {
        gap: var(--spacing-1);
    }
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