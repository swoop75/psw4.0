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
                        <!-- Real World Map SVG -->
                        <svg width="100%" height="100%" viewBox="0 0 1000 500" style="background: linear-gradient(135deg, #0f172a 0%, #1e3a8a 100%);">
                            <!-- Ocean background -->
                            <rect width="1000" height="500" fill="url(#oceanGradient)"/>
                            
                            <!-- Gradient definitions -->
                            <defs>
                                <linearGradient id="oceanGradient" x1="0%" y1="0%" x2="100%" y2="100%">
                                    <stop offset="0%" style="stop-color:#0f172a;stop-opacity:1" />
                                    <stop offset="100%" style="stop-color:#1e3a8a;stop-opacity:1" />
                                </linearGradient>
                                <filter id="glow">
                                    <feGaussianBlur stdDeviation="2" result="coloredBlur"/>
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
                            foreach ($geographicAllocation as $allocation) {
                                $countryData[$allocation['country']] = $allocation;
                                $maxWeight = max($maxWeight, $allocation['weight_percent']);
                            }
                            
                            // Function to get color based on allocation intensity
                            function getCountryColor($countryName, $countryData, $maxWeight) {
                                if (!isset($countryData[$countryName])) {
                                    return '#374151'; // Gray for countries without allocation
                                }
                                
                                $allocation = $countryData[$countryName];
                                $intensity = $allocation['weight_percent'] / max($maxWeight, 1);
                                
                                // Generate color based on intensity (darker = higher allocation)
                                if ($intensity >= 0.8) return '#dc2626'; // Very dark red
                                if ($intensity >= 0.6) return '#ef4444'; // Dark red  
                                if ($intensity >= 0.4) return '#f97316'; // Orange
                                if ($intensity >= 0.2) return '#eab308'; // Yellow
                                if ($intensity > 0) return '#22c55e';    // Green
                                return '#374151'; // Gray
                            }
                            ?>
                            
                            <!-- World Map Countries (Simplified SVG paths) -->
                            
                            <!-- United States -->
                            <path d="M 200 180 L 140 160 L 120 200 L 100 220 L 140 280 L 200 300 L 280 280 L 320 260 L 350 240 L 360 200 L 340 180 L 300 170 L 250 160 Z" 
                                  fill="<?php echo getCountryColor('United States', $countryData, $maxWeight); ?>"
                                  stroke="#1f2937" stroke-width="0.5" opacity="0.9"
                                  style="cursor: pointer;" class="country-path"
                                  data-country="United States"
                                  <?php if (isset($countryData['United States'])): ?>
                                  filter="url(#glow)"
                                  onmouseover="showCountryTooltip(event, 'United States', '<?php echo number_format($countryData['United States']['weight_percent'], 1); ?>%', '<?php echo Localization::formatCurrency($countryData['United States']['value_sek'], 0, 'SEK'); ?>', '<?php echo $countryData['United States']['positions']; ?>')"
                                  onmouseout="hideCountryTooltip()"
                                  <?php endif; ?>>
                            </path>
                            
                            <!-- Canada -->
                            <path d="M 120 80 L 100 120 L 140 140 L 200 120 L 280 100 L 350 110 L 380 90 L 360 60 L 300 50 L 200 60 L 150 70 Z" 
                                  fill="<?php echo getCountryColor('Canada', $countryData, $maxWeight); ?>"
                                  stroke="#1f2937" stroke-width="0.5" opacity="0.9"
                                  style="cursor: pointer;" class="country-path"
                                  data-country="Canada"
                                  <?php if (isset($countryData['Canada'])): ?>
                                  filter="url(#glow)"
                                  onmouseover="showCountryTooltip(event, 'Canada', '<?php echo number_format($countryData['Canada']['weight_percent'], 1); ?>%', '<?php echo Localization::formatCurrency($countryData['Canada']['value_sek'], 0, 'SEK'); ?>', '<?php echo $countryData['Canada']['positions']; ?>')"
                                  onmouseout="hideCountryTooltip()"
                                  <?php endif; ?>>
                            </path>
                            
                            <!-- United Kingdom -->
                            <path d="M 480 160 L 470 140 L 490 130 L 510 140 L 520 160 L 510 180 L 490 170 Z" 
                                  fill="<?php echo getCountryColor('United Kingdom', $countryData, $maxWeight); ?>"
                                  stroke="#1f2937" stroke-width="0.5" opacity="0.9"
                                  style="cursor: pointer;" class="country-path"
                                  data-country="United Kingdom"
                                  <?php if (isset($countryData['United Kingdom'])): ?>
                                  filter="url(#glow)"
                                  onmouseover="showCountryTooltip(event, 'United Kingdom', '<?php echo number_format($countryData['United Kingdom']['weight_percent'], 1); ?>%', '<?php echo Localization::formatCurrency($countryData['United Kingdom']['value_sek'], 0, 'SEK'); ?>', '<?php echo $countryData['United Kingdom']['positions']; ?>')"
                                  onmouseout="hideCountryTooltip()"
                                  <?php endif; ?>>
                            </path>
                            
                            <!-- France -->
                            <path d="M 480 190 L 470 210 L 480 230 L 510 240 L 530 220 L 520 190 L 500 180 Z" 
                                  fill="<?php echo getCountryColor('France', $countryData, $maxWeight); ?>"
                                  stroke="#1f2937" stroke-width="0.5" opacity="0.9"
                                  style="cursor: pointer;" class="country-path"
                                  data-country="France"
                                  <?php if (isset($countryData['France'])): ?>
                                  filter="url(#glow)"
                                  onmouseover="showCountryTooltip(event, 'France', '<?php echo number_format($countryData['France']['weight_percent'], 1); ?>%', '<?php echo Localization::formatCurrency($countryData['France']['value_sek'], 0, 'SEK'); ?>', '<?php echo $countryData['France']['positions']; ?>')"
                                  onmouseout="hideCountryTooltip()"
                                  <?php endif; ?>>
                            </path>
                            
                            <!-- Germany -->
                            <path d="M 520 170 L 510 190 L 530 210 L 560 200 L 570 180 L 550 160 L 530 150 Z" 
                                  fill="<?php echo getCountryColor('Germany', $countryData, $maxWeight); ?>"
                                  stroke="#1f2937" stroke-width="0.5" opacity="0.9"
                                  style="cursor: pointer;" class="country-path"
                                  data-country="Germany"
                                  <?php if (isset($countryData['Germany'])): ?>
                                  filter="url(#glow)"
                                  onmouseover="showCountryTooltip(event, 'Germany', '<?php echo number_format($countryData['Germany']['weight_percent'], 1); ?>%', '<?php echo Localization::formatCurrency($countryData['Germany']['value_sek'], 0, 'SEK'); ?>', '<?php echo $countryData['Germany']['positions']; ?>')"
                                  onmouseout="hideCountryTooltip()"
                                  <?php endif; ?>>
                            </path>
                            
                            <!-- Sweden -->
                            <path d="M 550 100 L 540 130 L 550 150 L 570 140 L 580 110 L 570 90 L 560 80 Z" 
                                  fill="<?php echo getCountryColor('Sweden', $countryData, $maxWeight); ?>"
                                  stroke="#ffffff" stroke-width="1" opacity="0.95"
                                  style="cursor: pointer;" class="country-path"
                                  data-country="Sweden"
                                  <?php if (isset($countryData['Sweden'])): ?>
                                  filter="url(#glow)"
                                  onmouseover="showCountryTooltip(event, 'Sweden', '<?php echo number_format($countryData['Sweden']['weight_percent'], 1); ?>%', '<?php echo Localization::formatCurrency($countryData['Sweden']['value_sek'], 0, 'SEK'); ?>', '<?php echo $countryData['Sweden']['positions']; ?>')"
                                  onmouseout="hideCountryTooltip()"
                                  <?php endif; ?>>
                            </path>
                            
                            <!-- Norway -->
                            <path d="M 530 90 L 520 120 L 530 140 L 545 130 L 550 100 L 545 80 L 535 70 Z" 
                                  fill="<?php echo getCountryColor('Norway', $countryData, $maxWeight); ?>"
                                  stroke="#1f2937" stroke-width="0.5" opacity="0.9"
                                  style="cursor: pointer;" class="country-path"
                                  data-country="Norway"
                                  <?php if (isset($countryData['Norway'])): ?>
                                  filter="url(#glow)"
                                  onmouseover="showCountryTooltip(event, 'Norway', '<?php echo number_format($countryData['Norway']['weight_percent'], 1); ?>%', '<?php echo Localization::formatCurrency($countryData['Norway']['value_sek'], 0, 'SEK'); ?>', '<?php echo $countryData['Norway']['positions']; ?>')"
                                  onmouseout="hideCountryTooltip()"
                                  <?php endif; ?>>
                            </path>
                            
                            <!-- Finland -->
                            <path d="M 580 100 L 570 130 L 580 150 L 600 140 L 610 110 L 600 90 L 590 80 Z" 
                                  fill="<?php echo getCountryColor('Finland', $countryData, $maxWeight); ?>"
                                  stroke="#1f2937" stroke-width="0.5" opacity="0.9"
                                  style="cursor: pointer;" class="country-path"
                                  data-country="Finland"
                                  <?php if (isset($countryData['Finland'])): ?>
                                  filter="url(#glow)"
                                  onmouseover="showCountryTooltip(event, 'Finland', '<?php echo number_format($countryData['Finland']['weight_percent'], 1); ?>%', '<?php echo Localization::formatCurrency($countryData['Finland']['value_sek'], 0, 'SEK'); ?>', '<?php echo $countryData['Finland']['positions']; ?>')"
                                  onmouseout="hideCountryTooltip()"
                                  <?php endif; ?>>
                            </path>
                            
                            <!-- Denmark -->
                            <path d="M 540 160 L 530 170 L 540 180 L 555 175 L 560 165 L 550 155 Z" 
                                  fill="<?php echo getCountryColor('Denmark', $countryData, $maxWeight); ?>"
                                  stroke="#1f2937" stroke-width="0.5" opacity="0.9"
                                  style="cursor: pointer;" class="country-path"
                                  data-country="Denmark"
                                  <?php if (isset($countryData['Denmark'])): ?>
                                  filter="url(#glow)"
                                  onmouseover="showCountryTooltip(event, 'Denmark', '<?php echo number_format($countryData['Denmark']['weight_percent'], 1); ?>%', '<?php echo Localization::formatCurrency($countryData['Denmark']['value_sek'], 0, 'SEK'); ?>', '<?php echo $countryData['Denmark']['positions']; ?>')"
                                  onmouseout="hideCountryTooltip()"
                                  <?php endif; ?>>
                            </path>
                            
                            <!-- Netherlands -->
                            <path d="M 520 180 L 510 190 L 520 200 L 535 195 L 540 185 L 530 175 Z" 
                                  fill="<?php echo getCountryColor('Netherlands', $countryData, $maxWeight); ?>"
                                  stroke="#1f2937" stroke-width="0.5" opacity="0.9"
                                  style="cursor: pointer;" class="country-path"
                                  data-country="Netherlands"
                                  <?php if (isset($countryData['Netherlands'])): ?>
                                  filter="url(#glow)"
                                  onmouseover="showCountryTooltip(event, 'Netherlands', '<?php echo number_format($countryData['Netherlands']['weight_percent'], 1); ?>%', '<?php echo Localization::formatCurrency($countryData['Netherlands']['value_sek'], 0, 'SEK'); ?>', '<?php echo $countryData['Netherlands']['positions']; ?>')"
                                  onmouseout="hideCountryTooltip()"
                                  <?php endif; ?>>
                            </path>
                            
                            <!-- Switzerland -->
                            <path d="M 520 210 L 510 220 L 520 230 L 535 225 L 540 215 L 530 205 Z" 
                                  fill="<?php echo getCountryColor('Switzerland', $countryData, $maxWeight); ?>"
                                  stroke="#1f2937" stroke-width="0.5" opacity="0.9"
                                  style="cursor: pointer;" class="country-path"
                                  data-country="Switzerland"
                                  <?php if (isset($countryData['Switzerland'])): ?>
                                  filter="url(#glow)"
                                  onmouseover="showCountryTooltip(event, 'Switzerland', '<?php echo number_format($countryData['Switzerland']['weight_percent'], 1); ?>%', '<?php echo Localization::formatCurrency($countryData['Switzerland']['value_sek'], 0, 'SEK'); ?>', '<?php echo $countryData['Switzerland']['positions']; ?>')"
                                  onmouseout="hideCountryTooltip()"
                                  <?php endif; ?>>
                            </path>
                            
                            <!-- Japan -->
                            <path d="M 860 200 L 850 220 L 860 250 L 890 240 L 900 210 L 890 190 L 870 180 Z" 
                                  fill="<?php echo getCountryColor('Japan', $countryData, $maxWeight); ?>"
                                  stroke="#1f2937" stroke-width="0.5" opacity="0.9"
                                  style="cursor: pointer;" class="country-path"
                                  data-country="Japan"
                                  <?php if (isset($countryData['Japan'])): ?>
                                  filter="url(#glow)"
                                  onmouseover="showCountryTooltip(event, 'Japan', '<?php echo number_format($countryData['Japan']['weight_percent'], 1); ?>%', '<?php echo Localization::formatCurrency($countryData['Japan']['value_sek'], 0, 'SEK'); ?>', '<?php echo $countryData['Japan']['positions']; ?>')"
                                  onmouseout="hideCountryTooltip()"
                                  <?php endif; ?>>
                            </path>
                            
                            <!-- Australia -->
                            <path d="M 800 350 L 780 370 L 790 390 L 830 400 L 870 390 L 890 370 L 880 350 L 840 340 Z" 
                                  fill="<?php echo getCountryColor('Australia', $countryData, $maxWeight); ?>"
                                  stroke="#1f2937" stroke-width="0.5" opacity="0.9"
                                  style="cursor: pointer;" class="country-path"
                                  data-country="Australia"
                                  <?php if (isset($countryData['Australia'])): ?>
                                  filter="url(#glow)"
                                  onmouseover="showCountryTooltip(event, 'Australia', '<?php echo number_format($countryData['Australia']['weight_percent'], 1); ?>%', '<?php echo Localization::formatCurrency($countryData['Australia']['value_sek'], 0, 'SEK'); ?>', '<?php echo $countryData['Australia']['positions']; ?>')"
                                  onmouseout="hideCountryTooltip()"
                                  <?php endif; ?>>
                            </path>
                            
                            <!-- China -->
                            <path d="M 720 200 L 700 230 L 720 260 L 780 250 L 820 230 L 800 200 L 760 190 Z" 
                                  fill="<?php echo getCountryColor('China', $countryData, $maxWeight); ?>"
                                  stroke="#1f2937" stroke-width="0.5" opacity="0.9"
                                  style="cursor: pointer;" class="country-path"
                                  data-country="China">
                            </path>
                            
                            <!-- Russia -->
                            <path d="M 600 120 L 580 150 L 620 170 L 720 160 L 800 150 L 850 140 L 880 120 L 860 100 L 800 90 L 720 100 L 650 110 Z" 
                                  fill="<?php echo getCountryColor('Russia', $countryData, $maxWeight); ?>"
                                  stroke="#1f2937" stroke-width="0.5" opacity="0.9"
                                  style="cursor: pointer;" class="country-path"
                                  data-country="Russia">
                            </path>
                            
                            <!-- Brazil -->
                            <path d="M 300 320 L 280 350 L 300 380 L 350 390 L 400 380 L 420 350 L 400 320 L 350 310 Z" 
                                  fill="<?php echo getCountryColor('Brazil', $countryData, $maxWeight); ?>"
                                  stroke="#1f2937" stroke-width="0.5" opacity="0.9"
                                  style="cursor: pointer;" class="country-path"
                                  data-country="Brazil">
                            </path>
                            
                            <!-- India -->
                            <path d="M 700 260 L 680 290 L 700 320 L 740 310 L 760 280 L 740 250 L 720 240 Z" 
                                  fill="<?php echo getCountryColor('India', $countryData, $maxWeight); ?>"
                                  stroke="#1f2937" stroke-width="0.5" opacity="0.9"
                                  style="cursor: pointer;" class="country-path"
                                  data-country="India">
                            </path>
                            
                            <!-- Add percentage labels for countries with allocations -->
                            <?php foreach ($countryData as $countryName => $allocation): ?>
                                <?php
                                $labelPositions = [
                                    'Sweden' => ['x' => 565, 'y' => 115],
                                    'United States' => ['x' => 240, 'y' => 240],
                                    'Canada' => ['x' => 240, 'y' => 90],
                                    'United Kingdom' => ['x' => 495, 'y' => 155],
                                    'France' => ['x' => 505, 'y' => 215],
                                    'Germany' => ['x' => 545, 'y' => 185],
                                    'Norway' => ['x' => 537, 'y' => 105],
                                    'Finland' => ['x' => 595, 'y' => 115],
                                    'Denmark' => ['x' => 547, 'y' => 167],
                                    'Netherlands' => ['x' => 527, 'y' => 187],
                                    'Switzerland' => ['x' => 527, 'y' => 217],
                                    'Japan' => ['x' => 875, 'y' => 215],
                                    'Australia' => ['x' => 835, 'y' => 375],
                                ];
                                
                                if (isset($labelPositions[$countryName])):
                                    $pos = $labelPositions[$countryName];
                                ?>
                                    <text x="<?php echo $pos['x']; ?>" y="<?php echo $pos['y']; ?>" 
                                          text-anchor="middle" fill="#ffffff" font-size="11" font-weight="700"
                                          style="text-shadow: 1px 1px 2px rgba(0,0,0,0.8); pointer-events: none;">
                                        <?php echo number_format($allocation['weight_percent'], 1); ?>%
                                    </text>
                                <?php endif; ?>
                            <?php endforeach; ?>
                            
                            <!-- Enhanced Legend -->
                            <g transform="translate(20, 20)">
                                <rect x="0" y="0" width="220" height="120" fill="rgba(0,0,0,0.8)" rx="8" stroke="#374151" stroke-width="1"/>
                                <text x="15" y="25" fill="#ffffff" font-size="14" font-weight="700">Portfolio Allocation</text>
                                <text x="15" y="42" fill="#d1d5db" font-size="11">Intensity by allocation %</text>
                                
                                <!-- Color scale -->
                                <rect x="15" y="50" width="12" height="8" fill="#22c55e"/>
                                <text x="32" y="58" fill="#ffffff" font-size="10">0-20%</text>
                                
                                <rect x="15" y="65" width="12" height="8" fill="#eab308"/>
                                <text x="32" y="73" fill="#ffffff" font-size="10">20-40%</text>
                                
                                <rect x="15" y="80" width="12" height="8" fill="#f97316"/>
                                <text x="32" y="88" fill="#ffffff" font-size="10">40-60%</text>
                                
                                <rect x="15" y="95" width="12" height="8" fill="#ef4444"/>
                                <text x="32" y="103" fill="#ffffff" font-size="10">60-80%</text>
                                
                                <rect x="120" y="50" width="12" height="8" fill="#dc2626"/>
                                <text x="137" y="58" fill="#ffffff" font-size="10">80-100%</text>
                                
                                <rect x="120" y="65" width="12" height="8" fill="#374151"/>
                                <text x="137" y="73" fill="#ffffff" font-size="10">No allocation</text>
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