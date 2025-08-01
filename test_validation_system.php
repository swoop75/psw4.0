<?php
/**
 * File: test_validation_system.php
 * Description: Test script for the data validation system
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/src/utils/DataValidator.php';

echo "PSW 4.0 Data Validation System Test\n";
echo "=====================================\n\n";

// Test the 4 identified unsupported companies
$testCompanies = [
    [
        'isin' => 'CZ0008019106',
        'company_name' => 'Test Czech Company',
        'country' => 'Czech Republic',
        'currency' => 'CZK',
        'ticker' => 'TEST1',
        'company_type' => 'stock',
        'dividend_frequency' => 'annual'
    ],
    [
        'isin' => 'IE0003290289', 
        'company_name' => 'Test Irish Company',
        'country' => 'Ireland',
        'currency' => 'EUR',
        'ticker' => 'TEST2',
        'company_type' => 'closed_end_fund',
        'dividend_frequency' => 'quarterly'
    ],
    [
        'isin' => 'GB0001990497',
        'company_name' => 'Test UK Company', 
        'country' => 'United Kingdom',
        'currency' => 'GBP',
        'ticker' => 'TEST3',
        'company_type' => 'closed_end_fund',
        'dividend_frequency' => 'monthly'
    ],
    [
        'isin' => 'CA33843T1084',
        'company_name' => 'Test Canadian Company',
        'country' => 'Canada', 
        'currency' => 'CAD',
        'ticker' => 'TEST4',
        'company_type' => 'stock',
        'dividend_frequency' => 'quarterly'
    ]
];

echo "1. Testing Individual Validation Functions\n";
echo "------------------------------------------\n";

// Test ISIN validation
echo "ISIN Validation Tests:\n";
foreach ($testCompanies as $i => $company) {
    $validation = DataValidator::validateISIN($company['isin']);
    echo "  {$company['isin']}: " . ($validation['valid'] ? 'VALID' : 'INVALID - ' . $validation['error']) . "\n";
}

// Test invalid ISINs
echo "\nInvalid ISIN Tests:\n";
$invalidISINs = ['US037833100X', 'SHORT', 'TOOLONGTOBEANISIN'];
foreach ($invalidISINs as $isin) {
    $validation = DataValidator::validateISIN($isin);
    echo "  $isin: " . ($validation['valid'] ? 'VALID' : 'INVALID - ' . $validation['error']) . "\n";
}

echo "\n2. Testing Company Name Validation\n";
echo "----------------------------------\n";
$testNames = ['Valid Company Name', 'A', '12345', str_repeat('x', 300)];
foreach ($testNames as $name) {
    $validation = DataValidator::validateCompanyName($name);
    $displayName = strlen($name) > 50 ? substr($name, 0, 50) . '...' : $name;
    echo "  '$displayName': " . ($validation['valid'] ? 'VALID' : 'INVALID - ' . $validation['error']) . "\n";
}

echo "\n3. Testing Country Validation\n";
echo "-----------------------------\n";
$testCountries = ['Czech Republic', 'Ireland', 'United Kingdom', 'Canada', 'Fakeland'];
foreach ($testCountries as $country) {
    $validation = DataValidator::validateCountry($country);
    echo "  $country: " . ($validation['valid'] ? 'VALID' : 'INVALID - ' . $validation['error']) . "\n";
}

echo "\n4. Testing Comprehensive Validation\n";
echo "-----------------------------------\n";
foreach ($testCompanies as $i => $company) {
    echo "Company " . ($i + 1) . " ({$company['isin']}):\n";
    $validation = DataValidator::validateManualCompanyData($company);
    if ($validation['valid']) {
        echo "  ✓ ALL VALIDATION PASSED\n";
    } else {
        echo "  ✗ VALIDATION ERRORS:\n";
        foreach ($validation['errors'] as $field => $error) {
            echo "    - $field: $error\n";
        }
    }
    echo "\n";
}

echo "5. Testing Data Sanitization\n";
echo "----------------------------\n";
$messyData = [
    'isin' => ' cz0008019106 ',
    'ticker' => ' test1 ',
    'company_name' => '  Test Company  ',
    'country' => ' Czech Republic ',
    'currency' => ' czk ',
    'company_type' => 'stock',
    'dividend_frequency' => 'annual'
];

echo "Before sanitization:\n";
print_r($messyData);

$sanitized = DataValidator::sanitizeManualCompanyData($messyData);
echo "\nAfter sanitization:\n";
print_r($sanitized);

echo "\n6. Testing Database Duplicate Check\n";
echo "-----------------------------------\n";
try {
    $foundationDb = Database::getConnection('foundation');
    echo "Database connection: SUCCESS\n";
    
    foreach ($testCompanies as $i => $company) {
        $duplicateCheck = DataValidator::checkDuplicateCompany($company['isin'], $foundationDb);
        if (isset($duplicateCheck['error'])) {
            echo "  {$company['isin']}: ERROR - {$duplicateCheck['error']}\n";
        } else {
            echo "  {$company['isin']}: " . ($duplicateCheck['duplicate'] ? "DUPLICATE in {$duplicateCheck['source']}" : "UNIQUE") . "\n";
        }
    }
    
} catch (Exception $e) {
    echo "Database connection: FAILED - " . $e->getMessage() . "\n";
}

echo "\n7. Testing Edge Cases\n";
echo "--------------------\n";

// Test empty data
$emptyData = [];
$validation = DataValidator::validateManualCompanyData($emptyData);
echo "Empty data validation:\n";
if (!$validation['valid']) {
    foreach ($validation['errors'] as $field => $error) {
        echo "  - $field: $error\n";
    }
}

// Test partial data
$partialData = [
    'isin' => 'US0378331005',
    'company_name' => 'Apple Inc'
];
$validation = DataValidator::validateManualCompanyData($partialData);
echo "\nPartial data validation (missing country):\n";
if (!$validation['valid']) {
    foreach ($validation['errors'] as $field => $error) {
        echo "  - $field: $error\n";
    }
}

echo "\n=====================================\n";
echo "Validation System Test Complete!\n";
echo "=====================================\n";
?>