<?php
/**
 * File: src/controllers/MasterlistController.php
 * Description: Masterlist management controller for PSW 4.0 - handles company masterlist CRUD operations
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../middleware/Auth.php';
require_once __DIR__ . '/../utils/Logger.php';
require_once __DIR__ . '/../utils/Security.php';

class MasterlistController {
    private $foundationDb;
    
    public function __construct() {
        $this->foundationDb = Database::getConnection('foundation');
    }
    
    /**
     * Get all companies with optional filtering and pagination
     * @param array $filters Optional filters (country, market, share_type, delisted)
     * @param int $page Page number (default: 1)
     * @param int $limit Items per page (default: 50)
     * @return array Companies data with pagination info
     */
    public function getAllCompanies($filters = [], $page = 1, $limit = 50) {
        try {
            $whereConditions = [];
            $params = [];
            
            // Build WHERE conditions based on filters
            if (!empty($filters['country'])) {
                $whereConditions[] = "m.country = :country";
                $params[':country'] = $filters['country'];
            }
            
            if (!empty($filters['market'])) {
                $whereConditions[] = "m.market = :market";
                $params[':market'] = $filters['market'];
            }
            
            if (!empty($filters['share_type'])) {
                $whereConditions[] = "m.share_type_id = :share_type_id";
                $params[':share_type_id'] = $filters['share_type'];
            }
            
            if (isset($filters['delisted'])) {
                $whereConditions[] = "m.delisted = :delisted";
                $params[':delisted'] = (int)$filters['delisted'];
            }
            
            if (!empty($filters['search'])) {
                $whereConditions[] = "(m.name LIKE :search OR m.ticker LIKE :search OR m.isin LIKE :search)";
                $params[':search'] = '%' . $filters['search'] . '%';
            }
            
            // Specific field filters
            if (!empty($filters['isin'])) {
                $whereConditions[] = "m.isin LIKE :isin";
                $params[':isin'] = '%' . $filters['isin'] . '%';
            }
            
            if (!empty($filters['ticker'])) {
                $whereConditions[] = "m.ticker LIKE :ticker";
                $params[':ticker'] = '%' . $filters['ticker'] . '%';
            }
            
            if (!empty($filters['company_name'])) {
                $whereConditions[] = "m.name LIKE :company_name";
                $params[':company_name'] = '%' . $filters['company_name'] . '%';
            }
            
            $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
            
            // Count total records
            $countSql = "SELECT COUNT(*) as total FROM masterlist m $whereClause";
            $countStmt = $this->foundationDb->prepare($countSql);
            foreach ($params as $key => $value) {
                $countStmt->bindValue($key, $value);
            }
            $countStmt->execute();
            $totalRecords = $countStmt->fetch()['total'];
            
            // Calculate pagination
            $offset = ($page - 1) * $limit;
            $totalPages = ceil($totalRecords / $limit);
            
            // Get companies with share type info
            $sql = "SELECT m.*, st.code as share_type_code, st.description as share_type_description 
                    FROM masterlist m 
                    LEFT JOIN share_types st ON m.share_type_id = st.share_type_id 
                    $whereClause 
                    ORDER BY m.name ASC 
                    LIMIT :limit OFFSET :offset";
            
            $stmt = $this->foundationDb->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            
            $companies = $stmt->fetchAll();
            
            return [
                'companies' => $companies,
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
            Logger::error('Get all companies error: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Get company by ISIN
     * @param string $isin Company ISIN
     * @return array|false Company data or false if not found
     */
    public function getCompanyByIsin($isin) {
        try {
            $sql = "SELECT m.*, st.code as share_type_code, st.description as share_type_description 
                    FROM masterlist m 
                    LEFT JOIN share_types st ON m.share_type_id = st.share_type_id 
                    WHERE m.isin = :isin";
            
            $stmt = $this->foundationDb->prepare($sql);
            $stmt->bindValue(':isin', $isin);
            $stmt->execute();
            
            return $stmt->fetch() ?: false;
            
        } catch (Exception $e) {
            Logger::error('Get company by ISIN error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Create new company
     * @param array $data Company data
     * @return bool Success status
     */
    public function createCompany($data) {
        try {
            if (!Auth::isLoggedIn()) {
                throw new Exception('User not logged in');
            }
            
            // Validate required fields
            $required = ['isin', 'ticker', 'name', 'country'];
            foreach ($required as $field) {
                if (empty($data[$field])) {
                    throw new Exception("Field '$field' is required");
                }
            }
            
            // Validate ISIN format (12 characters)
            if (strlen($data['isin']) !== 12) {
                throw new Exception('ISIN must be exactly 12 characters');
            }
            
            // Check if ISIN already exists
            if ($this->getCompanyByIsin($data['isin'])) {
                throw new Exception('Company with this ISIN already exists');
            }
            
            // Sanitize input
            $cleanData = [
                'isin' => strtoupper(Security::sanitizeInput($data['isin'])),
                'ticker' => strtoupper(Security::sanitizeInput($data['ticker'])),
                'name' => Security::sanitizeInput($data['name']),
                'country' => strtoupper(Security::sanitizeInput($data['country'])),
                'market' => !empty($data['market']) ? Security::sanitizeInput($data['market']) : null,
                'share_type_id' => !empty($data['share_type_id']) ? (int)$data['share_type_id'] : 1,
                'delisted' => isset($data['delisted']) ? (int)$data['delisted'] : 0,
                'delisted_date' => !empty($data['delisted_date']) ? $data['delisted_date'] : null,
                'current_version' => isset($data['current_version']) ? (int)$data['current_version'] : 1
            ];
            
            $sql = "INSERT INTO masterlist (isin, ticker, name, country, market, share_type_id, delisted, delisted_date, current_version) 
                    VALUES (:isin, :ticker, :name, :country, :market, :share_type_id, :delisted, :delisted_date, :current_version)";
            
            $stmt = $this->foundationDb->prepare($sql);
            foreach ($cleanData as $key => $value) {
                $stmt->bindValue(":$key", $value);
            }
            
            $success = $stmt->execute();
            
            if ($success) {
                Logger::logUserAction('masterlist_create', 'Created company: ' . $cleanData['name'], ['isin' => $cleanData['isin']]);
            }
            
            return $success;
            
        } catch (Exception $e) {
            Logger::error('Create company error: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Update company
     * @param string $isin Company ISIN
     * @param array $data Update data
     * @return bool Success status
     */
    public function updateCompany($isin, $data) {
        try {
            if (!Auth::isLoggedIn()) {
                throw new Exception('User not logged in');
            }
            
            // Check if company exists
            $existingCompany = $this->getCompanyByIsin($isin);
            if (!$existingCompany) {
                throw new Exception('Company not found');
            }
            
            $updateFields = [];
            $params = [':isin' => $isin];
            
            // Build update fields
            $allowedFields = ['ticker', 'name', 'country', 'market', 'share_type_id', 'delisted', 'delisted_date', 'current_version'];
            
            foreach ($allowedFields as $field) {
                if (isset($data[$field])) {
                    switch ($field) {
                        case 'ticker':
                            $value = strtoupper(Security::sanitizeInput($data[$field]));
                            break;
                        case 'name':
                        case 'market':
                            $value = Security::sanitizeInput($data[$field]);
                            break;
                        case 'country':
                            $value = strtoupper(Security::sanitizeInput($data[$field]));
                            break;
                        case 'share_type_id':
                        case 'delisted':
                        case 'current_version':
                            $value = (int)$data[$field];
                            break;
                        case 'delisted_date':
                            $value = !empty($data[$field]) ? $data[$field] : null;
                            break;
                        default:
                            $value = $data[$field];
                    }
                    
                    $updateFields[] = "$field = :$field";
                    $params[":$field"] = $value;
                }
            }
            
            if (empty($updateFields)) {
                throw new Exception('No valid fields to update');
            }
            
            $sql = "UPDATE masterlist SET " . implode(', ', $updateFields) . " WHERE isin = :isin";
            $stmt = $this->foundationDb->prepare($sql);
            
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            
            $success = $stmt->execute();
            
            if ($success) {
                Logger::logUserAction('masterlist_update', 'Updated company: ' . $existingCompany['name'], ['isin' => $isin, 'fields' => array_keys($data)]);
            }
            
            return $success;
            
        } catch (Exception $e) {
            Logger::error('Update company error: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Delete company (soft delete by marking as delisted)
     * @param string $isin Company ISIN
     * @return bool Success status
     */
    public function deleteCompany($isin) {
        try {
            if (!Auth::isLoggedIn()) {
                throw new Exception('User not logged in');
            }
            
            // Check if company exists
            $existingCompany = $this->getCompanyByIsin($isin);
            if (!$existingCompany) {
                throw new Exception('Company not found');
            }
            
            // Soft delete by marking as delisted
            $sql = "UPDATE masterlist SET delisted = 1, delisted_date = NOW() WHERE isin = :isin";
            $stmt = $this->foundationDb->prepare($sql);
            $stmt->bindValue(':isin', $isin);
            
            $success = $stmt->execute();
            
            if ($success) {
                Logger::logUserAction('masterlist_delete', 'Deleted company: ' . $existingCompany['name'], ['isin' => $isin]);
            }
            
            return $success;
            
        } catch (Exception $e) {
            Logger::error('Delete company error: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Get share types for dropdown
     * @return array Share types
     */
    public function getShareTypes() {
        try {
            $sql = "SELECT * FROM share_types ORDER BY code";
            $stmt = $this->foundationDb->prepare($sql);
            $stmt->execute();
            
            return $stmt->fetchAll();
            
        } catch (Exception $e) {
            Logger::error('Get share types error: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get statistics for dashboard
     * @return array Statistics
     */
    public function getStatistics() {
        try {
            $stats = [];
            
            // Total companies
            $stmt = $this->foundationDb->query("SELECT COUNT(*) as total FROM masterlist");
            $stats['total_companies'] = $stmt->fetch()['total'];
            
            // Active companies
            $stmt = $this->foundationDb->query("SELECT COUNT(*) as active FROM masterlist WHERE delisted = 0");
            $stats['active_companies'] = $stmt->fetch()['active'];
            
            // Delisted companies
            $stmt = $this->foundationDb->query("SELECT COUNT(*) as delisted FROM masterlist WHERE delisted = 1");
            $stats['delisted_companies'] = $stmt->fetch()['delisted'];
            
            // Countries
            $stmt = $this->foundationDb->query("SELECT COUNT(DISTINCT country) as countries FROM masterlist");
            $stats['total_countries'] = $stmt->fetch()['countries'];
            
            // Markets
            $stmt = $this->foundationDb->query("SELECT COUNT(DISTINCT market) as markets FROM masterlist WHERE market IS NOT NULL");
            $stats['total_markets'] = $stmt->fetch()['markets'];
            
            // Country breakdown
            $stmt = $this->foundationDb->query("SELECT country, COUNT(*) as count FROM masterlist GROUP BY country ORDER BY count DESC");
            $stats['by_country'] = $stmt->fetchAll();
            
            // Market breakdown
            $stmt = $this->foundationDb->query("SELECT market, COUNT(*) as count FROM masterlist WHERE market IS NOT NULL GROUP BY market ORDER BY count DESC");
            $stats['by_market'] = $stmt->fetchAll();
            
            // Share type breakdown
            $stmt = $this->foundationDb->query("
                SELECT st.code, st.description, COUNT(*) as count 
                FROM masterlist m 
                LEFT JOIN share_types st ON m.share_type_id = st.share_type_id 
                GROUP BY st.code, st.description 
                ORDER BY count DESC
            ");
            $stats['by_share_type'] = $stmt->fetchAll();
            
            return $stats;
            
        } catch (Exception $e) {
            Logger::error('Get statistics error: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get distinct values for filters
     * @return array Filter options
     */
    public function getFilterOptions() {
        try {
            $options = [];
            
            // Countries
            $stmt = $this->foundationDb->query("SELECT DISTINCT country FROM masterlist WHERE country IS NOT NULL ORDER BY country");
            $options['countries'] = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            // Markets
            $stmt = $this->foundationDb->query("SELECT DISTINCT market FROM masterlist WHERE market IS NOT NULL ORDER BY market");
            $options['markets'] = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            // Share types
            $options['share_types'] = $this->getShareTypes();
            
            return $options;
            
        } catch (Exception $e) {
            Logger::error('Get filter options error: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Export companies to CSV
     * @param array $filters Optional filters
     * @return string CSV content
     */
    public function exportToCSV($filters = []) {
        try {
            $whereConditions = [];
            $params = [];
            
            // Build WHERE conditions based on filters
            if (!empty($filters['country'])) {
                $whereConditions[] = "m.country = :country";
                $params[':country'] = $filters['country'];
            }
            
            if (!empty($filters['market'])) {
                $whereConditions[] = "m.market = :market";
                $params[':market'] = $filters['market'];
            }
            
            if (!empty($filters['share_type'])) {
                $whereConditions[] = "m.share_type_id = :share_type_id";
                $params[':share_type_id'] = $filters['share_type'];
            }
            
            if (isset($filters['delisted'])) {
                $whereConditions[] = "m.delisted = :delisted";
                $params[':delisted'] = (int)$filters['delisted'];
            }
            
            if (!empty($filters['search'])) {
                $whereConditions[] = "(m.name LIKE :search OR m.ticker LIKE :search OR m.isin LIKE :search)";
                $params[':search'] = '%' . $filters['search'] . '%';
            }
            
            $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';
            
            // Get all companies matching filters
            $sql = "SELECT m.isin, m.ticker, m.name, m.country, m.market, 
                           st.code as share_type_code, st.description as share_type_description,
                           CASE WHEN m.delisted = 1 THEN 'Delisted' ELSE 'Active' END as status,
                           m.delisted_date, m.current_version
                    FROM masterlist m 
                    LEFT JOIN share_types st ON m.share_type_id = st.share_type_id 
                    $whereClause 
                    ORDER BY m.name ASC";
            
            $stmt = $this->foundationDb->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->execute();
            
            $companies = $stmt->fetchAll();
            
            // Create CSV content
            $csv = [];
            
            // CSV Headers
            $headers = [
                'ISIN',
                'Ticker',
                'Company Name',
                'Country',
                'Market',
                'Share Type Code',
                'Share Type Description',
                'Status',
                'Delisted Date',
                'Current Version'
            ];
            $csv[] = $this->arrayToCSVLine($headers);
            
            // CSV Data
            foreach ($companies as $company) {
                $row = [
                    $company['isin'],
                    $company['ticker'],
                    $company['name'],
                    $company['country'],
                    $company['market'] ?: '',
                    $company['share_type_code'] ?: '',
                    $company['share_type_description'] ?: '',
                    $company['status'],
                    $company['delisted_date'] ?: '',
                    $company['current_version'] ? '1' : '0'
                ];
                $csv[] = $this->arrayToCSVLine($row);
            }
            
            Logger::logUserAction('masterlist_export', 'Exported masterlist to CSV', ['count' => count($companies), 'filters' => array_keys(array_filter($filters))]);
            
            return implode("\n", $csv);
            
        } catch (Exception $e) {
            Logger::error('Export to CSV error: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Convert array to CSV line
     * @param array $data Array of values
     * @return string CSV line
     */
    private function arrayToCSVLine($data) {
        $escaped = [];
        foreach ($data as $value) {
            // Escape quotes and wrap in quotes if necessary
            $value = str_replace('"', '""', $value);
            if (strpos($value, ',') !== false || strpos($value, '"') !== false || strpos($value, "\n") !== false) {
                $value = '"' . $value . '"';
            }
            $escaped[] = $value;
        }
        return implode(',', $escaped);
    }
}