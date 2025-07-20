<?php
/**
 * File: src/controllers/DividendEstimateController.php
 * Path: C:\Users\laoan\Documents\GitHub\psw\psw4.0\src\controllers\DividendEstimateController.php
 * Description: Dividend estimate controller for PSW 4.0 - handles dividend forecasting and estimates
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../models/Portfolio.php';
require_once __DIR__ . '/../models/Dividend.php';
require_once __DIR__ . '/../models/Company.php';
require_once __DIR__ . '/../middleware/Auth.php';
require_once __DIR__ . '/../utils/Logger.php';

class DividendEstimateController {
    private $portfolioModel;
    private $dividendModel;
    private $companyModel;
    
    public function __construct() {
        $this->portfolioModel = new Portfolio();
        $this->dividendModel = new Dividend();
        $this->companyModel = new Company();
    }
    
    /**
     * Get dividend estimate overview data
     * @return array Overview data
     */
    public function getOverviewData() {
        try {
            $userId = Auth::getUserId();
            $isAdmin = Auth::isAdmin();
            
            $overviewData = [
                'annual_estimates' => $this->getAnnualEstimates($userId, $isAdmin),
                'monthly_breakdown' => $this->getMonthlyBreakdown($userId, $isAdmin),
                'quarterly_summary' => $this->getQuarterlySummary($userId, $isAdmin),
                'upcoming_payments' => $this->getUpcomingPayments($userId, $isAdmin),
                'estimate_accuracy' => $this->getEstimateAccuracy($userId, $isAdmin),
                'dividend_growth_forecast' => $this->getDividendGrowthForecast($userId, $isAdmin),
                'yield_projections' => $this->getYieldProjections($userId, $isAdmin)
            ];
            
            return $overviewData;
            
        } catch (Exception $e) {
            Logger::error('Dividend estimate overview error: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Get annual dividend estimates
     * @param int|null $userId User ID
     * @param bool $isAdmin Is user admin
     * @return array Annual estimates
     */
    private function getAnnualEstimates($userId, $isAdmin) {
        try {
            $stats = $this->dividendModel->getDividendStatistics($userId, $isAdmin);
            
            return [
                'current_year_estimate' => $stats['current_annual_run_rate'],
                'ytd_actual' => $stats['ytd_total'],
                'remaining_estimate' => max(0, $stats['current_annual_run_rate'] - $stats['ytd_total']),
                'previous_year_actual' => $this->getPreviousYearTotal($userId, $isAdmin),
                'growth_estimate' => $this->calculateGrowthEstimate($userId, $isAdmin),
                'confidence_level' => $this->calculateConfidenceLevel($userId, $isAdmin)
            ];
            
        } catch (Exception $e) {
            Logger::error('Annual estimates error: ' . $e->getMessage());
            return [
                'current_year_estimate' => 0,
                'ytd_actual' => 0,
                'remaining_estimate' => 0,
                'previous_year_actual' => 0,
                'growth_estimate' => 0,
                'confidence_level' => 0
            ];
        }
    }
    
    /**
     * Get monthly dividend breakdown
     * @param int|null $userId User ID
     * @param bool $isAdmin Is user admin
     * @return array Monthly breakdown
     */
    private function getMonthlyBreakdown($userId, $isAdmin) {
        try {
            $monthlyData = $this->dividendModel->getMonthlyDividendIncome($userId, $isAdmin, 12);
            
            // Add estimates for future months
            $currentMonth = (int) date('n');
            $currentYear = (int) date('Y');
            
            $estimatedMonthly = [];
            for ($month = 1; $month <= 12; $month++) {
                $monthKey = sprintf('%04d-%02d', $currentYear, $month);
                
                // Find actual data for this month
                $actualData = array_filter($monthlyData, function($item) use ($monthKey) {
                    return $item['month'] === $monthKey;
                });
                
                if (!empty($actualData)) {
                    // Use actual data
                    $monthData = reset($actualData);
                    $estimatedMonthly[] = [
                        'month' => $month,
                        'month_name' => date('F', mktime(0, 0, 0, $month, 1)),
                        'actual_amount' => $monthData['amount'],
                        'estimated_amount' => $monthData['amount'],
                        'payment_count' => $monthData['count'],
                        'is_actual' => true
                    ];
                } else if ($month <= $currentMonth) {
                    // Past month with no data
                    $estimatedMonthly[] = [
                        'month' => $month,
                        'month_name' => date('F', mktime(0, 0, 0, $month, 1)),
                        'actual_amount' => 0,
                        'estimated_amount' => 0,
                        'payment_count' => 0,
                        'is_actual' => true
                    ];
                } else {
                    // Future month - estimate based on historical pattern
                    $historicalAverage = $this->getHistoricalMonthlyAverage($userId, $isAdmin, $month);
                    $estimatedMonthly[] = [
                        'month' => $month,
                        'month_name' => date('F', mktime(0, 0, 0, $month, 1)),
                        'actual_amount' => null,
                        'estimated_amount' => $historicalAverage,
                        'payment_count' => $this->estimateMonthlyPaymentCount($userId, $isAdmin, $month),
                        'is_actual' => false
                    ];
                }
            }
            
            return $estimatedMonthly;
            
        } catch (Exception $e) {
            Logger::error('Monthly breakdown error: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get quarterly summary
     * @param int|null $userId User ID
     * @param bool $isAdmin Is user admin
     * @return array Quarterly data
     */
    private function getQuarterlySummary($userId, $isAdmin) {
        try {
            $monthlyData = $this->getMonthlyBreakdown($userId, $isAdmin);
            $quarters = [
                'Q1' => [1, 2, 3],
                'Q2' => [4, 5, 6],
                'Q3' => [7, 8, 9],
                'Q4' => [10, 11, 12]
            ];
            
            $quarterlySummary = [];
            foreach ($quarters as $quarter => $months) {
                $quarterData = array_filter($monthlyData, function($item) use ($months) {
                    return in_array($item['month'], $months);
                });
                
                $actualTotal = array_sum(array_column($quarterData, 'actual_amount'));
                $estimatedTotal = array_sum(array_column($quarterData, 'estimated_amount'));
                
                $quarterlySummary[] = [
                    'quarter' => $quarter,
                    'actual_amount' => $actualTotal,
                    'estimated_amount' => $estimatedTotal,
                    'payment_count' => array_sum(array_column($quarterData, 'payment_count')),
                    'is_complete' => max($months) <= (int) date('n')
                ];
            }
            
            return $quarterlySummary;
            
        } catch (Exception $e) {
            Logger::error('Quarterly summary error: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get upcoming dividend payments
     * @param int|null $userId User ID
     * @param bool $isAdmin Is user admin
     * @return array Upcoming payments
     */
    private function getUpcomingPayments($userId, $isAdmin) {
        try {
            return $this->dividendModel->getUpcomingDividends($userId, $isAdmin, 20);
        } catch (Exception $e) {
            Logger::error('Upcoming payments error: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get estimate accuracy metrics
     * @param int|null $userId User ID
     * @param bool $isAdmin Is user admin
     * @return array Accuracy metrics
     */
    private function getEstimateAccuracy($userId, $isAdmin) {
        try {
            // This would compare previous estimates vs actual results
            // For now, return mock accuracy data
            return [
                'overall_accuracy' => 87.5, // percentage
                'monthly_accuracy' => 92.1,
                'annual_accuracy' => 89.3,
                'last_updated' => date('Y-m-d H:i:s')
            ];
            
        } catch (Exception $e) {
            Logger::error('Estimate accuracy error: ' . $e->getMessage());
            return [
                'overall_accuracy' => 0,
                'monthly_accuracy' => 0,
                'annual_accuracy' => 0,
                'last_updated' => null
            ];
        }
    }
    
    /**
     * Get dividend growth forecast
     * @param int|null $userId User ID
     * @param bool $isAdmin Is user admin
     * @return array Growth forecast
     */
    private function getDividendGrowthForecast($userId, $isAdmin) {
        try {
            $growthAnalysis = $this->dividendModel->getDividendGrowthAnalysis($userId, $isAdmin);
            
            return [
                'current_growth_rate' => $growthAnalysis['year_over_year_growth'] ?? 0,
                'projected_next_year' => 0, // Would calculate based on company-specific forecasts
                'three_year_projection' => 0,
                'growth_sustainability' => 'Medium', // High/Medium/Low based on analysis
                'risk_factors' => $this->identifyGrowthRiskFactors($userId, $isAdmin)
            ];
            
        } catch (Exception $e) {
            Logger::error('Growth forecast error: ' . $e->getMessage());
            return [
                'current_growth_rate' => 0,
                'projected_next_year' => 0,
                'three_year_projection' => 0,
                'growth_sustainability' => 'Unknown',
                'risk_factors' => []
            ];
        }
    }
    
    /**
     * Get yield projections
     * @param int|null $userId User ID
     * @param bool $isAdmin Is user admin
     * @return array Yield projections
     */
    private function getYieldProjections($userId, $isAdmin) {
        try {
            $portfolioSummary = $this->portfolioModel->getPortfolioSummary($userId, $isAdmin);
            
            return [
                'current_yield' => $portfolioSummary['current_yield'] ?? 0,
                'projected_yield_1y' => 0, // Would calculate based on expected dividend changes
                'projected_yield_3y' => 0,
                'yield_trend' => 'Stable', // Increasing/Stable/Decreasing
                'yield_vs_benchmark' => 0 // Comparison to market benchmarks
            ];
            
        } catch (Exception $e) {
            Logger::error('Yield projections error: ' . $e->getMessage());
            return [
                'current_yield' => 0,
                'projected_yield_1y' => 0,
                'projected_yield_3y' => 0,
                'yield_trend' => 'Unknown',
                'yield_vs_benchmark' => 0
            ];
        }
    }
    
    /**
     * Helper methods
     */
    
    private function getPreviousYearTotal($userId, $isAdmin) {
        // Implementation would query previous year's total
        return 0;
    }
    
    private function calculateGrowthEstimate($userId, $isAdmin) {
        // Implementation would calculate expected growth based on trends
        return 0;
    }
    
    private function calculateConfidenceLevel($userId, $isAdmin) {
        // Implementation would calculate confidence in estimates
        return 85; // percentage
    }
    
    private function getHistoricalMonthlyAverage($userId, $isAdmin, $month) {
        // Implementation would get historical average for specific month
        return 0;
    }
    
    private function estimateMonthlyPaymentCount($userId, $isAdmin, $month) {
        // Implementation would estimate number of payments for month
        return 0;
    }
    
    private function identifyGrowthRiskFactors($userId, $isAdmin) {
        // Implementation would identify potential risks to growth
        return [];
    }
}