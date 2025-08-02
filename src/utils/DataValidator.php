<?php
/**
 * File: src/utils/DataValidator.php
 * Description: Client-side and server-side data validation utilities
 */

class DataValidator {
    
    /**
     * Validate ISIN format and checksum
     * @param string $isin
     * @return array ['valid' => bool, 'error' => string]
     */
    public static function validateISIN($isin) {
        $isin = strtoupper(trim($isin));
        
        // Check basic format
        if (strlen($isin) !== 12) {
            return ['valid' => false, 'error' => 'ISIN must be exactly 12 characters'];
        }
        
        if (!preg_match('/^[A-Z]{2}[A-Z0-9]{9}[0-9]$/', $isin)) {
            return ['valid' => false, 'error' => 'Invalid ISIN format. Must be 2 letters + 9 alphanumeric + 1 digit'];
        }
        
        // Validate country code (first 2 characters)
        $countryCode = substr($isin, 0, 2);
        $validCountryCodes = [
            'AD', 'AE', 'AF', 'AG', 'AI', 'AL', 'AM', 'AO', 'AQ', 'AR', 'AS', 'AT', 'AU', 'AW', 'AX', 'AZ',
            'BA', 'BB', 'BD', 'BE', 'BF', 'BG', 'BH', 'BI', 'BJ', 'BL', 'BM', 'BN', 'BO', 'BQ', 'BR', 'BS',
            'BT', 'BV', 'BW', 'BY', 'BZ', 'CA', 'CC', 'CD', 'CF', 'CG', 'CH', 'CI', 'CK', 'CL', 'CM', 'CN',
            'CO', 'CR', 'CU', 'CV', 'CW', 'CX', 'CY', 'CZ', 'DE', 'DJ', 'DK', 'DM', 'DO', 'DZ', 'EC', 'EE',
            'EG', 'EH', 'ER', 'ES', 'ET', 'FI', 'FJ', 'FK', 'FM', 'FO', 'FR', 'GA', 'GB', 'GD', 'GE', 'GF',
            'GG', 'GH', 'GI', 'GL', 'GM', 'GN', 'GP', 'GQ', 'GR', 'GS', 'GT', 'GU', 'GW', 'GY', 'HK', 'HM',
            'HN', 'HR', 'HT', 'HU', 'ID', 'IE', 'IL', 'IM', 'IN', 'IO', 'IQ', 'IR', 'IS', 'IT', 'JE', 'JM',
            'JO', 'JP', 'KE', 'KG', 'KH', 'KI', 'KM', 'KN', 'KP', 'KR', 'KW', 'KY', 'KZ', 'LA', 'LB', 'LC',
            'LI', 'LK', 'LR', 'LS', 'LT', 'LU', 'LV', 'LY', 'MA', 'MC', 'MD', 'ME', 'MF', 'MG', 'MH', 'MK',
            'ML', 'MM', 'MN', 'MO', 'MP', 'MQ', 'MR', 'MS', 'MT', 'MU', 'MV', 'MW', 'MX', 'MY', 'MZ', 'NA',
            'NC', 'NE', 'NF', 'NG', 'NI', 'NL', 'NO', 'NP', 'NR', 'NU', 'NZ', 'OM', 'PA', 'PE', 'PF', 'PG',
            'PH', 'PK', 'PL', 'PM', 'PN', 'PR', 'PS', 'PT', 'PW', 'PY', 'QA', 'RE', 'RO', 'RS', 'RU', 'RW',
            'SA', 'SB', 'SC', 'SD', 'SE', 'SG', 'SH', 'SI', 'SJ', 'SK', 'SL', 'SM', 'SN', 'SO', 'SR', 'SS',
            'ST', 'SV', 'SX', 'SY', 'SZ', 'TC', 'TD', 'TF', 'TG', 'TH', 'TJ', 'TK', 'TL', 'TM', 'TN', 'TO',
            'TR', 'TT', 'TV', 'TW', 'TZ', 'UA', 'UG', 'UM', 'US', 'UY', 'UZ', 'VA', 'VC', 'VE', 'VG', 'VI',
            'VN', 'VU', 'WF', 'WS', 'YE', 'YT', 'ZA', 'ZM', 'ZW'
        ];
        
        if (!in_array($countryCode, $validCountryCodes)) {
            return ['valid' => false, 'error' => "Invalid country code: $countryCode"];
        }
        
        // Validate checksum using Luhn algorithm (warning only, not blocking)
        if (!self::validateISINChecksum($isin)) {
            // For now, we'll allow ISINs with invalid checksums but log a warning
            // This is because broker data might have slightly different ISIN formats
            error_log("Warning: ISIN $isin has invalid checksum but will be accepted");
        }
        
        return ['valid' => true, 'error' => null];
    }
    
    /**
     * Validate ISIN checksum using Luhn algorithm
     * @param string $isin
     * @return bool
     */
    private static function validateISINChecksum($isin) {
        $numString = '';
        
        // Convert letters to numbers
        for ($i = 0; $i < 11; $i++) {
            $char = $isin[$i];
            if (is_numeric($char)) {
                $numString .= $char;
            } else {
                $numString .= (ord($char) - 55);
            }
        }
        
        // Apply Luhn algorithm
        $sum = 0;
        $pos = 1;
        for ($i = strlen($numString) - 1; $i >= 0; $i--) {
            $digit = (int)$numString[$i];
            if ($pos % 2 == 0) {
                $digit *= 2;
                if ($digit > 9) {
                    $digit -= 9;
                }
            }
            $sum += $digit;
            $pos++;
        }
        
        $checkDigit = (10 - ($sum % 10)) % 10;
        return $checkDigit == (int)$isin[11];
    }
    
    /**
     * Validate company name
     * @param string $name
     * @return array ['valid' => bool, 'error' => string]
     */
    public static function validateCompanyName($name) {
        $name = trim($name);
        
        if (empty($name)) {
            return ['valid' => false, 'error' => 'Company name is required'];
        }
        
        if (strlen($name) < 2) {
            return ['valid' => false, 'error' => 'Company name must be at least 2 characters'];
        }
        
        if (strlen($name) > 255) {
            return ['valid' => false, 'error' => 'Company name must not exceed 255 characters'];
        }
        
        // Check for suspicious patterns
        if (preg_match('/^[0-9]+$/', $name)) {
            return ['valid' => false, 'error' => 'Company name cannot be only numbers'];
        }
        
        return ['valid' => true, 'error' => null];
    }
    
    /**
     * Validate country name
     * @param string $country
     * @return array ['valid' => bool, 'error' => string]
     */
    public static function validateCountry($country) {
        $country = trim($country);
        
        if (empty($country)) {
            return ['valid' => false, 'error' => 'Country is required'];
        }
        
        $validCountries = [
            'Austria', 'Belgium', 'Canada', 'Czech Republic', 'Denmark', 'Finland',
            'France', 'Germany', 'Ireland', 'Italy', 'Netherlands', 'Norway',
            'Poland', 'Spain', 'Sweden', 'Switzerland', 'United Kingdom', 'United States',
            'Australia', 'Japan', 'South Korea', 'Singapore', 'Hong Kong'
        ];
        
        if (!in_array($country, $validCountries)) {
            return ['valid' => false, 'error' => "Unsupported country: $country. Please contact admin to add new countries."];
        }
        
        return ['valid' => true, 'error' => null];
    }
    
    /**
     * Validate currency code
     * @param string $currency
     * @return array ['valid' => bool, 'error' => string]
     */
    public static function validateCurrency($currency) {
        if (empty($currency)) {
            return ['valid' => true, 'error' => null]; // Currency is optional
        }
        
        $currency = strtoupper(trim($currency));
        
        if (strlen($currency) !== 3) {
            return ['valid' => false, 'error' => 'Currency must be 3 characters (ISO code)'];
        }
        
        $validCurrencies = [
            'AUD', 'CAD', 'CHF', 'CZK', 'DKK', 'EUR', 'GBP', 'HKD', 'JPY',
            'NOK', 'PLN', 'SEK', 'SGD', 'USD', 'KRW'
        ];
        
        if (!in_array($currency, $validCurrencies)) {
            return ['valid' => false, 'error' => "Unsupported currency: $currency"];
        }
        
        return ['valid' => true, 'error' => null];
    }
    
    /**
     * Validate ticker symbol
     * @param string $ticker
     * @return array ['valid' => bool, 'error' => string]
     */
    public static function validateTicker($ticker) {
        if (empty($ticker)) {
            return ['valid' => true, 'error' => null]; // Ticker is optional
        }
        
        $ticker = strtoupper(trim($ticker));
        
        if (strlen($ticker) > 20) {
            return ['valid' => false, 'error' => 'Ticker must not exceed 20 characters'];
        }
        
        if (!preg_match('/^[A-Z0-9\.\-\s]+$/', $ticker)) {
            return ['valid' => false, 'error' => 'Ticker can only contain letters, numbers, dots, hyphens, and spaces'];
        }
        
        return ['valid' => true, 'error' => null];
    }
    
    /**
     * Comprehensive validation for manual company data
     * @param array $data
     * @return array ['valid' => bool, 'errors' => array]
     */
    public static function validateManualCompanyData($data) {
        $errors = [];
        
        // Validate ISIN
        $isinValidation = self::validateISIN($data['isin'] ?? '');
        if (!$isinValidation['valid']) {
            $errors['isin'] = $isinValidation['error'];
        }
        
        // Validate company name
        $nameValidation = self::validateCompanyName($data['company_name'] ?? '');
        if (!$nameValidation['valid']) {
            $errors['company_name'] = $nameValidation['error'];
        }
        
        // Validate country
        $countryValidation = self::validateCountry($data['country'] ?? '');
        if (!$countryValidation['valid']) {
            $errors['country'] = $countryValidation['error'];
        }
        
        // Validate currency
        $currencyValidation = self::validateCurrency($data['currency'] ?? '');
        if (!$currencyValidation['valid']) {
            $errors['currency'] = $currencyValidation['error'];
        }
        
        // Validate ticker
        $tickerValidation = self::validateTicker($data['ticker'] ?? '');
        if (!$tickerValidation['valid']) {
            $errors['ticker'] = $tickerValidation['error'];
        }
        
        // Validate enum fields
        $validCompanyTypes = ['stock', 'etf', 'closed_end_fund', 'reit', 'other'];
        if (!empty($data['company_type']) && !in_array($data['company_type'], $validCompanyTypes)) {
            $errors['company_type'] = 'Invalid company type';
        }
        
        $validDividendFrequencies = ['annual', 'semi_annual', 'quarterly', 'monthly', 'irregular', 'none'];
        if (!empty($data['dividend_frequency']) && !in_array($data['dividend_frequency'], $validDividendFrequencies)) {
            $errors['dividend_frequency'] = 'Invalid dividend frequency';
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
    
    /**
     * Check for duplicate company across all data sources
     * @param string $isin
     * @param PDO $db Database connection
     * @return array ['duplicate' => bool, 'source' => string|null]
     */
    public static function checkDuplicateCompany($isin, $db) {
        try {
            // Direct queries with explicit collation handling to avoid stored procedure collation issues
            $duplicateSource = null;
            
            // Check Nordic instruments with explicit collation
            $stmt = $db->prepare("
                SELECT COUNT(*) as count 
                FROM psw_marketdata.nordic_instruments 
                WHERE isin COLLATE utf8mb4_unicode_ci = ? COLLATE utf8mb4_unicode_ci
            ");
            $stmt->execute([$isin]);
            if ($stmt->fetch(PDO::FETCH_ASSOC)['count'] > 0) {
                $duplicateSource = 'Börsdata Nordic';
            } else {
                // Check Global instruments with explicit collation
                $stmt = $db->prepare("
                    SELECT COUNT(*) as count 
                    FROM psw_marketdata.global_instruments 
                    WHERE isin COLLATE utf8mb4_unicode_ci = ? COLLATE utf8mb4_unicode_ci
                ");
                $stmt->execute([$isin]);
                if ($stmt->fetch(PDO::FETCH_ASSOC)['count'] > 0) {
                    $duplicateSource = 'Börsdata Global';
                } else {
                    // Check manual company data
                    $stmt = $db->prepare("
                        SELECT COUNT(*) as count 
                        FROM psw_foundation.manual_company_data 
                        WHERE isin = ?
                    ");
                    $stmt->execute([$isin]);
                    if ($stmt->fetch(PDO::FETCH_ASSOC)['count'] > 0) {
                        $duplicateSource = 'Manual Data';
                    } else {
                        // Check masterlist
                        $stmt = $db->prepare("
                            SELECT COUNT(*) as count 
                            FROM psw_foundation.masterlist 
                            WHERE isin = ?
                        ");
                        $stmt->execute([$isin]);
                        if ($stmt->fetch(PDO::FETCH_ASSOC)['count'] > 0) {
                            $duplicateSource = 'Masterlist';
                        }
                    }
                }
            }
            
            return [
                'duplicate' => !empty($duplicateSource),
                'source' => $duplicateSource
            ];
            
        } catch (Exception $e) {
            return [
                'duplicate' => false,
                'source' => null,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Sanitize and normalize input data
     * @param array $data
     * @return array
     */
    public static function sanitizeManualCompanyData($data) {
        return [
            'isin' => strtoupper(trim($data['isin'] ?? '')),
            'ticker' => strtoupper(trim($data['ticker'] ?? '')),
            'company_name' => trim($data['company_name'] ?? ''),
            'country' => trim($data['country'] ?? ''),
            'sector' => trim($data['sector'] ?? '') ?: null,
            'branch' => trim($data['branch'] ?? '') ?: null,
            'market_exchange' => trim($data['market_exchange'] ?? '') ?: null,
            'currency' => strtoupper(trim($data['currency'] ?? '')) ?: null,
            'company_type' => $data['company_type'] ?? 'stock',
            'dividend_frequency' => $data['dividend_frequency'] ?? 'quarterly',
            'notes' => trim($data['notes'] ?? '') ?: null
        ];
    }
}
?>