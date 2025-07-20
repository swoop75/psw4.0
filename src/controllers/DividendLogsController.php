<?php
/**
 * File: src/controllers/DividendLogsController.php
 * Path: C:\Users\laoan\Documents\GitHub\psw\psw4.0\src\controllers\DividendLogsController.php
 * Description: Dividend logs controller for PSW 4.0 - handles dividend transaction history
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../models/Dividend.php';
require_once __DIR__ . '/../models/Company.php';
require_once __DIR__ . '/../middleware/Auth.php';
require_once __DIR__ . '/../utils/Logger.php';

class DividendLogsController {
    private $dividendModel;
    private $companyModel;
    private $portfolioDb;
    
    public function __construct() {
        $this->dividendModel = new Dividend();
        $this->companyModel = new Company();
        $this->portfolioDb = Database::getConnection('portfolio');
    }
    
    /**
     * Get dividend logs with filtering and pagination
     * @param array $filters Filter parameters
     * @param int $page Current page
     * @param int $perPage Items per page
     * @return array Logs data with pagination info
     */
    public function getDividendLogs($filters = [], $page = 1, $perPage = 50) {
        try {
            $userId = Auth::getUserId();
            $isAdmin = Auth::isAdmin();
            
            // Build the query
            $queryData = $this->buildDividendQuery($filters, $userId, $isAdmin);
            
            // Get total count
            $totalCount = $this->getTotalCount($queryData['where'], $queryData['params']);
            
            // Calculate pagination
            $totalPages = ceil($totalCount / $perPage);
            $offset = ($page - 1) * $perPage;
            
            // Get paginated results
            $dividends = $this->getPaginatedResults(
                $queryData['where'], 
                $queryData['params'], 
                $queryData['orderBy'], 
                $offset, 
                $perPage
            );
            
            // Get filter options
            $filterOptions = $this->getFilterOptions($userId, $isAdmin);
            
            // Calculate summary statistics
            $summaryStats = $this->calculateSummaryStats($queryData['where'], $queryData['params']);
            
            return [
                'dividends' => $dividends,
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $perPage,
                    'total_items' => $totalCount,
                    'total_pages' => $totalPages,
                    'has_prev' => $page > 1,
                    'has_next' => $page < $totalPages,
                    'prev_page' => max(1, $page - 1),
                    'next_page' => min($totalPages, $page + 1)
                ],
                'filters' => $filters,
                'filter_options' => $filterOptions,
                'summary_stats' => $summaryStats
            ];
            
        } catch (Exception $e) {
            Logger::error('Dividend logs error: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Build dividend query with filters
     * @param array $filters Filter parameters
     * @param int|null $userId User ID
     * @param bool $isAdmin Is user admin
     * @return array Query components
     */
    private function buildDividendQuery($filters, $userId, $isAdmin) {
        $where = ["ld.dividend_total_sek > 0"];
        $params = [];
        
        // Year filter
        if (!empty($filters['year']) && is_numeric($filters['year'])) {
            $where[] = "YEAR(ld.ex_date) = :year";
            $params[':year'] = (int) $filters['year'];
        }
        
        // Company filter (search in company name or symbol)
        if (!empty($filters['company'])) {
            $where[] = "(m.company_name LIKE :company OR m.ticker_symbol LIKE :company_symbol OR ld.isin LIKE :company_isin)";
            $params[':company'] = '%' . $filters['company'] . '%';
            $params[':company_symbol'] = '%' . $filters['company'] . '%';
            $params[':company_isin'] = '%' . $filters['company'] . '%';
        }
        
        // Currency filter
        if (!empty($filters['currency'])) {
            $where[] = "ld.original_currency = :currency";
            $params[':currency'] = $filters['currency'];
        }
        
        // Amount range filters
        if (!empty($filters['amount_min']) && is_numeric($filters['amount_min'])) {
            $where[] = "ld.dividend_total_sek >= :amount_min";
            $params[':amount_min'] = (float) $filters['amount_min'];
        }
        
        if (!empty($filters['amount_max']) && is_numeric($filters['amount_max'])) {
            $where[] = "ld.dividend_total_sek <= :amount_max";
            $params[':amount_max'] = (float) $filters['amount_max'];
        }
        
        // Date range filters
        if (!empty($filters['date_from'])) {
            $where[] = "ld.ex_date >= :date_from";
            $params[':date_from'] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $where[] = "ld.ex_date <= :date_to";
            $params[':date_to'] = $filters['date_to'];
        }
        
        // User filter (when multi-user support is implemented)
        if (!$isAdmin && $userId) {
            // TODO: Add user filtering when user column exists
            // $where[] = "ld.user_id = :user_id";
            // $params[':user_id'] = $userId;
        }
        
        // Build ORDER BY clause
        $validSortColumns = ['ex_date', 'pay_date', 'company_name', 'dividend_total_sek', 'original_currency'];
        $sortColumn = in_array($filters['sort'] ?? '', $validSortColumns) ? $filters['sort'] : 'ex_date';
        $sortOrder = (($filters['order'] ?? '') === 'ASC') ? 'ASC' : 'DESC';
        
        $orderBy = match($sortColumn) {
            'company_name' => "m.company_name {$sortOrder}, ld.ex_date DESC",
            'ex_date' => "ld.ex_date {$sortOrder}, ld.pay_date DESC",
            'pay_date' => "ld.pay_date {$sortOrder}, ld.ex_date DESC",
            'dividend_total_sek' => "ld.dividend_total_sek {$sortOrder}, ld.ex_date DESC",
            'original_currency' => "ld.original_currency {$sortOrder}, ld.ex_date DESC",
            default => "ld.ex_date {$sortOrder}, ld.pay_date DESC"
        };
        
        return [
            'where' => implode(' AND ', $where),
            'params' => $params,
            'orderBy' => $orderBy
        ];
    }
    
    /**
     * Get total count for pagination
     * @param string $whereClause WHERE clause
     * @param array $params Query parameters
     * @return int Total count
     */
    private function getTotalCount($whereClause, $params) {
        try {
            $sql = "SELECT COUNT(*) as total
                    FROM log_dividends ld
                    LEFT JOIN masterlist m ON ld.isin = m.isin
                    WHERE {$whereClause}";
            
            $stmt = $this->portfolioDb->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->execute();
            
            $result = $stmt->fetch();
            return (int) ($result['total'] ?? 0);
            
        } catch (Exception $e) {
            Logger::error('Get total count error: ' . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Get paginated results
     * @param string $whereClause WHERE clause
     * @param array $params Query parameters
     * @param string $orderBy ORDER BY clause
     * @param int $offset Offset for pagination
     * @param int $limit Limit for pagination
     * @return array Dividend records
     */
    private function getPaginatedResults($whereClause, $params, $orderBy, $offset, $limit) {
        try {
            $sql = "SELECT 
                        ld.ex_date,
                        ld.pay_date,
                        ld.isin,
                        ld.shares_on_pay_date as shares,
                        ld.dividend_per_share_original_currency,
                        ld.dividend_total_original_currency,
                        ld.original_currency,
                        ld.dividend_total_sek,
                        ld.withholding_tax_percent,
                        ld.withholding_tax_sek,
                        ld.net_dividend_sek,
                        ld.fx_rate_to_sek,
                        m.company_name,
                        m.ticker_symbol,
                        m.country_code,
                        c.country_name
                    FROM log_dividends ld
                    LEFT JOIN masterlist m ON ld.isin = m.isin
                    LEFT JOIN countries c ON m.country_code = c.country_code
                    WHERE {$whereClause}
                    ORDER BY {$orderBy}
                    LIMIT :limit OFFSET :offset";
            
            $stmt = $this->portfolioDb->prepare($sql);
            
            // Bind filter parameters
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            
            // Bind pagination parameters
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            
            $stmt->execute();
            $results = $stmt->fetchAll();
            
            // Process results
            $dividends = [];
            foreach ($results as $result) {
                $dividends[] = [
                    'ex_date' => $result['ex_date'],
                    'pay_date' => $result['pay_date'],
                    'isin' => $result['isin'],
                    'company_name' => $result['company_name'] ?? 'Unknown Company',
                    'ticker_symbol' => $result['ticker_symbol'] ?? $result['isin'],
                    'country_code' => $result['country_code'],
                    'country_name' => $result['country_name'],
                    'shares' => (int) $result['shares'],
                    'dividend_per_share' => (float) $result['dividend_per_share_original_currency'],
                    'dividend_total_original' => (float) $result['dividend_total_original_currency'],
                    'original_currency' => $result['original_currency'],
                    'dividend_total_sek' => (float) $result['dividend_total_sek'],
                    'withholding_tax_percent' => (float) $result['withholding_tax_percent'],
                    'withholding_tax_sek' => (float) $result['withholding_tax_sek'],
                    'net_dividend_sek' => (float) $result['net_dividend_sek'],
                    'fx_rate_to_sek' => (float) $result['fx_rate_to_sek']
                ];
            }
            
            return $dividends;
            
        } catch (Exception $e) {
            Logger::error('Get paginated results error: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get filter options for dropdowns
     * @param int|null $userId User ID
     * @param bool $isAdmin Is user admin
     * @return array Filter options
     */
    private function getFilterOptions($userId, $isAdmin) {
        try {
            // Get available years
            $yearsSql = "SELECT DISTINCT YEAR(ex_date) as year 
                        FROM log_dividends 
                        WHERE dividend_total_sek > 0 
                        ORDER BY year DESC";
            $yearsStmt = $this->portfolioDb->prepare($yearsSql);
            $yearsStmt->execute();
            $years = $yearsStmt->fetchAll(PDO::FETCH_COLUMN);
            
            // Get available currencies
            $currenciesSql = "SELECT DISTINCT original_currency 
                             FROM log_dividends 
                             WHERE dividend_total_sek > 0 AND original_currency IS NOT NULL
                             ORDER BY original_currency";
            $currenciesStmt = $this->portfolioDb->prepare($currenciesSql);
            $currenciesStmt->execute();
            $currencies = $currenciesStmt->fetchAll(PDO::FETCH_COLUMN);
            
            // Get top companies for quick filtering
            $companiesSql = "SELECT DISTINCT m.company_name, m.ticker_symbol
                            FROM log_dividends ld
                            LEFT JOIN masterlist m ON ld.isin = m.isin
                            WHERE ld.dividend_total_sek > 0 AND m.company_name IS NOT NULL
                            ORDER BY m.company_name
                            LIMIT 50";
            $companiesStmt = $this->portfolioDb->prepare($companiesSql);
            $companiesStmt->execute();
            $companies = $companiesStmt->fetchAll();
            
            return [
                'years' => $years,
                'currencies' => $currencies,
                'companies' => $companies
            ];
            
        } catch (Exception $e) {
            Logger::error('Get filter options error: ' . $e->getMessage());
            return [
                'years' => [],
                'currencies' => [],
                'companies' => []
            ];
        }
    }
    
    /**
     * Calculate summary statistics for current filters
     * @param string $whereClause WHERE clause
     * @param array $params Query parameters
     * @return array Summary statistics
     */
    private function calculateSummaryStats($whereClause, $params) {
        try {
            $sql = "SELECT 
                        COUNT(*) as total_payments,
                        COUNT(DISTINCT ld.isin) as unique_companies,
                        SUM(ld.dividend_total_sek) as total_amount_sek,
                        AVG(ld.dividend_total_sek) as avg_amount_sek,
                        MIN(ld.dividend_total_sek) as min_amount_sek,
                        MAX(ld.dividend_total_sek) as max_amount_sek,
                        SUM(ld.withholding_tax_sek) as total_tax_sek,
                        COUNT(DISTINCT ld.original_currency) as currencies_count,
                        MIN(ld.ex_date) as earliest_date,
                        MAX(ld.ex_date) as latest_date
                    FROM log_dividends ld
                    LEFT JOIN masterlist m ON ld.isin = m.isin
                    WHERE {$whereClause}";
            
            $stmt = $this->portfolioDb->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->execute();
            
            $result = $stmt->fetch();
            
            return [
                'total_payments' => (int) ($result['total_payments'] ?? 0),
                'unique_companies' => (int) ($result['unique_companies'] ?? 0),
                'total_amount_sek' => (float) ($result['total_amount_sek'] ?? 0),
                'avg_amount_sek' => (float) ($result['avg_amount_sek'] ?? 0),
                'min_amount_sek' => (float) ($result['min_amount_sek'] ?? 0),
                'max_amount_sek' => (float) ($result['max_amount_sek'] ?? 0),
                'total_tax_sek' => (float) ($result['total_tax_sek'] ?? 0),
                'currencies_count' => (int) ($result['currencies_count'] ?? 0),
                'earliest_date' => $result['earliest_date'],
                'latest_date' => $result['latest_date']
            ];
            
        } catch (Exception $e) {
            Logger::error('Calculate summary stats error: ' . $e->getMessage());
            return [
                'total_payments' => 0,
                'unique_companies' => 0,
                'total_amount_sek' => 0,
                'avg_amount_sek' => 0,
                'min_amount_sek' => 0,
                'max_amount_sek' => 0,
                'total_tax_sek' => 0,
                'currencies_count' => 0,
                'earliest_date' => null,
                'latest_date' => null
            ];
        }
    }
    
    /**
     * Export dividend logs to CSV
     * @param array $filters Filter parameters
     * @return string CSV content
     */
    public function exportToCsv($filters = []) {
        try {
            $userId = Auth::getUserId();
            $isAdmin = Auth::isAdmin();
            
            // Get all data without pagination
            $queryData = $this->buildDividendQuery($filters, $userId, $isAdmin);
            $dividends = $this->getPaginatedResults(
                $queryData['where'], 
                $queryData['params'], 
                $queryData['orderBy'], 
                0, 
                10000 // Large limit for export
            );
            
            // Generate CSV content
            $csv = "Ex Date,Pay Date,Company,Ticker,ISIN,Country,Shares,Dividend/Share,Total Original,Currency,Total SEK,Tax SEK,Net SEK,FX Rate\n";
            
            foreach ($dividends as $dividend) {
                $csv .= sprintf("%s,%s,%s,%s,%s,%s,%d,%.4f,%.2f,%s,%.2f,%.2f,%.2f,%.6f\n",
                    $dividend['ex_date'],
                    $dividend['pay_date'],
                    '"' . str_replace('"', '""', $dividend['company_name']) . '"',
                    $dividend['ticker_symbol'],
                    $dividend['isin'],
                    $dividend['country_name'] ?? '',
                    $dividend['shares'],
                    $dividend['dividend_per_share'],
                    $dividend['dividend_total_original'],
                    $dividend['original_currency'],
                    $dividend['dividend_total_sek'],
                    $dividend['withholding_tax_sek'],
                    $dividend['net_dividend_sek'],
                    $dividend['fx_rate_to_sek']
                );
            }
            
            Logger::logUserAction('dividend_logs_exported', 'Dividend logs exported to CSV', $filters);
            
            return $csv;
            
        } catch (Exception $e) {
            Logger::error('Export dividend logs error: ' . $e->getMessage());
            throw $e;
        }
    }
}