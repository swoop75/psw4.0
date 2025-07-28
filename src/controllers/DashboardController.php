<?php
/**
 * File: src/controllers/DashboardController.php
 * Path: C:\Users\laoan\Documents\GitHub\psw\psw4.0\src\controllers\DashboardController.php
 * Description: Dashboard controller for PSW 4.0 - handles dashboard data and logic
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../models/Portfolio.php';
require_once __DIR__ . '/../models/Dividend.php';
require_once __DIR__ . '/../models/Company.php';
require_once __DIR__ . '/../middleware/Auth.php';
require_once __DIR__ . '/../utils/Logger.php';

class DashboardController {
    private $portfolioModel;
    private $dividendModel;
    private $companyModel;
    
    public function __construct() {
        $this->portfolioModel = new Portfolio();
        $this->dividendModel = new Dividend();
        $this->companyModel = new Company();
    }
    
    /**
     * Get all dashboard data
     * @return array Dashboard data array
     */
    public function getDashboardData() {
        try {
            $userId = Auth::getUserId();
            $isAdmin = Auth::isAdmin();
            
            $dashboardData = [
                'portfolio_metrics' => $this->getPortfolioMetrics($userId, $isAdmin),
                'recent_dividends' => $this->getRecentDividends($userId, $isAdmin),
                'upcoming_dividends' => $this->getUpcomingDividends($userId, $isAdmin),
                'allocation_data' => $this->getAllocationData($userId, $isAdmin),
                'performance_data' => $this->getPerformanceData($userId, $isAdmin),
                'news_feed' => $this->getNewsFeed($isAdmin),
                'quick_stats' => $this->getQuickStats($userId, $isAdmin),
                'dividend_stats' => $this->getDividendStatistics($userId, $isAdmin)
            ];
            
            return $dashboardData;
            
        } catch (Exception $e) {
            Logger::error('Dashboard data retrieval error: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Get portfolio key metrics
     * @param int|null $userId User ID
     * @param bool $isAdmin Is user admin
     * @return array Portfolio metrics
     */
    private function getPortfolioMetrics($userId, $isAdmin) {
        try {
            $metrics = $this->portfolioModel->getPortfolioSummary($userId, $isAdmin);
            
            return [
                'total_value' => $metrics['total_value'] ?? 0,
                'daily_change' => $metrics['daily_change'] ?? 0,
                'daily_change_percent' => $metrics['daily_change_percent'] ?? 0,
                'total_dividends_ytd' => $metrics['total_dividends_ytd'] ?? 0,
                'total_dividends_all_time' => $metrics['total_dividends_all_time'] ?? 0,
                'current_yield' => $metrics['current_yield'] ?? 0,
                'expected_monthly_income' => $metrics['expected_monthly_income'] ?? 0,
                'total_holdings' => $metrics['total_holdings'] ?? 0,
                'total_companies' => $metrics['total_companies'] ?? 0
            ];
            
        } catch (Exception $e) {
            Logger::error('Portfolio metrics error: ' . $e->getMessage());
            return $this->getEmptyPortfolioMetrics();
        }
    }
    
    /**
     * Get recent dividend payments
     * @param int|null $userId User ID
     * @param bool $isAdmin Is user admin
     * @return array Recent dividends
     */
    private function getRecentDividends($userId, $isAdmin) {
        try {
            return $this->dividendModel->getRecentDividends($userId, $isAdmin, 10);
        } catch (Exception $e) {
            Logger::error('Recent dividends error: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get upcoming ex-dividend dates
     * @param int|null $userId User ID
     * @param bool $isAdmin Is user admin
     * @return array Upcoming dividends
     */
    private function getUpcomingDividends($userId, $isAdmin) {
        try {
            return $this->dividendModel->getUpcomingDividends($userId, $isAdmin, 10);
        } catch (Exception $e) {
            Logger::error('Upcoming dividends error: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get portfolio allocation data
     * @param int|null $userId User ID
     * @param bool $isAdmin Is user admin
     * @return array Allocation data
     */
    private function getAllocationData($userId, $isAdmin) {
        try {
            return $this->portfolioModel->getAllocationData($userId, $isAdmin);
        } catch (Exception $e) {
            Logger::error('Allocation data error: ' . $e->getMessage());
            return [
                'by_sector' => [],
                'by_country' => [],
                'by_asset_class' => []
            ];
        }
    }
    
    /**
     * Get performance data for charts
     * @param int|null $userId User ID
     * @param bool $isAdmin Is user admin
     * @return array Performance data
     */
    private function getPerformanceData($userId, $isAdmin) {
        try {
            return $this->portfolioModel->getPerformanceData($userId, $isAdmin);
        } catch (Exception $e) {
            Logger::error('Performance data error: ' . $e->getMessage());
            return [
                'portfolio_value_history' => [],
                'dividend_income_history' => []
            ];
        }
    }
    
    /**
     * Get news feed for dashboard
     * @param bool $isAdmin Is user admin
     * @return array News items
     */
    private function getNewsFeed($isAdmin) {
        try {
            // TODO: Implement news feed from API integrations
            return [
                [
                    'title' => 'Welcome to PSW 4.0',
                    'content' => 'Your dividend portfolio management system is ready to use.',
                    'date' => date('Y-m-d H:i:s'),
                    'type' => 'system'
                ]
            ];
        } catch (Exception $e) {
            Logger::error('News feed error: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get quick statistics for dashboard widgets
     * @param int|null $userId User ID
     * @param bool $isAdmin Is user admin
     * @return array Quick stats
     */
    private function getQuickStats($userId, $isAdmin) {
        try {
            return [
                'dividend_streak_months' => $this->dividendModel->getDividendStreak($userId, $isAdmin),
                'best_performing_stock' => $this->portfolioModel->getBestPerformer($userId, $isAdmin),
                'largest_holding' => $this->portfolioModel->getLargestHolding($userId, $isAdmin),
                'next_ex_div_date' => $this->dividendModel->getNextExDivDate($userId, $isAdmin)
            ];
        } catch (Exception $e) {
            Logger::error('Quick stats error: ' . $e->getMessage());
            return [
                'dividend_streak_months' => 0,
                'best_performing_stock' => null,
                'largest_holding' => null,
                'next_ex_div_date' => null
            ];
        }
    }
    
    /**
     * Get empty portfolio metrics for error handling
     * @return array Empty metrics
     */
    private function getEmptyPortfolioMetrics() {
        return [
            'total_value' => 0,
            'daily_change' => 0,
            'daily_change_percent' => 0,
            'total_dividends_ytd' => 0,
            'total_dividends_all_time' => 0,
            'current_yield' => 0,
            'expected_monthly_income' => 0,
            'total_holdings' => 0,
            'total_companies' => 0
        ];
    }
    
    /**
     * Get comprehensive dividend statistics for dashboard
     * @param int|null $userId User ID
     * @param bool $isAdmin Is user admin
     * @return array Dividend statistics
     */
    private function getDividendStatistics($userId, $isAdmin) {
        try {
            $db = Database::getConnection('portfolio');
            
            // Get top 5 paying companies (by total dividends received)
            $topPayersQuery = "
                SELECT 
                    ld.isin,
                    ml.name as company_name,
                    COUNT(*) as payment_count,
                    SUM(ld.net_dividend_sek) as total_dividends,
                    AVG(ld.net_dividend_sek) as avg_dividend
                FROM psw_portfolio.log_dividends ld
                LEFT JOIN psw_foundation.masterlist ml ON ld.isin COLLATE utf8mb4_unicode_ci = ml.isin COLLATE utf8mb4_unicode_ci
                WHERE ld.payment_date >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)
                GROUP BY ld.isin, ml.name
                ORDER BY total_dividends DESC
                LIMIT 5
            ";
            
            $topPayers = $db->query($topPayersQuery)->fetchAll(PDO::FETCH_ASSOC);
            
            // Get best dividend payment days (by day of week and amount)
            $bestDaysQuery = "
                SELECT 
                    DAYNAME(ld.payment_date) as day_name,
                    DAYOFWEEK(ld.payment_date) as day_number,
                    COUNT(*) as payment_count,
                    SUM(ld.net_dividend_sek) as total_amount,
                    AVG(ld.net_dividend_sek) as avg_amount
                FROM psw_portfolio.log_dividends ld
                WHERE ld.payment_date >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)
                GROUP BY DAYNAME(ld.payment_date), DAYOFWEEK(ld.payment_date)
                ORDER BY total_amount DESC
                LIMIT 7
            ";
            
            $bestDays = $db->query($bestDaysQuery)->fetchAll(PDO::FETCH_ASSOC);
            
            // Get monthly dividend trends for the chart (last 12 months)
            $monthlyTrendsQuery = "
                SELECT 
                    DATE_FORMAT(ld.payment_date, '%Y-%m') as month,
                    DATE_FORMAT(ld.payment_date, '%M %Y') as month_name,
                    COUNT(*) as payment_count,
                    SUM(ld.net_dividend_sek) as total_amount,
                    COUNT(DISTINCT ld.isin) as unique_companies
                FROM psw_portfolio.log_dividends ld
                WHERE ld.payment_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
                GROUP BY DATE_FORMAT(ld.payment_date, '%Y-%m')
                ORDER BY month ASC
            ";
            
            $monthlyTrends = $db->query($monthlyTrendsQuery)->fetchAll(PDO::FETCH_ASSOC);
            
            // Get additional insights
            $insightsQuery = "
                SELECT 
                    COUNT(*) as total_payments,
                    COUNT(DISTINCT ld.isin) as total_companies,
                    SUM(ld.net_dividend_sek) as total_amount_year,
                    AVG(ld.net_dividend_sek) as avg_payment,
                    MAX(ld.net_dividend_sek) as largest_payment,
                    MIN(ld.net_dividend_sek) as smallest_payment
                FROM psw_portfolio.log_dividends ld
                WHERE ld.payment_date >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)
            ";
            
            $insights = $db->query($insightsQuery)->fetch(PDO::FETCH_ASSOC);
            
            return [
                'top_paying_companies' => $topPayers,
                'best_payment_days' => $bestDays,
                'monthly_trends' => $monthlyTrends,
                'insights' => $insights
            ];
            
        } catch (Exception $e) {
            Logger::error('Dividend statistics error: ' . $e->getMessage());
            return [
                'top_paying_companies' => [],
                'best_payment_days' => [],
                'monthly_trends' => [],
                'insights' => [
                    'total_payments' => 0,
                    'total_companies' => 0,
                    'total_amount_year' => 0,
                    'avg_payment' => 0,
                    'largest_payment' => 0,
                    'smallest_payment' => 0
                ]
            ];
        }
    }
    
    /**
     * Get dashboard data for API (JSON response)
     * @return array JSON-formatted dashboard data
     */
    public function getDashboardDataForAPI() {
        try {
            $data = $this->getDashboardData();
            
            return [
                'success' => true,
                'data' => $data,
                'timestamp' => time()
            ];
            
        } catch (Exception $e) {
            Logger::error('Dashboard API error: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to load dashboard data',
                'timestamp' => time()
            ];
        }
    }
}