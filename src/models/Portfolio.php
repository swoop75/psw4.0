<?php
/**
 * File: src/models/Portfolio.php
 * Path: C:\Users\laoan\Documents\GitHub\psw\psw4.0\src\models\Portfolio.php
 * Description: Portfolio model for PSW 4.0 - handles portfolio data operations
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../utils/Logger.php';

class Portfolio {
    private $foundationDb;
    private $marketDataDb;
    private $portfolioDb;
    
    public function __construct() {
        $this->foundationDb = Database::getConnection('foundation');
        $this->marketDataDb = Database::getConnection('marketdata');
        $this->portfolioDb = Database::getConnection('portfolio');
    }
    
    /**
     * Get portfolio summary metrics
     * @param int|null $userId User ID (null for admin viewing all)
     * @param bool $isAdmin Is user admin
     * @return array Portfolio summary
     */
    public function getPortfolioSummary($userId, $isAdmin) {
        try {
            // Since holdings tables aren't implemented yet, we'll use dividend data for metrics
            $dividendModel = new Dividend();
            $dividendStats = $dividendModel->getDividendStatistics($userId, $isAdmin);
            
            // Get company count from dividend data
            $companyCountSql = "SELECT COUNT(DISTINCT isin) as unique_companies
                               FROM log_dividends 
                               WHERE dividend_total_sek > 0";
            
            $stmt = $this->portfolioDb->prepare($companyCountSql);
            $stmt->execute();
            $companyResult = $stmt->fetch();
            $uniqueCompanies = (int) ($companyResult['unique_companies'] ?? 0);
            
            // Calculate estimated portfolio value based on dividend yield assumptions
            // This is a rough estimate until holdings data is available
            $estimatedYield = 4.5; // Assume 4.5% average yield
            $estimatedPortfolioValue = $dividendStats['current_annual_run_rate'] > 0 
                ? ($dividendStats['current_annual_run_rate'] / $estimatedYield) * 100 
                : 0;
            
            // Mock daily change for now (would come from portfolio value tracking)
            $dailyChangePercent = (rand(-150, 150) / 100); // -1.5% to +1.5%
            $dailyChange = $estimatedPortfolioValue * ($dailyChangePercent / 100);
            
            // Calculate expected monthly income from annual run rate
            $expectedMonthlyIncome = $dividendStats['current_annual_run_rate'] / 12;
            
            // Estimate current yield
            $currentYield = $estimatedPortfolioValue > 0 
                ? ($dividendStats['current_annual_run_rate'] / $estimatedPortfolioValue) * 100 
                : 0;
            
            $result = [
                'total_value' => $estimatedPortfolioValue,
                'daily_change' => $dailyChange,
                'daily_change_percent' => $dailyChangePercent,
                'total_dividends_ytd' => $dividendStats['ytd_total'],
                'total_dividends_all_time' => $dividendStats['all_time_total'],
                'current_yield' => $currentYield,
                'expected_monthly_income' => $expectedMonthlyIncome,
                'total_holdings' => $dividendStats['ytd_count'], // Use YTD dividend count as proxy
                'total_companies' => $uniqueCompanies,
                'dividend_payments_ytd' => $dividendStats['ytd_count'],
                'dividend_payments_all_time' => $dividendStats['all_time_count']
            ];
            
            Logger::debug('Calculated portfolio summary from dividend data', [
                'estimated_value' => $estimatedPortfolioValue,
                'ytd_dividends' => $dividendStats['ytd_total'],
                'unique_companies' => $uniqueCompanies
            ]);
            
            return $result;
            
        } catch (Exception $e) {
            Logger::error('Portfolio summary error: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Get portfolio allocation by different categories
     * @param int|null $userId User ID
     * @param bool $isAdmin Is user admin
     * @return array Allocation data
     */
    public function getAllocationData($userId, $isAdmin) {
        try {
            // Use dividend data to approximate allocation until holdings are implemented
            
            // Get allocation by country based on dividend payments
            $countryAllocation = $this->getAllocationByCountry($userId, $isAdmin);
            
            // Get allocation by currency (proxy for some geographic distribution)
            $currencyAllocation = $this->getAllocationByCurrency($userId, $isAdmin);
            
            // Mock sector data (would need sector information in masterlist or separate table)
            $sectorAllocation = [
                ['name' => 'Financial Services', 'value' => 0, 'percentage' => 0],
                ['name' => 'Real Estate', 'value' => 0, 'percentage' => 0],
                ['name' => 'Utilities', 'value' => 0, 'percentage' => 0],
                ['name' => 'Energy', 'value' => 0, 'percentage' => 0],
                ['name' => 'Consumer Staples', 'value' => 0, 'percentage' => 0],
                ['name' => 'Healthcare', 'value' => 0, 'percentage' => 0],
                ['name' => 'Other', 'value' => 0, 'percentage' => 0]
            ];
            
            // Asset class allocation (would need share_type and asset class mapping)
            $assetClassAllocation = [
                ['name' => 'Common Stock', 'value' => 0, 'percentage' => 0],
                ['name' => 'REITs', 'value' => 0, 'percentage' => 0],
                ['name' => 'BDCs', 'value' => 0, 'percentage' => 0],
                ['name' => 'ETFs', 'value' => 0, 'percentage' => 0]
            ];
            
            return [
                'by_sector' => $sectorAllocation,
                'by_country' => $countryAllocation,
                'by_asset_class' => $assetClassAllocation
            ];
            
        } catch (Exception $e) {
            Logger::error('Allocation data error: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Get allocation by country based on dividend payments
     * @param int|null $userId User ID
     * @param bool $isAdmin Is user admin
     * @return array Country allocation
     */
    private function getAllocationByCountry($userId, $isAdmin) {
        try {
            // Simplified query to avoid cross-database join issues
            $sql = "SELECT 
                        'Sweden' as name,
                        COUNT(DISTINCT ld.isin) as company_count,
                        SUM(ld.dividend_total_sek) as total_dividends,
                        AVG(ld.dividend_total_sek) as avg_dividend
                    FROM log_dividends ld
                    WHERE ld.dividend_total_sek > 0
                    AND YEAR(ld.ex_date) = YEAR(CURDATE())";
            
            // TODO: Add user filtering when implemented
            
            $sql .= " GROUP BY 'Sweden'
                     ORDER BY total_dividends DESC";
            
            $stmt = $this->portfolioDb->prepare($sql);
            $stmt->execute();
            $results = $stmt->fetchAll();
            
            // Calculate percentages
            $totalDividends = array_sum(array_column($results, 'total_dividends'));
            $allocation = [];
            
            foreach ($results as $result) {
                $dividends = (float) $result['total_dividends'];
                $percentage = $totalDividends > 0 ? ($dividends / $totalDividends) * 100 : 0;
                
                $allocation[] = [
                    'name' => $result['name'] ?? 'Unknown',
                    'value' => $dividends,
                    'percentage' => round($percentage, 1),
                    'company_count' => (int) $result['company_count']
                ];
            }
            
            Logger::debug('Calculated country allocation from dividend data', [
                'countries' => count($allocation),
                'total_dividends' => $totalDividends
            ]);
            
            return $allocation;
            
        } catch (Exception $e) {
            Logger::error('Country allocation error: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get allocation by currency based on dividend payments
     * @param int|null $userId User ID
     * @param bool $isAdmin Is user admin
     * @return array Currency allocation
     */
    private function getAllocationByCurrency($userId, $isAdmin) {
        try {
            // Simplified query to avoid missing column errors
            $sql = "SELECT 
                        'SEK' as currency,
                        COUNT(DISTINCT ld.isin) as company_count,
                        SUM(ld.dividend_total_sek) as total_dividends_sek,
                        SUM(ld.dividend_total_sek) as total_dividends_original
                    FROM log_dividends ld
                    WHERE ld.dividend_total_sek > 0
                    AND YEAR(ld.ex_date) = YEAR(CURDATE())";
            
            // TODO: Add user filtering when implemented
            
            $sql .= " GROUP BY 'SEK'
                     ORDER BY total_dividends_sek DESC";
            
            $stmt = $this->portfolioDb->prepare($sql);
            $stmt->execute();
            $results = $stmt->fetchAll();
            
            // Calculate percentages
            $totalDividends = array_sum(array_column($results, 'total_dividends_sek'));
            $allocation = [];
            
            foreach ($results as $result) {
                $dividends = (float) $result['total_dividends_sek'];
                $percentage = $totalDividends > 0 ? ($dividends / $totalDividends) * 100 : 0;
                
                $allocation[] = [
                    'name' => $result['currency'] ?? 'Unknown',
                    'value' => $dividends,
                    'percentage' => round($percentage, 1),
                    'company_count' => (int) $result['company_count']
                ];
            }
            
            return $allocation;
            
        } catch (Exception $e) {
            Logger::error('Currency allocation error: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get performance data for charts
     * @param int|null $userId User ID
     * @param bool $isAdmin Is user admin
     * @return array Performance data
     */
    public function getPerformanceData($userId, $isAdmin) {
        try {
            // Mock performance data - replace with actual historical data
            $portfolioHistory = [];
            $dividendHistory = [];
            
            // Generate sample data for the last 12 months
            $baseValue = 2400000;
            $baseMonthlyDividend = 8000;
            
            for ($i = 11; $i >= 0; $i--) {
                $date = date('Y-m', strtotime("-$i months"));
                $variation = (rand(-50, 50) / 1000) * $baseValue;
                
                $portfolioHistory[] = [
                    'date' => $date,
                    'value' => round($baseValue + $variation, 2)
                ];
                
                $dividendVariation = rand(-500, 1500);
                $dividendHistory[] = [
                    'date' => $date,
                    'amount' => round($baseMonthlyDividend + $dividendVariation, 2)
                ];
            }
            
            return [
                'portfolio_value_history' => $portfolioHistory,
                'dividend_income_history' => $dividendHistory
            ];
            
        } catch (Exception $e) {
            Logger::error('Performance data error: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Get best performing stock
     * @param int|null $userId User ID
     * @param bool $isAdmin Is user admin
     * @return array|null Best performer
     */
    public function getBestPerformer($userId, $isAdmin) {
        try {
            // Mock data - replace with actual query
            return [
                'symbol' => 'AAPL',
                'name' => 'Apple Inc.',
                'gain_percent' => 23.45,
                'gain_amount' => 12456.78
            ];
            
        } catch (Exception $e) {
            Logger::error('Best performer error: ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get largest holding
     * @param int|null $userId User ID
     * @param bool $isAdmin Is user admin
     * @return array|null Largest holding
     */
    public function getLargestHolding($userId, $isAdmin) {
        try {
            // Mock data - replace with actual query
            return [
                'symbol' => 'MSFT',
                'name' => 'Microsoft Corporation',
                'value' => 156789.34,
                'percentage' => 6.15
            ];
            
        } catch (Exception $e) {
            Logger::error('Largest holding error: ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get portfolio holdings list
     * @param int|null $userId User ID
     * @param bool $isAdmin Is user admin
     * @param int $limit Number of holdings to return
     * @return array Holdings list
     */
    public function getHoldings($userId, $isAdmin, $limit = 50) {
        try {
            // This would query the actual portfolio tables
            // For now, return empty array
            return [];
            
        } catch (Exception $e) {
            Logger::error('Holdings error: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get top dividend paying stocks
     * @param int|null $userId User ID
     * @param bool $isAdmin Is user admin
     * @param int $limit Number of top payers to return
     * @return array Top dividend payers
     */
    public function getTopDividendPayers($userId, $isAdmin, $limit = 10) {
        try {
            // Mock data for top dividend payers
            return [
                ['symbol' => 'JNJ', 'name' => 'Johnson & Johnson', 'annual_dividends' => 4567.89, 'yield' => 2.8],
                ['symbol' => 'PG', 'name' => 'Procter & Gamble', 'annual_dividends' => 3456.78, 'yield' => 2.5],
                ['symbol' => 'KO', 'name' => 'Coca-Cola', 'annual_dividends' => 3234.56, 'yield' => 3.1],
                ['symbol' => 'PEP', 'name' => 'PepsiCo Inc.', 'annual_dividends' => 2987.45, 'yield' => 2.7],
                ['symbol' => 'MCD', 'name' => 'McDonalds Corp.', 'annual_dividends' => 2678.90, 'yield' => 2.3]
            ];
            
        } catch (Exception $e) {
            Logger::error('Top dividend payers error: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get portfolio statistics for admin overview
     * @return array Portfolio statistics
     */
    public function getPortfolioStatistics() {
        try {
            // This would aggregate data across all users for admin view
            return [
                'total_portfolios' => 1,
                'total_value_all_portfolios' => 2547863.45,
                'total_dividend_income_ytd' => 89234.56,
                'average_portfolio_yield' => 4.23,
                'most_popular_stock' => 'MSFT'
            ];
            
        } catch (Exception $e) {
            Logger::error('Portfolio statistics error: ' . $e->getMessage());
            return [];
        }
    }
}