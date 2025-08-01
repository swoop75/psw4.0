<?php
/**
 * File: allocation.php
 * Description: Portfolio allocation analysis with geographic, sector, and other breakdowns for PSW 4.0
 */

session_start();

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/src/middleware/Auth.php';
require_once __DIR__ . '/src/utils/Localization.php';

// Require authentication
Auth::requireAuth();

try {
    $portfolioDb = Database::getConnection('portfolio');
    $foundationDb = Database::getConnection('foundation');
    $marketDb = Database::getConnection('marketdata');
    
    // Get geographic allocation (by country/region)
    $geographicSql = "SELECT 
                        CASE 
                            WHEN LEFT(p.isin, 2) = 'SE' THEN 'Sweden'
                            WHEN LEFT(p.isin, 2) = 'US' THEN 'United States'
                            WHEN LEFT(p.isin, 2) = 'DK' THEN 'Denmark'
                            WHEN LEFT(p.isin, 2) = 'NO' THEN 'Norway'
                            WHEN LEFT(p.isin, 2) = 'FI' THEN 'Finland'
                            WHEN LEFT(p.isin, 2) = 'DE' THEN 'Germany'
                            WHEN LEFT(p.isin, 2) = 'FR' THEN 'France'
                            WHEN LEFT(p.isin, 2) = 'GB' THEN 'United Kingdom'
                            WHEN LEFT(p.isin, 2) = 'NL' THEN 'Netherlands'
                            WHEN LEFT(p.isin, 2) = 'CH' THEN 'Switzerland'
                            WHEN LEFT(p.isin, 2) = 'CA' THEN 'Canada'
                            WHEN LEFT(p.isin, 2) = 'AU' THEN 'Australia'
                            WHEN LEFT(p.isin, 2) = 'JP' THEN 'Japan'
                            WHEN LEFT(p.isin, 2) = 'HK' THEN 'Hong Kong'
                            WHEN LEFT(p.isin, 2) = 'SG' THEN 'Singapore'
                            ELSE 'Other'
                        END as country,
                        CASE 
                            WHEN LEFT(p.isin, 2) IN ('SE', 'DK', 'NO', 'FI') THEN 'Nordic'
                            WHEN LEFT(p.isin, 2) IN ('DE', 'FR', 'GB', 'NL', 'CH') THEN 'Europe'
                            WHEN LEFT(p.isin, 2) IN ('US', 'CA') THEN 'North America'
                            WHEN LEFT(p.isin, 2) IN ('AU', 'JP', 'HK', 'SG') THEN 'Asia-Pacific'
                            ELSE 'Other'
                        END as region,
                        COUNT(*) as positions,
                        SUM(COALESCE(p.current_value_sek, 0)) as value_sek,
                        (SUM(COALESCE(p.current_value_sek, 0)) / NULLIF((SELECT SUM(current_value_sek) FROM psw_portfolio.portfolio WHERE is_active = 1 AND shares_held > 0), 0)) * 100 as weight_percent
                      FROM psw_portfolio.portfolio p
                      WHERE p.is_active = 1 AND p.shares_held > 0
                      GROUP BY 
                        CASE 
                            WHEN LEFT(p.isin, 2) = 'SE' THEN 'Sweden'
                            WHEN LEFT(p.isin, 2) = 'US' THEN 'United States'
                            WHEN LEFT(p.isin, 2) = 'DK' THEN 'Denmark'
                            WHEN LEFT(p.isin, 2) = 'NO' THEN 'Norway'
                            WHEN LEFT(p.isin, 2) = 'FI' THEN 'Finland'
                            WHEN LEFT(p.isin, 2) = 'DE' THEN 'Germany'
                            WHEN LEFT(p.isin, 2) = 'FR' THEN 'France'
                            WHEN LEFT(p.isin, 2) = 'GB' THEN 'United Kingdom'
                            WHEN LEFT(p.isin, 2) = 'NL' THEN 'Netherlands'
                            WHEN LEFT(p.isin, 2) = 'CH' THEN 'Switzerland'
                            WHEN LEFT(p.isin, 2) = 'CA' THEN 'Canada'
                            WHEN LEFT(p.isin, 2) = 'AU' THEN 'Australia'
                            WHEN LEFT(p.isin, 2) = 'JP' THEN 'Japan'
                            WHEN LEFT(p.isin, 2) = 'HK' THEN 'Hong Kong'
                            WHEN LEFT(p.isin, 2) = 'SG' THEN 'Singapore'
                            ELSE 'Other'
                        END,
                        CASE 
                            WHEN LEFT(p.isin, 2) IN ('SE', 'DK', 'NO', 'FI') THEN 'Nordic'
                            WHEN LEFT(p.isin, 2) IN ('DE', 'FR', 'GB', 'NL', 'CH') THEN 'Europe'
                            WHEN LEFT(p.isin, 2) IN ('US', 'CA') THEN 'North America'
                            WHEN LEFT(p.isin, 2) IN ('AU', 'JP', 'HK', 'SG') THEN 'Asia-Pacific'
                            ELSE 'Other'
                        END
                      ORDER BY SUM(COALESCE(p.current_value_sek, 0)) DESC";
    
    $geographicStmt = $portfolioDb->prepare($geographicSql);
    $geographicStmt->execute();
    $geographicAllocation = $geographicStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get sector allocation
    $sectorSql = "SELECT 
                    COALESCE(s1.nameEn, s2.nameEn, 'Unknown') as sector,
                    COUNT(*) as positions,
                    SUM(COALESCE(p.current_value_sek, 0)) as value_sek,
                    (SUM(COALESCE(p.current_value_sek, 0)) / NULLIF((SELECT SUM(current_value_sek) FROM psw_portfolio.portfolio WHERE is_active = 1 AND shares_held > 0), 0)) * 100 as weight_percent
                  FROM psw_portfolio.portfolio p
                  LEFT JOIN psw_marketdata.nordic_instruments ni ON p.isin COLLATE utf8mb4_unicode_ci = ni.isin COLLATE utf8mb4_unicode_ci
                  LEFT JOIN psw_marketdata.global_instruments gi ON p.isin COLLATE utf8mb4_unicode_ci = gi.isin COLLATE utf8mb4_unicode_ci
                  LEFT JOIN psw_marketdata.sectors s1 ON ni.sectorID = s1.id
                  LEFT JOIN psw_marketdata.sectors s2 ON gi.sectorId = s2.id
                  WHERE p.is_active = 1 AND p.shares_held > 0
                  GROUP BY COALESCE(s1.nameEn, s2.nameEn, 'Unknown')
                  ORDER BY SUM(COALESCE(p.current_value_sek, 0)) DESC";
    
    $sectorStmt = $portfolioDb->prepare($sectorSql);
    $sectorStmt->execute();
    $sectorAllocation = $sectorStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get currency allocation
    $currencySql = "SELECT 
                        COALESCE(p.currency_local, 'SEK') as currency,
                        COUNT(*) as positions,
                        SUM(COALESCE(p.current_value_sek, 0)) as value_sek,
                        (SUM(COALESCE(p.current_value_sek, 0)) / NULLIF((SELECT SUM(current_value_sek) FROM psw_portfolio.portfolio WHERE is_active = 1 AND shares_held > 0), 0)) * 100 as weight_percent
                    FROM psw_portfolio.portfolio p
                    WHERE p.is_active = 1 AND p.shares_held > 0
                    GROUP BY COALESCE(p.currency_local, 'SEK')
                    ORDER BY SUM(COALESCE(p.current_value_sek, 0)) DESC";
    
    $currencyStmt = $portfolioDb->prepare($currencySql);
    $currencyStmt->execute();
    $currencyAllocation = $currencyStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get position size allocation (Small, Medium, Large positions)
    $positionSizeSql = "SELECT 
                            CASE 
                                WHEN p.current_value_sek < 50000 THEN 'Small (< 50k SEK)'
                                WHEN p.current_value_sek < 150000 THEN 'Medium (50k-150k SEK)'
                                WHEN p.current_value_sek < 300000 THEN 'Large (150k-300k SEK)'
                                ELSE 'Extra Large (> 300k SEK)'
                            END as position_size,
                            COUNT(*) as positions,
                            SUM(COALESCE(p.current_value_sek, 0)) as value_sek,
                            (SUM(COALESCE(p.current_value_sek, 0)) / NULLIF((SELECT SUM(current_value_sek) FROM psw_portfolio.portfolio WHERE is_active = 1 AND shares_held > 0), 0)) * 100 as weight_percent
                        FROM psw_portfolio.portfolio p
                        WHERE p.is_active = 1 AND p.shares_held > 0
                        GROUP BY 
                            CASE 
                                WHEN p.current_value_sek < 50000 THEN 'Small (< 50k SEK)'
                                WHEN p.current_value_sek < 150000 THEN 'Medium (50k-150k SEK)'
                                WHEN p.current_value_sek < 300000 THEN 'Large (150k-300k SEK)'
                                ELSE 'Extra Large (> 300k SEK)'
                            END
                        ORDER BY SUM(COALESCE(p.current_value_sek, 0)) DESC";
    
    $positionSizeStmt = $portfolioDb->prepare($positionSizeSql);
    $positionSizeStmt->execute();
    $positionSizeAllocation = $positionSizeStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get regional aggregation for world map
    $regionalSql = "SELECT 
                        CASE 
                            WHEN LEFT(p.isin, 2) IN ('SE', 'DK', 'NO', 'FI') THEN 'Nordic'
                            WHEN LEFT(p.isin, 2) IN ('DE', 'FR', 'GB', 'NL', 'CH', 'IT', 'ES') THEN 'Europe'
                            WHEN LEFT(p.isin, 2) IN ('US', 'CA') THEN 'North America'
                            WHEN LEFT(p.isin, 2) IN ('AU', 'JP', 'HK', 'SG', 'KR', 'CN', 'IN') THEN 'Asia-Pacific'
                            WHEN LEFT(p.isin, 2) IN ('BR', 'MX', 'AR', 'CL') THEN 'Latin America'
                            WHEN LEFT(p.isin, 2) IN ('ZA', 'NG', 'EG') THEN 'Africa'
                            ELSE 'Other'
                        END as region,
                        COUNT(*) as positions,
                        SUM(COALESCE(p.current_value_sek, 0)) as value_sek,
                        (SUM(COALESCE(p.current_value_sek, 0)) / NULLIF((SELECT SUM(current_value_sek) FROM psw_portfolio.portfolio WHERE is_active = 1 AND shares_held > 0), 0)) * 100 as weight_percent
                    FROM psw_portfolio.portfolio p
                    WHERE p.is_active = 1 AND p.shares_held > 0
                    GROUP BY 
                        CASE 
                            WHEN LEFT(p.isin, 2) IN ('SE', 'DK', 'NO', 'FI') THEN 'Nordic'
                            WHEN LEFT(p.isin, 2) IN ('DE', 'FR', 'GB', 'NL', 'CH', 'IT', 'ES') THEN 'Europe'
                            WHEN LEFT(p.isin, 2) IN ('US', 'CA') THEN 'North America'
                            WHEN LEFT(p.isin, 2) IN ('AU', 'JP', 'HK', 'SG', 'KR', 'CN', 'IN') THEN 'Asia-Pacific'
                            WHEN LEFT(p.isin, 2) IN ('BR', 'MX', 'AR', 'CL') THEN 'Latin America'
                            WHEN LEFT(p.isin, 2) IN ('ZA', 'NG', 'EG') THEN 'Africa'
                            ELSE 'Other'
                        END
                    ORDER BY SUM(COALESCE(p.current_value_sek, 0)) DESC";
    
    $regionalStmt = $portfolioDb->prepare($regionalSql);
    $regionalStmt->execute();
    $regionalAllocation = $regionalStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Debug: Check what data we actually have
    error_log("Geographic allocation count: " . count($geographicAllocation));
    error_log("Sector allocation count: " . count($sectorAllocation));
    error_log("Currency allocation count: " . count($currencyAllocation));
    
    // If no data, try a simple fallback query to see what we have
    if (empty($sectorAllocation)) {
        $testSql = "SELECT COUNT(*) as total_holdings, 
                           SUM(COALESCE(current_value_sek, 0)) as total_value
                    FROM psw_portfolio.portfolio 
                    WHERE is_active = 1 AND shares_held > 0";
        $testStmt = $portfolioDb->prepare($testSql);
        $testStmt->execute();
        $testResult = $testStmt->fetch(PDO::FETCH_ASSOC);
        error_log("Test query result: " . print_r($testResult, true));
        
        // Create simple fallback data for Sweden
        if ($testResult['total_holdings'] > 0) {
            $sectorAllocation = [
                ['sector' => 'Mixed Sectors', 'positions' => $testResult['total_holdings'], 'value_sek' => $testResult['total_value'], 'weight_percent' => 100]
            ];
            $geographicAllocation = [
                ['country' => 'Sweden', 'region' => 'Nordic', 'positions' => $testResult['total_holdings'], 'value_sek' => $testResult['total_value'], 'weight_percent' => 100]
            ];
            $regionalAllocation = [
                ['region' => 'Nordic', 'positions' => $testResult['total_holdings'], 'value_sek' => $testResult['total_value'], 'weight_percent' => 100]
            ];
            $currencyAllocation = [
                ['currency' => 'SEK', 'positions' => $testResult['total_holdings'], 'value_sek' => $testResult['total_value'], 'weight_percent' => 100]
            ];
            $positionSizeAllocation = [
                ['position_size' => 'Mixed Sizes', 'positions' => $testResult['total_holdings'], 'value_sek' => $testResult['total_value'], 'weight_percent' => 100]
            ];
        }
    }
    
} catch (Exception $e) {
    $error = "Error loading allocation data: " . $e->getMessage();
    $geographicAllocation = [];
    $sectorAllocation = [];
    $currencyAllocation = [];
    $positionSizeAllocation = [];
    $regionalAllocation = [];
}

// Initialize variables for template
$pageTitle = 'Portfolio Allocation - PSW 4.0';
$pageDescription = 'Geographic, sector, and allocation analysis of your portfolio';
$additionalCSS = [];
$additionalJS = [
    'https://cdn.jsdelivr.net/npm/chart.js',
    'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js'
];
$additionalCSS = ['https://unpkg.com/leaflet@1.9.4/dist/leaflet.css'];

// Prepare content
ob_start();
?>
<div class="psw-content">
    <!-- Page Header -->
    <div class="psw-card psw-mb-4">
        <div class="psw-card-header" style="display: flex; justify-content: space-between; align-items: flex-start;">
            <div>
                <h1 class="psw-card-title">
                    <i class="fas fa-globe-americas psw-card-title-icon"></i>
                    Portfolio Allocation
                </h1>
                <p class="psw-card-subtitle">Geographic, sector, and diversification analysis of your investments</p>
            </div>
            <div style="display: flex; gap: 0.5rem; align-items: center;">
                <button type="button" class="psw-btn psw-btn-secondary" onclick="refreshAllocation()">
                    <i class="fas fa-sync psw-btn-icon"></i>Refresh
                </button>
            </div>
        </div>
    </div>

    <?php if (isset($error)): ?>
        <div class="psw-alert psw-alert-error psw-mb-4">
            <i class="fas fa-exclamation-triangle"></i>
            <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>


    <!-- World Map & Regional Allocation -->
    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 2rem; margin-bottom: 2rem;">
        <div class="psw-card">
            <div class="psw-card-header">
                <div class="psw-card-title">
                    <i class="fas fa-globe psw-card-title-icon"></i>
                    World Map Visualization
                </div>
            </div>
            <div class="psw-card-content">
                <?php if (!empty($geographicAllocation)): ?>
                    <div id="worldMapContainer" style="position: relative; height: 400px; background: var(--bg-secondary); border-radius: var(--radius-md); overflow: hidden;">
                        <!-- Real Interactive World Map using Leaflet.js -->
                        <div id="worldMap" style="height: 100%; width: 100%; border-radius: var(--radius-md);"></div>
                        
                        <!-- Map Legend -->
                        <div id="mapLegend" style="
                            position: absolute;
                            top: 10px;
                            right: 10px;
                            background: rgba(255, 255, 255, 0.95);
                            padding: 15px;
                            border-radius: 8px;
                            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
                            z-index: 1000;
                            font-size: 12px;
                            max-width: 200px;
                        ">
                            <div style="font-weight: 700; margin-bottom: 8px; color: #1f2937;">Portfolio Allocation</div>
                            <div style="color: #6b7280; margin-bottom: 10px; font-size: 11px;">Marker size = allocation %</div>
                            
                            <!-- Gradient color bar -->
                            <div style="background: linear-gradient(to right, #fce7f3, #f3e8ff, #ddd6fe, #a78bfa, #581c87); height: 12px; border-radius: 6px; margin-bottom: 8px; border: 1px solid #d1d5db;"></div>
                            
                            <!-- Scale labels -->
                            <div style="display: flex; justify-content: space-between; font-size: 9px; color: #374151;">
                                <span>0%</span>
                                <span>50%</span>
                                <span>100%</span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Store allocation data for JavaScript -->
                    <script type="application/json" id="allocationData">
                        <?php echo json_encode($geographicAllocation); ?>
                    </script>
                        
                        <!-- Tooltip -->
                        <div id="countryTooltip" style="
                            position: absolute;
                            background: rgba(0,0,0,0.9);
                            color: white;
                            padding: 0.5rem;
                            border-radius: 4px;
                            font-size: 0.875rem;
                            pointer-events: none;
                            opacity: 0;
                            transition: opacity 0.3s;
                            z-index: 1000;
                        "></div>
                    </div>
                <?php else: ?>
                    <div id="worldMapContainer" style="position: relative; height: 400px; background: var(--bg-secondary); border-radius: var(--radius-md); display: flex; align-items: center; justify-content: center; border: 2px dashed var(--border-primary);">
                        <div style="text-align: center; color: var(--text-muted);">
                            <i class="fas fa-globe" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.5;"></i>
                            <h3 style="margin-bottom: 0.5rem;">No Geographic Data</h3>
                            <p>No geographic allocation data available.</p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="psw-card">
            <div class="psw-card-header">
                <div class="psw-card-title">
                    <i class="fas fa-map-marked-alt psw-card-title-icon"></i>
                    Regional Allocation
                </div>
            </div>
            <div class="psw-card-content">
                <?php if (!empty($regionalAllocation)): ?>
                    <canvas id="regionalChart" width="300" height="300"></canvas>
                <?php else: ?>
                    <div style="text-align: center; padding: 2rem; color: var(--text-muted);">
                        <i class="fas fa-map-marked-alt" style="font-size: 2rem; margin-bottom: 1rem; opacity: 0.5;"></i>
                        <p>No regional data available</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Geographic & Sector Allocation -->
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; margin-bottom: 2rem;">
        <div class="psw-card">
            <div class="psw-card-header">
                <div class="psw-card-title">
                    <i class="fas fa-flag psw-card-title-icon"></i>
                    Geographic Allocation
                </div>
            </div>
            <div class="psw-card-content" style="padding: 0;">
                <?php if (!empty($geographicAllocation)): ?>
                    <table class="psw-table">
                        <thead>
                            <tr>
                                <th>Country/Region</th>
                                <th style="text-align: center;">Positions</th>
                                <th style="text-align: right;">Value (SEK)</th>
                                <th style="text-align: right;">Weight %</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($geographicAllocation as $allocation): ?>
                                <tr>
                                    <td style="font-weight: 600;">
                                        <div style="display: flex; align-items: center; gap: 0.5rem;">
                                            <div style="width: 12px; height: 8px; background: var(--primary-accent); border-radius: 2px;"></div>
                                            <?php echo htmlspecialchars($allocation['country']); ?>
                                        </div>
                                        <div style="color: var(--text-muted); font-size: 0.75rem; margin-left: 1.75rem;">
                                            <?php echo htmlspecialchars($allocation['region']); ?>
                                        </div>
                                    </td>
                                    <td style="text-align: center;"><?php echo $allocation['positions']; ?></td>
                                    <td style="text-align: right; font-weight: 600;">
                                        <?php echo Localization::formatCurrency($allocation['value_sek'], 0, 'SEK'); ?>
                                    </td>
                                    <td style="text-align: right;">
                                        <div style="display: flex; align-items: center; justify-content: flex-end; gap: 0.5rem;">
                                            <div style="width: 40px; height: 4px; background: var(--bg-secondary); border-radius: 2px; overflow: hidden;">
                                                <div style="width: <?php echo min(100, $allocation['weight_percent']); ?>%; height: 100%; background: var(--primary-accent);"></div>
                                            </div>
                                            <?php echo number_format($allocation['weight_percent'], 1); ?>%
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div style="text-align: center; padding: 3rem; color: var(--text-muted);">
                        <i class="fas fa-flag" style="font-size: 3rem; margin-bottom: 1rem; opacity: 0.5;"></i>
                        <h3 style="margin-bottom: 0.5rem;">No Geographic Data</h3>
                        <p>No geographic allocation data available.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="psw-card">
            <div class="psw-card-header">
                <div class="psw-card-title">
                    <i class="fas fa-industry psw-card-title-icon"></i>
                    Sector Allocation
                </div>
            </div>
            <div class="psw-card-content">
                <?php if (!empty($sectorAllocation)): ?>
                    <canvas id="sectorChart" width="400" height="300"></canvas>
                <?php else: ?>
                    <div style="text-align: center; padding: 2rem; color: var(--text-muted);">
                        <i class="fas fa-industry" style="font-size: 2rem; margin-bottom: 1rem; opacity: 0.5;"></i>
                        <p>No sector data available</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Currency & Position Size Allocation -->
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; margin-bottom: 2rem;">
        <div class="psw-card">
            <div class="psw-card-header">
                <div class="psw-card-title">
                    <i class="fas fa-coins psw-card-title-icon"></i>
                    Currency Allocation
                </div>
            </div>
            <div class="psw-card-content">
                <?php if (!empty($currencyAllocation)): ?>
                    <canvas id="currencyChart" width="400" height="300"></canvas>
                <?php else: ?>
                    <div style="text-align: center; padding: 2rem; color: var(--text-muted);">
                        <i class="fas fa-coins" style="font-size: 2rem; margin-bottom: 1rem; opacity: 0.5;"></i>
                        <p>No currency data available</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="psw-card">
            <div class="psw-card-header">
                <div class="psw-card-title">
                    <i class="fas fa-chart-bar psw-card-title-icon"></i>
                    Position Size Distribution
                </div>
            </div>
            <div class="psw-card-content">
                <?php if (!empty($positionSizeAllocation)): ?>
                    <canvas id="positionSizeChart" width="400" height="300"></canvas>
                <?php else: ?>
                    <div style="text-align: center; padding: 2rem; color: var(--text-muted);">
                        <i class="fas fa-chart-bar" style="font-size: 2rem; margin-bottom: 1rem; opacity: 0.5;"></i>
                        <p>No position size data available</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
console.log('Allocation page loaded');

// Wait for Chart.js to load and DOM to be ready
document.addEventListener('DOMContentLoaded', function() {
    // Check if Chart.js is loaded
    if (typeof Chart === 'undefined') {
        console.error('Chart.js is not loaded');
        return;
    }
    
    console.log('Chart.js is available, creating charts...');

    // Regional allocation chart
    <?php if (!empty($regionalAllocation)): ?>
    console.log('Creating regional chart with data:', <?php echo json_encode($regionalAllocation); ?>);
    const regionalCtx = document.getElementById('regionalChart');
    if (regionalCtx) {
        const regionalChart = new Chart(regionalCtx.getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: [
                    <?php foreach ($regionalAllocation as $region): ?>
                        '<?php echo addslashes($region['region']); ?>',
                    <?php endforeach; ?>
                ],
                datasets: [{
                    data: [
                        <?php foreach ($regionalAllocation as $region): ?>
                            <?php echo $region['weight_percent']; ?>,
                        <?php endforeach; ?>
                    ],
                    backgroundColor: [
                        '#3B82F6', '#10B981', '#F59E0B', '#EF4444', '#8B5CF6', '#06B6D4'
                    ],
                    borderWidth: 2,
                    borderColor: '#ffffff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 15,
                            usePointStyle: true,
                            font: { size: 11 }
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.label + ': ' + context.parsed.toFixed(1) + '%';
                            }
                        }
                    }
                }
            }
        });
    }
    <?php endif; ?>

    // Sector allocation chart
    <?php if (!empty($sectorAllocation)): ?>
    console.log('Creating sector chart with data:', <?php echo json_encode($sectorAllocation); ?>);
    const sectorCtx = document.getElementById('sectorChart');
    if (sectorCtx) {
        const sectorChart = new Chart(sectorCtx.getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: [
                    <?php foreach ($sectorAllocation as $sector): ?>
                        '<?php echo addslashes($sector['sector']); ?>',
                    <?php endforeach; ?>
                ],
                datasets: [{
                    data: [
                        <?php foreach ($sectorAllocation as $sector): ?>
                            <?php echo $sector['weight_percent']; ?>,
                        <?php endforeach; ?>
                    ],
                    backgroundColor: [
                        '#3B82F6', '#10B981', '#F59E0B', '#EF4444', '#8B5CF6', 
                        '#06B6D4', '#84CC16', '#F97316', '#EC4899', '#6B7280'
                    ],
                    borderWidth: 2,
                    borderColor: '#ffffff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 15,
                            usePointStyle: true,
                            font: { size: 11 }
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.label + ': ' + context.parsed.toFixed(1) + '%';
                            }
                        }
                    }
                }
            }
        });
    }
    <?php endif; ?>

    // Currency allocation chart
    <?php if (!empty($currencyAllocation)): ?>
    console.log('Creating currency chart with data:', <?php echo json_encode($currencyAllocation); ?>);
    const currencyCtx = document.getElementById('currencyChart');
    if (currencyCtx) {
        const currencyChart = new Chart(currencyCtx.getContext('2d'), {
            type: 'pie',
            data: {
                labels: [
                    <?php foreach ($currencyAllocation as $currency): ?>
                        '<?php echo addslashes($currency['currency']); ?>',
                    <?php endforeach; ?>
                ],
                datasets: [{
                    data: [
                        <?php foreach ($currencyAllocation as $currency): ?>
                            <?php echo $currency['weight_percent']; ?>,
                        <?php endforeach; ?>
                    ],
                    backgroundColor: [
                        '#10B981', '#3B82F6', '#F59E0B', '#EF4444', '#8B5CF6', '#06B6D4'
                    ],
                    borderWidth: 2,
                    borderColor: '#ffffff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 15,
                            usePointStyle: true,
                            font: { size: 12 }
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.label + ': ' + context.parsed.toFixed(1) + '%';
                            }
                        }
                    }
                }
            }
        });
    }
    <?php endif; ?>

    // Position size distribution chart
    <?php if (!empty($positionSizeAllocation)): ?>
    console.log('Creating position size chart with data:', <?php echo json_encode($positionSizeAllocation); ?>);
    const positionSizeCtx = document.getElementById('positionSizeChart');
    if (positionSizeCtx) {
        const positionSizeChart = new Chart(positionSizeCtx.getContext('2d'), {
            type: 'bar',
            data: {
                labels: [
                    <?php foreach ($positionSizeAllocation as $size): ?>
                        '<?php echo addslashes($size['position_size']); ?>',
                    <?php endforeach; ?>
                ],
                datasets: [{
                    label: 'Portfolio Weight %',
                    data: [
                        <?php foreach ($positionSizeAllocation as $size): ?>
                            <?php echo $size['weight_percent']; ?>,
                        <?php endforeach; ?>
                    ],
                    backgroundColor: '#3B82F6',
                    borderColor: '#2563EB',
                    borderWidth: 1,
                    borderRadius: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.parsed.y.toFixed(1) + '% of portfolio';
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return value + '%';
                            }
                        }
                    }
                }
            }
        });
    }
    <?php endif; ?>
    
    // Initialize Leaflet World Map
    <?php if (!empty($geographicAllocation)): ?>
    initializeWorldMap();
    <?php endif; ?>
    
}); // End DOMContentLoaded

function initializeWorldMap() {
    // Get allocation data
    const allocationDataElement = document.getElementById('allocationData');
    if (!allocationDataElement) return;
    
    const allocationData = JSON.parse(allocationDataElement.textContent);
    console.log('Allocation data:', allocationData);
    
    // Initialize map centered on Europe (since you have Swedish investments)
    const map = L.map('worldMap').setView([55.0, 15.0], 4);
    
    // Add OpenStreetMap tiles
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors',
        maxZoom: 18,
    }).addTo(map);
    
    // Country coordinates for marker placement
    const countryCoordinates = {
        'Sweden': [59.3293, 18.0686],
        'Norway': [60.4720, 8.4689],
        'Finland': [61.9241, 25.7482],
        'Denmark': [56.2639, 9.5018],
        'Germany': [51.1657, 10.4515],
        'France': [46.2276, 2.2137],
        'United Kingdom': [55.3781, -3.4360],
        'Netherlands': [52.1326, 5.2913],
        'Switzerland': [46.8182, 8.2275],
        'United States': [39.8283, -98.5795],
        'Canada': [56.1304, -106.3468],
        'Japan': [36.2048, 138.2529],
        'Australia': [-25.2744, 133.7751],
        'Hong Kong': [22.3193, 114.1694],
        'Singapore': [1.3521, 103.8198]
    };
    
    // Calculate min/max weights for color scaling
    let maxWeight = 0;
    let minWeight = 100;
    allocationData.forEach(country => {
        maxWeight = Math.max(maxWeight, country.weight_percent);
        minWeight = Math.min(minWeight, country.weight_percent);
    });
    
    // Function to get color based on allocation percentage
    function getMarkerColor(weight) {
        const range = Math.max(maxWeight - minWeight, 1);
        const normalized = (weight - minWeight) / range;
        
        // Create smooth gradient from light pink to dark purple
        const r = Math.round(252 - (normalized * (252 - 88)));
        const g = Math.round(231 - (normalized * (231 - 28)));
        const b = Math.round(243 - (normalized * (243 - 135)));
        
        return `rgb(${r}, ${g}, ${b})`;
    }
    
    // Function to get marker size based on allocation percentage
    function getMarkerSize(weight) {
        const baseSize = 8;
        const maxSize = 25;
        const sizeMultiplier = weight / maxWeight;
        return Math.max(baseSize, baseSize + (maxSize - baseSize) * sizeMultiplier);
    }
    
    // Add markers for each country with allocation
    allocationData.forEach(country => {
        const coords = countryCoordinates[country.country];
        if (coords) {
            const color = getMarkerColor(country.weight_percent);
            const size = getMarkerSize(country.weight_percent);
            
            // Create custom marker
            const marker = L.circleMarker(coords, {
                radius: size,
                fillColor: color,
                color: '#ffffff',
                weight: 2,
                opacity: 1,
                fillOpacity: 0.8
            }).addTo(map);
            
            // Add popup with detailed information
            const popupContent = `
                <div style="font-family: inherit; min-width: 200px;">
                    <h4 style="margin: 0 0 8px 0; color: #1f2937; font-size: 16px;">${country.country}</h4>
                    <div style="margin-bottom: 4px;"><strong>Region:</strong> ${country.region}</div>
                    <div style="margin-bottom: 4px;"><strong>Allocation:</strong> ${country.weight_percent.toFixed(1)}%</div>
                    <div style="margin-bottom: 4px;"><strong>Value:</strong> ${new Intl.NumberFormat('sv-SE', { 
                        style: 'currency', 
                        currency: 'SEK',
                        minimumFractionDigits: 0,
                        maximumFractionDigits: 0
                    }).format(country.value_sek)}</div>
                    <div><strong>Positions:</strong> ${country.positions}</div>
                </div>
            `;
            
            marker.bindPopup(popupContent);
            
            // Add hover effect
            marker.on('mouseover', function(e) {
                this.setStyle({
                    weight: 3,
                    fillOpacity: 1.0
                });
            });
            
            marker.on('mouseout', function(e) {
                this.setStyle({
                    weight: 2,
                    fillOpacity: 0.8
                });
            });
        }
    });
    
    // Add custom control for map info
    const info = L.control({position: 'bottomleft'});
    info.onAdd = function (map) {
        this._div = L.DomUtil.create('div', 'info');
        this._div.innerHTML = `
            <div style="background: rgba(255,255,255,0.9); padding: 8px; border-radius: 4px; font-size: 11px; color: #666;">
                <strong>Interactive Portfolio Map</strong><br>
                Click markers for details • Zoom and pan to explore
            </div>
        `;
        return this._div;
    };
    info.addTo(map);
}

function refreshAllocation() {
    location.reload();
}
</script>

<?php
$content = ob_get_clean();

// Include base layout
include __DIR__ . '/templates/layouts/base-redesign.php';
?>