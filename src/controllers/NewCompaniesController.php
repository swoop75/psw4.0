<?php
/**
 * File: src/controllers/NewCompaniesController.php
 * Description: New Companies (watchlist) controller for PSW 4.0 - handles psw_portfolio.new_companies table
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../middleware/Auth.php';
require_once __DIR__ . '/../utils/Logger.php';
require_once __DIR__ . '/../utils/Security.php';

class NewCompaniesController {
    private $portfolioDb;
    
    public function __construct() {
        $this->portfolioDb = Database::getConnection('portfolio');
    }
    
    /**
     * Get all new companies entries with optional filtering
     * @param array $filters Optional filters
     * @param int $page Page number
     * @param int $limit Items per page
     * @return array New companies data with pagination
     */
    public function getNewCompanies($filters = [], $page = 1, $limit = 50) {
        try {
            $whereConditions = ['1=1'];
            $params = [];
            
            // Build WHERE conditions based on filters
            if (!empty($filters['search'])) {
                $searchTerm = '%' . $filters['search'] . '%';
                $whereConditions[] = "(nc.company LIKE :search_company OR nc.ticker LIKE :search_ticker OR nc.isin LIKE :search_isin)";
                $params[':search_company'] = $searchTerm;
                $params[':search_ticker'] = $searchTerm;
                $params[':search_isin'] = $searchTerm;
            }
            
            // Handle multi-value filters with comma-separated values and null support
            if (!empty($filters['country_name'])) {
                $countryValues = explode(',', $filters['country_name']);
                $countryConditions = [];
                foreach ($countryValues as $index => $value) {
                    if ($value === 'null') {
                        $countryConditions[] = "nc.country_name IS NULL";
                    } else {
                        $paramName = ":country_name_$index";
                        $countryConditions[] = "nc.country_name = $paramName";
                        $params[$paramName] = $value;
                    }
                }
                if (!empty($countryConditions)) {
                    $whereConditions[] = "(" . implode(' OR ', $countryConditions) . ")";
                }
            }
            
            if (!empty($filters['new_companies_status_id'])) {
                $statusValues = explode(',', $filters['new_companies_status_id']);
                $statusConditions = [];
                foreach ($statusValues as $index => $value) {
                    if ($value === 'null') {
                        $statusConditions[] = "nc.new_companies_status_id IS NULL";
                    } else {
                        $paramName = ":status_id_$index";
                        $statusConditions[] = "nc.new_companies_status_id = $paramName";
                        $params[$paramName] = $value;
                    }
                }
                if (!empty($statusConditions)) {
                    $whereConditions[] = "(" . implode(' OR ', $statusConditions) . ")";
                }
            }
            
            if (!empty($filters['strategy_group_id'])) {
                $strategyValues = explode(',', $filters['strategy_group_id']);
                $strategyConditions = [];
                foreach ($strategyValues as $index => $value) {
                    if ($value === 'null') {
                        $strategyConditions[] = "nc.strategy_group_id IS NULL";
                    } else {
                        $paramName = ":strategy_group_id_$index";
                        $strategyConditions[] = "nc.strategy_group_id = $paramName";
                        $params[$paramName] = $value;
                    }
                }
                if (!empty($strategyConditions)) {
                    $whereConditions[] = "(" . implode(' OR ', $strategyConditions) . ")";
                }
            }
            
            if (!empty($filters['broker_id'])) {
                $brokerValues = explode(',', $filters['broker_id']);
                $brokerConditions = [];
                foreach ($brokerValues as $index => $value) {
                    if ($value === 'null') {
                        $brokerConditions[] = "nc.broker_id IS NULL";
                    } else {
                        $paramName = ":broker_id_$index";
                        $brokerConditions[] = "nc.broker_id = $paramName";
                        $params[$paramName] = $value;
                    }
                }
                if (!empty($brokerConditions)) {
                    $whereConditions[] = "(" . implode(' OR ', $brokerConditions) . ")";
                }
            }
            
            if (isset($filters['yield_min'])) {
                $whereConditions[] = "nc.yield >= :yield_min";
                $params[':yield_min'] = $filters['yield_min'];
            }
            
            if (isset($filters['yield_max'])) {
                $whereConditions[] = "nc.yield <= :yield_max";
                $params[':yield_max'] = $filters['yield_max'];
            }
            
            $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);
            
            // Handle sorting
            $validSortFields = [
                'company' => 'nc.company',
                'country_name' => 'nc.country_name',
                'broker_name' => 'br.broker_name', 
                'yield_current' => 'COALESCE(nc.yield_current, nc.yield)',
                'strategy_name' => 'psg.strategy_name',
                'status_name' => 'ncs.status'
            ];
            
            $sortBy = $filters['sort_by'] ?? 'company';
            $sortOrder = strtoupper($filters['sort_order'] ?? 'ASC');
            
            // Validate sort parameters
            if (!isset($validSortFields[$sortBy])) {
                $sortBy = 'company';
            }
            if (!in_array($sortOrder, ['ASC', 'DESC'])) {
                $sortOrder = 'ASC';
            }
            
            $orderClause = "ORDER BY {$validSortFields[$sortBy]} $sortOrder";
            
            // Count total records - include JOINs to match main query
            $countSql = "SELECT COUNT(*) as total 
                        FROM new_companies nc 
                        LEFT JOIN psw_foundation.portfolio_strategy_groups psg ON nc.strategy_group_id = psg.strategy_group_id
                        LEFT JOIN psw_foundation.brokers br ON nc.broker_id = br.broker_id
                        LEFT JOIN new_companies_status ncs ON nc.new_companies_status_id = ncs.id
                        $whereClause";
            $countStmt = $this->portfolioDb->prepare($countSql);
            foreach ($params as $key => $value) {
                $countStmt->bindValue($key, $value);
            }
            $countStmt->execute();
            $totalRecords = $countStmt->fetch()['total'];
            
            // Calculate pagination
            $offset = ($page - 1) * $limit;
            $totalPages = ceil($totalRecords / $limit);
            
            // Get new_companies entries with reference data (cross-database joins)
            $sql = "SELECT nc.new_company_id as new_companies_id,
                           nc.company,
                           nc.ticker,
                           nc.isin,
                           nc.country_name,
                           nc.country_id,
                           nc.yield,
                           nc.yield_current,
                           nc.yield_1y_avg,
                           nc.yield_1y_cagr,
                           nc.yield_3y_avg,
                           nc.yield_3y_cagr,
                           nc.yield_5y_avg,
                           nc.yield_5y_cagr,
                           nc.yield_10y_avg,
                           nc.yield_10y_cagr,
                           nc.yield_data_updated_at,
                           nc.yield_source,
                           nc.strategy_group_id,
                           psg.strategy_name,
                           nc.new_group_id,
                           nc.broker_id,
                           br.broker_name,
                           nc.inspiration,
                           nc.comments,
                           nc.new_companies_status_id,
                           ncs.status as status_name,
                           nc.new_companies_col
                    FROM new_companies nc 
                    LEFT JOIN psw_foundation.portfolio_strategy_groups psg ON nc.strategy_group_id = psg.strategy_group_id
                    LEFT JOIN psw_foundation.brokers br ON nc.broker_id = br.broker_id
                    LEFT JOIN new_companies_status ncs ON nc.new_companies_status_id = ncs.id
                    $whereClause 
                    $orderClause 
                    LIMIT :limit OFFSET :offset";
            
            $stmt = $this->portfolioDb->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            
            $entries = $stmt->fetchAll();
            
            // Format entries for display
            $formattedEntries = [];
            foreach ($entries as $entry) {
                $formattedEntries[] = [
                    'new_companies_id' => $entry['new_companies_id'],
                    'company' => $entry['company'],
                    'ticker' => $entry['ticker'],
                    'isin' => $entry['isin'],
                    'country_name' => $entry['country_name'],
                    'country_id' => $entry['country_id'],
                    'yield' => (float) ($entry['yield'] ?? 0),
                    'yield_current' => (float) ($entry['yield_current'] ?? 0),
                    'yield_1y_avg' => (float) ($entry['yield_1y_avg'] ?? 0),
                    'yield_1y_cagr' => (float) ($entry['yield_1y_cagr'] ?? 0),
                    'yield_3y_avg' => (float) ($entry['yield_3y_avg'] ?? 0),
                    'yield_3y_cagr' => (float) ($entry['yield_3y_cagr'] ?? 0),
                    'yield_5y_avg' => (float) ($entry['yield_5y_avg'] ?? 0),
                    'yield_5y_cagr' => (float) ($entry['yield_5y_cagr'] ?? 0),
                    'yield_10y_avg' => (float) ($entry['yield_10y_avg'] ?? 0),
                    'yield_10y_cagr' => (float) ($entry['yield_10y_cagr'] ?? 0),
                    'yield_data_updated_at' => $entry['yield_data_updated_at'],
                    'yield_source' => $entry['yield_source'],
                    'strategy_group_id' => $entry['strategy_group_id'],
                    'strategy_name' => $entry['strategy_name'],
                    'new_group_id' => $entry['new_group_id'],
                    'broker_id' => $entry['broker_id'],
                    'broker_name' => $entry['broker_name'],
                    'inspiration' => $entry['inspiration'],
                    'comments' => $entry['comments'],
                    'new_companies_status_id' => $entry['new_companies_status_id'],
                    'status_name' => $entry['status_name'],
                    'new_companies_col' => $entry['new_companies_col']
                ];
            }
            
            return [
                'entries' => $formattedEntries,
                'pagination' => [
                    'current_page' => $page,
                    'total_pages' => $totalPages,
                    'total_records' => $totalRecords,
                    'limit' => $limit,
                    'has_previous' => $page > 1,
                    'has_next' => $page < $totalPages
                ]
            ];
            
        } catch (Exception $e) {
            Logger::error('New companies error: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Add new company entry
     * @param array $data Entry data
     * @return bool Success status
     */
    public function addNewCompanyEntry($data) {
        try {
            // Validate required fields based on data source
            $isBorsdataMode = isset($data['borsdata_available']) && $data['borsdata_available'] == '1';
            
            if ($isBorsdataMode) {
                // In Börsdata mode, only ISIN is required
                if (empty($data['isin'])) {
                    throw new Exception('ISIN is required for Börsdata auto-population');
                }
            } else {
                // In manual mode, company name is required
                if (empty($data['company'])) {
                    throw new Exception('Company name is required');
                }
            }
            
            // Check for duplicates based on mode
            if ($isBorsdataMode && !empty($data['isin'])) {
                // In Börsdata mode, check by ISIN
                $checkSql = "SELECT new_company_id FROM new_companies WHERE isin = :isin";
                $checkParams = [':isin' => $data['isin']];
            } else if (!empty($data['company'])) {
                // In manual mode, check by company name and ticker
                $checkSql = "SELECT new_company_id FROM new_companies WHERE company = :company";
                $checkParams = [':company' => $data['company']];
                
                if (!empty($data['ticker'])) {
                    $checkSql .= " AND ticker = :ticker";
                    $checkParams[':ticker'] = $data['ticker'];
                }
            } else {
                // Skip duplicate check if no identifying fields
                $checkParams = [];
            }
            
            if (!empty($checkParams)) {
                $stmt = $this->portfolioDb->prepare($checkSql);
                foreach ($checkParams as $key => $value) {
                    $stmt->bindValue($key, $value);
                }
                $stmt->execute();
                
                if ($stmt->fetch()) {
                    $identifier = $isBorsdataMode ? 'ISIN' : 'company';
                    throw new Exception("This $identifier is already in the new companies list");
                }
            }
            
            // Prepare data for insertion
            $insertData = [
                'company' => !empty($data['company']) ? Security::sanitizeInput($data['company']) : ($isBorsdataMode ? 'Pending Börsdata lookup' : null),
                'ticker' => !empty($data['ticker']) ? strtoupper(Security::sanitizeInput($data['ticker'])) : null,
                'isin' => !empty($data['isin']) ? Security::sanitizeInput($data['isin']) : null,
                'country_name' => !empty($data['country_name']) ? Security::sanitizeInput($data['country_name']) : null,
                'country_id' => !empty($data['country_id']) ? (int)$data['country_id'] : null,
                'yield' => !empty($data['yield']) ? (float)$data['yield'] : null,
                'yield_current' => !empty($data['yield_current']) ? (float)$data['yield_current'] : null,
                'yield_1y_avg' => !empty($data['yield_1y_avg']) ? (float)$data['yield_1y_avg'] : null,
                'yield_1y_cagr' => !empty($data['yield_1y_cagr']) ? (float)$data['yield_1y_cagr'] : null,
                'yield_3y_avg' => !empty($data['yield_3y_avg']) ? (float)$data['yield_3y_avg'] : null,
                'yield_3y_cagr' => !empty($data['yield_3y_cagr']) ? (float)$data['yield_3y_cagr'] : null,
                'yield_5y_avg' => !empty($data['yield_5y_avg']) ? (float)$data['yield_5y_avg'] : null,
                'yield_5y_cagr' => !empty($data['yield_5y_cagr']) ? (float)$data['yield_5y_cagr'] : null,
                'yield_10y_avg' => !empty($data['yield_10y_avg']) ? (float)$data['yield_10y_avg'] : null,
                'yield_10y_cagr' => !empty($data['yield_10y_cagr']) ? (float)$data['yield_10y_cagr'] : null,
                'strategy_group_id' => !empty($data['strategy_group_id']) ? (int)$data['strategy_group_id'] : null,
                'new_group_id' => !empty($data['new_group_id']) ? (int)$data['new_group_id'] : null,
                'broker_id' => !empty($data['broker_id']) ? (int)$data['broker_id'] : null,
                'inspiration' => !empty($data['inspiration']) ? Security::sanitizeInput($data['inspiration']) : null,
                'comments' => !empty($data['comments']) ? Security::sanitizeInput($data['comments']) : null,
                'new_companies_status_id' => $this->validateStatusId($data['new_companies_status_id'] ?? ''),
                'new_companies_col' => !empty($data['new_companies_col']) ? Security::sanitizeInput($data['new_companies_col']) : null,
                'borsdata_available' => isset($data['borsdata_available']) ? (bool)$data['borsdata_available'] : false
            ];
            
            // Build INSERT query
            $fields = array_keys($insertData);
            $placeholders = ':' . implode(', :', $fields);
            
            $sql = "INSERT INTO new_companies (" . implode(', ', $fields) . ") VALUES ($placeholders)";
            $stmt = $this->portfolioDb->prepare($sql);
            
            foreach ($insertData as $key => $value) {
                $stmt->bindValue(":$key", $value);
            }
            
            $success = $stmt->execute();
            
            if ($success) {
                $newEntryId = $this->portfolioDb->lastInsertId();
                
                // If in Börsdata mode, try to populate data immediately
                if ($isBorsdataMode && !empty($data['isin'])) {
                    $this->populateBorsdataData($newEntryId, $data['isin']);
                }
                
                Logger::info('New company entry added successfully', [
                    'company' => $data['company'],
                    'isin' => $data['isin']
                ]);
            }
            
            return $success;
            
        } catch (Exception $e) {
            Logger::error('Add new company entry error: ' . $e->getMessage());
            return $e->getMessage(); // Return the actual error message
        }
    }
    
    /**
     * Update new company entry
     * @param int $companyId Entry ID
     * @param array $data Updated data
     * @return bool Success status
     */
    public function updateNewCompanyEntry($companyId, $data) {
        try {
            // Get current entry
            $currentEntry = $this->getNewCompanyEntry($companyId);
            if (!$currentEntry) {
                throw new Exception('New company entry not found');
            }
            
            // Prepare update data
            $updateData = [];
            $params = [':new_company_id' => $companyId];
            
            if (isset($data['company'])) {
                $updateData[] = "company = :company";
                $params[':company'] = Security::sanitizeInput($data['company']);
            }
            
            if (isset($data['ticker'])) {
                $updateData[] = "ticker = :ticker";
                $params[':ticker'] = !empty($data['ticker']) ? strtoupper(Security::sanitizeInput($data['ticker'])) : null;
            }
            
            if (isset($data['country_name'])) {
                $updateData[] = "country_name = :country_name";
                $params[':country_name'] = Security::sanitizeInput($data['country_name']);
            }
            
            if (isset($data['yield'])) {
                $updateData[] = "yield = :yield";
                $params[':yield'] = (float)$data['yield'];
            }
            
            if (isset($data['yield_current'])) {
                $updateData[] = "yield_current = :yield_current";
                $params[':yield_current'] = !empty($data['yield_current']) ? (float)$data['yield_current'] : null;
            }
            
            if (isset($data['yield_1y_avg'])) {
                $updateData[] = "yield_1y_avg = :yield_1y_avg";
                $params[':yield_1y_avg'] = !empty($data['yield_1y_avg']) ? (float)$data['yield_1y_avg'] : null;
            }
            
            if (isset($data['yield_1y_cagr'])) {
                $updateData[] = "yield_1y_cagr = :yield_1y_cagr";
                $params[':yield_1y_cagr'] = !empty($data['yield_1y_cagr']) ? (float)$data['yield_1y_cagr'] : null;
            }
            
            if (isset($data['yield_3y_avg'])) {
                $updateData[] = "yield_3y_avg = :yield_3y_avg";
                $params[':yield_3y_avg'] = !empty($data['yield_3y_avg']) ? (float)$data['yield_3y_avg'] : null;
            }
            
            if (isset($data['yield_3y_cagr'])) {
                $updateData[] = "yield_3y_cagr = :yield_3y_cagr";
                $params[':yield_3y_cagr'] = !empty($data['yield_3y_cagr']) ? (float)$data['yield_3y_cagr'] : null;
            }
            
            if (isset($data['yield_5y_avg'])) {
                $updateData[] = "yield_5y_avg = :yield_5y_avg";
                $params[':yield_5y_avg'] = !empty($data['yield_5y_avg']) ? (float)$data['yield_5y_avg'] : null;
            }
            
            if (isset($data['yield_5y_cagr'])) {
                $updateData[] = "yield_5y_cagr = :yield_5y_cagr";
                $params[':yield_5y_cagr'] = !empty($data['yield_5y_cagr']) ? (float)$data['yield_5y_cagr'] : null;
            }
            
            if (isset($data['yield_10y_avg'])) {
                $updateData[] = "yield_10y_avg = :yield_10y_avg";
                $params[':yield_10y_avg'] = !empty($data['yield_10y_avg']) ? (float)$data['yield_10y_avg'] : null;
            }
            
            if (isset($data['yield_10y_cagr'])) {
                $updateData[] = "yield_10y_cagr = :yield_10y_cagr";
                $params[':yield_10y_cagr'] = !empty($data['yield_10y_cagr']) ? (float)$data['yield_10y_cagr'] : null;
            }
            
            if (isset($data['comments'])) {
                $updateData[] = "comments = :comments";
                $params[':comments'] = Security::sanitizeInput($data['comments']);
            }
            
            if (isset($data['new_companies_status_id'])) {
                $updateData[] = "new_companies_status_id = :new_companies_status_id";
                $params[':new_companies_status_id'] = $this->validateStatusId($data['new_companies_status_id']);
            }
            
            if (isset($data['isin'])) {
                $updateData[] = "isin = :isin";
                $params[':isin'] = Security::sanitizeInput($data['isin']);
            }
            
            if (isset($data['country_id'])) {
                $updateData[] = "country_id = :country_id";
                $params[':country_id'] = !empty($data['country_id']) ? (int)$data['country_id'] : null;
            }
            
            if (isset($data['strategy_group_id'])) {
                $updateData[] = "strategy_group_id = :strategy_group_id";
                $params[':strategy_group_id'] = !empty($data['strategy_group_id']) ? (int)$data['strategy_group_id'] : null;
            }
            
            if (isset($data['new_group_id'])) {
                $updateData[] = "new_group_id = :new_group_id";
                $params[':new_group_id'] = !empty($data['new_group_id']) ? (int)$data['new_group_id'] : null;
            }
            
            if (isset($data['broker_id'])) {
                $updateData[] = "broker_id = :broker_id";
                $params[':broker_id'] = !empty($data['broker_id']) ? (int)$data['broker_id'] : null;
            }
            
            if (isset($data['inspiration'])) {
                $updateData[] = "inspiration = :inspiration";
                $params[':inspiration'] = !empty($data['inspiration']) ? Security::sanitizeInput($data['inspiration']) : null;
            }
            
            if (isset($data['borsdata_available'])) {
                $updateData[] = "borsdata_available = :borsdata_available";
                $params[':borsdata_available'] = (bool)$data['borsdata_available'];
            }
            
            if (empty($updateData)) {
                return true; // Nothing to update
            }
            
            $sql = "UPDATE new_companies SET " . implode(', ', $updateData) . " WHERE new_company_id = :new_company_id";
            $stmt = $this->portfolioDb->prepare($sql);
            
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            
            return $stmt->execute();
            
        } catch (Exception $e) {
            Logger::error('Update new company entry error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Delete new company entry
     * @param int $companyId Entry ID
     * @return bool Success status
     */
    public function deleteNewCompanyEntry($companyId) {
        try {
            error_log("DeleteNewCompanyEntry called with ID: " . $companyId);
            
            $sql = "DELETE FROM new_companies WHERE new_company_id = :new_company_id";
            $stmt = $this->portfolioDb->prepare($sql);
            $stmt->bindValue(':new_company_id', $companyId, PDO::PARAM_INT);
            
            $result = $stmt->execute();
            $rowCount = $stmt->rowCount();
            
            error_log("Delete SQL executed. Result: " . ($result ? 'true' : 'false') . ", Rows affected: " . $rowCount);
            
            return $result && $rowCount > 0;
            
        } catch (Exception $e) {
            error_log('Delete new company entry error: ' . $e->getMessage());
            Logger::error('Delete new company entry error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get single new company entry with reference data
     * @param int $companyId Entry ID
     * @return array|false Entry data or false if not found
     */
    public function getNewCompanyEntry($companyId) {
        try {
            $sql = "SELECT nc.*,
                           psg.strategy_name,
                           br.broker_name,
                           ncs.status as status_name
                    FROM new_companies nc 
                    LEFT JOIN psw_foundation.portfolio_strategy_groups psg ON nc.strategy_group_id = psg.strategy_group_id
                    LEFT JOIN psw_foundation.brokers br ON nc.broker_id = br.broker_id
                    LEFT JOIN new_companies_status ncs ON nc.new_companies_status_id = ncs.id
                    WHERE nc.new_company_id = :new_company_id";
            $stmt = $this->portfolioDb->prepare($sql);
            $stmt->bindValue(':new_company_id', $companyId, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetch() ?: false;
            
        } catch (Exception $e) {
            Logger::error('Get new company entry error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get new companies statistics
     * @return array Statistics
     */
    public function getNewCompaniesStatistics() {
        try {
            $sql = "SELECT 
                        COUNT(*) as total_entries,
                        COUNT(DISTINCT country_name) as unique_countries,
                        AVG(yield) as avg_yield,
                        MAX(yield) as max_yield,
                        MIN(yield) as min_yield
                    FROM new_companies 
                    WHERE yield IS NOT NULL AND yield > 0";
            
            $stmt = $this->portfolioDb->prepare($sql);
            $stmt->execute();
            $stats = $stmt->fetch();
            
            return [
                'total_entries' => (int)($stats['total_entries'] ?? 0),
                'unique_countries' => (int)($stats['unique_countries'] ?? 0),
                'avg_yield' => round((float)($stats['avg_yield'] ?? 0), 2),
                'max_yield' => round((float)($stats['max_yield'] ?? 0), 2),
                'min_yield' => round((float)($stats['min_yield'] ?? 0), 2)
            ];
            
        } catch (Exception $e) {
            Logger::error('New companies statistics error: ' . $e->getMessage());
            return [
                'total_entries' => 0,
                'unique_countries' => 0,
                'avg_yield' => 0,
                'max_yield' => 0,
                'min_yield' => 0
            ];
        }
    }
    
    /**
     * Get filter options for the interface
     * @return array Filter options
     */
    public function getFilterOptions() {
        try {
            // Get unique countries
            $countriesSql = "SELECT DISTINCT country_name 
                            FROM new_companies 
                            WHERE country_name IS NOT NULL AND country_name != ''
                            ORDER BY country_name";
            $stmt = $this->portfolioDb->prepare($countriesSql);
            $stmt->execute();
            $countries = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            // Get strategy groups (cross-database query)
            $strategiesSql = "SELECT strategy_group_id, strategy_name FROM psw_foundation.portfolio_strategy_groups ORDER BY strategy_name";
            $stmt = $this->portfolioDb->prepare($strategiesSql);
            $stmt->execute();
            $strategies = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get brokers (cross-database query)
            $brokersSql = "SELECT broker_id, broker_name FROM psw_foundation.brokers ORDER BY broker_name";
            $stmt = $this->portfolioDb->prepare($brokersSql);
            $stmt->execute();
            $brokers = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get new companies statuses
            $statusesSql = "SELECT id, status FROM new_companies_status ORDER BY id";
            $stmt = $this->portfolioDb->prepare($statusesSql);
            $stmt->execute();
            $statuses = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            return [
                'countries' => $countries,
                'strategies' => $strategies,
                'brokers' => $brokers,
                'statuses' => $statuses,
                'yield_ranges' => [
                    ['min' => 0, 'max' => 2, 'label' => '0-2%'],
                    ['min' => 2, 'max' => 4, 'label' => '2-4%'],
                    ['min' => 4, 'max' => 6, 'label' => '4-6%'],
                    ['min' => 6, 'max' => 8, 'label' => '6-8%'],
                    ['min' => 8, 'max' => 100, 'label' => '8%+']
                ]
            ];
            
        } catch (Exception $e) {
            Logger::error('Get filter options error: ' . $e->getMessage());
            return [
                'countries' => [],
                'strategies' => [],
                'brokers' => [],
                'statuses' => [],
                'yield_ranges' => []
            ];
        }
    }
    
    /**
     * Add new company entry to masterlist (placeholder for portfolio schema)
     * @param int $companyId New company entry ID
     * @param array $masterlistData Additional masterlist data
     * @return bool Success status
     */
    public function addToMasterlist($companyId, $masterlistData = []) {
        try {
            // Get new company entry
            $companyEntry = $this->getNewCompanyEntry($companyId);
            if (!$companyEntry) {
                throw new Exception('New company entry not found');
            }
            
            // For now, just return true as a placeholder
            // This would need to be implemented with the actual masterlist integration
            Logger::info('Add to masterlist placeholder called', [
                'company_id' => $companyId,
                'company' => $companyEntry['company']
            ]);
            
            return true;
            
        } catch (Exception $e) {
            Logger::error('Add to masterlist error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Export new companies to CSV
     * @param array $filters Optional filters
     * @return string CSV content
     */
    public function exportToCSV($filters = []) {
        try {
            $data = $this->getNewCompanies($filters, 1, 10000); // Get all entries
            
            $csv = "Company,Ticker,ISIN,Country,Yield,Comments,Inspiration\n";
            
            foreach ($data['entries'] as $entry) {
                $csv .= '"' . str_replace('"', '""', $entry['company']) . '",';
                $csv .= '"' . str_replace('"', '""', $entry['ticker']) . '",';
                $csv .= '"' . str_replace('"', '""', $entry['isin']) . '",';
                $csv .= '"' . str_replace('"', '""', $entry['country_name']) . '",';
                $csv .= $entry['yield'] . ',';
                $csv .= '"' . str_replace('"', '""', $entry['comments']) . '",';
                $csv .= '"' . str_replace('"', '""', $entry['inspiration']) . '"';
                $csv .= "\n";
            }
            
            return $csv;
            
        } catch (Exception $e) {
            Logger::error('CSV export error: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Populate Börsdata data for a new company entry
     * @param int $entryId The new company entry ID
     * @param string $isin The ISIN to lookup
     * @return bool Success status
     */
    private function populateBorsdataData($entryId, $isin) {
        try {
            $marketdataDb = Database::getConnection('marketdata');
            
            // Try global_instruments first
            $stmt = $marketdataDb->prepare("
                SELECT gi.name, gi.yahoo, c.nameEN as country_name, c.id as country_id
                FROM global_instruments gi 
                LEFT JOIN countries c ON gi.countryId = c.id 
                WHERE gi.isin = ? 
                LIMIT 1
            ");
            $stmt->execute([$isin]);
            $data = $stmt->fetch();
            
            // If not found in global, try nordic
            if (!$data) {
                $stmt = $marketdataDb->prepare("
                    SELECT ni.name, ni.yahoo, c.nameEN as country_name, c.id as country_id
                    FROM nordic_instruments ni 
                    LEFT JOIN countries c ON ni.countryId = c.id 
                    WHERE ni.isin = ? 
                    LIMIT 1
                ");
                $stmt->execute([$isin]);
                $data = $stmt->fetch();
            }
            
            // If we found data, update the entry
            if ($data) {
                $updateSql = "UPDATE new_companies SET 
                             company = :company,
                             ticker = :ticker,
                             country_name = :country_name,
                             country_id = :country_id
                             WHERE new_company_id = :id";
                
                $stmt = $this->portfolioDb->prepare($updateSql);
                $stmt->execute([
                    ':company' => $data['name'],
                    ':ticker' => $data['yahoo'],
                    ':country_name' => $data['country_name'],
                    ':country_id' => $data['country_id'],
                    ':id' => $entryId
                ]);
                
                Logger::info('Börsdata data populated successfully', [
                    'entry_id' => $entryId,
                    'isin' => $isin,
                    'company' => $data['name']
                ]);
                
                return true;
            } else {
                // If no data found, keep the placeholder company name
                Logger::warning('ISIN not found in Börsdata', [
                    'entry_id' => $entryId,
                    'isin' => $isin
                ]);
                return false;
            }
            
        } catch (Exception $e) {
            Logger::error('Börsdata population error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Validate status ID and return null for invalid/empty values
     * @param mixed $statusId Status ID to validate
     * @return int|null Valid status ID or null
     */
    private function validateStatusId($statusId) {
        try {
            $statusId = trim((string)$statusId);
            
            // Handle empty or special values
            if (empty($statusId) || $statusId === '' || $statusId === '0' || $statusId === 'null') {
                error_log("Status ID being set to NULL (empty/default)");
                return null;
            }
            
            // Validate that the status ID exists in the database
            $checkSql = "SELECT id FROM new_companies_status WHERE id = :status_id";
            $checkStmt = $this->portfolioDb->prepare($checkSql);
            $checkStmt->bindValue(':status_id', (int)$statusId, PDO::PARAM_INT);
            $checkStmt->execute();
            
            if ($checkStmt->fetch()) {
                error_log("Status ID being set to valid ID: " . $statusId);
                return (int)$statusId;
            } else {
                // Invalid status ID, set to null
                error_log("Invalid status ID (" . $statusId . "), setting to NULL");
                return null;
            }
        } catch (Exception $e) {
            error_log("Error validating status ID: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Check if an ISIN already exists in the new companies list
     * @param string $isin ISIN to check
     * @return bool True if ISIN exists, false otherwise
     */
    public function checkISINExists($isin) {
        try {
            $sql = "SELECT COUNT(*) FROM new_companies WHERE isin = :isin";
            $stmt = $this->portfolioDb->prepare($sql);
            $stmt->bindValue(':isin', $isin);
            $stmt->execute();
            
            return $stmt->fetchColumn() > 0;
        } catch (Exception $e) {
            Logger::error('Error checking ISIN existence: ' . $e->getMessage());
            throw $e;
        }
    }
}
?>