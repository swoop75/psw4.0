<?php

require_once __DIR__ . '/../config/BrokerCsvConfig.php';

class DividendCsvParser {
    
    private $brokerId;
    private $config;
    private $errors = [];
    private $warnings = [];
    
    public function __construct($brokerId) {
        $this->brokerId = $brokerId;
        $this->config = BrokerCsvConfig::getBrokerConfig($brokerId);
        
        if (!$this->config) {
            throw new InvalidArgumentException("Invalid broker ID: {$brokerId}");
        }
    }
    
    public function parseCsvFile($filePath) {
        if (!file_exists($filePath)) {
            throw new InvalidArgumentException("File not found: {$filePath}");
        }
        
        $this->errors = [];
        $this->warnings = [];
        
        $handle = fopen($filePath, 'r');
        if (!$handle) {
            throw new RuntimeException("Could not open file: {$filePath}");
        }
        
        $csvFormat = $this->config['csv_format'];
        $columnMapping = $this->config['column_mapping'];
        $requiredColumns = $this->config['required_columns'];
        
        // Skip header rows
        for ($i = 0; $i < $csvFormat['skip_header_rows']; $i++) {
            fgetcsv($handle, 0, $csvFormat['delimiter'], $csvFormat['enclosure'], $csvFormat['escape']);
        }
        
        // Read header row to map columns
        $headerRow = fgetcsv($handle, 0, $csvFormat['delimiter'], $csvFormat['enclosure'], $csvFormat['escape']);
        if (!$headerRow) {
            fclose($handle);
            throw new RuntimeException("Could not read header row from CSV file");
        }
        
        $columnIndexes = $this->mapColumnIndexes($headerRow, $columnMapping);
        $this->validateRequiredColumns($columnIndexes, $requiredColumns);
        
        $dividends = [];
        $rowNumber = $csvFormat['skip_header_rows'] + 2; // +1 for header, +1 for 1-based counting
        
        while (($row = fgetcsv($handle, 0, $csvFormat['delimiter'], $csvFormat['enclosure'], $csvFormat['escape'])) !== false) {
            try {
                $dividend = $this->parseRow($row, $columnIndexes, $rowNumber);
                if ($dividend) {
                    $dividends[] = $dividend;
                }
            } catch (Exception $e) {
                $this->errors[] = "Row {$rowNumber}: " . $e->getMessage();
            }
            $rowNumber++;
        }
        
        fclose($handle);
        
        return [
            'dividends' => $dividends,
            'errors' => $this->errors,
            'warnings' => $this->warnings,
            'total_rows' => count($dividends),
            'broker_id' => $this->brokerId
        ];
    }
    
    private function mapColumnIndexes($headerRow, $columnMapping) {
        $indexes = [];
        
        foreach ($columnMapping as $dbColumn => $csvColumn) {
            $index = array_search($csvColumn, $headerRow);
            if ($index !== false) {
                $indexes[$dbColumn] = $index;
            }
        }
        
        return $indexes;
    }
    
    private function validateRequiredColumns($columnIndexes, $requiredColumns) {
        $missing = [];
        
        foreach ($requiredColumns as $column) {
            if (!isset($columnIndexes[$column])) {
                $missing[] = $column;
            }
        }
        
        if (!empty($missing)) {
            throw new RuntimeException("Missing required columns: " . implode(', ', $missing));
        }
    }
    
    private function parseRow($row, $columnIndexes, $rowNumber) {
        $csvFormat = $this->config['csv_format'];
        $dividend = [
            'broker_id' => $this->brokerId,
            'portfolio_account_group_id' => null, // Will be set during import
            'dividend_type_id' => null, // Will be set during import
            'distribution_classification_id' => null, // Will be set during import
            'currency_id' => null, // Will be set during import
            'is_complete' => 1,
            'incomplete_fields' => null,
            'related_corporate_action_id' => null,
            'notes' => null
        ];
        
        // Parse each mapped column
        foreach ($columnIndexes as $dbColumn => $csvIndex) {
            if (isset($row[$csvIndex])) {
                $value = trim($row[$csvIndex]);
                $dividend[$dbColumn] = $this->parseValue($dbColumn, $value, $csvFormat, $rowNumber);
            }
        }
        
        // Validate required fields
        if (empty($dividend['payment_date']) || empty($dividend['isin']) || empty($dividend['shares_held'])) {
            throw new Exception("Missing required data in row");
        }
        
        // Calculate derived fields
        $this->calculateDerivedFields($dividend, $rowNumber);
        
        return $dividend;
    }
    
    private function parseValue($columnName, $value, $csvFormat, $rowNumber) {
        if (empty($value)) {
            return null;
        }
        
        switch ($columnName) {
            case 'payment_date':
                return $this->parseDate($value, $csvFormat['date_format'], $rowNumber);
                
            case 'shares_held':
            case 'dividend_amount_local':
            case 'tax_amount_local':
            case 'dividend_amount_sek':
            case 'tax_amount_sek':
            case 'net_dividend_sek':
            case 'exchange_rate_used':
                return $this->parseDecimal($value, $csvFormat, $rowNumber);
                
            case 'isin':
                return $this->validateIsin($value, $rowNumber);
                
            case 'currency_local':
                return $this->validateCurrency($value, $rowNumber);
                
            default:
                return $value;
        }
    }
    
    private function parseDate($value, $format, $rowNumber) {
        $date = DateTime::createFromFormat($format, $value);
        if (!$date) {
            throw new Exception("Invalid date format in row {$rowNumber}: {$value}");
        }
        return $date->format('Y-m-d');
    }
    
    private function parseDecimal($value, $csvFormat, $rowNumber) {
        // Remove thousand separators
        if (!empty($csvFormat['thousand_separator'])) {
            $value = str_replace($csvFormat['thousand_separator'], '', $value);
        }
        
        // Convert decimal separator to dot
        if ($csvFormat['decimal_separator'] !== '.') {
            $value = str_replace($csvFormat['decimal_separator'], '.', $value);
        }
        
        // Remove any non-numeric characters except dot and minus
        $value = preg_replace('/[^0-9.-]/', '', $value);
        
        if (!is_numeric($value)) {
            throw new Exception("Invalid numeric value in row {$rowNumber}: {$value}");
        }
        
        return (float)$value;
    }
    
    private function validateIsin($value, $rowNumber) {
        $isin = strtoupper(trim($value));
        
        if (strlen($isin) !== 12) {
            $this->warnings[] = "Row {$rowNumber}: ISIN length is not 12 characters: {$isin}";
        }
        
        if (!preg_match('/^[A-Z]{2}[A-Z0-9]{9}[0-9]$/', $isin)) {
            $this->warnings[] = "Row {$rowNumber}: ISIN format may be invalid: {$isin}";
        }
        
        return $isin;
    }
    
    private function validateCurrency($value, $rowNumber) {
        $currency = strtoupper(trim($value));
        
        if (strlen($currency) !== 3) {
            $this->warnings[] = "Row {$rowNumber}: Currency code length is not 3 characters: {$currency}";
        }
        
        return $currency;
    }
    
    private function calculateDerivedFields(&$dividend, $rowNumber) {
        // Calculate tax rate percentage if possible
        if (isset($dividend['dividend_amount_local']) && isset($dividend['tax_amount_local']) && 
            $dividend['dividend_amount_local'] > 0) {
            $dividend['tax_rate_percent'] = ($dividend['tax_amount_local'] / $dividend['dividend_amount_local']) * 100;
        }
        
        // Calculate net dividend SEK if not provided
        if (!isset($dividend['net_dividend_sek']) && 
            isset($dividend['dividend_amount_sek']) && isset($dividend['tax_amount_sek'])) {
            $dividend['net_dividend_sek'] = $dividend['dividend_amount_sek'] - $dividend['tax_amount_sek'];
        }
        
        // Calculate exchange rate if not provided
        if (!isset($dividend['exchange_rate_used']) && 
            isset($dividend['dividend_amount_local']) && isset($dividend['dividend_amount_sek']) &&
            $dividend['dividend_amount_local'] > 0) {
            $dividend['exchange_rate_used'] = $dividend['dividend_amount_sek'] / $dividend['dividend_amount_local'];
        }
        
        // Mark incomplete if missing key calculations
        $incomplete = [];
        if (!isset($dividend['dividend_amount_sek']) || $dividend['dividend_amount_sek'] <= 0) {
            $incomplete[] = 'dividend_amount_sek';
        }
        if (!isset($dividend['tax_amount_sek'])) {
            $incomplete[] = 'tax_amount_sek';
        }
        if (!isset($dividend['net_dividend_sek'])) {
            $incomplete[] = 'net_dividend_sek';
        }
        
        if (!empty($incomplete)) {
            $dividend['is_complete'] = 0;
            $dividend['incomplete_fields'] = implode(',', $incomplete);
            $this->warnings[] = "Row {$rowNumber}: Incomplete data - missing: " . implode(', ', $incomplete);
        }
    }
    
    public function getErrors() {
        return $this->errors;
    }
    
    public function getWarnings() {
        return $this->warnings;
    }
    
    public function hasErrors() {
        return !empty($this->errors);
    }
    
    public function hasWarnings() {
        return !empty($this->warnings);
    }
}