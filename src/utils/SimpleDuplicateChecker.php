<?php
/**
 * File: src/utils/SimpleDuplicateChecker.php
 * Description: Simple duplicate checker that avoids collation issues
 */

class SimpleDuplicateChecker {
    
    /**
     * Check for duplicates with collation-safe queries
     * @param string $isin
     * @param PDO $foundationDb
     * @return array ['duplicate' => bool, 'source' => string|null]
     */
    public static function checkDuplicate($isin, $foundationDb) {
        try {
            // Only check manual_company_data table to avoid cross-database collation issues
            $stmt = $foundationDb->prepare("
                SELECT COUNT(*) as count 
                FROM manual_company_data 
                WHERE isin = ?
            ");
            $stmt->execute([$isin]);
            $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
            
            if ($count > 0) {
                return [
                    'duplicate' => true,
                    'source' => 'Manual Data'
                ];
            }
            
            // For now, we'll only check the manual table to avoid collation issues
            // The unique constraint on the table will prevent actual duplicates
            return [
                'duplicate' => false,
                'source' => null
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
     * Check if ISIN exists in Börsdata (informational only)
     * @param string $isin
     * @param PDO $foundationDb  
     * @return array ['exists' => bool, 'source' => string|null]
     */
    public static function checkBorsdataExists($isin, $foundationDb) {
        try {
            // Try to check if company exists in Börsdata but don't fail if collation issues
            $marketDataDb = Database::getConnection('marketdata');
            
            // Check Nordic first
            $stmt = $marketDataDb->prepare("SELECT COUNT(*) as count FROM nordic_instruments WHERE isin = ?");
            $stmt->execute([$isin]);
            if ($stmt->fetch(PDO::FETCH_ASSOC)['count'] > 0) {
                return ['exists' => true, 'source' => 'Börsdata Nordic'];
            }
            
            // Check Global  
            $stmt = $marketDataDb->prepare("SELECT COUNT(*) as count FROM global_instruments WHERE isin = ?");
            $stmt->execute([$isin]);
            if ($stmt->fetch(PDO::FETCH_ASSOC)['count'] > 0) {
                return ['exists' => true, 'source' => 'Börsdata Global'];
            }
            
            return ['exists' => false, 'source' => null];
            
        } catch (Exception $e) {
            // If there are connection or collation issues, just return unknown
            return ['exists' => false, 'source' => null, 'error' => $e->getMessage()];
        }
    }
}
?>