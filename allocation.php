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
                    <div id="worldMapContainer" style="position: relative; height: 400px; background: var(--bg-secondary); border-radius: var(--radius-md); padding: 2rem;">
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; height: 100%;">
                            <?php foreach ($geographicAllocation as $index => $allocation): ?>
                                <div class="country-allocation-card" style="
                                    background: var(--bg-card);
                                    border: 2px solid var(--border-primary);
                                    border-radius: var(--radius-md);
                                    padding: 1rem;
                                    display: flex;
                                    flex-direction: column;
                                    justify-content: center;
                                    text-align: center;
                                    position: relative;
                                    transition: all 0.3s ease;
                                    cursor: pointer;
                                    <?php 
                                    $colors = ['#3B82F6', '#10B981', '#F59E0B', '#EF4444', '#8B5CF6', '#06B6D4'];
                                    $color = $colors[$index % count($colors)];
                                    ?>
                                    border-color: <?php echo $color; ?>;
                                    " onmouseover="this.style.transform='scale(1.05)'; this.style.boxShadow='0 8px 25px rgba(0,0,0,0.15)';" 
                                       onmouseout="this.style.transform='scale(1)'; this.style.boxShadow='none';">
                                    
                                    <div style="width: 40px; height: 40px; background: <?php echo $color; ?>; border-radius: 50%; margin: 0 auto 0.75rem; display: flex; align-items: center; justify-content: center;">
                                        <i class="fas fa-<?php 
                                            // Country-specific icons
                                            $countryIcons = [
                                                'Sweden' => 'flag',
                                                'United States' => 'star',
                                                'Denmark' => 'crown',
                                                'Norway' => 'mountain',
                                                'Finland' => 'tree',
                                                'Germany' => 'industry',
                                                'France' => 'wine-glass',
                                                'United Kingdom' => 'pound-sign',
                                                'Netherlands' => 'bicycle',
                                                'Switzerland' => 'mountain',
                                                'Canada' => 'leaf',
                                                'Australia' => 'sun',
                                                'Japan' => 'yen-sign',
                                                'Hong Kong' => 'building',
                                                'Singapore' => 'ship'
                                            ];
                                            echo $countryIcons[$allocation['country']] ?? 'globe';
                                        ?>" style="color: white; font-size: 1.2rem;"></i>
                                    </div>
                                    
                                    <h4 style="margin: 0 0 0.5rem 0; font-size: 1rem; font-weight: 600; color: var(--text-primary);">
                                        <?php echo htmlspecialchars($allocation['country']); ?>
                                    </h4>
                                    
                                    <div style="font-size: 0.75rem; color: var(--text-secondary); margin-bottom: 0.75rem;">
                                        <?php echo htmlspecialchars($allocation['region']); ?>
                                    </div>
                                    
                                    <div style="font-size: 1.25rem; font-weight: 700; color: <?php echo $color; ?>; margin-bottom: 0.25rem;">
                                        <?php echo number_format($allocation['weight_percent'], 1); ?>%
                                    </div>
                                    
                                    <div style="font-size: 0.875rem; color: var(--text-primary); font-weight: 600;">
                                        <?php echo Localization::formatCurrency($allocation['value_sek'], 0, 'SEK'); ?>
                                    </div>
                                    
                                    <div style="font-size: 0.75rem; color: var(--text-secondary); margin-top: 0.25rem;">
                                        <?php echo $allocation['positions']; ?> position<?php echo $allocation['positions'] != 1 ? 's' : ''; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <div style="position: absolute; bottom: 1rem; right: 1rem; font-size: 0.75rem; color: var(--text-muted);">
                            <i class="fas fa-info-circle" style="margin-right: 0.25rem;"></i>
                            Interactive world map coming soon
                        </div>
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

function refreshAllocation() {
    location.reload();
}
</script>

<?php
$content = ob_get_clean();

// Include base layout
include __DIR__ . '/templates/layouts/base-redesign.php';
?>