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
    private $foundationDb;
    
    public function __construct() {
        $this->foundationDb = Database::getConnection('foundation');
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
            
            $whereConditions = ['b.user_id = :user_id'];
            $params = [':user_id' => $userId];
            
            // Build WHERE conditions based on filters
            if (!empty($filters['status_id'])) {
                $whereConditions[] = "b.status_id = :status_id";
                $params[':status_id'] = $filters['status_id'];
            }
            
            if (!empty($filters['priority_level'])) {
                $whereConditions[] = "b.priority_level = :priority_level";
                $params[':priority_level'] = $filters['priority_level'];
            }
            
            if (!empty($filters['risk_level'])) {
                $whereConditions[] = "b.risk_level = :risk_level";
                $params[':risk_level'] = $filters['risk_level'];
            }
            
            if (!empty($filters['sector'])) {
                $whereConditions[] = "b.sector = :sector";
                $params[':sector'] = $filters['sector'];
            }
            
            if (!empty($filters['market_cap_category'])) {
                $whereConditions[] = "b.market_cap_category = :market_cap_category";
                $params[':market_cap_category'] = $filters['market_cap_category'];
            }
            
            if (!empty($filters['search'])) {
                $whereConditions[] = "(b.company_name LIKE :search OR b.ticker LIKE :search OR b.notes LIKE :search OR b.research_notes LIKE :search)";
                $params[':search'] = '%' . $filters['search'] . '%';
            }
            
            if (isset($filters['price_min'])) {
                $whereConditions[] = "b.target_price >= :price_min";
                $params[':price_min'] = $filters['price_min'];
            }
            
            if (isset($filters['price_max'])) {
                $whereConditions[] = "b.target_price <= :price_max";
                $params[':price_max'] = $filters['price_max'];
            }
            
            $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);
            
            // Count total records
            $countSql = "SELECT COUNT(*) as total 
                        FROM buylist b 
                        $whereClause";
            $countStmt = $this->foundationDb->prepare($countSql);
            foreach ($params as $key => $value) {
                $countStmt->bindValue($key, $value);
            }
            $countStmt->execute();
            $totalRecords = $countStmt->fetch()['total'];
            
            // Calculate pagination
            $offset = ($page - 1) * $limit;
            $totalPages = ceil($totalRecords / $limit);
            
            // Get buylist entries with status info
            $sql = "SELECT b.*, 
                           bs.status_name, bs.status_color
                    FROM buylist b 
                    LEFT JOIN buylist_status bs ON b.status_id = bs.status_id 
                    $whereClause 
                    ORDER BY b.priority_level DESC, b.updated_at DESC 
                    LIMIT :limit OFFSET :offset";
            
            $stmt = $this->foundationDb->prepare($sql);
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
            
            $stmt = $this->foundationDb->prepare($sql);
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
            
            // Validate required fields
            if (empty($data['company_name'])) {
                throw new Exception('Company name is required');
            }
            
            if (empty($data['ticker'])) {
                throw new Exception('Ticker is required');
            }
            
            if (empty($data['status_id'])) {
                throw new Exception('Status is required');
            }
            
            // Check if user already has this company in buylist
            $stmt = $this->foundationDb->prepare("SELECT buylist_id FROM buylist WHERE user_id = :user_id AND company_name = :company_name AND ticker = :ticker");
            $stmt->bindValue(':user_id', $userId);
            $stmt->bindValue(':company_name', $data['company_name']);
            $stmt->bindValue(':ticker', $data['ticker']);
            $stmt->execute();
            
            if ($stmt->fetch()) {
                throw new Exception('This company is already in your buylist');
            }
            
            // Prepare data for insertion
            $insertData = [
                'isin' => !empty($data['isin']) ? Security::sanitizeInput($data['isin']) : null,
                'company_name' => Security::sanitizeInput($data['company_name']),
                'ticker' => strtoupper(Security::sanitizeInput($data['ticker'])),
                'country' => !empty($data['country']) ? strtoupper(Security::sanitizeInput($data['country'])) : null,
                'currency' => !empty($data['currency']) ? strtoupper(Security::sanitizeInput($data['currency'])) : 'SEK',
                'exchange' => !empty($data['exchange']) ? Security::sanitizeInput($data['exchange']) : null,
                'company_website' => !empty($data['company_website']) ? Security::sanitizeInput($data['company_website']) : null,
                'business_description' => !empty($data['business_description']) ? Security::sanitizeInput($data['business_description']) : null,
                'user_id' => $userId,
                'status_id' => (int)$data['status_id'],
                'target_price' => !empty($data['target_price']) ? (float)$data['target_price'] : null,
                'target_quantity' => !empty($data['target_quantity']) ? (int)$data['target_quantity'] : null,
                'priority_level' => !empty($data['priority_level']) ? (int)$data['priority_level'] : 3,
                'notes' => !empty($data['notes']) ? Security::sanitizeInput($data['notes']) : null,
                'research_notes' => !empty($data['research_notes']) ? Security::sanitizeInput($data['research_notes']) : null,
                'expected_dividend_yield' => !empty($data['expected_dividend_yield']) ? (float)$data['expected_dividend_yield'] : null,
                'pe_ratio' => !empty($data['pe_ratio']) ? (float)$data['pe_ratio'] : null,
                'price_to_book' => !empty($data['price_to_book']) ? (float)$data['price_to_book'] : null,
                'debt_to_equity' => !empty($data['debt_to_equity']) ? (float)$data['debt_to_equity'] : null,
                'roe' => !empty($data['roe']) ? (float)$data['roe'] : null,
                'analyst_rating' => !empty($data['analyst_rating']) ? Security::sanitizeInput($data['analyst_rating']) : null,
                'risk_level' => !empty($data['risk_level']) ? (int)$data['risk_level'] : 3,
                'sector' => !empty($data['sector']) ? Security::sanitizeInput($data['sector']) : null,
                'market_cap_category' => !empty($data['market_cap_category']) ? $data['market_cap_category'] : 'Mid',
                'target_allocation_percent' => !empty($data['target_allocation_percent']) ? (float)$data['target_allocation_percent'] : null,
                'stop_loss_price' => !empty($data['stop_loss_price']) ? (float)$data['stop_loss_price'] : null,
                'take_profit_price' => !empty($data['take_profit_price']) ? (float)$data['take_profit_price'] : null,
                'entry_strategy' => !empty($data['entry_strategy']) ? Security::sanitizeInput($data['entry_strategy']) : null,
                'exit_strategy' => !empty($data['exit_strategy']) ? Security::sanitizeInput($data['exit_strategy']) : null,
                'last_analysis_date' => !empty($data['last_analysis_date']) ? $data['last_analysis_date'] : null,
                'next_review_date' => !empty($data['next_review_date']) ? $data['next_review_date'] : null,
                'price_alert_enabled' => isset($data['price_alert_enabled']) ? (bool)$data['price_alert_enabled'] : true,
                'price_alert_target' => !empty($data['price_alert_target']) ? (float)$data['price_alert_target'] : null,
                'created_by' => $userId,
                'updated_by' => $userId
            ];
            
            // Build INSERT query
            $fields = array_keys($insertData);
            $placeholders = ':' . implode(', :', $fields);
            
            $sql = "INSERT INTO buylist (" . implode(', ', $fields) . ") VALUES ($placeholders)";
            $stmt = $this->foundationDb->prepare($sql);
            
            foreach ($insertData as $key => $value) {
                $stmt->bindValue(":$key", $value);
            }
            
            $success = $stmt->execute();
            
            if ($success) {
                $buylistId = $this->foundationDb->lastInsertId();
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
            $stmt = $this->foundationDb->prepare($sql);
            
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
            $stmt = $this->foundationDb->prepare($sql);
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
            $stmt = $this->foundationDb->prepare($sql);
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
            $stmt = $this->foundationDb->prepare("SELECT COUNT(*) as total FROM buylist WHERE user_id = :user_id");
            $stmt->bindValue(':user_id', $userId);
            $stmt->execute();
            $stats['total_entries'] = $stmt->fetch()['total'];
            
            // Status breakdown
            $stmt = $this->foundationDb->prepare("
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
            $stmt = $this->foundationDb->prepare("
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
            $stmt = $this->foundationDb->prepare("
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
            
            $stmt = $this->foundationDb->prepare($sql);
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
            $stmt = $this->foundationDb->prepare("SELECT isin FROM masterlist WHERE isin = :isin OR (name = :name AND ticker = :ticker)");
            $stmt->bindValue(':isin', $isin);
            $stmt->bindValue(':name', $buylistEntry['company_name']);
            $stmt->bindValue(':ticker', $buylistEntry['ticker']);
            $stmt->execute();
            $existingMasterlist = $stmt->fetch();
            
            if ($existingMasterlist) {
                // Company already exists in masterlist
                $this->logMasterlistAction($buylistId, $existingMasterlist['isin'], 'already_exists', $userId);
                
                // Update buylist entry
                $this->foundationDb->prepare("UPDATE buylist SET added_to_masterlist = 1, added_to_masterlist_date = NOW() WHERE buylist_id = :buylist_id")
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
            $stmt = $this->foundationDb->prepare($sql);
            
            foreach ($masterlistInsertData as $key => $value) {
                $stmt->bindValue(":$key", $value);
            }
            
            $masterlistSuccess = $stmt->execute();
            
            if ($masterlistSuccess) {
                // Update buylist entry
                $this->foundationDb->prepare("UPDATE buylist SET added_to_masterlist = 1, added_to_masterlist_date = NOW(), isin = :isin WHERE buylist_id = :buylist_id")
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
            $stmt = $this->foundationDb->prepare($sql);
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
            $stmt = $this->foundationDb->prepare("
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