<?php

class BrokerCsvConfig {
    
    private static $brokerConfigs = [
        1 => [
            'name' => 'Broker 1',
            'csv_format' => [
                'delimiter' => ',',
                'enclosure' => '"',
                'escape' => '\\',
                'skip_header_rows' => 1,
                'date_format' => 'Y-m-d',
                'decimal_separator' => '.',
                'thousand_separator' => '',
                'encoding' => 'UTF-8'
            ],
            'column_mapping' => [
                'payment_date' => 'Payment Date',
                'isin' => 'ISIN',
                'ticker' => 'Symbol',
                'shares_held' => 'Shares',
                'dividend_amount_local' => 'Dividend Amount',
                'tax_amount_local' => 'Tax Amount',
                'currency_local' => 'Currency',
                'dividend_amount_sek' => 'Dividend SEK',
                'tax_amount_sek' => 'Tax SEK',
                'net_dividend_sek' => 'Net SEK',
                'exchange_rate_used' => 'FX Rate'
            ],
            'required_columns' => ['payment_date', 'isin', 'shares_held', 'dividend_amount_local']
        ],
        
        2 => [
            'name' => 'Broker 2',
            'csv_format' => [
                'delimiter' => ';',
                'enclosure' => '"',
                'escape' => '\\',
                'skip_header_rows' => 1,
                'date_format' => 'd/m/Y',
                'decimal_separator' => ',',
                'thousand_separator' => ' ',
                'encoding' => 'UTF-8'
            ],
            'column_mapping' => [
                'payment_date' => 'Ex-Date',
                'isin' => 'ISIN Code',
                'ticker' => 'Ticker',
                'shares_held' => 'Quantity',
                'dividend_amount_local' => 'Gross Amount',
                'tax_amount_local' => 'Withholding Tax',
                'currency_local' => 'Currency',
                'dividend_amount_sek' => 'Gross SEK',
                'tax_amount_sek' => 'Tax SEK',
                'net_dividend_sek' => 'Net Amount SEK',
                'exchange_rate_used' => 'Exchange Rate'
            ],
            'required_columns' => ['payment_date', 'isin', 'shares_held', 'dividend_amount_local']
        ],
        
        3 => [
            'name' => 'Broker 3',
            'csv_format' => [
                'delimiter' => ',',
                'enclosure' => '"',
                'escape' => '\\',
                'skip_header_rows' => 2,
                'date_format' => 'm/d/Y',
                'decimal_separator' => '.',
                'thousand_separator' => ',',
                'encoding' => 'UTF-8'
            ],
            'column_mapping' => [
                'payment_date' => 'Date',
                'isin' => 'ISIN',
                'ticker' => 'Symbol',
                'shares_held' => 'Shares',
                'dividend_amount_local' => 'Amount',
                'tax_amount_local' => 'Tax',
                'currency_local' => 'Curr',
                'dividend_amount_sek' => 'Amount SEK',
                'tax_amount_sek' => 'Tax SEK',
                'net_dividend_sek' => 'Net SEK',
                'exchange_rate_used' => 'Rate'
            ],
            'required_columns' => ['payment_date', 'isin', 'shares_held', 'dividend_amount_local']
        ],
        
        4 => [
            'name' => 'Broker 4',
            'csv_format' => [
                'delimiter' => '\t',
                'enclosure' => '"',
                'escape' => '\\',
                'skip_header_rows' => 1,
                'date_format' => 'Y-m-d',
                'decimal_separator' => '.',
                'thousand_separator' => '',
                'encoding' => 'UTF-8'
            ],
            'column_mapping' => [
                'payment_date' => 'PaymentDate',
                'isin' => 'ISIN',
                'ticker' => 'Symbol',
                'shares_held' => 'SharesHeld',
                'dividend_amount_local' => 'DividendLocal',
                'tax_amount_local' => 'TaxLocal',
                'currency_local' => 'Currency',
                'dividend_amount_sek' => 'DividendSEK',
                'tax_amount_sek' => 'TaxSEK',
                'net_dividend_sek' => 'NetSEK',
                'exchange_rate_used' => 'FXRate'
            ],
            'required_columns' => ['payment_date', 'isin', 'shares_held', 'dividend_amount_local']
        ],
        
        5 => [
            'name' => 'Broker 5',
            'csv_format' => [
                'delimiter' => ',',
                'enclosure' => '"',
                'escape' => '\\',
                'skip_header_rows' => 1,
                'date_format' => 'd-m-Y',
                'decimal_separator' => '.',
                'thousand_separator' => '',
                'encoding' => 'UTF-8'
            ],
            'column_mapping' => [
                'payment_date' => 'Payment_Date',
                'isin' => 'ISIN_Code',
                'ticker' => 'Ticker_Symbol',
                'shares_held' => 'Shares_Quantity',
                'dividend_amount_local' => 'Dividend_Amount_Local',
                'tax_amount_local' => 'Tax_Amount_Local',
                'currency_local' => 'Local_Currency',
                'dividend_amount_sek' => 'Dividend_Amount_SEK',
                'tax_amount_sek' => 'Tax_Amount_SEK',
                'net_dividend_sek' => 'Net_Dividend_SEK',
                'exchange_rate_used' => 'Exchange_Rate'
            ],
            'required_columns' => ['payment_date', 'isin', 'shares_held', 'dividend_amount_local']
        ]
    ];

    public static function getBrokerConfig($brokerId) {
        return isset(self::$brokerConfigs[$brokerId]) ? self::$brokerConfigs[$brokerId] : null;
    }

    public static function getAllBrokerConfigs() {
        return self::$brokerConfigs;
    }

    public static function getBrokerNames() {
        $names = [];
        foreach (self::$brokerConfigs as $id => $config) {
            $names[$id] = $config['name'];
        }
        return $names;
    }

    public static function isValidBroker($brokerId) {
        return isset(self::$brokerConfigs[$brokerId]);
    }

    public static function getRequiredColumns($brokerId) {
        $config = self::getBrokerConfig($brokerId);
        return $config ? $config['required_columns'] : [];
    }

    public static function getColumnMapping($brokerId) {
        $config = self::getBrokerConfig($brokerId);
        return $config ? $config['column_mapping'] : [];
    }

    public static function getCsvFormat($brokerId) {
        $config = self::getBrokerConfig($brokerId);
        return $config ? $config['csv_format'] : [];
    }
}