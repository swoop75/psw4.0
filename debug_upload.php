<?php
// Simple debug script to test file upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    $file = $_FILES['csv_file'];
    
    echo "<h3>File Info:</h3>";
    echo "Name: " . $file['name'] . "<br>";
    echo "Size: " . $file['size'] . "<br>";
    echo "Type: " . $file['type'] . "<br>";
    echo "Tmp: " . $file['tmp_name'] . "<br>";
    echo "Error: " . $file['error'] . "<br>";
    
    if ($file['error'] === 0) {
        echo "<h3>File Contents (raw):</h3>";
        echo "<pre>" . htmlspecialchars(file_get_contents($file['tmp_name'])) . "</pre>";
        
        echo "<h3>File Contents (CSV parsed with tabs):</h3>";
        $handle = fopen($file['tmp_name'], 'r');
        $rowNum = 0;
        while (($row = fgetcsv($handle, 0, "\t")) !== false) {
            echo "Row $rowNum: " . print_r($row, true) . "<br>";
            $rowNum++;
            if ($rowNum > 5) break; // Only show first 5 rows
        }
        fclose($handle);
        
        echo "<h3>File Contents (CSV parsed with semicolons):</h3>";
        $handle = fopen($file['tmp_name'], 'r');
        $rowNum = 0;
        while (($row = fgetcsv($handle, 0, ";")) !== false) {
            echo "Row $rowNum: " . print_r($row, true) . "<br>";
            $rowNum++;
            if ($rowNum > 5) break; // Only show first 5 rows
        }
        fclose($handle);
        
        echo "<h3>File Contents (CSV parsed with commas):</h3>";
        $handle = fopen($file['tmp_name'], 'r');
        $rowNum = 0;
        while (($row = fgetcsv($handle, 0, ",")) !== false) {
            echo "Row $rowNum: " . print_r($row, true) . "<br>";
            $rowNum++;
            if ($rowNum > 5) break; // Only show first 5 rows
        }
        fclose($handle);
    }
} else {
    echo '<form method="POST" enctype="multipart/form-data">
            <input type="file" name="csv_file" accept=".csv">
            <button type="submit">Debug Upload</button>
          </form>';
}
?>