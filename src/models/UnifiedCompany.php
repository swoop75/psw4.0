<?php
/**
 * File: src/models/UnifiedCompany.php  
 * Description: Enhanced Company model that uses the unified view to access all data sources
 * Replaces the basic Company.php model with unified data source support
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../utils/Logger.php';

class UnifiedCompany {
    private $foundationDb;
    private $marketDataDb;
    
    public function __construct() {
        $this->foundationDb = Database::getConnection('foundation');
        $this->marketDataDb = Database::getConnection('marketdata');
    }
    
    /**
     * Get company information by ISIN from unified view
     * @param string $isin ISIN code
     * @return array|null Company information
     */
    public function getCompanyByIsin($isin) {
        try {
            $sql = "SELECT 
                        data_source,
                        source_id,
                        isin,
                        ticker,
                        company_name,
                        country,
                        sector,
                        branch,
                        market_exchange,
                        currency,
                        company_type,
                        dividend_frequency,
                        last_updated,
                        source_description,
                        is_manual,
                        manual_notes
                    FROM vw_unified_companies
                    WHERE isin = :isin
                    LIMIT 1";
            
            $stmt = $this->foundationDb->prepare($sql);
            $stmt->bindValue(':isin', $isin);
            $stmt->execute();
            
            $company = $stmt->fetch();
            
            if ($company) {
                Logger::debug('Retrieved company by ISIN from unified view', [
                    'isin' => $isin, 
                    'company' => $company['company_name'],
                    'source' => $company['data_source']
                ]);
            }
            
            return $company;
            
        } catch (Exception $e) {
            Logger::error('Get company by ISIN error (unified view): ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Search companies across all data sources
     * @param string $searchTerm Search term
     * @param int $limit Limit results
     * @return array Companies matching search
     */
    public function searchCompanies($searchTerm, $limit = 50) {
        try {
            $sql = "SELECT 
                        isin,
                        company_name,
                        ticker,
                        country,
                        currency,
                        data_source,
                        source_description,
                        is_manual,
                        sector,
                        company_type
                    FROM vw_unified_companies
                    WHERE (company_name LIKE :search_term
                        OR ticker LIKE :search_term
                        OR isin LIKE :search_term)
                    ORDER BY 
                        CASE 
                            WHEN ticker = :exact_term THEN 1
                            WHEN ticker LIKE :search_term THEN 2
                            WHEN company_name LIKE :search_term THEN 3
                            ELSE 4
                        END,
                        data_source,
                        company_name ASC
                    LIMIT :limit";
            
            $stmt = $this->foundationDb->prepare($sql);
            $searchPattern = '%' . $searchTerm . '%';
            $stmt->bindValue(':search_term', $searchPattern);
            $stmt->bindValue(':exact_term', $searchTerm);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            
            $companies = $stmt->fetchAll();
            
            Logger::debug('Company search performed (unified view)', [
                'search_term' => $searchTerm,
                'results_count' => count($companies)
            ]);
            
            return $companies;
            
        } catch (Exception $e) {
            Logger::error('Company search error (unified view): ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get companies by country from all data sources
     * @param string $country Country name
     * @param int $limit Limit results
     * @return array Companies in country
     */
    public function getCompaniesByCountry($country, $limit = 100) {
        try {
            $sql = "SELECT 
                        isin,
                        company_name,
                        ticker,
                        currency,
                        data_source,
                        source_description,
                        sector,
                        company_type,
                        dividend_frequency
                    FROM vw_unified_companies
                    WHERE country = :country
                    ORDER BY data_source, company_name ASC
                    LIMIT :limit";
            
            $stmt = $this->foundationDb->prepare($sql);
            $stmt->bindValue(':country', $country);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            
            $companies = $stmt->fetchAll();
            
            Logger::debug('Retrieved companies by country (unified view)', [
                'country' => $country,
                'count' => count($companies)
            ]);
            
            return $companies;
            
        } catch (Exception $e) {
            Logger::error('Get companies by country error (unified view): ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get companies by sector from all data sources
     * @param string $sector Sector name
     * @param int $limit Limit results
     * @return array Companies in sector
     */
    public function getCompaniesBySector($sector, $limit = 100) {
        try {
            $sql = "SELECT 
                        isin,
                        company_name,
                        ticker,
                        country,
                        currency,
                        data_source,
                        source_description,
                        branch,
                        company_type
                    FROM vw_unified_companies
                    WHERE sector = :sector
                    ORDER BY data_source, company_name ASC
                    LIMIT :limit";
            
            $stmt = $this->foundationDb->prepare($sql);
            $stmt->bindValue(':sector', $sector);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            
            $companies = $stmt->fetchAll();
            
            Logger::debug('Retrieved companies by sector (unified view)', [
                'sector' => $sector,
                'count' => count($companies)
            ]);
            
            return $companies;
            
        } catch (Exception $e) {
            Logger::error('Get companies by sector error (unified view): ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get unified company statistics across all data sources
     * @return array Company statistics
     */
    public function getUnifiedCompanyStatistics() {
        try {
            $sql = "SELECT 
                        COUNT(*) as total_companies,
                        COUNT(CASE WHEN data_source = 'borsdata_nordic' THEN 1 END) as nordic_companies,
                        COUNT(CASE WHEN data_source = 'borsdata_global' THEN 1 END) as global_companies,
                        COUNT(CASE WHEN data_source = 'manual' THEN 1 END) as manual_companies,
                        COUNT(DISTINCT country) as countries_count,
                        COUNT(DISTINCT currency) as currencies_count,
                        COUNT(DISTINCT sector) as sectors_count,
                        COUNT(CASE WHEN isin IS NOT NULL AND isin != '' THEN 1 END) as companies_with_isin
                    FROM vw_unified_companies";
            
            $stmt = $this->foundationDb->prepare($sql);
            $stmt->execute();
            
            $stats = $stmt->fetch();
            
            Logger::debug('Retrieved unified company statistics', $stats);
            
            return [
                'total_companies' => (int) ($stats['total_companies'] ?? 0),
                'nordic_companies' => (int) ($stats['nordic_companies'] ?? 0),
                'global_companies' => (int) ($stats['global_companies'] ?? 0),
                'manual_companies' => (int) ($stats['manual_companies'] ?? 0),
                'countries_count' => (int) ($stats['countries_count'] ?? 0),
                'currencies_count' => (int) ($stats['currencies_count'] ?? 0),
                'sectors_count' => (int) ($stats['sectors_count'] ?? 0),
                'companies_with_isin' => (int) ($stats['companies_with_isin'] ?? 0)
            ];
            
        } catch (Exception $e) {
            Logger::error('Get unified company statistics error: ' . $e->getMessage());
            return [
                'total_companies' => 0,
                'nordic_companies' => 0,
                'global_companies' => 0,
                'manual_companies' => 0,
                'countries_count' => 0,
                'currencies_count' => 0,
                'sectors_count' => 0,
                'companies_with_isin' => 0
            ];
        }
    }
    
    /**
     * Get data source breakdown
     * @return array Data sources with counts
     */
    public function getDataSourceBreakdown() {
        try {
            $sql = "SELECT 
                        data_source,
                        source_description,
                        COUNT(*) as company_count,
                        COUNT(CASE WHEN isin IS NOT NULL AND isin != '' THEN 1 END) as with_isin,
                        COUNT(DISTINCT country) as countries,
                        COUNT(DISTINCT currency) as currencies
                    FROM vw_unified_companies
                    GROUP BY data_source, source_description
                    ORDER BY company_count DESC";
            
            $stmt = $this->foundationDb->prepare($sql);
            $stmt->execute();
            
            $breakdown = $stmt->fetchAll();
            
            Logger::debug('Retrieved data source breakdown', [
                'sources_count' => count($breakdown)
            ]);
            
            return $breakdown;
            
        } catch (Exception $e) {
            Logger::error('Get data source breakdown error: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get countries with company counts from unified view
     * @return array Countries with company counts
     */
    public function getCountriesWithCompanyCounts() {
        try {
            $sql = "SELECT 
                        country,
                        COUNT(*) as company_count,
                        COUNT(CASE WHEN data_source = 'borsdata_nordic' THEN 1 END) as nordic_count,
                        COUNT(CASE WHEN data_source = 'borsdata_global' THEN 1 END) as global_count,
                        COUNT(CASE WHEN data_source = 'manual' THEN 1 END) as manual_count,
                        GROUP_CONCAT(DISTINCT currency) as currencies,
                        GROUP_CONCAT(DISTINCT data_source) as data_sources
                    FROM vw_unified_companies
                    WHERE country IS NOT NULL AND country != ''
                    GROUP BY country
                    ORDER BY company_count DESC";
            
            $stmt = $this->foundationDb->prepare($sql);
            $stmt->execute();
            
            $countries = $stmt->fetchAll();
            
            Logger::debug('Retrieved countries with company counts (unified view)', [
                'countries_count' => count($countries)
            ]);
            
            return $countries;
            
        } catch (Exception $e) {
            Logger::error('Get countries with company counts error (unified view): ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Check if company exists in any data source
     * @param string $isin ISIN code
     * @return array Result with existence info
     */
    public function checkCompanyExists($isin) {
        try {
            $sql = "SELECT 
                        isin,
                        company_name,
                        ticker,
                        data_source,
                        source_description
                    FROM vw_unified_companies
                    WHERE isin = :isin
                    LIMIT 1";
            
            $stmt = $this->foundationDb->prepare($sql);
            $stmt->bindValue(':isin', $isin);
            $stmt->execute();
            
            $company = $stmt->fetch();
            
            if ($company) {
                return [
                    'exists' => true,
                    'source' => $company['data_source'],
                    'company_name' => $company['company_name'],
                    'ticker' => $company['ticker']
                ];
            } else {
                return [
                    'exists' => false,
                    'source' => null,
                    'company_name' => null,
                    'ticker' => null
                ];
            }
            
        } catch (Exception $e) {
            Logger::error('Check company exists error: ' . $e->getMessage());
            return [
                'exists' => false,
                'source' => null,
                'company_name' => null,
                'ticker' => null,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Get manual companies only
     * @param int $limit Limit results
     * @return array Manual companies
     */
    public function getManualCompanies($limit = 100) {
        try {
            $sql = "SELECT 
                        isin,
                        company_name,
                        ticker,
                        country,
                        currency,
                        sector,
                        branch,
                        market_exchange,
                        company_type,
                        dividend_frequency,
                        manual_notes,
                        last_updated
                    FROM vw_unified_companies
                    WHERE data_source = 'manual'
                    ORDER BY last_updated DESC
                    LIMIT :limit";
            
            $stmt = $this->foundationDb->prepare($sql);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            
            $companies = $stmt->fetchAll();
            
            Logger::debug('Retrieved manual companies', [
                'count' => count($companies)
            ]);
            
            return $companies;
            
        } catch (Exception $e) {
            Logger::error('Get manual companies error: ' . $e->getMessage());
            return [];
        }
    }
}
?>