<?php
// Create a test CSV file with your exact data in different formats

$testData = [
    'tab_separated.csv' => "payment_date\tisin\tshares_held\tdividend_amount_local\ttax_amount_local\tcurrency_local\tdividend_amount_sek\tnet_dividend_sek\texchange_rate_used\n2015-12-02\tSE0021309614\t37\t23.42\t0\tSEK\t156.18\t132.76\t1",
    
    'comma_separated.csv' => "payment_date,isin,shares_held,dividend_amount_local,tax_amount_local,currency_local,dividend_amount_sek,net_dividend_sek,exchange_rate_used\n2015-12-02,SE0021309614,37,23.42,0,SEK,156.18,132.76,1",
    
    'no_header_tab.csv' => "2015-12-02\tSE0021309614\t37\t23.42\t0\tSEK\t156.18\t132.76\t1",
    
    'your_format.csv' => "2015-12-02\tSE0021309614\t37\t2\t23,42\tSEK\t156,18\t132,76\t1"
];

foreach ($testData as $filename => $content) {
    file_put_contents($filename, $content);
    echo "Created: $filename\n";
    echo "Content: " . str_replace(["\t", "\n"], ["[TAB]", "[NEWLINE]"], $content) . "\n\n";
}

echo "Files created. You can download and test these.\n";
?>

<form method="POST">
<button type="submit" name="create">Create Test Files</button>
</form>

<?php
if (isset($_POST['create'])) {
    foreach ($testData as $filename => $content) {
        file_put_contents($filename, $content);
    }
    echo "<p>Test files created!</p>";
    foreach ($testData as $filename => $content) {
        echo "<p><a href='$filename' download>Download $filename</a></p>";
    }
}
?>