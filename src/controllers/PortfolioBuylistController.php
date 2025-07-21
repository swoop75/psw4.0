<?php
/**
 * File: src/controllers/PortfolioBuylistController.php
 * Description: Portfolio Buylist controller for PSW 4.0 - handles psw_portfolio.buylist table
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../middleware/Auth.php';
require_once __DIR__ . '/../utils/Logger.php';
require_once __DIR__ . '/../utils/Security.php';

class PortfolioBuylistController {
    private $portfolioDb;
    
    public function __construct() {
        $this->portfolioDb = Database::getConnection('portfolio');
    }
    
    /**
     * Get all buylist entries with optional filtering
     * @param array $filters Optional filters
     * @param int $page Page number
     * @param int $limit Items per page
     * @return array Buylist data with pagination
     */
    public function getBuylist($filters = [], $page = 1, $limit = 50) {
        try {
            $whereConditions = ['1=1'];
            $params = [];
            
            // Build WHERE conditions based on filters
            if (!empty($filters['search'])) {
                $whereConditions[] = "(b.company LIKE :search OR b.ticker LIKE :search OR b.isin LIKE :search)";
                $params[':search'] = '%' . $filters['search'] . '%';
            }
            
            if (!empty($filters['country_name'])) {
                $whereConditions[] = "b.country_name = :country_name";
                $params[':country_name'] = $filters['country_name'];
            }
            
            if (!empty($filters['buylist_status_id'])) {
                $whereConditions[] = "b.buylist_status_id = :status_id";
                $params[':status_id'] = $filters['buylist_status_id'];
            }
            
            if (!empty($filters['strategy_group_id'])) {
                $whereConditions[] = "b.strategy_group_id = :strategy_group_id";
                $params[':strategy_group_id'] = $filters['strategy_group_id'];
            }
            
            if (!empty($filters['broker_id'])) {
                $whereConditions[] = "b.broker_id = :broker_id";
                $params[':broker_id'] = $filters['broker_id'];
            }
            
            if (isset($filters['yield_min'])) {
                $whereConditions[] = "b.yield >= :yield_min";
                $params[':yield_min'] = $filters['yield_min'];
            }
            
            if (isset($filters['yield_max'])) {
                $whereConditions[] = "b.yield <= :yield_max";
                $params[':yield_max'] = $filters['yield_max'];
            }
            
            $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);
            
            // Count total records
            $countSql = "SELECT COUNT(*) as total FROM buylist b $whereClause";
            $countStmt = $this->portfolioDb->prepare($countSql);
            foreach ($params as $key => $value) {
                $countStmt->bindValue($key, $value);
            }
            $countStmt->execute();
            $totalRecords = $countStmt->fetch()['total'];
            
            // Calculate pagination
            $offset = ($page - 1) * $limit;
            $totalPages = ceil($totalRecords / $limit);
            
            // Get buylist entries with reference data
            $sql = "SELECT b.buy_list_id,
                           b.company,
                           b.ticker,
                           b.isin,
                           b.country_name,
                           b.country_id,
                           b.yield,
                           b.strategy_group_id,
                           psg.strategy_name,
                           b.new_group_id,
                           b.broker_id,
                           br.broker_name,
                           b.inspiration,
                           b.comments,
                           b.buylist_status_id,
                           bs.status as status_name,
                           b.buylistcol
                    FROM buylist b 
                    LEFT JOIN portfolio_strategy_groups psg ON b.strategy_group_id = psg.strategy_group_id
                    LEFT JOIN brokers br ON b.broker_id = br.broker_id
                    LEFT JOIN buylist_status bs ON b.buylist_status_id = bs.id
                    $whereClause 
                    ORDER BY b.buy_list_id DESC 
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
                    'buy_list_id' => $entry['buy_list_id'],
                    'company' => $entry['company'],
                    'ticker' => $entry['ticker'],
                    'isin' => $entry['isin'],
                    'country_name' => $entry['country_name'],
                    'country_id' => $entry['country_id'],
                    'yield' => (float) ($entry['yield'] ?? 0),
                    'strategy_group_id' => $entry['strategy_group_id'],
                    'strategy_name' => $entry['strategy_name'],
                    'new_group_id' => $entry['new_group_id'],
                    'broker_id' => $entry['broker_id'],
                    'broker_name' => $entry['broker_name'],
                    'inspiration' => $entry['inspiration'],
                    'comments' => $entry['comments'],
                    'buylist_status_id' => $entry['buylist_status_id'],
                    'status_name' => $entry['status_name'],
                    'buylistcol' => $entry['buylistcol']
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
            Logger::error('Portfolio buylist error: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Add new buylist entry
     * @param array $data Entry data
     * @return bool Success status
     */
    public function addBuylistEntry($data) {
        try {
            // Validate required fields
            if (empty($data['company'])) {
                throw new Exception('Company name is required');
            }
            
            // Check if this company is already in buylist (by company name and ticker if available)
            $checkSql = "SELECT buy_list_id FROM buylist WHERE company = :company";
            $checkParams = [':company' => $data['company']];
            
            if (!empty($data['ticker'])) {
                $checkSql .= " AND ticker = :ticker";
                $checkParams[':ticker'] = $data['ticker'];
            }
            
            $stmt = $this->portfolioDb->prepare($checkSql);
            foreach ($checkParams as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->execute();
            
            if ($stmt->fetch()) {
                throw new Exception('This company is already in the buylist');
            }
            
            // Prepare data for insertion
            $insertData = [
                'company' => Security::sanitizeInput($data['company']),
                'ticker' => !empty($data['ticker']) ? strtoupper(Security::sanitizeInput($data['ticker'])) : null,
                'isin' => Security::sanitizeInput($data['isin']),
                'country_name' => !empty($data['country_name']) ? Security::sanitizeInput($data['country_name']) : null,
                'country_id' => !empty($data['country_id']) ? (int)$data['country_id'] : null,
                'yield' => !empty($data['yield']) ? (float)$data['yield'] : null,
                'strategy_group_id' => !empty($data['strategy_group_id']) ? (int)$data['strategy_group_id'] : null,
                'new_group_id' => !empty($data['new_group_id']) ? (int)$data['new_group_id'] : null,
                'broker_id' => !empty($data['broker_id']) ? (int)$data['broker_id'] : null,
                'inspiration' => !empty($data['inspiration']) ? Security::sanitizeInput($data['inspiration']) : null,
                'comments' => !empty($data['comments']) ? Security::sanitizeInput($data['comments']) : null,
                'buylist_status_id' => !empty($data['buylist_status_id']) ? (int)$data['buylist_status_id'] : null,
                'buylistcol' => !empty($data['buylistcol']) ? Security::sanitizeInput($data['buylistcol']) : null
            ];
            
            // Build INSERT query
            $fields = array_keys($insertData);
            $placeholders = ':' . implode(', :', $fields);
            
            $sql = "INSERT INTO buylist (" . implode(', ', $fields) . ") VALUES ($placeholders)";
            $stmt = $this->portfolioDb->prepare($sql);
            
            foreach ($insertData as $key => $value) {
                $stmt->bindValue(":$key", $value);
            }
            
            $success = $stmt->execute();
            
            if ($success) {
                Logger::info('Buylist entry added successfully', [
                    'company' => $data['company'],
                    'isin' => $data['isin']
                ]);
            }
            
            return $success;
            
        } catch (Exception $e) {
            Logger::error('Add buylist entry error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Update buylist entry
     * @param int $buylistId Entry ID
     * @param array $data Updated data
     * @return bool Success status
     */
    public function updateBuylistEntry($buylistId, $data) {
        try {
            // Get current entry
            $currentEntry = $this->getBuylistEntry($buylistId);
            if (!$currentEntry) {
                throw new Exception('Buylist entry not found');
            }
            
            // Prepare update data
            $updateData = [];
            $params = [':buy_list_id' => $buylistId];
            
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
            
            if (isset($data['comments'])) {
                $updateData[] = "comments = :comments";
                $params[':comments'] = Security::sanitizeInput($data['comments']);
            }
            
            if (isset($data['buylist_status_id'])) {
                $updateData[] = "buylist_status_id = :buylist_status_id";
                $params[':buylist_status_id'] = (int)$data['buylist_status_id'];
            }
            
            if (empty($updateData)) {
                return true; // Nothing to update
            }
            
            $sql = "UPDATE buylist SET " . implode(', ', $updateData) . " WHERE buy_list_id = :buy_list_id";
            $stmt = $this->portfolioDb->prepare($sql);
            
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            
            return $stmt->execute();
            
        } catch (Exception $e) {
            Logger::error('Update buylist entry error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Delete buylist entry
     * @param int $buylistId Entry ID
     * @return bool Success status
     */
    public function deleteBuylistEntry($buylistId) {
        try {
            $sql = "DELETE FROM buylist WHERE buy_list_id = :buy_list_id";
            $stmt = $this->portfolioDb->prepare($sql);
            $stmt->bindValue(':buy_list_id', $buylistId, PDO::PARAM_INT);
            
            return $stmt->execute();
            
        } catch (Exception $e) {
            Logger::error('Delete buylist entry error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get single buylist entry
     * @param int $buylistId Entry ID
     * @return array|false Entry data or false if not found
     */
    public function getBuylistEntry($buylistId) {
        try {
            $sql = "SELECT * FROM buylist WHERE buy_list_id = :buy_list_id";
            $stmt = $this->portfolioDb->prepare($sql);
            $stmt->bindValue(':buy_list_id', $buylistId, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetch() ?: false;
            
        } catch (Exception $e) {
            Logger::error('Get buylist entry error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get buylist statistics
     * @return array Statistics
     */
    public function getBuylistStatistics() {
        try {
            $sql = "SELECT 
                        COUNT(*) as total_entries,
                        COUNT(DISTINCT country_name) as unique_countries,
                        AVG(yield) as avg_yield,
                        MAX(yield) as max_yield,
                        MIN(yield) as min_yield
                    FROM buylist 
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
            Logger::error('Buylist statistics error: ' . $e->getMessage());
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
                            FROM buylist 
                            WHERE country_name IS NOT NULL AND country_name != ''
                            ORDER BY country_name";
            $stmt = $this->portfolioDb->prepare($countriesSql);
            $stmt->execute();
            $countries = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            // Get strategy groups
            $strategiesSql = "SELECT strategy_group_id, strategy_name FROM portfolio_strategy_groups ORDER BY strategy_name";
            $stmt = $this->portfolioDb->prepare($strategiesSql);
            $stmt->execute();
            $strategies = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get brokers
            $brokersSql = "SELECT broker_id, broker_name FROM brokers ORDER BY broker_name";
            $stmt = $this->portfolioDb->prepare($brokersSql);
            $stmt->execute();
            $brokers = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get buylist statuses
            $statusesSql = "SELECT id, status FROM buylist_status ORDER BY id";
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
     * Add buylist entry to masterlist (placeholder for portfolio schema)
     * @param int $buylistId Buylist entry ID
     * @param array $masterlistData Additional masterlist data
     * @return bool Success status
     */
    public function addToMasterlist($buylistId, $masterlistData = []) {
        try {
            // Get buylist entry
            $buylistEntry = $this->getBuylistEntry($buylistId);
            if (!$buylistEntry) {
                throw new Exception('Buylist entry not found');
            }
            
            // For now, just return true as a placeholder
            // This would need to be implemented with the actual masterlist integration
            Logger::info('Add to masterlist placeholder called', [
                'buylist_id' => $buylistId,
                'company' => $buylistEntry['company']
            ]);
            
            return true;
            
        } catch (Exception $e) {
            Logger::error('Add to masterlist error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Export buylist to CSV
     * @param array $filters Optional filters
     * @return string CSV content
     */
    public function exportToCSV($filters = []) {
        try {
            $data = $this->getBuylist($filters, 1, 10000); // Get all entries
            
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
}
?>