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
            // For now, return mock data since we need to understand the exact table structure
            // TODO: Replace with actual database queries once table relationships are confirmed
            
            return [
                'total_value' => 2547863.45,
                'daily_change' => 12456.78,
                'daily_change_percent' => 0.49,
                'total_dividends_ytd' => 89234.56,
                'total_dividends_all_time' => 456789.12,
                'current_yield' => 4.23,
                'expected_monthly_income' => 8567.34,
                'total_holdings' => 127,
                'total_companies' => 89
            ];
            
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
            // Mock data for dashboard - replace with actual queries
            return [
                'by_sector' => [
                    ['name' => 'Financial Services', 'value' => 645123.45, 'percentage' => 25.3],
                    ['name' => 'Real Estate', 'value' => 534567.89, 'percentage' => 21.0],
                    ['name' => 'Utilities', 'value' => 382345.67, 'percentage' => 15.0],
                    ['name' => 'Energy', 'value' => 318901.23, 'percentage' => 12.5],
                    ['name' => 'Consumer Staples', 'value' => 254786.34, 'percentage' => 10.0],
                    ['name' => 'Healthcare', 'value' => 203829.56, 'percentage' => 8.0],
                    ['name' => 'Other', 'value' => 208309.31, 'percentage' => 8.2]
                ],
                'by_country' => [
                    ['name' => 'Sweden', 'value' => 1273931.73, 'percentage' => 50.0],
                    ['name' => 'United States', 'value' => 636965.86, 'percentage' => 25.0],
                    ['name' => 'Norway', 'value' => 254786.34, 'percentage' => 10.0],
                    ['name' => 'Denmark', 'value' => 127393.17, 'percentage' => 5.0],
                    ['name' => 'United Kingdom', 'value' => 127393.17, 'percentage' => 5.0],
                    ['name' => 'Other', 'value' => 127393.18, 'percentage' => 5.0]
                ],
                'by_asset_class' => [
                    ['name' => 'Common Stock', 'value' => 1783504.42, 'percentage' => 70.0],
                    ['name' => 'REITs', 'value' => 382345.67, 'percentage' => 15.0],
                    ['name' => 'BDCs', 'value' => 254786.34, 'percentage' => 10.0],
                    ['name' => 'ETFs', 'value' => 127393.17, 'percentage' => 5.0]
                ]
            ];
            
        } catch (Exception $e) {
            Logger::error('Allocation data error: ' . $e->getMessage());
            throw $e;
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