<?php
/**
 * File: api/validate_company.php  
 * Description: AJAX endpoint for validating company data
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/utils/DataValidator.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON input']);
    exit;
}

try {
    $response = [];
    
    // Sanitize input
    $sanitizedData = DataValidator::sanitizeManualCompanyData($input);
    
    // Validate all fields
    $validation = DataValidator::validateManualCompanyData($sanitizedData);
    $response['validation'] = $validation;
    
    // Check for duplicates if ISIN is valid
    if (isset($sanitizedData['isin']) && !empty($sanitizedData['isin'])) {
        $foundationDb = Database::getConnection('foundation');
        $duplicateCheck = DataValidator::checkDuplicateCompany($sanitizedData['isin'], $foundationDb);
        $response['duplicate_check'] = $duplicateCheck;
    }
    
    // Return sanitized data for confirmation
    $response['sanitized_data'] = $sanitizedData;
    
    echo json_encode($response);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>