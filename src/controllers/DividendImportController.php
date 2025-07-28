<?php

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../services/DividendCsvParser.php';
require_once __DIR__ . '/../config/BrokerCsvConfig.php';

class DividendImportController {
    
    private $db;
    
    public function __construct() {
        $this->db = new PDO("mysql:host=" . DB_HOST . ";dbname=psw_portfolio", DB_USER, DB_PASS);
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }
    
    public function handleRequest() {
        $method = $_SERVER['REQUEST_METHOD'];
        $action = $_GET['action'] ?? '';
        
        try {
            switch ($method) {
                case 'GET':
                    switch ($action) {
                        case 'brokers':
                            $this->getBrokers();
                            break;
                        case 'preview':
                            $this->previewImport();
                            break;
                        default:
                            $this->returnError('Invalid action', 400);
                    }
                    break;
                    
                case 'POST':
                    switch ($action) {
                        case 'upload':
                            $this->uploadFile();
                            break;
                        case 'import':
                            $this->importDividends();
                            break;
                        default:
                            $this->returnError('Invalid action', 400);
                    }
                    break;
                    
                default:
                    $this->returnError('Method not allowed', 405);
            }
        } catch (Exception $e) {
            $this->returnError($e->getMessage(), 500);
        }
    }
    
    private function getBrokers() {
        // Get brokers from foundation database
        $foundationDb = new PDO("mysql:host=" . DB_HOST . ";dbname=psw_foundation", DB_USER, DB_PASS);
        $foundationDb->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $stmt = $foundationDb->prepare("SELECT broker_id, broker_name FROM brokers ORDER BY broker_name");
        $stmt->execute();
        $brokers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Also include config broker names as fallback
        $configBrokers = BrokerCsvConfig::getBrokerNames();
        
        $this->returnJson([
            'brokers' => $brokers,
            'config_brokers' => $configBrokers
        ]);
    }
    
    private function uploadFile() {
        if (!isset($_FILES['csv_file']) || !isset($_POST['broker_id'])) {
            $this->returnError('Missing file or broker_id', 400);
        }
        
        $brokerId = (int)$_POST['broker_id'];
        $file = $_FILES['csv_file'];
        
        // Validate broker ID
        if (!BrokerCsvConfig::isValidBroker($brokerId)) {
            $this->returnError('Invalid broker ID', 400);
        }
        
        // Validate file
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $this->returnError('File upload error: ' . $file['error'], 400);
        }
        
        if ($file['size'] > UPLOAD_MAX_SIZE) {
            $this->returnError('File too large. Maximum size: ' . UPLOAD_MAX_SIZE . ' bytes', 400);
        }
        
        $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($fileExtension, ALLOWED_UPLOAD_TYPES)) {
            $this->returnError('Invalid file type. Allowed: ' . implode(', ', ALLOWED_UPLOAD_TYPES), 400);
        }
        
        // Create upload directory if it doesn't exist
        $uploadDir = __DIR__ . '/../../uploads/dividends/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        // Generate unique filename
        $filename = 'dividend_import_' . $brokerId . '_' . date('Y-m-d_H-i-s') . '.' . $fileExtension;
        $filePath = $uploadDir . $filename;
        
        if (!move_uploaded_file($file['tmp_name'], $filePath)) {
            $this->returnError('Failed to save uploaded file', 500);
        }
        
        // Parse CSV file
        try {
            $parser = new DividendCsvParser($brokerId);
            $result = $parser->parseCsvFile($filePath);
            
            // Store parsed data in session for preview
            session_start();
            $_SESSION['dividend_import_data'] = $result;
            $_SESSION['dividend_import_file'] = $filePath;
            
            $this->returnJson([
                'success' => true,
                'filename' => $filename,
                'total_rows' => $result['total_rows'],
                'errors' => $result['errors'],
                'warnings' => $result['warnings'],
                'preview_data' => array_slice($result['dividends'], 0, 5) // First 5 rows for preview
            ]);
            
        } catch (Exception $e) {
            unlink($filePath); // Clean up file on error
            $this->returnError('CSV parsing error: ' . $e->getMessage(), 400);
        }
    }
    
    private function previewImport() {
        session_start();
        
        if (!isset($_SESSION['dividend_import_data'])) {
            $this->returnError('No import data found. Please upload a file first.', 400);
        }
        
        $data = $_SESSION['dividend_import_data'];
        $page = (int)($_GET['page'] ?? 1);
        $limit = (int)($_GET['limit'] ?? 50);
        $offset = ($page - 1) * $limit;
        
        $dividends = array_slice($data['dividends'], $offset, $limit);
        
        $this->returnJson([
            'dividends' => $dividends,
            'total_rows' => $data['total_rows'],
            'errors' => $data['errors'],
            'warnings' => $data['warnings'],
            'page' => $page,
            'limit' => $limit,
            'total_pages' => ceil($data['total_rows'] / $limit)
        ]);
    }
    
    private function importDividends() {
        session_start();
        
        if (!isset($_SESSION['dividend_import_data'])) {
            $this->returnError('No import data found. Please upload a file first.', 400);
        }
        
        $data = $_SESSION['dividend_import_data'];
        $dividends = $data['dividends'];
        
        if (empty($dividends)) {
            $this->returnError('No valid dividends found to import', 400);
        }
        
        // Check for duplicates
        $duplicates = $this->checkForDuplicates($dividends);
        
        if (!empty($duplicates) && !($_POST['ignore_duplicates'] ?? false)) {
            $this->returnJson([
                'duplicates_found' => true,
                'duplicates' => $duplicates,
                'message' => 'Duplicates found. Set ignore_duplicates=true to proceed.'
            ]);
            return;
        }
        
        // Start transaction
        $this->db->beginTransaction();
        
        try {
            $imported = 0;
            $skipped = 0;
            $errors = [];
            
            $stmt = $this->db->prepare("
                INSERT INTO log_dividends (
                    payment_date, isin, ticker, shares_held, dividend_amount_local, 
                    tax_amount_local, currency_local, dividend_amount_sek, tax_amount_sek, 
                    net_dividend_sek, tax_rate_percent, broker_id, portfolio_account_group_id,
                    dividend_type_id, distribution_classification_id, currency_id, 
                    is_complete, incomplete_fields, related_corporate_action_id, 
                    exchange_rate_used, notes
                ) VALUES (
                    :payment_date, :isin, :ticker, :shares_held, :dividend_amount_local,
                    :tax_amount_local, :currency_local, :dividend_amount_sek, :tax_amount_sek,
                    :net_dividend_sek, :tax_rate_percent, :broker_id, :portfolio_account_group_id,
                    :dividend_type_id, :distribution_classification_id, :currency_id,
                    :is_complete, :incomplete_fields, :related_corporate_action_id,
                    :exchange_rate_used, :notes
                )
            ");
            
            foreach ($dividends as $dividend) {
                try {
                    // Skip duplicates if found
                    if ($this->isDuplicate($dividend) && ($_POST['ignore_duplicates'] ?? false)) {
                        $skipped++;
                        continue;
                    }
                    
                    $stmt->execute($dividend);
                    $imported++;
                    
                } catch (PDOException $e) {
                    $errors[] = "Row with ISIN {$dividend['isin']}: " . $e->getMessage();
                }
            }
            
            if (empty($errors)) {
                $this->db->commit();
                
                // Clean up session and temporary file
                if (isset($_SESSION['dividend_import_file']) && file_exists($_SESSION['dividend_import_file'])) {
                    unlink($_SESSION['dividend_import_file']);
                }
                unset($_SESSION['dividend_import_data']);
                unset($_SESSION['dividend_import_file']);
                
                $this->returnJson([
                    'success' => true,
                    'imported' => $imported,
                    'skipped' => $skipped,
                    'total_processed' => $imported + $skipped,
                    'message' => "Successfully imported {$imported} dividends" . 
                                ($skipped > 0 ? " (skipped {$skipped} duplicates)" : "")
                ]);
                
            } else {
                $this->db->rollback();
                $this->returnError('Import failed with errors: ' . implode('; ', $errors), 400);
            }
            
        } catch (Exception $e) {
            $this->db->rollback();
            $this->returnError('Import failed: ' . $e->getMessage(), 500);
        }
    }
    
    private function checkForDuplicates($dividends) {
        $potentialDuplicates = [];
        
        foreach ($dividends as $dividend) {
            if ($this->isDuplicate($dividend)) {
                $potentialDuplicates[] = [
                    'isin' => $dividend['isin'],
                    'payment_date' => $dividend['payment_date'],
                    'shares_held' => $dividend['shares_held'],
                    'dividend_amount_sek' => $dividend['dividend_amount_sek']
                ];
            }
        }
        
        return $potentialDuplicates;
    }
    
    private function isDuplicate($dividend) {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM log_dividends 
            WHERE isin = :isin 
            AND payment_date = :payment_date 
            AND broker_id = :broker_id
            AND ABS(shares_held - :shares_held) < 0.0001
        ");
        
        $stmt->execute([
            'isin' => $dividend['isin'],
            'payment_date' => $dividend['payment_date'],
            'broker_id' => $dividend['broker_id'],
            'shares_held' => $dividend['shares_held']
        ]);
        
        return $stmt->fetchColumn() > 0;
    }
    
    private function returnJson($data) {
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }
    
    private function returnError($message, $code = 400) {
        http_response_code($code);
        header('Content-Type: application/json');
        echo json_encode(['error' => $message]);
        exit;
    }
}

// Handle request if called directly
if (basename($_SERVER['PHP_SELF']) === 'DividendImportController.php') {
    $controller = new DividendImportController();
    $controller->handleRequest();
}