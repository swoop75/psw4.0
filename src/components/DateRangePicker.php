<?php
/**
 * File: src/components/DateRangePicker.php
 * Description: PHP component for rendering date range picker
 */

class DateRangePicker {
    private $id;
    private $name;
    private $options;
    private $currentValues;
    
    public function __construct($id, $name = null, $options = []) {
        $this->id = $id;
        $this->name = $name ?: $id;
        $this->options = array_merge([
            'defaultMonthsBack' => 3,
            'format' => 'Y-m-d',
            'autoApply' => false,
            'showPresets' => true,
            'placeholder' => null,
            'class' => '',
            'required' => false
        ], $options);
        
        $this->currentValues = $this->getDefaultDateRange();
    }
    
    /**
     * Get default date range (current month + 3 months back)
     */
    private function getDefaultDateRange() {
        $today = new DateTime();
        $currentMonth = new DateTime($today->format('Y-m-01'));
        $monthsBack = (clone $currentMonth)->modify('-' . $this->options['defaultMonthsBack'] . ' months');
        $lastDayOfCurrentMonth = (clone $currentMonth)->modify('last day of this month');
        
        return [
            'from' => $monthsBack->format($this->options['format']),
            'to' => $lastDayOfCurrentMonth->format($this->options['format'])
        ];
    }
    
    /**
     * Set current date range values
     */
    public function setValues($from, $to) {
        $this->currentValues = [
            'from' => $from ?: $this->currentValues['from'],
            'to' => $to ?: $this->currentValues['to']
        ];
        return $this;
    }
    
    /**
     * Get current date range values
     */
    public function getValues() {
        return $this->currentValues;
    }
    
    /**
     * Render the date range picker HTML
     */
    public function render() {
        $fromName = $this->name . '_from';
        $toName = $this->name . '_to';
        $placeholder = $this->options['placeholder'] ?: 
            $this->currentValues['from'] . ' - ' . $this->currentValues['to'];
        
        $classes = 'date-range-picker ' . $this->options['class'];
        $required = $this->options['required'] ? 'required' : '';
        
        $optionsJson = htmlspecialchars(json_encode($this->options), ENT_QUOTES, 'UTF-8');
        
        ob_start();
        ?>
        <div id="<?php echo $this->id; ?>" 
             class="<?php echo trim($classes); ?>" 
             data-date-range-picker
             data-date-range-options='<?php echo $optionsJson; ?>'>
            
            <!-- Hidden inputs for form submission -->
            <input type="hidden" 
                   name="<?php echo $fromName; ?>" 
                   value="<?php echo htmlspecialchars($this->currentValues['from']); ?>" 
                   data-date-range-from
                   <?php echo $required; ?>>
            
            <input type="hidden" 
                   name="<?php echo $toName; ?>" 
                   value="<?php echo htmlspecialchars($this->currentValues['to']); ?>" 
                   data-date-range-to
                   <?php echo $required; ?>>
            
            <!-- Component will be rendered by JavaScript -->
            <div class="date-range-loading">
                <i class="fas fa-calendar-alt"></i>
                <span>Loading date picker...</span>
            </div>
        </div>
        
        <style>
        .date-range-loading {
            display: flex;
            align-items: center;
            padding: 8px 12px;
            background: #f8f9fa;
            border: 1px solid #ddd;
            border-radius: 4px;
            color: #6c757d;
            font-size: 14px;
        }
        
        .date-range-loading i {
            margin-right: 8px;
        }
        
        .date-range-picker:not(.loading) .date-range-loading {
            display: none;
        }
        </style>
        
        <?php
        return ob_get_clean();
    }
    
    /**
     * Render as a form field with label
     */
    public function renderField($label = null, $fieldOptions = []) {
        $fieldClass = $fieldOptions['class'] ?? 'form-group';
        $labelClass = $fieldOptions['labelClass'] ?? 'form-label';
        $required = $this->options['required'];
        
        ob_start();
        ?>
        <div class="<?php echo $fieldClass; ?>">
            <?php if ($label): ?>
                <label for="<?php echo $this->id; ?>" class="<?php echo $labelClass; ?>">
                    <?php echo htmlspecialchars($label); ?>
                    <?php if ($required): ?>
                        <span class="required">*</span>
                    <?php endif; ?>
                </label>
            <?php endif; ?>
            <?php echo $this->render(); ?>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Get JavaScript initialization code
     */
    public function getInitScript() {
        $options = json_encode($this->options);
        
        return "
        document.addEventListener('DOMContentLoaded', function() {
            const picker = new DateRangePicker('{$this->id}', {$options});
            
            // Update hidden inputs when date range changes
            picker.options.onApply = function(dateRange) {
                const fromInput = document.querySelector('input[data-date-range-from]');
                const toInput = document.querySelector('input[data-date-range-to]');
                
                if (fromInput) fromInput.value = dateRange.from;
                if (toInput) toInput.value = dateRange.to;
                
                // Trigger change events for form validation
                if (fromInput) fromInput.dispatchEvent(new Event('change'));
                if (toInput) toInput.dispatchEvent(new Event('change'));
            };
        });
        ";
    }
    
    /**
     * Process form data and return date range
     */
    public static function processFormData($data, $name) {
        $fromKey = $name . '_from';
        $toKey = $name . '_to';
        
        $from = $data[$fromKey] ?? null;
        $to = $data[$toKey] ?? null;
        
        // Validate dates
        if ($from && !self::isValidDate($from)) {
            $from = null;
        }
        
        if ($to && !self::isValidDate($to)) {
            $to = null;
        }
        
        // Ensure from <= to
        if ($from && $to && strtotime($from) > strtotime($to)) {
            $temp = $from;
            $from = $to;
            $to = $temp;
        }
        
        return [
            'from' => $from,
            'to' => $to,
            'valid' => $from && $to
        ];
    }
    
    /**
     * Validate date string
     */
    private static function isValidDate($date) {
        $d = DateTime::createFromFormat('Y-m-d', $date);
        return $d && $d->format('Y-m-d') === $date;
    }
    
    /**
     * Get SQL WHERE clause for date range filtering
     */
    public static function getSqlWhereClause($dateRange, $columnName) {
        if (!$dateRange['valid']) {
            return '';
        }
        
        $conditions = [];
        
        if ($dateRange['from']) {
            $conditions[] = "{$columnName} >= '" . addslashes($dateRange['from']) . "'";
        }
        
        if ($dateRange['to']) {
            $conditions[] = "{$columnName} <= '" . addslashes($dateRange['to']) . " 23:59:59'";
        }
        
        return $conditions ? '(' . implode(' AND ', $conditions) . ')' : '';
    }
    
    /**
     * Get prepared statement parameters for date range
     */
    public static function getPreparedParams($dateRange, $paramPrefix = 'date') {
        $params = [];
        $conditions = [];
        
        if ($dateRange['valid']) {
            if ($dateRange['from']) {
                $params[$paramPrefix . '_from'] = $dateRange['from'];
                $conditions[] = "DATE(column_name) >= :${paramPrefix}_from";
            }
            
            if ($dateRange['to']) {
                $params[$paramPrefix . '_to'] = $dateRange['to'];
                $conditions[] = "DATE(column_name) <= :${paramPrefix}_to";
            }
        }
        
        return [
            'params' => $params,
            'conditions' => $conditions,
            'where_clause' => $conditions ? '(' . implode(' AND ', $conditions) . ')' : ''
        ];
    }
    
    /**
     * Format date range for display
     */
    public static function formatDateRangeForDisplay($from, $to, $format = 'M j, Y') {
        if (!$from || !$to) {
            return 'All dates';
        }
        
        $fromDate = new DateTime($from);
        $toDate = new DateTime($to);
        
        if ($from === $to) {
            return $fromDate->format($format);
        }
        
        return $fromDate->format($format) . ' - ' . $toDate->format($format);
    }
}
?>