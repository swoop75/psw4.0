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
    
    $fileName = $file['name'];
    $filePath = $file['tmp_name'];
    $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    
    $dividendData = [];
    $warnings = [];
    $errors = [];
    
    if ($fileExt === 'xlsx') {
        // Handle Excel files - convert to CSV first
        $csvPath = convertExcelToCsv($filePath);
        if ($csvPath) {
            $content = file_get_contents($csvPath);
            $delimiter = ','; // Excel exports typically use comma
            unlink($csvPath); // Clean up temp file
        } else {
            $errors[] = "Could not process Excel file. Please save as CSV format.";
        }
        
    } else {
        // Handle CSV/text files
        $content = file_get_contents($filePath);
        
        // Detect delimiter by testing the first few lines
        $delimiter = detectDelimiter($content);
        
        // Parse CSV with detected delimiter
        $lines = str_getcsv($content, "\n");
        $headerProcessed = false;
        $expectedColumns = ['payment_date', 'isin', 'shares_held', 'dividend_amount_local', 'tax_amount_local', 'currency_local', 'dividend_amount_sek', 'net_dividend_sek', 'exchange_rate_used'];
        
        foreach ($lines as $lineNum => $line) {
            if (empty(trim($line))) continue;
            
            $row = str_getcsv($line, $delimiter);
            
            // Skip header row (first non-empty line or any line with text headers)
            if (!$headerProcessed || containsHeaders($row)) {
                $headerProcessed = true;
                continue;
            }
            
            // Clean and process data row
            $cleanRow = array_map('trim', $row);
            
            if (count($cleanRow) >= 4) { // Minimum required columns
                $dividend = [
                    'payment_date' => $cleanRow[0] ?? '',
                    'isin' => $cleanRow[1] ?? '',
                    'shares_held' => parseNumber($cleanRow[2] ?? '0'),
                    'dividend_amount_local' => parseNumber($cleanRow[3] ?? '0'),
                    'tax_amount_local' => parseNumber($cleanRow[4] ?? '0'),
                    'currency_local' => strtoupper(trim($cleanRow[5] ?? 'SEK')),
                    'dividend_amount_sek' => parseNumber($cleanRow[6] ?? '0'),
                    'net_dividend_sek' => parseNumber($cleanRow[7] ?? '0'),
                    'exchange_rate_used' => parseNumber($cleanRow[8] ?? '1')
                ];
                
                // Validate required fields
                if (empty($dividend['payment_date']) || empty($dividend['isin']) || $dividend['shares_held'] <= 0) {
                    $errors[] = "Row " . ($lineNum + 1) . ": Missing required data (date, ISIN, or shares)";
                    continue;
                }
                
                // Validate date format
                if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dividend['payment_date'])) {
                    // Try to convert other date formats
                    $dividend['payment_date'] = convertDateFormat($dividend['payment_date']);
                    if (!$dividend['payment_date']) {
                        $errors[] = "Row " . ($lineNum + 1) . ": Invalid date format";
                        continue;
                    }
                }
                
                // Validate ISIN
                if (strlen($dividend['isin']) !== 12) {
                    $warnings[] = "Row " . ($lineNum + 1) . ": ISIN should be 12 characters (found: " . $dividend['isin'] . ")";
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
                        $warnings[] = "Row " . ($lineNum + 1) . ": Company not found for ISIN " . $dividend['isin'];
                        $dividend['company_name'] = 'Unknown Company';
                        $dividend['ticker'] = '';
                    }
                } catch (Exception $e) {
                    $warnings[] = "Row " . ($lineNum + 1) . ": Database lookup failed";
                    $dividend['company_name'] = 'Unknown';
                    $dividend['ticker'] = '';
                }
                
                // Calculate derived values
                if ($dividend['dividend_amount_sek'] == 0 && $dividend['dividend_amount_local'] > 0 && $dividend['exchange_rate_used'] > 0) {
                    $dividend['dividend_amount_sek'] = $dividend['dividend_amount_local'] * $dividend['exchange_rate_used'];
                }
                
                if ($dividend['net_dividend_sek'] == 0 && $dividend['dividend_amount_sek'] > 0 && $dividend['tax_amount_local'] >= 0) {
                    $taxSek = $dividend['tax_amount_local'] * $dividend['exchange_rate_used'];
                    $dividend['net_dividend_sek'] = $dividend['dividend_amount_sek'] - $taxSek;
                }
                
                // Calculate tax in SEK
                $dividend['tax_amount_sek'] = $dividend['dividend_amount_sek'] - $dividend['net_dividend_sek'];
                
                // Determine completeness
                $dividend['is_complete'] = ($dividend['dividend_amount_sek'] > 0 && $dividend['net_dividend_sek'] >= 0) ? 1 : 0;
                
                $dividendData[] = $dividend;
            } else {
                $warnings[] = "Row " . ($lineNum + 1) . ": Insufficient columns (found " . count($cleanRow) . ")";
            }
        }
    }
    
    echo json_encode([
        'success' => true,
        'total_rows' => count($dividendData),
        'errors' => $errors,
        'warnings' => $warnings,
        'preview_data' => $dividendData,
        'broker_id' => 'minimal',
        'detected_delimiter' => $delimiter ?? 'unknown'
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

// Helper functions
function detectDelimiter($content) {
    $lines = explode("\n", $content);
    $firstLine = $lines[0] ?? '';
    
    // Count occurrences of potential delimiters
    $delimiters = [';' => 0, ',' => 0, "\t" => 0];
    
    foreach ($delimiters as $delimiter => $count) {
        $delimiters[$delimiter] = substr_count($firstLine, $delimiter);
    }
    
    // Return the delimiter with the highest count
    return array_search(max($delimiters), $delimiters);
}

function containsHeaders($row) {
    $headerKeywords = ['payment_date', 'isin', 'shares', 'dividend', 'currency', 'sek'];
    $rowText = strtolower(implode(' ', $row));
    
    foreach ($headerKeywords as $keyword) {
        if (strpos($rowText, $keyword) !== false) {
            return true;
        }
    }
    return false;
}

function parseNumber($value) {
    if (empty($value)) return 0;
    
    // Remove any non-numeric characters except commas, dots, and minus
    $cleaned = preg_replace('/[^\d,.-]/', '', $value);
    
    // Handle European format (comma as decimal separator)
    if (strpos($cleaned, ',') !== false && strpos($cleaned, '.') === false) {
        $cleaned = str_replace(',', '.', $cleaned);
    } elseif (strpos($cleaned, ',') !== false && strpos($cleaned, '.') !== false) {
        // Both comma and dot - assume comma is thousands separator
        $cleaned = str_replace(',', '', $cleaned);
    }
    
    return floatval($cleaned);
}

function convertDateFormat($dateStr) {
    $formats = ['Y-m-d', 'd/m/Y', 'm/d/Y', 'd-m-Y', 'Y/m/d'];
    
    foreach ($formats as $format) {
        $date = DateTime::createFromFormat($format, $dateStr);
        if ($date && $date->format($format) === $dateStr) {
            return $date->format('Y-m-d');
        }
    }
    
    return false;
}

function convertExcelToCsv($excelPath) {
    // Simple Excel to CSV conversion using a basic XML parser
    // This works for simple Excel files without complex formatting
    
    try {
        $zip = new ZipArchive;
        if ($zip->open($excelPath) === TRUE) {
            // Get the worksheet data
            $sharedStrings = [];
            $worksheetData = '';
            
            // Read shared strings
            if (($sharedStringsXml = $zip->getFromName('xl/sharedStrings.xml')) !== false) {
                $xml = simplexml_load_string($sharedStringsXml);
                foreach ($xml->si as $si) {
                    $sharedStrings[] = (string)$si->t;
                }
            }
            
            // Read first worksheet
            if (($worksheetXml = $zip->getFromName('xl/worksheets/sheet1.xml')) !== false) {
                $xml = simplexml_load_string($worksheetXml);
                $csvLines = [];
                
                foreach ($xml->sheetData->row as $row) {
                    $csvRow = [];
                    foreach ($row->c as $cell) {
                        $value = '';
                        if (isset($cell->v)) {
                            $cellValue = (string)$cell->v;
                            // Check if it's a shared string reference
                            if (isset($cell['t']) && $cell['t'] == 's') {
                                $value = isset($sharedStrings[$cellValue]) ? $sharedStrings[$cellValue] : '';
                            } else {
                                $value = $cellValue;
                            }
                        }
                        $csvRow[] = $value;
                    }
                    $csvLines[] = implode(',', array_map(function($field) {
                        return '"' . str_replace('"', '""', $field) . '"';
                    }, $csvRow));
                }
                
                $zip->close();
                
                // Write to temporary CSV file
                $tempCsv = tempnam(sys_get_temp_dir(), 'excel_import_');
                file_put_contents($tempCsv, implode("\n", $csvLines));
                return $tempCsv;
            }
            
            $zip->close();
        }
    } catch (Exception $e) {
        // Excel conversion failed, return false
        return false;
    }
    
    return false;
}
?>