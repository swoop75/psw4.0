<?php
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    $file = $_FILES['csv_file'];
    $content = file_get_contents($file['tmp_name']);
    
    // Test different parsing methods
    $result = [
        'raw_content' => $content,
        'lines' => [],
        'parsing_tests' => []
    ];
    
    // Split into lines
    $lines = preg_split('/\r\n|\r|\n/', $content);
    foreach ($lines as $i => $line) {
        $result['lines'][] = "Line $i: '" . $line . "'";
    }
    
    // Test different delimiters
    $delimiters = ["\t" => 'tab', ',' => 'comma', ';' => 'semicolon', ' ' => 'space'];
    
    foreach ($delimiters as $delimiter => $name) {
        $result['parsing_tests'][$name] = [];
        foreach ($lines as $i => $line) {
            if (!empty(trim($line))) {
                if ($delimiter === ' ') {
                    // For space, use preg_split to handle multiple spaces
                    $parsed = preg_split('/\s+/', trim($line));
                } else {
                    $parsed = str_getcsv($line, $delimiter);
                }
                $result['parsing_tests'][$name][] = "Line $i (".count($parsed)." cols): " . json_encode($parsed);
            }
        }
    }
    
    echo json_encode($result, JSON_PRETTY_PRINT);
} else {
    echo json_encode(['error' => 'No file uploaded']);
}
?>