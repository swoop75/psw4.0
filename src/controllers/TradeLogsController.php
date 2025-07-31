<?php
/**
 * File: src/controllers/TradeLogsController.php
 * Path: C:\Users\laoan\Documents\GitHub\psw\psw4.0\src\controllers\TradeLogsController.php
 * Description: Trade logs controller for PSW 4.0 - handles trade transaction history
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../middleware/Auth.php';
require_once __DIR__ . '/../utils/Logger.php';

class TradeLogsController {
    private $portfolioDb;
    private $foundationDb;
    
    public function __construct() {
        $this->portfolioDb = Database::getConnection('portfolio');
        $this->foundationDb = Database::getConnection('foundation');
    }
    
    /**
     * Get trade logs with filtering and pagination
     * @param array $filters Filter parameters
     * @param int $page Current page
     * @param int $perPage Items per page
     * @return array Logs data with pagination info
     */
    public function getTradeLogs($filters = [], $page = 1, $perPage = 50) {
        try {
            $userId = Auth::getUserId();
            $isAdmin = Auth::isAdmin();
            
            // Build the query
            $queryData = $this->buildTradeQuery($filters, $userId, $isAdmin);
            
            // Get total count
            $totalCount = $this->getTotalCount($queryData['where'], $queryData['params']);
            
            // Calculate pagination
            $totalPages = ceil($totalCount / $perPage);
            $offset = ($page - 1) * $perPage;
            
            // Get paginated results
            $trades = $this->getPaginatedResults(
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
                'trades' => $trades,
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
            Logger::error('Trade logs error: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Build trade query with filters
     * @param array $filters Filter parameters
     * @param int|null $userId User ID
     * @param bool $isAdmin Is user admin
     * @return array Query components
     */
    private function buildTradeQuery($filters, $userId, $isAdmin) {
        $where = ["1=1"];
        $params = [];
        
        // Search filter (ISIN, company name, or ticker)
        if (!empty($filters['search'])) {
            $where[] = "(lt.isin LIKE :search OR ml.name LIKE :search_name OR lt.ticker LIKE :search_ticker)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params[':search'] = $searchTerm;
            $params[':search_name'] = $searchTerm;
            $params[':search_ticker'] = $searchTerm;
        }
        
        // Date range filters
        if (!empty($filters['date_from'])) {
            $where[] = "lt.trade_date >= :date_from";
            $params[':date_from'] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $where[] = "lt.trade_date <= :date_to";
            $params[':date_to'] = $filters['date_to'];
        }
        
        // Broker filter
        if (!empty($filters['broker_id'])) {
            $where[] = "lt.broker_id = :broker_id";
            $params[':broker_id'] = $filters['broker_id'];
        }
        
        // Account group filter
        if (!empty($filters['account_group_id'])) {
            $where[] = "lt.portfolio_account_group_id = :account_group_id";
            $params[':account_group_id'] = $filters['account_group_id'];
        }
        
        // Trade type filter
        if (!empty($filters['trade_type_id'])) {
            $where[] = "lt.trade_type_id = :trade_type_id";
            $params[':trade_type_id'] = $filters['trade_type_id'];
        }
        
        // Currency filter
        if (!empty($filters['currency_local'])) {
            $where[] = "lt.currency_local = :currency_local";
            $params[':currency_local'] = $filters['currency_local'];
        }
        
        // User filter (when multi-user support is implemented)
        if (!$isAdmin && $userId) {
            // TODO: Add user filtering when user column exists
            // $where[] = "lt.user_id = :user_id";
            // $params[':user_id'] = $userId;
        }
        
        // Build ORDER BY clause
        $validSortColumns = [
            'trade_date' => 'lt.trade_date',
            'isin' => 'lt.isin',
            'company_name' => 'ml.name',
            'ticker' => 'lt.ticker',
            'trade_type' => 'tt.type_name',
            'broker_name' => 'b.broker_name',
            'account_group' => 'pag.portfolio_group_name',
            'shares_traded' => 'lt.shares_traded',
            'price_per_share_sek' => 'lt.price_per_share_sek',
            'total_amount_sek' => 'lt.total_amount_sek',
            'net_amount_sek' => 'lt.net_amount_sek'
        ];
        
        $sortColumn = $validSortColumns[$filters['sort_by'] ?? 'trade_date'] ?? 'lt.trade_date';
        $sortOrder = strtoupper($filters['sort_order'] ?? 'desc') === 'ASC' ? 'ASC' : 'DESC';
        
        $orderBy = "$sortColumn $sortOrder";
        
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
                    FROM psw_portfolio.log_trades lt
                    LEFT JOIN psw_foundation.masterlist ml ON lt.isin COLLATE utf8mb4_unicode_ci = ml.isin COLLATE utf8mb4_unicode_ci
                    LEFT JOIN psw_foundation.trade_types tt ON lt.trade_type_id = tt.trade_type_id
                    LEFT JOIN psw_foundation.brokers b ON lt.broker_id = b.broker_id
                    LEFT JOIN psw_foundation.portfolio_account_groups pag ON lt.portfolio_account_group_id = pag.portfolio_account_group_id
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
     * @return array Trade records
     */
    private function getPaginatedResults($whereClause, $params, $orderBy, $offset, $limit) {
        try {
            $sql = "SELECT 
                        lt.trade_id,
                        lt.trade_date,
                        lt.settlement_date,
                        lt.isin,
                        lt.ticker,
                        lt.shares_traded,
                        lt.price_per_share_local,
                        lt.total_amount_local,
                        lt.currency_local,
                        lt.price_per_share_sek,
                        lt.total_amount_sek,
                        lt.exchange_rate_used,
                        lt.broker_fees_local,
                        lt.broker_fees_sek,
                        lt.tft_tax_local,
                        lt.tft_tax_sek,
                        lt.tft_rate_percent,
                        lt.net_amount_local,
                        lt.net_amount_sek,
                        lt.broker_transaction_id,
                        lt.order_type,
                        lt.execution_status,
                        lt.data_source,
                        lt.notes,
                        lt.created_at,
                        ml.name as company_name,
                        tt.type_code,
                        tt.type_name as trade_type_name,
                        tt.affects_position,
                        b.broker_name,
                        pag.portfolio_group_name as account_group_name
                    FROM psw_portfolio.log_trades lt
                    LEFT JOIN psw_foundation.masterlist ml ON lt.isin COLLATE utf8mb4_unicode_ci = ml.isin COLLATE utf8mb4_unicode_ci
                    LEFT JOIN psw_foundation.trade_types tt ON lt.trade_type_id = tt.trade_type_id
                    LEFT JOIN psw_foundation.brokers b ON lt.broker_id = b.broker_id
                    LEFT JOIN psw_foundation.portfolio_account_groups pag ON lt.portfolio_account_group_id = pag.portfolio_account_group_id
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
            $trades = [];
            foreach ($results as $result) {
                // Calculate broker fees percentage
                $totalAmountSek = (float) $result['total_amount_sek'];
                $brokerFeesSek = (float) $result['broker_fees_sek'];
                $brokerFeesPercent = 0;
                
                if ($totalAmountSek > 0) {
                    $brokerFeesPercent = ($brokerFeesSek / $totalAmountSek) * 100;
                }
                
                $trades[] = [
                    'trade_id' => (int) $result['trade_id'],
                    'trade_date' => $result['trade_date'],
                    'settlement_date' => $result['settlement_date'],
                    'isin' => $result['isin'],
                    'ticker' => $result['ticker'],
                    'company_name' => $result['company_name'] ?? 'Unknown Company',
                    'shares_traded' => (float) $result['shares_traded'],
                    'price_per_share_local' => (float) $result['price_per_share_local'],
                    'total_amount_local' => (float) $result['total_amount_local'],
                    'currency_local' => $result['currency_local'],
                    'price_per_share_sek' => (float) $result['price_per_share_sek'],
                    'total_amount_sek' => $totalAmountSek,
                    'exchange_rate_used' => (float) $result['exchange_rate_used'],
                    'broker_fees_local' => (float) $result['broker_fees_local'],
                    'broker_fees_sek' => $brokerFeesSek,
                    'broker_fees_percent' => $brokerFeesPercent,
                    'tft_tax_local' => (float) $result['tft_tax_local'],
                    'tft_tax_sek' => (float) $result['tft_tax_sek'],
                    'tft_rate_percent' => (float) $result['tft_rate_percent'],
                    'net_amount_local' => (float) $result['net_amount_local'],
                    'net_amount_sek' => (float) $result['net_amount_sek'],
                    'type_code' => $result['type_code'],
                    'trade_type_name' => $result['trade_type_name'],
                    'affects_position' => (bool) $result['affects_position'],
                    'broker_name' => $result['broker_name'] ?? '-',
                    'account_group_name' => $result['account_group_name'] ?? '-',
                    'broker_transaction_id' => $result['broker_transaction_id'],
                    'order_type' => $result['order_type'],
                    'execution_status' => $result['execution_status'],
                    'data_source' => $result['data_source'],
                    'notes' => $result['notes'],
                    'created_at' => $result['created_at']
                ];
            }
            
            return $trades;
            
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
            // Get available trade types
            $tradeTypesSql = "SELECT trade_type_id, type_code, type_name 
                             FROM psw_foundation.trade_types 
                             WHERE is_active = 1 
                             ORDER BY type_name";
            $tradeTypesStmt = $this->foundationDb->prepare($tradeTypesSql);
            $tradeTypesStmt->execute();
            $tradeTypes = $tradeTypesStmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get available brokers
            $brokersSql = "SELECT broker_id, broker_name 
                          FROM psw_foundation.brokers 
                          ORDER BY broker_name";
            $brokersStmt = $this->foundationDb->prepare($brokersSql);
            $brokersStmt->execute();
            $brokers = $brokersStmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get available account groups
            $accountGroupsSql = "SELECT portfolio_account_group_id, portfolio_group_name 
                                FROM psw_foundation.portfolio_account_groups 
                                ORDER BY portfolio_group_name";
            $accountGroupsStmt = $this->foundationDb->prepare($accountGroupsSql);
            $accountGroupsStmt->execute();
            $accountGroups = $accountGroupsStmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get available currencies
            $currenciesSql = "SELECT DISTINCT currency_local 
                             FROM psw_portfolio.log_trades 
                             WHERE currency_local IS NOT NULL 
                             ORDER BY currency_local";
            $currenciesStmt = $this->portfolioDb->prepare($currenciesSql);
            $currenciesStmt->execute();
            $currencies = $currenciesStmt->fetchAll(PDO::FETCH_COLUMN);
            
            return [
                'trade_types' => $tradeTypes,
                'brokers' => $brokers,
                'account_groups' => $accountGroups,
                'currencies' => $currencies
            ];
            
        } catch (Exception $e) {
            Logger::error('Get filter options error: ' . $e->getMessage());
            return [
                'trade_types' => [],
                'brokers' => [],
                'account_groups' => [],
                'currencies' => []
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
                        COUNT(*) as total_trades,
                        COUNT(DISTINCT lt.isin) as unique_companies,
                        SUM(CASE WHEN tt.type_code IN ('BUY', 'DIVIDEND_REINVEST', 'TRANSFER_IN', 'RIGHTS_ISSUE', 'BONUS_ISSUE') THEN lt.net_amount_sek ELSE 0 END) as total_purchases_sek,
                        SUM(CASE WHEN tt.type_code IN ('SELL', 'TRANSFER_OUT') THEN lt.net_amount_sek ELSE 0 END) as total_sales_sek,
                        SUM(lt.broker_fees_sek) as total_fees_sek,
                        SUM(lt.tft_tax_sek) as total_taxes_sek,
                        COUNT(DISTINCT lt.currency_local) as currencies_count,
                        MIN(lt.trade_date) as earliest_date,
                        MAX(lt.trade_date) as latest_date
                    FROM psw_portfolio.log_trades lt
                    LEFT JOIN psw_foundation.masterlist ml ON lt.isin COLLATE utf8mb4_unicode_ci = ml.isin COLLATE utf8mb4_unicode_ci
                    LEFT JOIN psw_foundation.trade_types tt ON lt.trade_type_id = tt.trade_type_id
                    LEFT JOIN psw_foundation.brokers b ON lt.broker_id = b.broker_id
                    LEFT JOIN psw_foundation.portfolio_account_groups pag ON lt.portfolio_account_group_id = pag.portfolio_account_group_id
                    WHERE {$whereClause}";
            
            $stmt = $this->portfolioDb->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->execute();
            
            $result = $stmt->fetch();
            
            return [
                'total_trades' => (int) ($result['total_trades'] ?? 0),
                'unique_companies' => (int) ($result['unique_companies'] ?? 0),
                'total_purchases_sek' => (float) ($result['total_purchases_sek'] ?? 0),
                'total_sales_sek' => (float) ($result['total_sales_sek'] ?? 0),
                'total_fees_sek' => (float) ($result['total_fees_sek'] ?? 0),
                'total_taxes_sek' => (float) ($result['total_taxes_sek'] ?? 0),
                'currencies_count' => (int) ($result['currencies_count'] ?? 0),
                'earliest_date' => $result['earliest_date'],
                'latest_date' => $result['latest_date']
            ];
            
        } catch (Exception $e) {
            Logger::error('Calculate summary stats error: ' . $e->getMessage());
            return [
                'total_trades' => 0,
                'unique_companies' => 0,
                'total_purchases_sek' => 0,
                'total_sales_sek' => 0,
                'total_fees_sek' => 0,
                'total_taxes_sek' => 0,
                'currencies_count' => 0,
                'earliest_date' => null,
                'latest_date' => null
            ];
        }
    }
    
    /**
     * Export trade logs to CSV
     * @param array $filters Filter parameters
     * @return string CSV content
     */
    public function exportToCsv($filters = []) {
        try {
            $userId = Auth::getUserId();
            $isAdmin = Auth::isAdmin();
            
            // Get all data without pagination
            $queryData = $this->buildTradeQuery($filters, $userId, $isAdmin);
            $trades = $this->getPaginatedResults(
                $queryData['where'], 
                $queryData['params'], 
                $queryData['orderBy'], 
                0, 
                10000 // Large limit for export
            );
            
            // Generate CSV content
            $csv = "Trade Date,Settlement Date,ISIN,Company,Ticker,Trade Type,Shares,Price/Share (Local),Total (Local),Currency,Price/Share (SEK),Total (SEK),Fees (SEK),Tax (SEK),Net (SEK),Broker,Account Group,Transaction ID,Order Type,Status,Data Source,Notes\n";
            
            foreach ($trades as $trade) {
                $csv .= sprintf("%s,%s,%s,%s,%s,%s,%.0f,%.2f,%.2f,%s,%.2f,%.2f,%.2f,%.2f,%.2f,%s,%s,%s,%s,%s,%s,%s\n",
                    $trade['trade_date'],
                    $trade['settlement_date'] ?? '',
                    $trade['isin'],
                    '"' . str_replace('"', '""', $trade['company_name']) . '"',
                    $trade['ticker'] ?? '',
                    $trade['trade_type_name'],
                    $trade['shares_traded'],
                    $trade['price_per_share_local'],
                    $trade['total_amount_local'],
                    $trade['currency_local'],
                    $trade['price_per_share_sek'],
                    $trade['total_amount_sek'],
                    $trade['broker_fees_sek'],
                    $trade['tft_tax_sek'],
                    $trade['net_amount_sek'],
                    $trade['broker_name'],
                    $trade['account_group_name'],
                    $trade['broker_transaction_id'] ?? '',
                    $trade['order_type'] ?? '',
                    $trade['execution_status'],
                    $trade['data_source'] ?? '',
                    '"' . str_replace('"', '""', $trade['notes'] ?? '') . '"'
                );
            }
            
            Logger::logUserAction('trade_logs_exported', 'Trade logs exported to CSV', $filters);
            
            return $csv;
            
        } catch (Exception $e) {
            Logger::error('Export trade logs error: ' . $e->getMessage());
            throw $e;
        }
    }
}