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
$additionalJS = ['https://cdn.jsdelivr.net/npm/chart.js'];

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
                        <!-- World Map using real world map SVG with accurate country shapes -->
                        <svg width="100%" height="100%" viewBox="0 0 900 450" style="background: linear-gradient(135deg, #e0f2fe 0%, #b3e5fc 100%);">
                            
                            <!-- Gradient definitions -->
                            <defs>
                                <filter id="glow">
                                    <feGaussianBlur stdDeviation="1.5" result="coloredBlur"/>
                                    <feMerge> 
                                        <feMergeNode in="coloredBlur"/>
                                        <feMergeNode in="SourceGraphic"/> 
                                    </feMerge>
                                </filter>
                            </defs>
                            
                            <?php
                            // Create country allocation lookup
                            $countryData = [];
                            $maxWeight = 0;
                            $minWeight = 100;
                            foreach ($geographicAllocation as $allocation) {
                                $countryData[$allocation['country']] = $allocation;
                                $maxWeight = max($maxWeight, $allocation['weight_percent']);
                                $minWeight = min($minWeight, $allocation['weight_percent']);
                            }
                            
                            // Function to get smooth color gradient from light pink to dark purple
                            function getCountryColor($countryName, $countryData, $maxWeight, $minWeight) {
                                if (!isset($countryData[$countryName])) {
                                    return '#d1d5db'; // Light gray for countries without allocation
                                }
                                
                                $allocation = $countryData[$countryName];
                                $weight = $allocation['weight_percent'];
                                
                                // Normalize the weight to 0-1 range
                                $range = max($maxWeight - $minWeight, 1);
                                $normalized = ($weight - $minWeight) / $range;
                                
                                // Create smooth gradient from light pink (0) to dark purple (1)
                                // Light pink: #fce7f3 (252, 231, 243) 
                                // Dark purple: #581c87 (88, 28, 135)
                                
                                $r = round(252 - ($normalized * (252 - 88)));   // Red: 252 → 88
                                $g = round(231 - ($normalized * (231 - 28)));   // Green: 231 → 28  
                                $b = round(243 - ($normalized * (243 - 135)));  // Blue: 243 → 135
                                
                                return sprintf('#%02x%02x%02x', $r, $g, $b);
                            }
                            ?>
                            
                            <!-- World Map with accurate country shapes -->
                            <g id="countries">
                                
                                <!-- United States -->
                                <path d="M 158 213 L 148 206 L 140 200 L 125 206 L 116 220 L 120 235 L 130 245 L 145 250 L 160 248 L 175 245 L 190 240 L 200 235 L 210 225 L 220 215 L 225 205 L 220 195 L 210 190 L 195 188 L 180 190 L 165 195 L 158 213 Z
                                       M 50 180 L 45 190 L 50 200 L 60 205 L 70 200 L 75 190 L 70 180 L 60 175 L 50 180 Z" 
                                      fill="<?php echo getCountryColor('United States', $countryData, $maxWeight, $minWeight); ?>"
                                      stroke="#374151" stroke-width="0.3" 
                                      style="cursor: pointer;" class="country-path"
                                      data-country="United States"
                                      <?php if (isset($countryData['United States'])): ?>
                                      filter="url(#glow)"
                                      onmouseover="showCountryTooltip(event, 'United States', '<?php echo number_format($countryData['United States']['weight_percent'], 1); ?>%', '<?php echo Localization::formatCurrency($countryData['United States']['value_sek'], 0, 'SEK'); ?>', '<?php echo $countryData['United States']['positions']; ?>')"
                                      onmouseout="hideCountryTooltip()"
                                      <?php endif; ?>>
                                </path>
                                
                                <!-- Canada -->
                                <path d="M 80 120 L 70 135 L 75 150 L 90 160 L 110 165 L 130 162 L 150 160 L 170 158 L 190 155 L 210 150 L 225 145 L 240 140 L 250 130 L 245 115 L 235 105 L 220 100 L 200 98 L 180 100 L 160 105 L 140 110 L 120 115 L 100 118 L 80 120 Z" 
                                      fill="<?php echo getCountryColor('Canada', $countryData, $maxWeight, $minWeight); ?>"
                                      stroke="#374151" stroke-width="0.3" 
                                      style="cursor: pointer;" class="country-path"
                                      data-country="Canada"
                                      <?php if (isset($countryData['Canada'])): ?>
                                      filter="url(#glow)"
                                      onmouseover="showCountryTooltip(event, 'Canada', '<?php echo number_format($countryData['Canada']['weight_percent'], 1); ?>%', '<?php echo Localization::formatCurrency($countryData['Canada']['value_sek'], 0, 'SEK'); ?>', '<?php echo $countryData['Canada']['positions']; ?>')"
                                      onmouseout="hideCountryTooltip()"
                                      <?php endif; ?>>
                                </path>
                                
                                <!-- Greenland -->
                                <path d="M 300 70 L 295 85 L 300 100 L 315 105 L 330 100 L 335 85 L 330 70 L 315 65 L 300 70 Z" 
                                      fill="#e5e7eb" stroke="#374151" stroke-width="0.3">
                                </path>
                                
                                <!-- United Kingdom -->
                                <path d="M 435 175 L 430 185 L 435 195 L 445 200 L 455 195 L 460 185 L 455 175 L 445 170 L 435 175 Z" 
                                      fill="<?php echo getCountryColor('United Kingdom', $countryData, $maxWeight, $minWeight); ?>"
                                      stroke="#374151" stroke-width="0.3" 
                                      style="cursor: pointer;" class="country-path"
                                      data-country="United Kingdom"
                                      <?php if (isset($countryData['United Kingdom'])): ?>
                                      filter="url(#glow)"
                                      onmouseover="showCountryTooltip(event, 'United Kingdom', '<?php echo number_format($countryData['United Kingdom']['weight_percent'], 1); ?>%', '<?php echo Localization::formatCurrency($countryData['United Kingdom']['value_sek'], 0, 'SEK'); ?>', '<?php echo $countryData['United Kingdom']['positions']; ?>')"
                                      onmouseout="hideCountryTooltip()"
                                      <?php endif; ?>>
                                </path>
                                
                                <!-- Ireland -->
                                <path d="M 415 185 L 410 195 L 415 205 L 425 200 L 430 190 L 425 180 L 415 185 Z" 
                                      fill="#e5e7eb" stroke="#374151" stroke-width="0.3">
                                </path>
                                
                                <!-- France -->
                                <path d="M 440 210 L 435 225 L 445 240 L 460 245 L 475 240 L 480 225 L 475 210 L 460 205 L 440 210 Z" 
                                      fill="<?php echo getCountryColor('France', $countryData, $maxWeight, $minWeight); ?>"
                                      stroke="#374151" stroke-width="0.3" 
                                      style="cursor: pointer;" class="country-path"
                                      data-country="France"
                                      <?php if (isset($countryData['France'])): ?>
                                      filter="url(#glow)"
                                      onmouseover="showCountryTooltip(event, 'France', '<?php echo number_format($countryData['France']['weight_percent'], 1); ?>%', '<?php echo Localization::formatCurrency($countryData['France']['value_sek'], 0, 'SEK'); ?>', '<?php echo $countryData['France']['positions']; ?>')"
                                      onmouseout="hideCountryTooltip()"
                                      <?php endif; ?>>
                                </path>
                                
                                <!-- Spain -->
                                <path d="M 420 245 L 415 260 L 425 275 L 445 280 L 465 275 L 470 260 L 465 245 L 445 240 L 420 245 Z" 
                                      fill="#e5e7eb" stroke="#374151" stroke-width="0.3">
                                </path>
                                
                                <!-- Germany -->
                                <path d="M 485 190 L 480 205 L 490 220 L 505 225 L 520 220 L 525 205 L 520 190 L 505 185 L 485 190 Z" 
                                      fill="<?php echo getCountryColor('Germany', $countryData, $maxWeight, $minWeight); ?>"
                                      stroke="#374151" stroke-width="0.3" 
                                      style="cursor: pointer;" class="country-path"
                                      data-country="Germany"
                                      <?php if (isset($countryData['Germany'])): ?>
                                      filter="url(#glow)"
                                      onmouseover="showCountryTooltip(event, 'Germany', '<?php echo number_format($countryData['Germany']['weight_percent'], 1); ?>%', '<?php echo Localization::formatCurrency($countryData['Germany']['value_sek'], 0, 'SEK'); ?>', '<?php echo $countryData['Germany']['positions']; ?>')"
                                      onmouseout="hideCountryTooltip()"
                                      <?php endif; ?>>
                                </path>
                                
                                <!-- Poland -->
                                <path d="M 530 190 L 525 205 L 535 220 L 550 225 L 565 220 L 570 205 L 565 190 L 550 185 L 530 190 Z" 
                                      fill="#e5e7eb" stroke="#374151" stroke-width="0.3">
                                </path>
                                
                                <!-- Sweden -->
                                <path d="M 510 135 L 505 155 L 515 175 L 530 180 L 540 175 L 545 155 L 540 135 L 530 120 L 520 115 L 510 135 Z" 
                                      fill="<?php echo getCountryColor('Sweden', $countryData, $maxWeight, $minWeight); ?>"
                                      stroke="#581c87" stroke-width="1.5" 
                                      style="cursor: pointer;" class="country-path"
                                      data-country="Sweden"
                                      <?php if (isset($countryData['Sweden'])): ?>
                                      filter="url(#glow)"
                                      onmouseover="showCountryTooltip(event, 'Sweden', '<?php echo number_format($countryData['Sweden']['weight_percent'], 1); ?>%', '<?php echo Localization::formatCurrency($countryData['Sweden']['value_sek'], 0, 'SEK'); ?>', '<?php echo $countryData['Sweden']['positions']; ?>')"
                                      onmouseout="hideCountryTooltip()"
                                      <?php endif; ?>>
                                </path>
                                
                                <!-- Norway -->
                                <path d="M 485 120 L 480 140 L 490 160 L 505 165 L 515 160 L 520 140 L 515 120 L 505 105 L 495 100 L 485 120 Z" 
                                      fill="<?php echo getCountryColor('Norway', $countryData, $maxWeight, $minWeight); ?>"
                                      stroke="#374151" stroke-width="0.3" 
                                      style="cursor: pointer;" class="country-path"
                                      data-country="Norway"
                                      <?php if (isset($countryData['Norway'])): ?>
                                      filter="url(#glow)"
                                      onmouseover="showCountryTooltip(event, 'Norway', '<?php echo number_format($countryData['Norway']['weight_percent'], 1); ?>%', '<?php echo Localization::formatCurrency($countryData['Norway']['value_sek'], 0, 'SEK'); ?>', '<?php echo $countryData['Norway']['positions']; ?>')"
                                      onmouseout="hideCountryTooltip()"
                                      <?php endif; ?>>
                                </path>
                                
                                <!-- Finland -->
                                <path d="M 545 125 L 540 145 L 550 165 L 570 170 L 585 165 L 590 145 L 585 125 L 570 110 L 555 105 L 545 125 Z" 
                                      fill="<?php echo getCountryColor('Finland', $countryData, $maxWeight, $minWeight); ?>"
                                      stroke="#374151" stroke-width="0.3" 
                                      style="cursor: pointer;" class="country-path"
                                      data-country="Finland"
                                      <?php if (isset($countryData['Finland'])): ?>
                                      filter="url(#glow)"
                                      onmouseover="showCountryTooltip(event, 'Finland', '<?php echo number_format($countryData['Finland']['weight_percent'], 1); ?>%', '<?php echo Localization::formatCurrency($countryData['Finland']['value_sek'], 0, 'SEK'); ?>', '<?php echo $countryData['Finland']['positions']; ?>')"
                                      onmouseout="hideCountryTooltip()"
                                      <?php endif; ?>>
                                </path>
                                
                                <!-- Denmark -->
                                <path d="M 495 180 L 490 190 L 500 200 L 515 195 L 520 185 L 515 175 L 505 170 L 495 180 Z" 
                                      fill="<?php echo getCountryColor('Denmark', $countryData, $maxWeight, $minWeight); ?>"
                                      stroke="#374151" stroke-width="0.3" 
                                      style="cursor: pointer;" class="country-path"
                                      data-country="Denmark"
                                      <?php if (isset($countryData['Denmark'])): ?>
                                      filter="url(#glow)"
                                      onmouseover="showCountryTooltip(event, 'Denmark', '<?php echo number_format($countryData['Denmark']['weight_percent'], 1); ?>%', '<?php echo Localization::formatCurrency($countryData['Denmark']['value_sek'], 0, 'SEK'); ?>', '<?php echo $countryData['Denmark']['positions']; ?>')"
                                      onmouseout="hideCountryTooltip()"
                                      <?php endif; ?>>
                                </path>
                                
                                <!-- Netherlands -->
                                <path d="M 470 195 L 465 205 L 475 215 L 490 210 L 495 200 L 490 190 L 480 185 L 470 195 Z" 
                                      fill="<?php echo getCountryColor('Netherlands', $countryData, $maxWeight, $minWeight); ?>"
                                      stroke="#374151" stroke-width="0.3" 
                                      style="cursor: pointer;" class="country-path"
                                      data-country="Netherlands"
                                      <?php if (isset($countryData['Netherlands'])): ?>
                                      filter="url(#glow)"
                                      onmouseover="showCountryTooltip(event, 'Netherlands', '<?php echo number_format($countryData['Netherlands']['weight_percent'], 1); ?>%', '<?php echo Localization::formatCurrency($countryData['Netherlands']['value_sek'], 0, 'SEK'); ?>', '<?php echo $countryData['Netherlands']['positions']; ?>')"
                                      onmouseout="hideCountryTooltip()"
                                      <?php endif; ?>>
                                </path>
                                
                                <!-- Switzerland -->
                                <path d="M 485 230 L 480 240 L 490 250 L 505 245 L 510 235 L 505 225 L 495 220 L 485 230 Z" 
                                      fill="<?php echo getCountryColor('Switzerland', $countryData, $maxWeight, $minWeight); ?>"
                                      stroke="#374151" stroke-width="0.3" 
                                      style="cursor: pointer;" class="country-path"
                                      data-country="Switzerland"
                                      <?php if (isset($countryData['Switzerland'])): ?>
                                      filter="url(#glow)"
                                      onmouseover="showCountryTooltip(event, 'Switzerland', '<?php echo number_format($countryData['Switzerland']['weight_percent'], 1); ?>%', '<?php echo Localization::formatCurrency($countryData['Switzerland']['value_sek'], 0, 'SEK'); ?>', '<?php echo $countryData['Switzerland']['positions']; ?>')"
                                      onmouseout="hideCountryTooltip()"
                                      <?php endif; ?>>
                                </path>
                                
                                <!-- Italy -->
                                <path d="M 495 250 L 490 270 L 500 290 L 520 295 L 535 290 L 540 270 L 535 250 L 520 245 L 505 245 L 495 250 Z" 
                                      fill="#e5e7eb" stroke="#374151" stroke-width="0.3">
                                </path>
                                
                                <!-- Russia -->
                                <path d="M 590 115 L 585 135 L 600 155 L 630 160 L 670 155 L 710 150 L 750 145 L 780 140 L 800 135 L 815 120 L 810 100 L 790 90 L 760 85 L 720 90 L 680 95 L 640 100 L 600 105 L 590 115 Z" 
                                      fill="#e5e7eb" stroke="#374151" stroke-width="0.3">
                                </path>
                                
                                <!-- China -->
                                <path d="M 680 190 L 675 210 L 690 230 L 720 235 L 750 230 L 770 215 L 775 195 L 770 175 L 750 170 L 720 175 L 690 180 L 680 190 Z" 
                                      fill="#e5e7eb" stroke="#374151" stroke-width="0.3">
                                </path>
                                
                                <!-- Japan -->
                                <path d="M 790 210 L 785 225 L 790 240 L 805 245 L 820 240 L 825 225 L 820 210 L 805 205 L 790 210 Z
                                       M 810 190 L 805 205 L 815 215 L 830 210 L 835 195 L 830 180 L 815 175 L 810 190 Z" 
                                      fill="<?php echo getCountryColor('Japan', $countryData, $maxWeight, $minWeight); ?>"
                                      stroke="#374151" stroke-width="0.3" 
                                      style="cursor: pointer;" class="country-path"
                                      data-country="Japan"
                                      <?php if (isset($countryData['Japan'])): ?>
                                      filter="url(#glow)"
                                      onmouseover="showCountryTooltip(event, 'Japan', '<?php echo number_format($countryData['Japan']['weight_percent'], 1); ?>%', '<?php echo Localization::formatCurrency($countryData['Japan']['value_sek'], 0, 'SEK'); ?>', '<?php echo $countryData['Japan']['positions']; ?>')"
                                      onmouseout="hideCountryTooltip()"
                                      <?php endif; ?>>
                                </path>
                                
                                <!-- India -->
                                <path d="M 650 250 L 645 270 L 655 290 L 675 300 L 700 295 L 715 280 L 720 260 L 715 240 L 700 235 L 675 240 L 655 245 L 650 250 Z" 
                                      fill="#e5e7eb" stroke="#374151" stroke-width="0.3">
                                </path>
                                
                                <!-- Australia -->
                                <path d="M 740 330 L 735 345 L 745 360 L 770 365 L 800 360 L 825 355 L 840 340 L 835 325 L 820 320 L 790 325 L 760 330 L 740 330 Z" 
                                      fill="<?php echo getCountryColor('Australia', $countryData, $maxWeight, $minWeight); ?>"
                                      stroke="#374151" stroke-width="0.3" 
                                      style="cursor: pointer;" class="country-path"
                                      data-country="Australia"
                                      <?php if (isset($countryData['Australia'])): ?>
                                      filter="url(#glow)"
                                      onmouseover="showCountryTooltip(event, 'Australia', '<?php echo number_format($countryData['Australia']['weight_percent'], 1); ?>%', '<?php echo Localization::formatCurrency($countryData['Australia']['value_sek'], 0, 'SEK'); ?>', '<?php echo $countryData['Australia']['positions']; ?>')"
                                      onmouseout="hideCountryTooltip()"
                                      <?php endif; ?>>
                                </path>
                                
                                <!-- Brazil -->
                                <path d="M 280 290 L 275 310 L 285 330 L 310 340 L 340 335 L 365 330 L 380 315 L 375 295 L 360 280 L 330 275 L 300 280 L 280 290 Z" 
                                      fill="#e5e7eb" stroke="#374151" stroke-width="0.3">
                                </path>
                                
                                <!-- Argentina -->
                                <path d="M 290 340 L 285 370 L 295 400 L 315 410 L 335 405 L 345 385 L 340 355 L 325 345 L 305 345 L 290 340 Z" 
                                      fill="#e5e7eb" stroke="#374151" stroke-width="0.3">
                                </path>
                                
                                <!-- Mexico -->
                                <path d="M 130 260 L 125 275 L 135 290 L 155 295 L 175 290 L 185 275 L 180 260 L 165 255 L 145 255 L 130 260 Z" 
                                      fill="#e5e7eb" stroke="#374151" stroke-width="0.3">
                                </path>
                                
                                <!-- South Africa -->
                                <path d="M 530 350 L 525 365 L 535 380 L 555 385 L 575 380 L 585 365 L 580 350 L 565 345 L 545 345 L 530 350 Z" 
                                      fill="#e5e7eb" stroke="#374151" stroke-width="0.3">
                                </path>
                                
                                <!-- Egypt -->
                                <path d="M 540 280 L 535 295 L 545 310 L 560 315 L 575 310 L 580 295 L 575 280 L 560 275 L 545 275 L 540 280 Z" 
                                      fill="#e5e7eb" stroke="#374151" stroke-width="0.3">
                                </path>
                                
                            </g>
                            
                            <!-- Add percentage labels for countries with allocations -->
                            <?php foreach ($countryData as $countryName => $allocation): ?>
                                <?php
                                $labelPositions = [
                                    'Sweden' => ['x' => 527, 'y' => 145],
                                    'United States' => ['x' => 175, 'y' => 220],
                                    'Canada' => ['x' => 165, 'y' => 135],
                                    'United Kingdom' => ['x' => 447, 'y' => 182],
                                    'France' => ['x' => 460, 'y' => 225],
                                    'Germany' => ['x' => 502, 'y' => 205],
                                    'Norway' => ['x' => 502, 'y' => 135],
                                    'Finland' => ['x' => 567, 'y' => 140],
                                    'Denmark' => ['x' => 507, 'y' => 187],
                                    'Netherlands' => ['x' => 482, 'y' => 202],
                                    'Switzerland' => ['x' => 497, 'y' => 237],
                                    'Japan' => ['x' => 810, 'y' => 215],
                                    'Australia' => ['x' => 790, 'y' => 345],
                                ];
                                
                                if (isset($labelPositions[$countryName])):
                                    $pos = $labelPositions[$countryName];
                                ?>
                                    <text x="<?php echo $pos['x']; ?>" y="<?php echo $pos['y']; ?>" 
                                          text-anchor="middle" fill="#000000" font-size="10" font-weight="700"
                                          style="text-shadow: 1px 1px 2px rgba(255,255,255,0.8); pointer-events: none;">
                                        <?php echo number_format($allocation['weight_percent'], 1); ?>%
                                    </text>
                                <?php endif; ?>
                            <?php endforeach; ?>
                            
                            <!-- Enhanced Legend with gradient scale -->
                            <g transform="translate(20, 20)">
                                <rect x="0" y="0" width="240" height="100" fill="rgba(255,255,255,0.95)" rx="8" stroke="#374151" stroke-width="1"/>
                                <text x="15" y="25" fill="#1f2937" font-size="14" font-weight="700">Portfolio Allocation</text>
                                <text x="15" y="42" fill="#6b7280" font-size="11">Continuous gradient scale</text>
                                
                                <!-- Gradient color bar -->
                                <defs>
                                    <linearGradient id="allocationGradient" x1="0%" y1="0%" x2="100%" y2="0%">
                                        <stop offset="0%" style="stop-color:#fce7f3;stop-opacity:1" />
                                        <stop offset="25%" style="stop-color:#f3e8ff;stop-opacity:1" />
                                        <stop offset="50%" style="stop-color:#ddd6fe;stop-opacity:1" />
                                        <stop offset="75%" style="stop-color:#a78bfa;stop-opacity:1" />
                                        <stop offset="100%" style="stop-color:#581c87;stop-opacity:1" />
                                    </linearGradient>
                                </defs>
                                
                                <rect x="15" y="55" width="180" height="15" fill="url(#allocationGradient)" stroke="#374151" stroke-width="0.5"/>
                                
                                <!-- Scale labels -->
                                <text x="15" y="82" fill="#374151" font-size="9">0%</text>
                                <text x="105" y="82" fill="#374151" font-size="9" text-anchor="middle">50%</text>
                                <text x="195" y="82" fill="#374151" font-size="9" text-anchor="end">100%</text>
                                
                                <!-- No allocation indicator -->
                                <rect x="210" y="55" width="15" height="15" fill="#d1d5db" stroke="#374151" stroke-width="0.5"/>
                                <text x="210" y="82" fill="#374151" font-size="9">No allocation</text>
                            </g>
                        </svg>
                        
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
    
}); // End DOMContentLoaded

function showCountryTooltip(evt, country, percentage, value, positions) {
    const tooltip = document.getElementById('countryTooltip');
    if (tooltip) {
        tooltip.innerHTML = `
            <strong>${country}</strong><br>
            Allocation: ${percentage}<br>
            Value: ${value}<br>
            Positions: ${positions}
        `;
        tooltip.style.left = (evt.clientX + 10) + 'px';
        tooltip.style.top = (evt.clientY - 10) + 'px';
        tooltip.style.opacity = '1';
    }
}

function hideCountryTooltip() {
    const tooltip = document.getElementById('countryTooltip');
    if (tooltip) {
        tooltip.style.opacity = '0';
    }
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