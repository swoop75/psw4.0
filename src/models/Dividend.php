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
            // Query log_dividends table - get company info separately to avoid cross-database issues
            $sql = "SELECT 
                        ld.payment_date as date,
                        ld.payment_date as pay_date,
                        ld.isin,
                        ld.shares_held as shares,
                        ld.dividend_amount_local / ld.shares_held as dividend_per_share,
                        ld.dividend_amount_local as total_amount,
                        ld.dividend_amount_sek as sek_amount,
                        ld.tax_rate_percent as withholding_tax_percent,
                        ld.tax_amount_sek as withholding_tax_sek
                    FROM log_dividends ld
                    WHERE ld.dividend_amount_sek > 0";
            
            // Add user filtering if not admin (when user system is implemented)
            if (!$isAdmin && $userId) {
                // TODO: Add user filtering when portfolio holdings are implemented
                // $sql .= " AND ld.user_id = :user_id";
            }
            
            $sql .= " ORDER BY ld.payment_date DESC LIMIT :limit";
            
            $stmt = $this->portfolioDb->prepare($sql);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            
            // TODO: Bind user_id when user filtering is implemented
            // if (!$isAdmin && $userId) {
            //     $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            // }
            
            $stmt->execute();
            $dividends = $stmt->fetchAll();
            
            // Process the results and get company information from foundation database
            $processedDividends = [];
            foreach ($dividends as $dividend) {
                // Get company info from foundation database
                $companyInfo = $this->getCompanyInfo($dividend['isin']);
                
                $processedDividends[] = [
                    'date' => $dividend['date'],
                    'pay_date' => $dividend['pay_date'],
                    'company' => $companyInfo['name'] ?? 'Unknown Company',
                    'symbol' => $companyInfo['ticker'] ?? $dividend['isin'],
                    'isin' => $dividend['isin'],
                    'shares' => (int) $dividend['shares'],
                    'dividend_per_share' => (float) $dividend['dividend_per_share'],
                    'total_amount' => (float) $dividend['total_amount'],
                    'currency' => $companyInfo['currency'] ?? 'SEK',
                    'sek_amount' => (float) $dividend['sek_amount'],
                    'withholding_tax_percent' => (float) $dividend['withholding_tax_percent'],
                    'withholding_tax_sek' => (float) $dividend['withholding_tax_sek']
                ];
            }
            
            Logger::debug('Retrieved recent dividends from database', [
                'count' => count($processedDividends),
                'limit' => $limit,
                'user_id' => $userId,
                'is_admin' => $isAdmin
            ]);
            
            return $processedDividends;
            
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
                    'payment_date' => '2025-07-25',
                    'pay_date' => '2025-08-15',
                    'company' => 'Apple Inc.',
                    'symbol' => 'AAPL',
                    'shares' => 100,
                    'estimated_dividend' => 0.25,
                    'estimated_total' => 25.00,
                    'currency' => 'USD'
                ],
                [
                    'payment_date' => '2025-07-28',
                    'pay_date' => '2025-08-20',
                    'company' => 'Procter & Gamble',
                    'symbol' => 'PG',
                    'shares' => 75,
                    'estimated_dividend' => 0.96,
                    'estimated_total' => 72.00,
                    'currency' => 'USD'
                ],
                [
                    'payment_date' => '2025-08-02',
                    'pay_date' => '2025-08-25',
                    'company' => 'Hennes & Mauritz AB',
                    'symbol' => 'HM-B.ST',
                    'shares' => 300,
                    'estimated_dividend' => 1.50,
                    'estimated_total' => 450.00,
                    'currency' => 'SEK'
                ],
                [
                    'payment_date' => '2025-08-05',
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
            $currentYear = date('Y');
            
            // YTD statistics
            $ytdSql = "SELECT 
                        COUNT(*) as ytd_count,
                        SUM(dividend_amount_sek) as ytd_total
                    FROM log_dividends 
                    WHERE YEAR(payment_date) = :current_year 
                    AND dividend_amount_sek > 0";
            
            // All-time statistics  
            $allTimeSql = "SELECT 
                            COUNT(*) as all_time_count,
                            SUM(dividend_amount_sek) as all_time_total
                        FROM log_dividends 
                        WHERE dividend_amount_sek > 0";
            
            // Monthly statistics for current year
            $monthlySql = "SELECT 
                            MONTH(payment_date) as month,
                            SUM(dividend_amount_sek) as monthly_total
                        FROM log_dividends 
                        WHERE YEAR(payment_date) = :current_year 
                        AND dividend_amount_sek > 0
                        GROUP BY MONTH(payment_date)
                        ORDER BY monthly_total DESC";
            
            // TODO: Add user filtering when portfolio system is implemented
            
            // Execute YTD query
            $ytdStmt = $this->portfolioDb->prepare($ytdSql);
            $ytdStmt->bindValue(':current_year', $currentYear, PDO::PARAM_INT);
            $ytdStmt->execute();
            $ytdResult = $ytdStmt->fetch();
            
            // Execute all-time query
            $allTimeStmt = $this->portfolioDb->prepare($allTimeSql);
            $allTimeStmt->execute();
            $allTimeResult = $allTimeStmt->fetch();
            
            // Execute monthly query
            $monthlyStmt = $this->portfolioDb->prepare($monthlySql);
            $monthlyStmt->bindValue(':current_year', $currentYear, PDO::PARAM_INT);
            $monthlyStmt->execute();
            $monthlyResults = $monthlyStmt->fetchAll();
            
            // Calculate averages and metrics
            $ytdTotal = (float) ($ytdResult['ytd_total'] ?? 0);
            $ytdCount = (int) ($ytdResult['ytd_count'] ?? 0);
            $allTimeTotal = (float) ($allTimeResult['all_time_total'] ?? 0);
            $allTimeCount = (int) ($allTimeResult['all_time_count'] ?? 0);
            
            // Calculate monthly average (current year)
            $currentMonth = (int) date('n');
            $averageMonthly = $currentMonth > 0 ? $ytdTotal / $currentMonth : 0;
            
            // Find highest monthly amount
            $highestMonthly = 0;
            if (!empty($monthlyResults)) {
                $highestMonthly = (float) $monthlyResults[0]['monthly_total'];
            }
            
            // Calculate annual run rate based on YTD performance
            $currentDayOfYear = (int) date('z') + 1;
            $daysInYear = date('L') ? 366 : 365;
            $annualRunRate = $currentDayOfYear > 0 ? ($ytdTotal / $currentDayOfYear) * $daysInYear : 0;
            
            Logger::debug('Calculated dividend statistics', [
                'ytd_total' => $ytdTotal,
                'ytd_count' => $ytdCount,
                'all_time_total' => $allTimeTotal,
                'monthly_records' => count($monthlyResults)
            ]);
            
            return [
                'ytd_total' => $ytdTotal,
                'ytd_count' => $ytdCount,
                'all_time_total' => $allTimeTotal,
                'all_time_count' => $allTimeCount,
                'average_monthly' => $averageMonthly,
                'highest_monthly' => $highestMonthly,
                'current_annual_run_rate' => $annualRunRate
            ];
            
        } catch (Exception $e) {
            Logger::error('Dividend statistics error: ' . $e->getMessage());
            return [
                'ytd_total' => 0,
                'ytd_count' => 0,
                'all_time_total' => 0,
                'all_time_count' => 0,
                'average_monthly' => 0,
                'highest_monthly' => 0,
                'current_annual_run_rate' => 0
            ];
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
    
    /**
     * Get company information from foundation database
     * @param string $isin ISIN code
     * @return array Company info
     */
    private function getCompanyInfo($isin) {
        try {
            if (empty($isin)) {
                return ['name' => 'Unknown Company', 'ticker' => 'N/A', 'currency' => 'SEK'];
            }
            
            $sql = "SELECT name, ticker, current_version FROM masterlist WHERE isin = :isin LIMIT 1";
            $stmt = $this->foundationDb->prepare($sql);
            $stmt->bindValue(':isin', $isin);
            $stmt->execute();
            $company = $stmt->fetch();
            
            if ($company) {
                return [
                    'name' => $company['name'],
                    'ticker' => $company['ticker'],
                    'currency' => 'SEK' // Default currency, can be enhanced later
                ];
            } else {
                return ['name' => 'Unknown Company', 'ticker' => 'N/A', 'currency' => 'SEK'];
            }
            
        } catch (Exception $e) {
            Logger::error('Get company info error: ' . $e->getMessage());
            return ['name' => 'Unknown Company', 'ticker' => 'N/A', 'currency' => 'SEK'];
        }
    }
}