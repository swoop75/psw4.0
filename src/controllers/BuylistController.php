<?php
/**
 * File: src/controllers/BuylistController.php
 * Description: Buylist management controller for PSW 4.0 - handles watchlist and buy targets
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../middleware/Auth.php';
require_once __DIR__ . '/../utils/Logger.php';
require_once __DIR__ . '/../utils/Security.php';

class BuylistController {
    private $portfolioDb;
    
    public function __construct() {
        $this->portfolioDb = Database::getConnection('portfolio');
    }
    
    /**
     * Get all buylist entries for current user with optional filtering
     * @param array $filters Optional filters
     * @param int $page Page number
     * @param int $limit Items per page
     * @return array Buylist data with pagination
     */
    public function getUserBuylist($filters = [], $page = 1, $limit = 50) {
        try {
            $userId = Auth::getUserId();
            if (!$userId) {
                throw new Exception('User not authenticated');
            }
            
            $whereConditions = ['1=1']; // No user_id in portfolio.buylist
            $params = [];
            
            // Build WHERE conditions based on filters (adapted for portfolio.buylist schema)
            if (!empty($filters['status_id'])) {
                $whereConditions[] = "b.buylist_status_id = :status_id";
                $params[':status_id'] = $filters['status_id'];
            }
            
            if (!empty($filters['search'])) {
                $whereConditions[] = "(b.company LIKE :search OR b.ticker LIKE :search OR b.comments LIKE :search OR b.inspiration LIKE :search)";
                $params[':search'] = '%' . $filters['search'] . '%';
            }
            
            if (!empty($filters['country_name'])) {
                $whereConditions[] = "b.country_name = :country_name";
                $params[':country_name'] = $filters['country_name'];
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
            $countSql = "SELECT COUNT(*) as total 
                        FROM buylist b 
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
            
            // Get buylist entries (simplified for portfolio.buylist schema)
            $sql = "SELECT b.buy_list_id as buylist_id,
                           b.company as company_name,
                           b.ticker,
                           b.isin,
                           b.country_name,
                           b.country_id,
                           b.yield as expected_dividend_yield,
                           b.strategy_group_id,
                           b.new_group_id,
                           b.broker_id,
                           b.inspiration,
                           b.comments as notes,
                           b.buylist_status_id as status_id,
                           b.buylistcol
                    FROM buylist b 
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
            
            return [
                'entries' => $entries,
                'pagination' => [
                    'current_page' => $page,
                    'total_pages' => $totalPages,
                    'total_records' => $totalRecords,
                    'limit' => $limit,
                    'has_next' => $page < $totalPages,
                    'has_prev' => $page > 1
                ]
            ];
            
        } catch (Exception $e) {
            Logger::error('Get user buylist error: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Get buylist entry by ID
     * @param int $buylistId Buylist entry ID
     * @return array|false Entry data or false if not found
     */
    public function getBuylistEntry($buylistId) {
        try {
            $userId = Auth::getUserId();
            if (!$userId) {
                throw new Exception('User not authenticated');
            }
            
            $sql = "SELECT b.*, 
                           bs.status_name, bs.status_color
                    FROM buylist b 
                    LEFT JOIN buylist_status bs ON b.status_id = bs.status_id 
                    WHERE b.buylist_id = :buylist_id AND b.user_id = :user_id";
            
            $stmt = $this->portfolioDb->prepare($sql);
            $stmt->bindValue(':buylist_id', $buylistId, PDO::PARAM_INT);
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetch() ?: false;
            
        } catch (Exception $e) {
            Logger::error('Get buylist entry error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Add new buylist entry
     * @param array $data Entry data
     * @return bool Success status
     */
    public function addBuylistEntry($data) {
        try {
            $userId = Auth::getUserId();
            if (!$userId) {
                throw new Exception('User not authenticated');
            }
            
            // Validate required fields (adapted for portfolio.buylist schema)
            if (empty($data['company'])) {
                throw new Exception('Company name is required');
            }
            
            if (empty($data['isin'])) {
                throw new Exception('ISIN is required');
            }
            
            // Check if this company is already in buylist
            $stmt = $this->portfolioDb->prepare("SELECT buy_list_id FROM buylist WHERE company = :company AND isin = :isin");
            $stmt->bindValue(':company', $data['company']);
            $stmt->bindValue(':isin', $data['isin']);
            $stmt->execute();
            
            if ($stmt->fetch()) {
                throw new Exception('This company is already in the buylist');
            }
            
            // Prepare data for insertion (simplified for portfolio.buylist schema)
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
                $buylistId = $this->portfolioDb->lastInsertId();
                Logger::logUserAction('buylist_add', 'Added company to buylist: ' . $company['name'], [
                    'buylist_id' => $buylistId,
                    'isin' => $data['isin']
                ]);
            }
            
            return $success;
            
        } catch (Exception $e) {
            Logger::error('Add buylist entry error: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Update buylist entry
     * @param int $buylistId Entry ID
     * @param array $data Update data
     * @return bool Success status
     */
    public function updateBuylistEntry($buylistId, $data) {
        try {
            $userId = Auth::getUserId();
            if (!$userId) {
                throw new Exception('User not authenticated');
            }
            
            // Check if entry exists and belongs to user
            $existingEntry = $this->getBuylistEntry($buylistId);
            if (!$existingEntry) {
                throw new Exception('Buylist entry not found');
            }
            
            $updateFields = [];
            $params = [':buylist_id' => $buylistId, ':user_id' => $userId, ':updated_by' => $userId];
            
            // Build update fields
            $allowedFields = [
                'status_id', 'target_price', 'target_quantity', 'priority_level', 'notes', 'research_notes',
                'expected_dividend_yield', 'pe_ratio', 'price_to_book', 'debt_to_equity', 'roe',
                'analyst_rating', 'risk_level', 'sector', 'market_cap_category', 'target_allocation_percent',
                'stop_loss_price', 'take_profit_price', 'entry_strategy', 'exit_strategy',
                'last_analysis_date', 'next_review_date', 'price_alert_enabled', 'price_alert_target'
            ];
            
            foreach ($allowedFields as $field) {
                if (array_key_exists($field, $data)) {
                    $value = $data[$field];
                    
                    // Type casting and sanitization
                    switch ($field) {
                        case 'status_id':
                        case 'target_quantity':
                        case 'priority_level':
                        case 'risk_level':
                            $value = !empty($value) ? (int)$value : null;
                            break;
                        case 'target_price':
                        case 'expected_dividend_yield':
                        case 'pe_ratio':
                        case 'price_to_book':
                        case 'debt_to_equity':
                        case 'roe':
                        case 'target_allocation_percent':
                        case 'stop_loss_price':
                        case 'take_profit_price':
                        case 'price_alert_target':
                            $value = !empty($value) ? (float)$value : null;
                            break;
                        case 'price_alert_enabled':
                            $value = (bool)$value;
                            break;
                        case 'notes':
                        case 'research_notes':
                        case 'analyst_rating':
                        case 'sector':
                        case 'entry_strategy':
                        case 'exit_strategy':
                            $value = !empty($value) ? Security::sanitizeInput($value) : null;
                            break;
                        case 'last_analysis_date':
                        case 'next_review_date':
                            $value = !empty($value) ? $value : null;
                            break;
                    }
                    
                    $updateFields[] = "$field = :$field";
                    $params[":$field"] = $value;
                    
                    // Track changes for history
                    if ($existingEntry[$field] != $value) {
                        $this->logFieldChange($buylistId, $field, $existingEntry[$field], $value, $userId);
                    }
                }
            }
            
            if (empty($updateFields)) {
                throw new Exception('No valid fields to update');
            }
            
            $updateFields[] = "updated_by = :updated_by";
            
            $sql = "UPDATE buylist SET " . implode(', ', $updateFields) . " WHERE buylist_id = :buylist_id AND user_id = :user_id";
            $stmt = $this->portfolioDb->prepare($sql);
            
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            
            $success = $stmt->execute();
            
            if ($success) {
                Logger::logUserAction('buylist_update', 'Updated buylist entry: ' . $existingEntry['company_name'], [
                    'buylist_id' => $buylistId,
                    'fields' => array_keys($data)
                ]);
            }
            
            return $success;
            
        } catch (Exception $e) {
            Logger::error('Update buylist entry error: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Delete buylist entry
     * @param int $buylistId Entry ID
     * @return bool Success status
     */
    public function deleteBuylistEntry($buylistId) {
        try {
            $userId = Auth::getUserId();
            if (!$userId) {
                throw new Exception('User not authenticated');
            }
            
            // Check if entry exists and belongs to user
            $existingEntry = $this->getBuylistEntry($buylistId);
            if (!$existingEntry) {
                throw new Exception('Buylist entry not found');
            }
            
            $sql = "DELETE FROM buylist WHERE buylist_id = :buylist_id AND user_id = :user_id";
            $stmt = $this->portfolioDb->prepare($sql);
            $stmt->bindValue(':buylist_id', $buylistId, PDO::PARAM_INT);
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            
            $success = $stmt->execute();
            
            if ($success) {
                Logger::logUserAction('buylist_delete', 'Deleted buylist entry: ' . $existingEntry['company_name'], [
                    'buylist_id' => $buylistId,
                    'isin' => $existingEntry['isin']
                ]);
            }
            
            return $success;
            
        } catch (Exception $e) {
            Logger::error('Delete buylist entry error: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Get available statuses
     * @return array Status list
     */
    public function getBuylistStatuses() {
        try {
            $sql = "SELECT * FROM buylist_status WHERE is_active = 1 ORDER BY sort_order";
            $stmt = $this->portfolioDb->prepare($sql);
            $stmt->execute();
            
            return $stmt->fetchAll();
            
        } catch (Exception $e) {
            Logger::error('Get buylist statuses error: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get buylist statistics for current user
     * @return array Statistics
     */
    public function getBuylistStatistics() {
        try {
            $userId = Auth::getUserId();
            if (!$userId) {
                throw new Exception('User not authenticated');
            }
            
            $stats = [];
            
            // Total entries
            $stmt = $this->portfolioDb->prepare("SELECT COUNT(*) as total FROM buylist WHERE user_id = :user_id");
            $stmt->bindValue(':user_id', $userId);
            $stmt->execute();
            $stats['total_entries'] = $stmt->fetch()['total'];
            
            // Status breakdown
            $stmt = $this->portfolioDb->prepare("
                SELECT bs.status_name, bs.status_color, COUNT(*) as count 
                FROM buylist b 
                JOIN buylist_status bs ON b.status_id = bs.status_id 
                WHERE b.user_id = :user_id 
                GROUP BY bs.status_id, bs.status_name, bs.status_color 
                ORDER BY count DESC
            ");
            $stmt->bindValue(':user_id', $userId);
            $stmt->execute();
            $stats['by_status'] = $stmt->fetchAll();
            
            // Priority breakdown
            $stmt = $this->portfolioDb->prepare("
                SELECT priority_level, COUNT(*) as count 
                FROM buylist 
                WHERE user_id = :user_id 
                GROUP BY priority_level 
                ORDER BY priority_level DESC
            ");
            $stmt->bindValue(':user_id', $userId);
            $stmt->execute();
            $stats['by_priority'] = $stmt->fetchAll();
            
            // Target value
            $stmt = $this->portfolioDb->prepare("
                SELECT SUM(target_price * target_quantity) as total_target_value,
                       AVG(target_price) as avg_target_price,
                       COUNT(*) as entries_with_price
                FROM buylist 
                WHERE user_id = :user_id AND target_price IS NOT NULL AND target_quantity IS NOT NULL
            ");
            $stmt->bindValue(':user_id', $userId);
            $stmt->execute();
            $priceStats = $stmt->fetch();
            $stats['target_value'] = $priceStats['total_target_value'] ?? 0;
            $stats['avg_target_price'] = $priceStats['avg_target_price'] ?? 0;
            $stats['entries_with_price'] = $priceStats['entries_with_price'] ?? 0;
            
            return $stats;
            
        } catch (Exception $e) {
            Logger::error('Get buylist statistics error: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Log field change for history tracking
     * @param int $buylistId Entry ID
     * @param string $fieldName Field name
     * @param mixed $oldValue Old value
     * @param mixed $newValue New value
     * @param int $userId User ID
     */
    private function logFieldChange($buylistId, $fieldName, $oldValue, $newValue, $userId) {
        try {
            $sql = "INSERT INTO buylist_history (buylist_id, field_name, old_value, new_value, changed_by) 
                    VALUES (:buylist_id, :field_name, :old_value, :new_value, :changed_by)";
            
            $stmt = $this->portfolioDb->prepare($sql);
            $stmt->bindValue(':buylist_id', $buylistId);
            $stmt->bindValue(':field_name', $fieldName);
            $stmt->bindValue(':old_value', $oldValue);
            $stmt->bindValue(':new_value', $newValue);
            $stmt->bindValue(':changed_by', $userId);
            $stmt->execute();
            
        } catch (Exception $e) {
            Logger::error('Log field change error: ' . $e->getMessage());
        }
    }
    
    /**
     * Add buylist entry to masterlist
     * @param int $buylistId Buylist entry ID
     * @param array $masterlistData Additional masterlist data
     * @return bool Success status
     */
    public function addToMasterlist($buylistId, $masterlistData = []) {
        try {
            $userId = Auth::getUserId();
            if (!$userId) {
                throw new Exception('User not authenticated');
            }
            
            // Get buylist entry
            $buylistEntry = $this->getBuylistEntry($buylistId);
            if (!$buylistEntry) {
                throw new Exception('Buylist entry not found');
            }
            
            // Check if already added to masterlist
            if ($buylistEntry['added_to_masterlist']) {
                throw new Exception('This company has already been added to masterlist');
            }
            
            // Generate ISIN if not provided
            $isin = $buylistEntry['isin'];
            if (empty($isin)) {
                // Generate a temporary ISIN-like identifier
                $isin = $buylistEntry['country'] . str_pad(rand(1, 9999999999), 10, '0', STR_PAD_LEFT);
            }
            
            // Check if company already exists in masterlist
            $stmt = $this->portfolioDb->prepare("SELECT isin FROM masterlist WHERE isin = :isin OR (name = :name AND ticker = :ticker)");
            $stmt->bindValue(':isin', $isin);
            $stmt->bindValue(':name', $buylistEntry['company_name']);
            $stmt->bindValue(':ticker', $buylistEntry['ticker']);
            $stmt->execute();
            $existingMasterlist = $stmt->fetch();
            
            if ($existingMasterlist) {
                // Company already exists in masterlist
                $this->logMasterlistAction($buylistId, $existingMasterlist['isin'], 'already_exists', $userId);
                
                // Update buylist entry
                $this->portfolioDb->prepare("UPDATE buylist SET added_to_masterlist = 1, added_to_masterlist_date = NOW() WHERE buylist_id = :buylist_id")
                    ->execute([':buylist_id' => $buylistId]);
                
                return true;
            }
            
            // Add to masterlist
            $masterlistInsertData = [
                'isin' => $isin,
                'ticker' => $buylistEntry['ticker'],
                'name' => $buylistEntry['company_name'],
                'country' => $buylistEntry['country'] ?: 'SE',
                'market' => $masterlistData['market'] ?? null,
                'share_type_id' => $masterlistData['share_type_id'] ?? 1,
                'delisted' => 0,
                'current_version' => 1
            ];
            
            $masterlistFields = array_keys($masterlistInsertData);
            $masterlistPlaceholders = ':' . implode(', :', $masterlistFields);
            
            $sql = "INSERT INTO masterlist (" . implode(', ', $masterlistFields) . ") VALUES ($masterlistPlaceholders)";
            $stmt = $this->portfolioDb->prepare($sql);
            
            foreach ($masterlistInsertData as $key => $value) {
                $stmt->bindValue(":$key", $value);
            }
            
            $masterlistSuccess = $stmt->execute();
            
            if ($masterlistSuccess) {
                // Update buylist entry
                $this->portfolioDb->prepare("UPDATE buylist SET added_to_masterlist = 1, added_to_masterlist_date = NOW(), isin = :isin WHERE buylist_id = :buylist_id")
                    ->execute([':isin' => $isin, ':buylist_id' => $buylistId]);
                
                // Log the action
                $this->logMasterlistAction($buylistId, $isin, 'added_to_masterlist', $userId);
                
                Logger::logUserAction('buylist_to_masterlist', 'Added buylist entry to masterlist: ' . $buylistEntry['company_name'], [
                    'buylist_id' => $buylistId,
                    'isin' => $isin
                ]);
                
                return true;
            }
            
            return false;
            
        } catch (Exception $e) {
            Logger::error('Add to masterlist error: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Log masterlist action
     * @param int $buylistId Buylist ID
     * @param string $isin ISIN
     * @param string $actionType Action type
     * @param int $userId User ID
     */
    private function logMasterlistAction($buylistId, $isin, $actionType, $userId) {
        try {
            $sql = "INSERT INTO buylist_masterlist_log (buylist_id, masterlist_isin, action_type, action_by) VALUES (?, ?, ?, ?)";
            $stmt = $this->portfolioDb->prepare($sql);
            $stmt->execute([$buylistId, $isin, $actionType, $userId]);
        } catch (Exception $e) {
            Logger::error('Log masterlist action error: ' . $e->getMessage());
        }
    }
    
    /**
     * Get filter options for UI
     * @return array Filter options
     */
    public function getFilterOptions() {
        try {
            $userId = Auth::getUserId();
            if (!$userId) {
                throw new Exception('User not authenticated');
            }
            
            $options = [];
            
            // Available statuses
            $options['statuses'] = $this->getBuylistStatuses();
            
            // Priority levels
            $options['priority_levels'] = [
                1 => 'Low',
                2 => 'Medium', 
                3 => 'High',
                4 => 'Critical'
            ];
            
            // Risk levels
            $options['risk_levels'] = [
                1 => 'Low Risk',
                2 => 'Medium Risk',
                3 => 'High Risk'
            ];
            
            // Market cap categories
            $options['market_cap_categories'] = ['Small', 'Mid', 'Large', 'Mega'];
            
            // Sectors (from existing entries)
            $stmt = $this->portfolioDb->prepare("
                SELECT DISTINCT sector 
                FROM buylist 
                WHERE user_id = :user_id AND sector IS NOT NULL 
                ORDER BY sector
            ");
            $stmt->bindValue(':user_id', $userId);
            $stmt->execute();
            $options['sectors'] = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            return $options;
            
        } catch (Exception $e) {
            Logger::error('Get filter options error: ' . $e->getMessage());
            return [];
        }
    }
}