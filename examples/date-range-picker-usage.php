<?php
/**
 * File: examples/date-range-picker-usage.php
 * Description: Usage examples for the central date range picker component
 */

require_once '../src/components/DateRangePicker.php';

// Example 1: Basic usage in a form
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Date Range Picker Examples</title>
    
    <!-- Required stylesheets -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/date-range-picker.css">
    
    <style>
        body { 
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; 
            max-width: 1200px; 
            margin: 0 auto; 
            padding: 20px; 
            background: #f5f5f5;
        }
        
        .example-section {
            background: white;
            padding: 30px;
            margin: 20px 0;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .example-title {
            color: #333;
            border-bottom: 2px solid #007bff;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            color: #333;
        }
        
        .required {
            color: #dc3545;
        }
        
        .code-example {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 4px;
            padding: 15px;
            margin: 10px 0;
            font-family: 'Courier New', monospace;
            font-size: 13px;
            overflow-x: auto;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
        }
        
        .btn-primary {
            background: #007bff;
            color: white;
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .result-display {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
            padding: 15px;
            border-radius: 4px;
            margin-top: 10px;
        }
    </style>
</head>
<body>
    <h1>Date Range Picker - Usage Examples</h1>
    <p>Complete examples showing how to use the central date range picker component.</p>

    <!-- Example 1: Basic Form Usage -->
    <div class="example-section">
        <h2 class="example-title">Example 1: Basic Form Usage</h2>
        <p>Standard date range picker with default settings (current month + 3 months back).</p>
        
        <form method="POST" action="">
            <?php
            $basicPicker = new DateRangePicker('basic-date-range', 'date_range');
            echo $basicPicker->renderField('Date Range');
            ?>
            
            <button type="submit" class="btn btn-primary">Submit</button>
        </form>
        
        <div class="code-example">
&lt;?php
$basicPicker = new DateRangePicker('basic-date-range', 'date_range');
echo $basicPicker->renderField('Date Range');
?&gt;
        </div>
    </div>

    <!-- Example 2: Custom Configuration -->
    <div class="example-section">
        <h2 class="example-title">Example 2: Custom Configuration</h2>
        <p>Date range picker with custom settings and required validation.</p>
        
        <form method="POST" action="">
            <?php
            $customPicker = new DateRangePicker('custom-date-range', 'custom_range', [
                'defaultMonthsBack' => 6,
                'required' => true,
                'autoApply' => false,
                'showPresets' => true
            ]);
            echo $customPicker->renderField('Custom Date Range *', ['class' => 'form-group custom']);
            ?>
            
            <button type="submit" class="btn btn-primary">Submit Custom</button>
        </form>
        
        <div class="code-example">
&lt;?php
$customPicker = new DateRangePicker('custom-date-range', 'custom_range', [
    'defaultMonthsBack' => 6,
    'required' => true,
    'autoApply' => false,
    'showPresets' => true
]);
echo $customPicker->renderField('Custom Date Range *');
?&gt;
        </div>
    </div>

    <!-- Example 3: Programmatic Control -->
    <div class="example-section">
        <h2 class="example-title">Example 3: Programmatic Control</h2>
        <p>Date range picker with preset values and JavaScript control.</p>
        
        <form method="POST" action="">
            <?php
            $controlPicker = new DateRangePicker('control-date-range', 'control_range');
            $controlPicker->setValues('2025-01-01', '2025-03-31');
            echo $controlPicker->renderField('Controlled Date Range');
            ?>
            
            <div style="margin: 10px 0;">
                <button type="button" class="btn btn-secondary" onclick="setThisYear()">Set This Year</button>
                <button type="button" class="btn btn-secondary" onclick="setLastMonth()">Set Last Month</button>
                <button type="button" class="btn btn-secondary" onclick="resetToDefault()">Reset to Default</button>
            </div>
            
            <button type="submit" class="btn btn-primary">Submit Controlled</button>
        </form>
        
        <div class="code-example">
&lt;?php
$controlPicker = new DateRangePicker('control-date-range', 'control_range');
$controlPicker->setValues('2025-01-01', '2025-03-31');
echo $controlPicker->renderField('Controlled Date Range');
?&gt;

&lt;script&gt;
function setThisYear() {
    const picker = window.controlDateRangePicker;
    if (picker) {
        const year = new Date().getFullYear();
        picker.setDateRange(`${year}-01-01`, `${year}-12-31`);
    }
}
&lt;/script&gt;
        </div>
    </div>

    <!-- Example 4: Integration with Existing Form -->
    <div class="example-section">
        <h2 class="example-title">Example 4: Replace Existing Date Inputs</h2>
        <p>How to replace existing separate date inputs with the unified date range picker.</p>
        
        <h4>Old approach (separate inputs):</h4>
        <div class="code-example">
&lt;div class="filter-group"&gt;
    &lt;label for="date_from"&gt;From Date&lt;/label&gt;
    &lt;input type="date" name="date_from" id="date_from" class="form-control"&gt;
&lt;/div&gt;

&lt;div class="filter-group"&gt;
    &lt;label for="date_to"&gt;To Date&lt;/label&gt;
    &lt;input type="date" name="date_to" id="date_to" class="form-control"&gt;
&lt;/div&gt;
        </div>
        
        <h4>New approach (unified picker):</h4>
        <form method="POST" action="">
            <?php
            $unifiedPicker = new DateRangePicker('unified-date-range', 'date', [
                'class' => 'form-control'
            ]);
            
            // If processing form data
            if ($_POST) {
                $dateRange = DateRangePicker::processFormData($_POST, 'date');
                $unifiedPicker->setValues($dateRange['from'], $dateRange['to']);
            }
            
            echo $unifiedPicker->renderField('Date Range Filter');
            ?>
            
            <button type="submit" class="btn btn-primary">Apply Filter</button>
        </form>
        
        <div class="code-example">
&lt;?php
$unifiedPicker = new DateRangePicker('unified-date-range', 'date');

// Process form data
if ($_POST) {
    $dateRange = DateRangePicker::processFormData($_POST, 'date');
    $unifiedPicker->setValues($dateRange['from'], $dateRange['to']);
}

echo $unifiedPicker->renderField('Date Range Filter');
?&gt;
        </div>
    </div>

    <!-- Form Processing Example -->
    <?php if ($_POST): ?>
    <div class="example-section">
        <h2 class="example-title">Form Processing Results</h2>
        
        <?php
        foreach ($_POST as $key => $value) {
            if (str_contains($key, '_from') || str_contains($key, '_to')) {
                $baseName = str_replace(['_from', '_to'], '', $key);
                if (!isset($processed[$baseName])) {
                    $dateRange = DateRangePicker::processFormData($_POST, $baseName);
                    $processed[$baseName] = $dateRange;
                    
                    echo '<div class="result-display">';
                    echo '<h4>' . ucfirst(str_replace('_', ' ', $baseName)) . ' Range:</h4>';
                    echo '<p><strong>From:</strong> ' . ($dateRange['from'] ?: 'Not set') . '</p>';
                    echo '<p><strong>To:</strong> ' . ($dateRange['to'] ?: 'Not set') . '</p>';
                    echo '<p><strong>Valid:</strong> ' . ($dateRange['valid'] ? 'Yes' : 'No') . '</p>';
                    echo '<p><strong>Display:</strong> ' . DateRangePicker::formatDateRangeForDisplay($dateRange['from'], $dateRange['to']) . '</p>';
                    
                    if ($dateRange['valid']) {
                        $sqlExample = DateRangePicker::getSqlWhereClause($dateRange, 'created_date');
                        echo '<p><strong>SQL WHERE:</strong> <code>' . htmlspecialchars($sqlExample) . '</code></p>';
                    }
                    echo '</div>';
                }
            }
        }
        ?>
    </div>
    <?php endif; ?>

    <!-- Usage Documentation -->
    <div class="example-section">
        <h2 class="example-title">Usage Documentation</h2>
        
        <h3>Basic Usage</h3>
        <ol>
            <li>Include the CSS and JavaScript files</li>
            <li>Create a new DateRangePicker instance</li>
            <li>Render it in your form</li>
            <li>Process the form data</li>
        </ol>
        
        <h3>Available Options</h3>
        <ul>
            <li><code>defaultMonthsBack</code> - Number of months to go back for default range (default: 3)</li>
            <li><code>format</code> - Date format for PHP (default: 'Y-m-d')</li>
            <li><code>autoApply</code> - Automatically apply selection (default: false)</li>
            <li><code>showPresets</code> - Show quick preset buttons (default: true)</li>
            <li><code>required</code> - Make the field required (default: false)</li>
            <li><code>class</code> - Additional CSS classes</li>
        </ul>
        
        <h3>Integration with Existing Code</h3>
        <p>To replace existing date range filters in your application:</p>
        <ol>
            <li>Replace separate <code>date_from</code> and <code>date_to</code> inputs with a single DateRangePicker</li>
            <li>Update your form processing to use <code>DateRangePicker::processFormData()</code></li>
            <li>Use the helper methods for SQL generation and display formatting</li>
            <li>The component automatically handles validation and maintains the same naming convention</li>
        </ol>
        
        <h3>Required Files</h3>
        <div class="code-example">
&lt;link rel="stylesheet" href="assets/css/date-range-picker.css"&gt;
&lt;script src="assets/js/date-range-picker.js"&gt;&lt;/script&gt;
        </div>
    </div>

    <!-- Required JavaScript -->
    <script src="../assets/js/date-range-picker.js"></script>
    
    <script>
        // Store picker instances for programmatic control
        let controlDateRangePicker;
        
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize control picker with global reference
            controlDateRangePicker = new DateRangePicker('control-date-range', {
                onApply: function(dateRange) {
                    console.log('Control picker updated:', dateRange);
                }
            });
        });
        
        function setThisYear() {
            const today = new Date();
            const year = today.getFullYear();
            controlDateRangePicker.setDateRange(`${year}-01-01`, `${year}-12-31`);
        }
        
        function setLastMonth() {
            const today = new Date();
            const lastMonth = new Date(today.getFullYear(), today.getMonth() - 1, 1);
            const lastDayOfLastMonth = new Date(today.getFullYear(), today.getMonth(), 0);
            
            const from = lastMonth.toISOString().split('T')[0];
            const to = lastDayOfLastMonth.toISOString().split('T')[0];
            
            controlDateRangePicker.setDateRange(from, to);
        }
        
        function resetToDefault() {
            controlDateRangePicker.reset();
        }
    </script>
</body>
</html>