<?php
/**
 * File: src/utils/Localization.php
 * Description: Localization utility for PSW 4.0 - handles number, date, and time formatting
 */

class Localization {
    
    // Format presets
    const FORMATS = [
        'US' => [
            'name' => 'United States',
            'number_thousands_separator' => ',',
            'number_decimal_separator' => '.',
            'date_format' => 'M d, Y',
            'date_format_short' => 'm/d/Y',
            'time_format' => 'g:i A',
            'datetime_format' => 'M d, Y g:i A',
            'currency_symbol' => '$',
            'currency_position' => 'before'
        ],
        'EU' => [
            'name' => 'European',
            'number_thousands_separator' => '.',
            'number_decimal_separator' => ',',
            'date_format' => 'd M Y',
            'date_format_short' => 'd/m/Y',
            'time_format' => 'H:i',
            'datetime_format' => 'd M Y H:i',
            'currency_symbol' => '€',
            'currency_position' => 'after'
        ],
        'SE' => [
            'name' => 'Swedish',
            'number_thousands_separator' => ' ',
            'number_decimal_separator' => ',',
            'date_format' => 'Y-m-d',
            'date_format_short' => 'Y-m-d',
            'time_format' => 'H:i',
            'datetime_format' => 'Y-m-d H:i',
            'currency_symbol' => 'kr',
            'currency_position' => 'after'
        ],
        'UK' => [
            'name' => 'United Kingdom',
            'number_thousands_separator' => ',',
            'number_decimal_separator' => '.',
            'date_format' => 'd M Y',
            'date_format_short' => 'd/m/Y',
            'time_format' => 'H:i',
            'datetime_format' => 'd M Y H:i',
            'currency_symbol' => '£',
            'currency_position' => 'before'
        ],
        'DE' => [
            'name' => 'German',
            'number_thousands_separator' => '.',
            'number_decimal_separator' => ',',
            'date_format' => 'd.m.Y',
            'date_format_short' => 'd.m.Y',
            'time_format' => 'H:i',
            'datetime_format' => 'd.m.Y H:i',
            'currency_symbol' => '€',
            'currency_position' => 'after'
        ],
        'FR' => [
            'name' => 'French',
            'number_thousands_separator' => ' ',
            'number_decimal_separator' => ',',
            'date_format' => 'd M Y',
            'date_format_short' => 'd/m/Y',
            'time_format' => 'H:i',
            'datetime_format' => 'd M Y H:i',
            'currency_symbol' => '€',
            'currency_position' => 'after'
        ]
    ];
    
    private static $userFormat = null;
    
    /**
     * Get user's preferred format settings
     */
    private static function getUserFormat() {
        if (self::$userFormat === null) {
            // Get from session or database
            if (isset($_SESSION['user_format_preference'])) {
                $formatKey = $_SESSION['user_format_preference'];
            } else {
                // Try to get from database
                try {
                    require_once __DIR__ . '/../middleware/Auth.php';
                    if (Auth::isLoggedIn()) {
                        $db = Database::getConnection('foundation');
                        $stmt = $db->prepare("SELECT format_preference FROM users WHERE user_id = ?");
                        $stmt->execute([Auth::getUserId()]);
                        $result = $stmt->fetch();
                        $formatKey = $result['format_preference'] ?? 'US';
                        $_SESSION['user_format_preference'] = $formatKey;
                    } else {
                        $formatKey = 'US';
                    }
                } catch (Exception $e) {
                    $formatKey = 'US'; // Default fallback
                }
            }
            
            self::$userFormat = self::FORMATS[$formatKey] ?? self::FORMATS['US'];
        }
        
        return self::$userFormat;
    }
    
    /**
     * Format a number according to user preferences
     */
    public static function formatNumber($number, $decimals = 0) {
        if ($number === null || $number === '') {
            return '';
        }
        
        $format = self::getUserFormat();
        return number_format(
            (float)$number, 
            $decimals, 
            $format['number_decimal_separator'], 
            $format['number_thousands_separator']
        );
    }
    
    /**
     * Format currency according to user preferences
     */
    public static function formatCurrency($amount, $decimals = 2, $currencySymbol = null) {
        if ($amount === null || $amount === '') {
            return '';
        }
        
        $format = self::getUserFormat();
        $symbol = $currencySymbol ?? $format['currency_symbol'];
        $formattedAmount = self::formatNumber($amount, $decimals);
        
        if ($format['currency_position'] === 'before') {
            return $symbol . $formattedAmount;
        } else {
            return $formattedAmount . ' ' . $symbol;
        }
    }
    
    /**
     * Format date according to user preferences
     */
    public static function formatDate($date, $short = false) {
        if (empty($date)) {
            return '';
        }
        
        $format = self::getUserFormat();
        $formatString = $short ? $format['date_format_short'] : $format['date_format'];
        
        if ($date instanceof DateTime) {
            return $date->format($formatString);
        }
        
        try {
            $dateObj = new DateTime($date);
            return $dateObj->format($formatString);
        } catch (Exception $e) {
            return $date; // Return original if parsing fails
        }
    }
    
    /**
     * Format time according to user preferences
     */
    public static function formatTime($time) {
        if (empty($time)) {
            return '';
        }
        
        $format = self::getUserFormat();
        
        if ($time instanceof DateTime) {
            return $time->format($format['time_format']);
        }
        
        try {
            $timeObj = new DateTime($time);
            return $timeObj->format($format['time_format']);
        } catch (Exception $e) {
            return $time; // Return original if parsing fails
        }
    }
    
    /**
     * Format datetime according to user preferences
     */
    public static function formatDateTime($datetime) {
        if (empty($datetime)) {
            return '';
        }
        
        $format = self::getUserFormat();
        
        if ($datetime instanceof DateTime) {
            return $datetime->format($format['datetime_format']);
        }
        
        try {
            $datetimeObj = new DateTime($datetime);
            return $datetimeObj->format($format['datetime_format']);
        } catch (Exception $e) {
            return $datetime; // Return original if parsing fails
        }
    }
    
    /**
     * Get all available formats for user selection
     */
    public static function getAvailableFormats() {
        return self::FORMATS;
    }
    
    /**
     * Get current user's format key
     */
    public static function getCurrentFormatKey() {
        if (isset($_SESSION['user_format_preference'])) {
            return $_SESSION['user_format_preference'];
        }
        
        try {
            require_once __DIR__ . '/../middleware/Auth.php';
            if (Auth::isLoggedIn()) {
                $db = Database::getConnection('foundation');
                $stmt = $db->prepare("SELECT format_preference FROM users WHERE user_id = ?");
                $stmt->execute([Auth::getUserId()]);
                $result = $stmt->fetch();
                return $result['format_preference'] ?? 'US';
            }
        } catch (Exception $e) {
            // Fallback to US format
        }
        
        return 'US';
    }
    
    /**
     * Update user's format preference
     */
    public static function updateUserFormat($formatKey) {
        if (!isset(self::FORMATS[$formatKey])) {
            throw new InvalidArgumentException('Invalid format key');
        }
        
        try {
            require_once __DIR__ . '/../middleware/Auth.php';
            if (Auth::isLoggedIn()) {
                $db = Database::getConnection('foundation');
                $stmt = $db->prepare("UPDATE users SET format_preference = ? WHERE user_id = ?");
                $stmt->execute([$formatKey, Auth::getUserId()]);
                
                // Update session
                $_SESSION['user_format_preference'] = $formatKey;
                
                // Clear cached format
                self::clearFormatCache();
                
                return true;
            }
        } catch (Exception $e) {
            throw new Exception('Failed to update format preference: ' . $e->getMessage());
        }
        
        return false;
    }
    
    /**
     * Clear cached format data
     */
    public static function clearFormatCache() {
        self::$userFormat = null;
    }
    
    /**
     * Get format examples for display
     */
    public static function getFormatExamples($formatKey) {
        if (!isset(self::FORMATS[$formatKey])) {
            return null;
        }
        
        $format = self::FORMATS[$formatKey];
        $sampleDate = new DateTime('2024-03-15 14:30:00');
        
        return [
            'number' => number_format(1234.56, 2, $format['number_decimal_separator'], $format['number_thousands_separator']),
            'large_number' => number_format(1234567, 0, $format['number_decimal_separator'], $format['number_thousands_separator']),
            'date' => $sampleDate->format($format['date_format']),
            'date_short' => $sampleDate->format($format['date_format_short']),
            'time' => $sampleDate->format($format['time_format']),
            'datetime' => $sampleDate->format($format['datetime_format']),
            'currency' => ($format['currency_position'] === 'before' ? $format['currency_symbol'] . '1.234,56' : '1.234,56 ' . $format['currency_symbol'])
        ];
    }
}
?>