<?php
/**
 * Test script to add the 4 identified unsupported companies to the system
 * This simulates the admin_company_management.php workflow
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/src/utils/DataValidator.php';
require_once __DIR__ . '/src/utils/SimpleDuplicateChecker.php';

echo "PSW 4.0 - Testing Addition of 4 Unsupported Companies\n";
echo "====================================================\n\n";

// The 4 unsupported companies from the test file
$testCompanies = [
    [
        'isin' => 'CZ0008019106',
        'company_name' => 'Test Czech Company',
        'country' => 'Czech Republic',
        'currency' => 'CZK',
        'ticker' => 'TEST1',
        'company_type' => 'stock',
        'dividend_frequency' => 'annual',
        'sector' => 'Technology',
        'branch' => 'Software',
        'market_exchange' => 'Prague Stock Exchange',
        'notes' => 'Added for testing unsupported company workflow'
    ],
    [
        'isin' => 'IE0003290289', 
        'company_name' => 'Test Irish Fund',
        'country' => 'Ireland',
        'currency' => 'EUR',
        'ticker' => 'TEST2',
        'company_type' => 'closed_end_fund',
        'dividend_frequency' => 'quarterly',
        'sector' => 'Financial Services',
        'branch' => 'Investment Funds',
        'market_exchange' => 'Irish Stock Exchange',
        'notes' => 'Added for testing unsupported company workflow'
    ],
    [
        'isin' => 'GB0001990497',
        'company_name' => 'Test UK Investment Trust', 
        'country' => 'United Kingdom',
        'currency' => 'GBP',
        'ticker' => 'TEST3',
        'company_type' => 'closed_end_fund',
        'dividend_frequency' => 'monthly',
        'sector' => 'Financial Services',
        'branch' => 'Investment Trusts',
        'market_exchange' => 'London Stock Exchange',
        'notes' => 'Added for testing unsupported company workflow'
    ],
    [
        'isin' => 'CA33843T1084',
        'company_name' => 'Test Canadian Corporation',
        'country' => 'Canada', 
        'currency' => 'CAD',
        'ticker' => 'TEST4',
        'company_type' => 'stock',
        'dividend_frequency' => 'quarterly',
        'sector' => 'Energy',
        'branch' => 'Oil & Gas',
        'market_exchange' => 'Toronto Stock Exchange',
        'notes' => 'Added for testing unsupported company workflow'
    ]
];

try {
    $foundationDb = Database::getConnection('foundation');
    echo "✓ Database connection successful\n\n";
    
    $addedCount = 0;
    $skippedCount = 0;
    
    foreach ($testCompanies as $i => $company) {
        echo "Processing Company " . ($i + 1) . ": {$company['company_name']}\n";
        echo "ISIN: {$company['isin']}\n";
        
        // 1. Sanitize data
        $sanitizedData = DataValidator::sanitizeManualCompanyData($company);
        echo "✓ Data sanitized\n";
        
        // 2. Validate data
        $validation = DataValidator::validateManualCompanyData($sanitizedData);
        if (!$validation['valid']) {
            echo "❌ Validation failed:\n";
            foreach ($validation['errors'] as $field => $error) {
                echo "   - $field: $error\n";
            }
            echo "\n";
            continue;
        }
        echo "✓ Validation passed\n";
        
        // 3. Check for duplicates
        $sql = "SELECT COUNT(*) as count FROM manual_company_data WHERE isin = ?";
        $stmt = $foundationDb->prepare($sql);
        $stmt->execute([$sanitizedData['isin']]);
        $existing = $stmt->fetch()['count'];
        
        if ($existing > 0) {
            echo "⚠ Company already exists in manual_company_data - skipping\n";
            $skippedCount++;
            echo "\n";
            continue;
        }
        echo "✓ No duplicate found\n";
        
        // 4. Add to database
        try {
            $sql = "INSERT INTO manual_company_data 
                    (isin, ticker, company_name, country, sector, branch, market_exchange, 
                     currency, company_type, dividend_frequency, notes, created_by, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            
            $stmt = $foundationDb->prepare($sql);
            $stmt->execute([
                $sanitizedData['isin'],
                $sanitizedData['ticker'] ?: null,
                $sanitizedData['company_name'],
                $sanitizedData['country'],
                $sanitizedData['sector'] ?: null,
                $sanitizedData['branch'] ?: null,
                $sanitizedData['market_exchange'] ?: null,
                $sanitizedData['currency'] ?: null,
                $sanitizedData['company_type'],
                $sanitizedData['dividend_frequency'],
                $sanitizedData['notes'] ?: null,
                'test_system'
            ]);
            
            echo "✅ Company added successfully!\n";
            $addedCount++;
            
        } catch (Exception $e) {
            echo "❌ Database error: " . $e->getMessage() . "\n";
        }
        
        echo "\n";
    }
    
    echo "====================================================\n";
    echo "Test Results:\n";
    echo "- Companies added: $addedCount\n";
    echo "- Companies skipped (already exist): $skippedCount\n";
    echo "- Total processed: " . count($testCompanies) . "\n";
    
    if ($addedCount > 0) {
        echo "\nVerifying added companies:\n";
        $stmt = $foundationDb->query("
            SELECT isin, ticker, company_name, country, currency, company_type 
            FROM manual_company_data 
            WHERE created_by = 'test_system' 
            ORDER BY created_at DESC 
            LIMIT 4
        ");
        $addedCompanies = $stmt->fetchAll();
        
        foreach ($addedCompanies as $company) {
            echo "✓ {$company['isin']} - {$company['company_name']} ({$company['ticker']})\n";
        }
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

echo "\n====================================================\n";
echo "Test Complete!\n";
?>