<?php
header('Content-Type: application/json');
session_start();

require_once 'config/config.php';
require_once 'config/database.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Only POST method allowed');
    }
    
    if (!isset($_FILES['csv_file'])) {
        throw new Exception('No file uploaded');
    }
    
    $file = $_FILES['csv_file'];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('File upload failed');
    }
    
    $filePath = $file['tmp_name'];
    $dividendData = [];
    $warnings = [];
    $errors = [];
    $debugInfo = [];
    
    // Try different delimiters to auto-detect (semicolon first since it's most common in your data)
    $delimiters = [";", ",", "\t"];
    $bestDelimiter = ";"; // Default to semicolon
    $maxColumns = 0;
    
    // Auto-detect delimiter by counting columns in header row
    $handle = fopen($filePath, 'r');
    $firstLine = fgets($handle); // Header
    fclose($handle);
    
    // Remove BOM if present
    $firstLine = str_replace("\xEF\xBB\xBF", '', $firstLine);
    
    foreach ($delimiters as $delimiter) {
        $parts = str_getcsv(trim($firstLine), $delimiter);
        $debugInfo[] = "Testing delimiter '" . ($delimiter === "\t" ? "TAB" : ($delimiter === "," ? "COMMA" : "SEMICOLON")) . "': " . count($parts) . " columns";
        if (count($parts) > $maxColumns) {
            $maxColumns = count($parts);
            $bestDelimiter = $delimiter;
        }
    }
    
    // If no good delimiter found, force semicolon for your data format
    if ($maxColumns < 5) {
        $bestDelimiter = ";";
        $maxColumns = count(str_getcsv(trim($firstLine), ";"));
        $debugInfo[] = "Auto-detection failed, forcing SEMICOLON delimiter";
    }
    
    $debugInfo[] = "Auto-detected delimiter: " . ($bestDelimiter === "\t" ? "TAB" : ($bestDelimiter === "," ? "COMMA" : "SEMICOLON"));
    $debugInfo[] = "Expected columns: $maxColumns";
    
    // Now parse the CSV properly
    $handle = fopen($filePath, 'r');
    $headerRow = fgetcsv($handle, 0, $bestDelimiter);
    
    // Remove BOM from first header if present
    if ($headerRow && isset($headerRow[0])) {
        $headerRow[0] = str_replace("\xEF\xBB\xBF", '', $headerRow[0]);
    }
    
    $debugInfo[] = "Header columns (" . count($headerRow) . "): " . json_encode($headerRow);
    
    // Expected column names (flexible matching)
    $expectedColumns = [
        'payment_date' => ['payment_date', 'date', 'pay_date'],
        'isin' => ['isin', 'isin_code'],
        'broker' => ['broker', 'broker_name'],
        'shares_held' => ['shares_held', 'shares', 'quantity'],
        'dividend_amount_local' => ['dividend_amount_local', 'dividend_local', 'dividend'],
        'tax_amount_local' => ['tax_amount_local', 'tax_local', 'tax'],
        'currency_local' => ['currency_local', 'currency', 'curr'],
        'dividend_amount_sek' => ['dividend_amount_sek', 'dividend_sek'],
        'net_dividend_sek' => ['net_dividend_sek', 'net_sek'],
        'exchange_rate_used' => ['exchange_rate_used', 'exchange_rate', 'rate'],
        'portfolio_account_group' => ['portfolio_account_group', 'account_group', 'account', 'portfolio_group']
    ];
    
    // Map header positions
    $columnMap = [];
    foreach ($expectedColumns as $fieldName => $possibleNames) {
        foreach ($headerRow as $index => $headerName) {
            $headerLower = strtolower(trim($headerName));
            if (in_array($headerLower, $possibleNames)) {
                $columnMap[$fieldName] = $index;
                break;
            }
        }
    }
    
    $debugInfo[] = "Column mapping: " . json_encode($columnMap);
    
    // Read data rows
    $rowNum = 1;
    while (($row = fgetcsv($handle, 0, $bestDelimiter)) !== false) {
        $rowNum++;
        
        // Skip empty rows
        if (empty(array_filter($row))) continue;
        
        $debugInfo[] = "Row $rowNum (" . count($row) . " cols): " . json_encode($row);
        
        // Extract values using column mapping
        $dividend = [
            'payment_date' => isset($columnMap['payment_date']) ? trim($row[$columnMap['payment_date']] ?? '') : '',
            'isin' => isset($columnMap['isin']) ? trim($row[$columnMap['isin']] ?? '') : '',
            'broker' => isset($columnMap['broker']) ? trim($row[$columnMap['broker']] ?? '') : '',
            'shares_held' => isset($columnMap['shares_held']) ? parseNumber($row[$columnMap['shares_held']] ?? '0') : 0,
            'dividend_amount_local' => isset($columnMap['dividend_amount_local']) ? parseNumber($row[$columnMap['dividend_amount_local']] ?? '0') : 0,
            'tax_amount_local' => isset($columnMap['tax_amount_local']) ? parseNumber($row[$columnMap['tax_amount_local']] ?? '0') : 0,
            'currency_local' => isset($columnMap['currency_local']) ? strtoupper(trim($row[$columnMap['currency_local']] ?? 'SEK')) : 'SEK',
            'dividend_amount_sek' => isset($columnMap['dividend_amount_sek']) ? parseNumber($row[$columnMap['dividend_amount_sek']] ?? '0') : 0,
            'net_dividend_sek' => isset($columnMap['net_dividend_sek']) ? parseNumber($row[$columnMap['net_dividend_sek']] ?? '0') : 0,
            'exchange_rate_used' => isset($columnMap['exchange_rate_used']) ? parseNumber($row[$columnMap['exchange_rate_used']] ?? '1') : 1,
            'portfolio_account_group' => isset($columnMap['portfolio_account_group']) ? trim($row[$columnMap['portfolio_account_group']] ?? '') : ''
        ];
        
        // Validate required fields
        if (empty($dividend['payment_date']) || empty($dividend['isin']) || $dividend['shares_held'] <= 0) {
            $errors[] = "Row $rowNum: Missing required data (date: '{$dividend['payment_date']}', isin: '{$dividend['isin']}', shares: {$dividend['shares_held']})";
            continue;
        }
        
        // Validate ISIN
        if (strlen($dividend['isin']) !== 12) {
            $warnings[] = "Row $rowNum: ISIN should be 12 characters (found: " . strlen($dividend['isin']) . ")";
        }
        
        // Look up company from masterlist
        try {
            $foundationDb = Database::getConnection('foundation');
            $stmt = $foundationDb->prepare("SELECT name, ticker FROM masterlist WHERE isin = ?");
            $stmt->execute([$dividend['isin']]);
            $company = $stmt->fetch();
            
            if ($company) {
                $dividend['company_name'] = $company['name'];
                $dividend['ticker'] = $company['ticker'];
            } else {
                $warnings[] = "Row $rowNum: Company not found for ISIN " . $dividend['isin'];
                $dividend['company_name'] = 'Unknown Company';
                $dividend['ticker'] = '';
            }
        } catch (Exception $e) {
            $warnings[] = "Row $rowNum: Database lookup failed";
            $dividend['company_name'] = 'Unknown';
            $dividend['ticker'] = '';
        }
        
        // Look up broker_id from broker name
        $dividend['broker_id'] = null;
        if (!empty($dividend['broker'])) {
            try {
                $stmt = $foundationDb->prepare("SELECT broker_id FROM brokers WHERE broker_name = ?");
                $stmt->execute([$dividend['broker']]);
                $broker = $stmt->fetch();
                
                if ($broker) {
                    $dividend['broker_id'] = $broker['broker_id'];
                } else {
                    $warnings[] = "Row $rowNum: Broker '{$dividend['broker']}' not found in database";
                }
            } catch (Exception $e) {
                $warnings[] = "Row $rowNum: Broker lookup failed";
            }
        }
        
        // Look up or create portfolio account group
        $dividend['portfolio_account_group_id'] = null;
        if (!empty($dividend['portfolio_account_group'])) {
            try {
                $stmt = $foundationDb->prepare("SELECT portfolio_account_group_id FROM portfolio_account_groups WHERE portfolio_group_name = ?");
                $stmt->execute([$dividend['portfolio_account_group']]);
                $accountGroup = $stmt->fetch();
                
                if ($accountGroup) {
                    $dividend['portfolio_account_group_id'] = $accountGroup['portfolio_account_group_id'];
                } else {
                    $warnings[] = "Row $rowNum: Account group '{$dividend['portfolio_account_group']}' not found. Will need to be created.";
                }
            } catch (Exception $e) {
                $warnings[] = "Row $rowNum: Account group lookup failed";
            }
        }
        
        // Calculate derived values
        if ($dividend['dividend_amount_sek'] == 0 && $dividend['dividend_amount_local'] > 0 && $dividend['exchange_rate_used'] > 0) {
            $dividend['dividend_amount_sek'] = $dividend['dividend_amount_local'] * $dividend['exchange_rate_used'];
        }
        
        if ($dividend['net_dividend_sek'] == 0 && $dividend['dividend_amount_sek'] > 0) {
            $taxSek = $dividend['tax_amount_local'] * $dividend['exchange_rate_used'];
            $dividend['net_dividend_sek'] = $dividend['dividend_amount_sek'] - $taxSek;
        }
        
        // Calculate tax in SEK
        $dividend['tax_amount_sek'] = $dividend['dividend_amount_sek'] - $dividend['net_dividend_sek'];
        
        // Check completeness
        $dividend['is_complete'] = ($dividend['dividend_amount_sek'] > 0) ? 1 : 0;
        
        $dividendData[] = $dividend;
    }
    
    fclose($handle);
    
    // Store data in session for import, including broker_id
    $_SESSION['dividend_import_data'] = $dividendData;
    $_SESSION['selected_broker_id'] = $_POST['broker_id'] ?? 'minimal';
    
    echo json_encode([
        'success' => true,
        'total_rows' => count($dividendData),
        'errors' => $errors,
        'warnings' => $warnings,
        'preview_data' => $dividendData,
        'debug_info' => $debugInfo,
        'broker_id' => $_SESSION['selected_broker_id']
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

function parseNumber($value) {
    if (empty($value)) return 0;
    
    // Handle European format (comma as decimal separator)
    $cleaned = trim($value);
    
    // If it contains both comma and dot, assume comma is thousands separator
    if (strpos($cleaned, ',') !== false && strpos($cleaned, '.') !== false) {
        $cleaned = str_replace(',', '', $cleaned);
    } else if (strpos($cleaned, ',') !== false) {
        // Only comma, treat as decimal separator
        $cleaned = str_replace(',', '.', $cleaned);
    }
    
    // Remove any non-numeric characters except dots and minus
    $cleaned = preg_replace('/[^\d.-]/', '', $cleaned);
    
    return floatval($cleaned);
}
?>