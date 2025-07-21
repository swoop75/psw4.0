<?php
/**
 * File: src/models/Company.php
 * Path: C:\Users\laoan\Documents\GitHub\psw\psw4.0\src\models\Company.php
 * Description: Company model for PSW 4.0 - handles company/masterlist data operations
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../utils/Logger.php';

class Company {
    private $foundationDb;
    private $marketDataDb;
    
    public function __construct() {
        $this->foundationDb = Database::getConnection('foundation');
        $this->marketDataDb = Database::getConnection('marketdata');
    }
    
    /**
     * Get company information by ISIN
     * @param string $isin ISIN code
     * @return array|null Company information
     */
    public function getCompanyByIsin($isin) {
        try {
            $sql = "SELECT 
                        m.*,
                        st.share_type_name,
                        c.country_name,
                        curr.currency_name
                    FROM masterlist m
                    LEFT JOIN share_types st ON m.share_type_id = st.share_type_id
                    LEFT JOIN countries c ON m.country_code = c.country_code
                    LEFT JOIN currencies curr ON m.currency_code = curr.currency_code
                    WHERE m.isin = :isin";
            
            $stmt = $this->foundationDb->prepare($sql);
            $stmt->bindValue(':isin', $isin);
            $stmt->execute();
            
            $company = $stmt->fetch();
            
            if ($company) {
                Logger::debug('Retrieved company by ISIN', ['isin' => $isin, 'company' => $company['company_name']]);
            }
            
            return $company;
            
        } catch (Exception $e) {
            Logger::error('Get company by ISIN error: ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Search companies in masterlist
     * @param string $searchTerm Search term
     * @param int $limit Limit results
     * @return array Companies matching search
     */
    public function searchCompanies($searchTerm, $limit = 50) {
        try {
            $sql = "SELECT 
                        m.isin,
                        m.company_name,
                        m.ticker_symbol,
                        m.currency_code,
                        m.country_code,
                        m.delisted_date,
                        st.share_type_name,
                        c.country_name
                    FROM masterlist m
                    LEFT JOIN share_types st ON m.share_type_id = st.share_type_id
                    LEFT JOIN countries c ON m.country_code = c.country_code
                    WHERE (m.company_name LIKE :search_term
                        OR m.ticker_symbol LIKE :search_term
                        OR m.isin LIKE :search_term)
                    ORDER BY 
                        CASE 
                            WHEN m.ticker_symbol = :exact_term THEN 1
                            WHEN m.ticker_symbol LIKE :search_term THEN 2
                            WHEN m.company_name LIKE :search_term THEN 3
                            ELSE 4
                        END,
                        m.company_name ASC
                    LIMIT :limit";
            
            $stmt = $this->foundationDb->prepare($sql);
            $searchPattern = '%' . $searchTerm . '%';
            $stmt->bindValue(':search_term', $searchPattern);
            $stmt->bindValue(':exact_term', $searchTerm);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            
            $companies = $stmt->fetchAll();
            
            Logger::debug('Company search performed', [
                'search_term' => $searchTerm,
                'results_count' => count($companies)
            ]);
            
            return $companies;
            
        } catch (Exception $e) {
            Logger::error('Company search error: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get companies by sector or industry
     * @param string $sector Sector name
     * @param string|null $industry Industry name (optional)
     * @param int $limit Limit results
     * @return array Companies in sector/industry
     */
    public function getCompaniesBySector($sector, $industry = null, $limit = 100) {
        try {
            // Note: This requires sector/industry data in masterlist or related tables
            // For now, return empty array as sector data structure needs to be confirmed
            
            Logger::debug('Get companies by sector called', [
                'sector' => $sector,
                'industry' => $industry
            ]);
            
            return [];
            
        } catch (Exception $e) {
            Logger::error('Get companies by sector error: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get companies by country
     * @param string $countryCode Country code
     * @param int $limit Limit results
     * @return array Companies in country
     */
    public function getCompaniesByCountry($countryCode, $limit = 100) {
        try {
            $sql = "SELECT 
                        m.isin,
                        m.company_name,
                        m.ticker_symbol,
                        m.currency_code,
                        m.delisted_date,
                        st.share_type_name,
                        c.country_name
                    FROM masterlist m
                    LEFT JOIN share_types st ON m.share_type_id = st.share_type_id
                    LEFT JOIN countries c ON m.country_code = c.country_code
                    WHERE m.country_code = :country_code
                    AND m.delisted_date IS NULL
                    ORDER BY m.company_name ASC
                    LIMIT :limit";
            
            $stmt = $this->foundationDb->prepare($sql);
            $stmt->bindValue(':country_code', $countryCode);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            
            $companies = $stmt->fetchAll();
            
            Logger::debug('Retrieved companies by country', [
                'country_code' => $countryCode,
                'count' => count($companies)
            ]);
            
            return $companies;
            
        } catch (Exception $e) {
            Logger::error('Get companies by country error: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get active companies count
     * @return int Number of active companies
     */
    public function getActiveCompaniesCount() {
        try {
            $sql = "SELECT COUNT(*) as count 
                    FROM masterlist 
                    WHERE delisted_date IS NULL";
            
            $stmt = $this->foundationDb->prepare($sql);
            $stmt->execute();
            
            $result = $stmt->fetch();
            $count = (int) ($result['count'] ?? 0);
            
            Logger::debug('Retrieved active companies count', ['count' => $count]);
            
            return $count;
            
        } catch (Exception $e) {
            Logger::error('Get active companies count error: ' . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Get companies with recent dividend payments
     * @param int $days Number of days to look back
     * @param int $limit Limit results
     * @return array Companies with recent dividends
     */
    public function getCompaniesWithRecentDividends($days = 30, $limit = 50) {
        try {
            $cutoffDate = date('Y-m-d', strtotime("-$days days"));
            
            $sql = "SELECT DISTINCT
                        m.isin,
                        m.company_name,
                        m.ticker_symbol,
                        m.currency_code,
                        c.country_name,
                        ld.latest_dividend_date,
                        ld.latest_dividend_amount
                    FROM masterlist m
                    LEFT JOIN countries c ON m.country_code = c.country_code
                    INNER JOIN (
                        SELECT 
                            isin,
                            MAX(payment_date) as latest_dividend_date,
                            dividend_amount_sek as latest_dividend_amount
                        FROM log_dividends
                        WHERE payment_date >= :cutoff_date
                        GROUP BY isin
                    ) ld ON m.isin = ld.isin
                    ORDER BY ld.latest_dividend_date DESC
                    LIMIT :limit";
            
            // Note: This query joins with portfolio database
            $stmt = $this->foundationDb->prepare($sql);
            $stmt->bindValue(':cutoff_date', $cutoffDate);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            
            $companies = $stmt->fetchAll();
            
            Logger::debug('Retrieved companies with recent dividends', [
                'days' => $days,
                'count' => count($companies)
            ]);
            
            return $companies;
            
        } catch (Exception $e) {
            Logger::error('Get companies with recent dividends error: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get company statistics
     * @return array Company statistics
     */
    public function getCompanyStatistics() {
        try {
            $sql = "SELECT 
                        COUNT(*) as total_companies,
                        COUNT(CASE WHEN delisted_date IS NULL THEN 1 END) as active_companies,
                        COUNT(CASE WHEN delisted_date IS NOT NULL THEN 1 END) as delisted_companies,
                        COUNT(DISTINCT country_code) as countries_count,
                        COUNT(DISTINCT currency_code) as currencies_count,
                        COUNT(DISTINCT share_type_id) as share_types_count
                    FROM masterlist";
            
            $stmt = $this->foundationDb->prepare($sql);
            $stmt->execute();
            
            $stats = $stmt->fetch();
            
            Logger::debug('Retrieved company statistics', $stats);
            
            return [
                'total_companies' => (int) ($stats['total_companies'] ?? 0),
                'active_companies' => (int) ($stats['active_companies'] ?? 0),
                'delisted_companies' => (int) ($stats['delisted_companies'] ?? 0),
                'countries_count' => (int) ($stats['countries_count'] ?? 0),
                'currencies_count' => (int) ($stats['currencies_count'] ?? 0),
                'share_types_count' => (int) ($stats['share_types_count'] ?? 0)
            ];
            
        } catch (Exception $e) {
            Logger::error('Get company statistics error: ' . $e->getMessage());
            return [
                'total_companies' => 0,
                'active_companies' => 0,
                'delisted_companies' => 0,
                'countries_count' => 0,
                'currencies_count' => 0,
                'share_types_count' => 0
            ];
        }
    }
    
    /**
     * Get countries with company counts
     * @return array Countries with company counts
     */
    public function getCountriesWithCompanyCounts() {
        try {
            $sql = "SELECT 
                        m.country_code,
                        c.country_name,
                        COUNT(*) as company_count,
                        COUNT(CASE WHEN m.delisted_date IS NULL THEN 1 END) as active_count
                    FROM masterlist m
                    LEFT JOIN countries c ON m.country_code = c.country_code
                    WHERE m.country_code IS NOT NULL
                    GROUP BY m.country_code, c.country_name
                    ORDER BY company_count DESC";
            
            $stmt = $this->foundationDb->prepare($sql);
            $stmt->execute();
            
            $countries = $stmt->fetchAll();
            
            Logger::debug('Retrieved countries with company counts', [
                'countries_count' => count($countries)
            ]);
            
            return $countries;
            
        } catch (Exception $e) {
            Logger::error('Get countries with company counts error: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Add new company to masterlist (admin function)
     * @param array $companyData Company data
     * @return array Result array
     */
    public function addCompany($companyData) {
        try {
            // TODO: Implement add company functionality
            // This would be used in the "New Companies" admin section
            
            Logger::info('Add company functionality called', ['data' => $companyData]);
            
            return [
                'success' => false,
                'message' => 'Add company functionality not yet implemented'
            ];
            
        } catch (Exception $e) {
            Logger::error('Add company error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to add company'
            ];
        }
    }
}