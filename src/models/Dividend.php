<?php
/**
 * File: src/models/Dividend.php
 * Path: C:\Users\laoan\Documents\GitHub\psw\psw4.0\src\models\Dividend.php
 * Description: Dividend model for PSW 4.0 - handles dividend-related data operations
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../utils/Logger.php';

class Dividend {
    private $foundationDb;
    private $portfolioDb;
    
    public function __construct() {
        $this->foundationDb = Database::getConnection('foundation');
        $this->portfolioDb = Database::getConnection('portfolio');
    }
    
    /**
     * Get recent dividend payments
     * @param int|null $userId User ID
     * @param bool $isAdmin Is user admin
     * @param int $limit Number of recent dividends to return
     * @return array Recent dividends
     */
    public function getRecentDividends($userId, $isAdmin, $limit = 10) {
        try {
            // Mock data for recent dividends - replace with actual query to log_dividends
            return [
                [
                    'date' => '2025-07-15',
                    'company' => 'Microsoft Corporation',
                    'symbol' => 'MSFT',
                    'shares' => 125,
                    'dividend_per_share' => 0.75,
                    'total_amount' => 93.75,
                    'currency' => 'USD',
                    'sek_amount' => 1034.25
                ],
                [
                    'date' => '2025-07-12',
                    'company' => 'Johnson & Johnson',
                    'symbol' => 'JNJ',
                    'shares' => 200,
                    'dividend_per_share' => 1.19,
                    'total_amount' => 238.00,
                    'currency' => 'USD',
                    'sek_amount' => 2627.40
                ],
                [
                    'date' => '2025-07-10',
                    'company' => 'Volvo AB',
                    'symbol' => 'VOLV-B.ST',
                    'shares' => 500,
                    'dividend_per_share' => 2.50,
                    'total_amount' => 1250.00,
                    'currency' => 'SEK',
                    'sek_amount' => 1250.00
                ],
                [
                    'date' => '2025-07-08',
                    'company' => 'Coca-Cola Company',
                    'symbol' => 'KO',
                    'shares' => 150,
                    'dividend_per_share' => 0.46,
                    'total_amount' => 69.00,
                    'currency' => 'USD',
                    'sek_amount' => 761.70
                ],
                [
                    'date' => '2025-07-05',
                    'company' => 'Ericsson AB',
                    'symbol' => 'ERIC-B.ST',
                    'shares' => 800,
                    'dividend_per_share' => 1.00,
                    'total_amount' => 800.00,
                    'currency' => 'SEK',
                    'sek_amount' => 800.00
                ]
            ];
            
        } catch (Exception $e) {
            Logger::error('Recent dividends error: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get upcoming ex-dividend dates
     * @param int|null $userId User ID
     * @param bool $isAdmin Is user admin
     * @param int $limit Number of upcoming dividends to return
     * @return array Upcoming dividends
     */
    public function getUpcomingDividends($userId, $isAdmin, $limit = 10) {
        try {
            // Mock data for upcoming ex-dividend dates
            return [
                [
                    'ex_date' => '2025-07-25',
                    'pay_date' => '2025-08-15',
                    'company' => 'Apple Inc.',
                    'symbol' => 'AAPL',
                    'shares' => 100,
                    'estimated_dividend' => 0.25,
                    'estimated_total' => 25.00,
                    'currency' => 'USD'
                ],
                [
                    'ex_date' => '2025-07-28',
                    'pay_date' => '2025-08-20',
                    'company' => 'Procter & Gamble',
                    'symbol' => 'PG',
                    'shares' => 75,
                    'estimated_dividend' => 0.96,
                    'estimated_total' => 72.00,
                    'currency' => 'USD'
                ],
                [
                    'ex_date' => '2025-08-02',
                    'pay_date' => '2025-08-25',
                    'company' => 'Hennes & Mauritz AB',
                    'symbol' => 'HM-B.ST',
                    'shares' => 300,
                    'estimated_dividend' => 1.50,
                    'estimated_total' => 450.00,
                    'currency' => 'SEK'
                ],
                [
                    'ex_date' => '2025-08-05',
                    'pay_date' => '2025-08-30',
                    'company' => 'PepsiCo Inc.',
                    'symbol' => 'PEP',
                    'shares' => 120,
                    'estimated_dividend' => 1.27,
                    'estimated_total' => 152.40,
                    'currency' => 'USD'
                ]
            ];
            
        } catch (Exception $e) {
            Logger::error('Upcoming dividends error: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get dividend streak in months
     * @param int|null $userId User ID
     * @param bool $isAdmin Is user admin
     * @return int Dividend streak months
     */
    public function getDividendStreak($userId, $isAdmin) {
        try {
            // Mock calculation - replace with actual query
            // This would count consecutive months with dividend payments
            return 24; // 24 consecutive months
            
        } catch (Exception $e) {
            Logger::error('Dividend streak error: ' . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Get next ex-dividend date
     * @param int|null $userId User ID
     * @param bool $isAdmin Is user admin
     * @return string|null Next ex-dividend date
     */
    public function getNextExDivDate($userId, $isAdmin) {
        try {
            // Return the earliest upcoming ex-dividend date
            return '2025-07-25';
            
        } catch (Exception $e) {
            Logger::error('Next ex-div date error: ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Get dividend income by month for chart
     * @param int|null $userId User ID
     * @param bool $isAdmin Is user admin
     * @param int $months Number of months to retrieve
     * @return array Monthly dividend income
     */
    public function getMonthlyDividendIncome($userId, $isAdmin, $months = 12) {
        try {
            // Mock monthly dividend data
            $monthlyData = [];
            $baseAmount = 8000;
            
            for ($i = $months - 1; $i >= 0; $i--) {
                $date = date('Y-m', strtotime("-$i months"));
                $variation = rand(-1000, 2000);
                
                $monthlyData[] = [
                    'month' => $date,
                    'amount' => $baseAmount + $variation,
                    'count' => rand(8, 25) // number of dividend payments
                ];
            }
            
            return $monthlyData;
            
        } catch (Exception $e) {
            Logger::error('Monthly dividend income error: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get dividend income statistics
     * @param int|null $userId User ID
     * @param bool $isAdmin Is user admin
     * @return array Dividend statistics
     */
    public function getDividendStatistics($userId, $isAdmin) {
        try {
            return [
                'ytd_total' => 89234.56,
                'ytd_count' => 156,
                'all_time_total' => 456789.12,
                'all_time_count' => 890,
                'average_monthly' => 8567.34,
                'highest_monthly' => 12456.78,
                'current_annual_run_rate' => 102808.08
            ];
            
        } catch (Exception $e) {
            Logger::error('Dividend statistics error: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get dividend growth analysis
     * @param int|null $userId User ID
     * @param bool $isAdmin Is user admin
     * @return array Dividend growth data
     */
    public function getDividendGrowthAnalysis($userId, $isAdmin) {
        try {
            return [
                'year_over_year_growth' => 8.5, // percentage
                'three_year_cagr' => 6.2,
                'five_year_cagr' => 7.8,
                'companies_increased' => 23,
                'companies_decreased' => 3,
                'companies_flat' => 8
            ];
            
        } catch (Exception $e) {
            Logger::error('Dividend growth analysis error: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get dividend calendar for specific month
     * @param int|null $userId User ID
     * @param bool $isAdmin Is user admin
     * @param string $month Month in Y-m format
     * @return array Dividend calendar
     */
    public function getDividendCalendar($userId, $isAdmin, $month) {
        try {
            // This would return all dividend payments and ex-dates for the specified month
            return [];
            
        } catch (Exception $e) {
            Logger::error('Dividend calendar error: ' . $e->getMessage());
            return [];
        }
    }
}