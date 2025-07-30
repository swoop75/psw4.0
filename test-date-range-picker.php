<?php
/**
 * File: test-date-range-picker.php
 * Description: Simple test page to verify the date range picker is working
 */

// Simple test without requiring authentication
require_once __DIR__ . '/src/components/DateRangePicker.php';

// Create test date picker
$testPicker = new DateRangePicker('test-date-range', 'test_date', [
    'defaultMonthsBack' => 3,
    'class' => 'form-control'
]);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Date Range Picker Test</title>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Date Range Picker CSS -->
    <link rel="stylesheet" href="assets/css/date-range-picker.css?v=<?php echo time(); ?>">
    
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        
        .test-container {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .test-form {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        
        .form-label {
            font-weight: 600;
            color: #333;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 500;
        }
        
        .btn-primary {
            background: #7c3aed;
            color: white;
        }
        
        .btn-primary:hover {
            background: #6d28d9;
        }
        
        .result {
            margin-top: 20px;
            padding: 15px;
            background: #f0f9ff;
            border: 1px solid #bfdbfe;
            border-radius: 4px;
        }
    </style>
</head>
<body>
    <div class="test-container">
        <h1>Date Range Picker Test</h1>
        <p>This page tests the central date range picker component.</p>
        
        <form method="POST" class="test-form">
            <div class="form-group">
                <?php echo $testPicker->renderField('Test Date Range', ['class' => 'form-group', 'labelClass' => 'form-label']); ?>
            </div>
            
            <button type="submit" class="btn btn-primary">Test Submit</button>
        </form>
        
        <?php if ($_POST): ?>
            <div class="result">
                <h3>Form Data Received:</h3>
                <pre><?php print_r($_POST); ?></pre>
                
                <?php
                $dateRange = DateRangePicker::processFormData($_POST, 'test_date');
                ?>
                
                <h3>Processed Date Range:</h3>
                <ul>
                    <li><strong>From:</strong> <?php echo $dateRange['from'] ?: 'Not set'; ?></li>
                    <li><strong>To:</strong> <?php echo $dateRange['to'] ?: 'Not set'; ?></li>
                    <li><strong>Valid:</strong> <?php echo $dateRange['valid'] ? 'Yes' : 'No'; ?></li>
                    <li><strong>Display:</strong> <?php echo DateRangePicker::formatDateRangeForDisplay($dateRange['from'], $dateRange['to']); ?></li>
                </ul>
                
                <?php if ($dateRange['valid']): ?>
                    <?php $sqlWhere = DateRangePicker::getSqlWhereClause($dateRange, 'created_date'); ?>
                    <p><strong>SQL WHERE clause:</strong><br>
                    <code><?php echo htmlspecialchars($sqlWhere); ?></code></p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Date Range Picker JavaScript -->
    <script src="assets/js/date-range-picker.js?v=<?php echo time(); ?>"></script>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            console.log('Initializing date range picker...');
            
            // Initialize the date range picker
            const dateRangePicker = new DateRangePicker('test-date-range', {
                defaultMonthsBack: 3,
                onApply: function(dateRange) {
                    console.log('Date range applied:', dateRange);
                    alert('Date range selected: ' + dateRange.from + ' to ' + dateRange.to);
                }
            });
            
            console.log('Date range picker initialized!');
        });
    </script>
</body>
</html>