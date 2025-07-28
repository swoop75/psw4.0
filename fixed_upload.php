<?php
header('Content-Type: application/json');

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
    
    $content = file_get_contents($file['tmp_name']);
    $lines = preg_split('/\r\n|\r|\n/', $content);
    
    $dividendData = [];
    $warnings = [];
    $errors = [];
    $debugInfo = [];
    
    foreach ($lines as $lineNum => $line) {
        $line = trim($line);
        if (empty($line)) continue;
        
        // Skip header line
        if (stripos($line, 'payment_date') !== false) {
            $debugInfo[] = "Skipped header: $line";
            continue;
        }
        
        // Parse using tabs first, then spaces if tabs don't work
        $parts = explode("\t", $line);
        if (count($parts) < 5) {
            $parts = preg_split('/\s+/', $line);
        }
        
        $debugInfo[] = "Line $lineNum: " . count($parts) . " parts: " . json_encode($parts);
        
        if (count($parts) >= 6) {
            // Based on your data structure, let me try this mapping:
            // 0: payment_date, 1: isin, 2: shares_held, 3: ???, 4: dividend_amount_local, 5: currency_local, 6: dividend_amount_sek, 7: net_dividend_sek, 8: exchange_rate_used
            
            $dividend = [
                'payment_date' => trim($parts[0]),
                'isin' => trim($parts[1]),
                'shares_held' => parseNumber($parts[2]),
                'dividend_amount_local' => parseNumber($parts[4]), // Skip parts[3] which seems to be wrong
                'tax_amount_local' => 0, // Calculate later
                'currency_local' => strtoupper(trim($parts[5])),
                'dividend_amount_sek' => parseNumber($parts[6]),
                'net_dividend_sek' => parseNumber($parts[7]),
                'exchange_rate_used' => parseNumber($parts[8] ?? '1')
            ];
            
            // Validate required fields
            if (empty($dividend['payment_date']) || empty($dividend['isin']) || $dividend['shares_held'] <= 0) {
                $errors[] = "Line $lineNum: Missing required data";
                continue;
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
                    $warnings[] = "Line $lineNum: Company not found for ISIN " . $dividend['isin'];
                    $dividend['company_name'] = 'Unknown Company';
                    $dividend['ticker'] = '';
                }
            } catch (Exception $e) {
                $warnings[] = "Line $lineNum: Database lookup failed";
                $dividend['company_name'] = 'Unknown';
                $dividend['ticker'] = '';
            }
            
            // Calculate tax amount: dividend_sek - net_sek
            $dividend['tax_amount_sek'] = $dividend['dividend_amount_sek'] - $dividend['net_dividend_sek'];
            $dividend['tax_amount_local'] = $dividend['tax_amount_sek'] / ($dividend['exchange_rate_used'] ?: 1);
            
            // Check completeness
            $dividend['is_complete'] = ($dividend['dividend_amount_sek'] > 0) ? 1 : 0;
            
            $dividendData[] = $dividend;
        } else {
            $warnings[] = "Line $lineNum: Insufficient columns (found " . count($parts) . ")";
        }
    }
    
    echo json_encode([
        'success' => true,
        'total_rows' => count($dividendData),
        'errors' => $errors,
        'warnings' => $warnings,
        'preview_data' => $dividendData,
        'debug_info' => $debugInfo,
        'broker_id' => 'minimal'
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
    $cleaned = str_replace(',', '.', trim($value));
    $cleaned = preg_replace('/[^\d.-]/', '', $cleaned);
    
    return floatval($cleaned);
}
?>