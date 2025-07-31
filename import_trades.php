<?php
/**
 * File: import_trades.php
 * Description: Trade CSV import interface for PSW 4.0 - Based on dividend import
 */

// Start session and include required files
session_start();

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/src/middleware/Auth.php';

// Require authentication
Auth::requireAuth();

// Set page variables
$pageTitle = 'Trade Import - PSW 4.0';
$pageDescription = 'Upload and process trade data files';

try {
    // Prepare content
    ob_start();
    ?>
    
    <div class="psw-trade-import">
        <!-- Page Header -->
        <div class="psw-card psw-mb-6">
            <div class="psw-card-header">
                <h1 class="psw-card-title">
                    <i class="fas fa-file-csv psw-card-title-icon"></i>
                    Trade CSV Import Tool
                </h1>
                <p class="psw-card-subtitle">Upload and process trade execution data files</p>
            </div>
        </div>

        <!-- Main Content -->
        <div class="psw-card">
            <div class="psw-card-content">
                
                <!-- Step 1: File Upload -->
                <div id="upload-section">
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: var(--spacing-4); margin-bottom: var(--spacing-6);">
                        <div class="psw-form-group">
                            <label for="broker-select" class="psw-form-label">
                                <i class="fas fa-building"></i>
                                Select Broker
                            </label>
                            <select id="broker-select" class="psw-form-input" required>
                                <option value="">Select a broker...</option>
                                <!-- Brokers will be populated dynamically -->
                            </select>
                        </div>
                        
                        <div class="psw-form-group">
                            <label for="account-group-select" class="psw-form-label">
                                <i class="fas fa-folder"></i>
                                Select Account Group
                            </label>
                            <select id="account-group-select" class="psw-form-input" required>
                                <option value="">Select an account group...</option>
                                <!-- Account groups will be populated dynamically -->
                            </select>
                        </div>
                    </div>
                    
                    <!-- File Upload Area -->
                    <div style="margin-bottom: var(--spacing-6);">
                        <label class="psw-form-label">
                            <i class="fas fa-upload"></i>
                            Upload Trade CSV File
                        </label>
                        <div class="psw-file-upload-area" id="file-upload-area">
                            <div class="psw-file-upload-content">
                                <i class="fas fa-cloud-upload-alt" style="font-size: 3rem; color: var(--primary-accent); margin-bottom: var(--spacing-3);"></i>
                                <h3>Drop your CSV file here</h3>
                                <p>Or click to browse and select a file</p>
                                <button type="button" class="psw-btn psw-btn-primary" onclick="document.getElementById('file-input').click()">
                                    Choose File
                                </button>
                                <input type="file" id="file-input" accept=".csv" style="display: none;">
                            </div>
                        </div>
                        <div id="file-info" style="display: none; margin-top: var(--spacing-3);">
                            <div class="psw-file-info">
                                <i class="fas fa-file-csv"></i>
                                <span id="file-name"></span>
                                <span id="file-size"></span>
                                <button type="button" class="psw-btn psw-btn-secondary psw-btn-sm" onclick="clearFile()">
                                    <i class="fas fa-times"></i> Remove
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <div style="display: flex; gap: var(--spacing-3); justify-content: flex-end;">
                        <a href="<?php echo BASE_URL; ?>/trade_logs.php" class="psw-btn psw-btn-secondary">
                            <i class="fas fa-arrow-left"></i> Back to Trade Logs
                        </a>
                        <button type="button" class="psw-btn psw-btn-primary" id="upload-btn" disabled onclick="uploadFile()">
                            <i class="fas fa-upload"></i> Upload & Process
                        </button>
                    </div>
                </div>
                
                <!-- Step 2: Processing Status -->
                <div id="processing-section" style="display: none;">
                    <div class="psw-processing-status">
                        <div class="psw-processing-spinner">
                            <i class="fas fa-spinner fa-spin"></i>
                        </div>
                        <h3>Processing trade data...</h3>
                        <p>Please wait while we import your trade records.</p>
                        <div class="psw-progress-bar">
                            <div class="psw-progress-fill" id="progress-fill"></div>
                        </div>
                        <div id="processing-status"></div>
                    </div>
                </div>
                
                <!-- Step 3: Results -->
                <div id="results-section" style="display: none;">
                    <div class="psw-import-results">
                        <div class="psw-results-header">
                            <h3>Import Complete</h3>
                        </div>
                        <div id="import-summary"></div>
                        <div style="display: flex; gap: var(--spacing-3); justify-content: flex-end; margin-top: var(--spacing-4);">
                            <button type="button" class="psw-btn psw-btn-secondary" onclick="resetImport()">
                                Import Another File
                            </button>
                            <a href="<?php echo BASE_URL; ?>/trade_logs.php" class="psw-btn psw-btn-primary">
                                <i class="fas fa-eye"></i> View Trade Logs
                            </a>
                        </div>
                    </div>
                </div>
                
            </div>
        </div>
        
        <!-- Import Instructions -->
        <div class="psw-card" style="margin-top: var(--spacing-6);">
            <div class="psw-card-header">
                <h2 class="psw-card-title">
                    <i class="fas fa-info-circle psw-card-title-icon"></i>
                    CSV Format Requirements
                </h2>
            </div>
            <div class="psw-card-content">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: var(--spacing-4);">
                    <div>
                        <h4 style="color: var(--primary-accent); margin-bottom: var(--spacing-2);">Required Columns</h4>
                        <ul style="list-style: none; padding: 0;">
                            <li><i class="fas fa-check text-success"></i> Trade Date</li>
                            <li><i class="fas fa-check text-success"></i> ISIN</li>
                            <li><i class="fas fa-check text-success"></i> Trade Type (BUY/SELL)</li>
                            <li><i class="fas fa-check text-success"></i> Shares</li>
                            <li><i class="fas fa-check text-success"></i> Price per Share</li>
                            <li><i class="fas fa-check text-success"></i> Currency</li>
                        </ul>
                    </div>
                    <div>
                        <h4 style="color: var(--primary-accent); margin-bottom: var(--spacing-2);">Optional Columns</h4>
                        <ul style="list-style: none; padding: 0;">
                            <li><i class="fas fa-circle text-muted"></i> Settlement Date</li>
                            <li><i class="fas fa-circle text-muted"></i> Ticker</li>
                            <li><i class="fas fa-circle text-muted"></i> Broker Fees</li>
                            <li><i class="fas fa-circle text-muted"></i> Transaction Tax</li>
                            <li><i class="fas fa-circle text-muted"></i> Transaction ID</li>
                            <li><i class="fas fa-circle text-muted"></i> Notes</li>
                        </ul>
                    </div>
                </div>
                
                <div style="margin-top: var(--spacing-4); padding: var(--spacing-4); background: var(--bg-tertiary); border-radius: var(--border-radius);">
                    <h4 style="margin-bottom: var(--spacing-2);">
                        <i class="fas fa-download"></i> Sample CSV Template
                    </h4>
                    <p>Download our sample CSV template to ensure your data is formatted correctly:</p>
                    <button type="button" class="psw-btn psw-btn-accent psw-btn-sm" onclick="downloadTemplate()">
                        <i class="fas fa-download"></i> Download Template
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
    // Initialize the page
    document.addEventListener('DOMContentLoaded', function() {
        loadBrokers();
        loadAccountGroups();
        setupFileUpload();
    });

    // Load brokers from database
    async function loadBrokers() {
        try {
            const response = await fetch('<?php echo BASE_URL; ?>/api/get_brokers.php');
            const brokers = await response.json();
            const select = document.getElementById('broker-select');
            
            brokers.forEach(broker => {
                const option = document.createElement('option');
                option.value = broker.broker_id;
                option.textContent = broker.broker_name;
                select.appendChild(option);
            });
        } catch (error) {
            console.error('Error loading brokers:', error);
        }
    }

    // Load account groups from database
    async function loadAccountGroups() {
        try {
            const response = await fetch('<?php echo BASE_URL; ?>/api/get_account_groups.php');
            const groups = await response.json();
            const select = document.getElementById('account-group-select');
            
            groups.forEach(group => {
                const option = document.createElement('option');
                option.value = group.portfolio_account_group_id;
                option.textContent = group.portfolio_group_name;
                select.appendChild(option);
            });
        } catch (error) {
            console.error('Error loading account groups:', error);
        }
    }

    // Setup file upload functionality
    function setupFileUpload() {
        const fileInput = document.getElementById('file-input');
        const uploadArea = document.getElementById('file-upload-area');
        const uploadBtn = document.getElementById('upload-btn');
        
        // File input change
        fileInput.addEventListener('change', handleFileSelect);
        
        // Drag and drop
        uploadArea.addEventListener('dragover', handleDragOver);
        uploadArea.addEventListener('drop', handleDrop);
        uploadArea.addEventListener('click', () => fileInput.click());
    }

    function handleFileSelect(event) {
        const file = event.target.files[0];
        if (file) {
            displayFileInfo(file);
        }
    }

    function handleDragOver(event) {
        event.preventDefault();
        event.currentTarget.classList.add('drag-over');
    }

    function handleDrop(event) {
        event.preventDefault();
        event.currentTarget.classList.remove('drag-over');
        
        const file = event.dataTransfer.files[0];
        if (file && file.type === 'text/csv') {
            document.getElementById('file-input').files = event.dataTransfer.files;
            displayFileInfo(file);
        }
    }

    function displayFileInfo(file) {
        document.getElementById('file-name').textContent = file.name;
        document.getElementById('file-size').textContent = formatFileSize(file.size);
        document.getElementById('file-info').style.display = 'block';
        document.getElementById('upload-btn').disabled = false;
    }

    function clearFile() {
        document.getElementById('file-input').value = '';
        document.getElementById('file-info').style.display = 'none';
        document.getElementById('upload-btn').disabled = true;
    }

    function formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }

    // Upload and process file
    async function uploadFile() {
        const fileInput = document.getElementById('file-input');
        const brokerSelect = document.getElementById('broker-select');
        const accountGroupSelect = document.getElementById('account-group-select');
        
        if (!fileInput.files[0] || !brokerSelect.value || !accountGroupSelect.value) {
            alert('Please select a file, broker, and account group.');
            return;
        }
        
        // Show processing section
        document.getElementById('upload-section').style.display = 'none';
        document.getElementById('processing-section').style.display = 'block';
        
        const formData = new FormData();
        formData.append('file', fileInput.files[0]);
        formData.append('broker_id', brokerSelect.value);
        formData.append('account_group_id', accountGroupSelect.value);
        
        try {
            const response = await fetch('<?php echo BASE_URL; ?>/api/import_trades.php', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            showResults(result);
        } catch (error) {
            console.error('Import error:', error);
            showError('Failed to import trade data. Please try again.');
        }
    }

    function showResults(result) {
        document.getElementById('processing-section').style.display = 'none';
        document.getElementById('results-section').style.display = 'block';
        
        const summaryHtml = `
            <div class="psw-import-summary">
                <div class="psw-summary-cards">
                    <div class="psw-summary-card success">
                        <i class="fas fa-check-circle"></i>
                        <div class="psw-summary-number">${result.success_count || 0}</div>
                        <div class="psw-summary-label">Trades Imported</div>
                    </div>
                    <div class="psw-summary-card error">
                        <i class="fas fa-exclamation-triangle"></i>
                        <div class="psw-summary-number">${result.error_count || 0}</div>
                        <div class="psw-summary-label">Errors</div>
                    </div>
                </div>
                ${result.errors ? `<div class="psw-error-details">Errors: ${result.errors}</div>` : ''}
            </div>
        `;
        
        document.getElementById('import-summary').innerHTML = summaryHtml;
    }

    function showError(message) {
        document.getElementById('processing-section').style.display = 'none';
        document.getElementById('upload-section').style.display = 'block';
        alert(message);
    }

    function resetImport() {
        clearFile();
        document.getElementById('results-section').style.display = 'none';
        document.getElementById('upload-section').style.display = 'block';
        document.getElementById('broker-select').value = '';
        document.getElementById('account-group-select').value = '';
    }

    function downloadTemplate() {
        // Create sample CSV content
        const csvContent = `Trade Date,Settlement Date,ISIN,Ticker,Trade Type,Shares,Price per Share,Total Amount,Currency,Broker Fees,Transaction Tax,Transaction ID,Notes
2025-01-15,2025-01-17,US0378331005,AAPL,BUY,100,227.50,22750.00,USD,14.95,0.00,TXN123456,Sample Apple purchase
2025-01-16,2025-01-18,GB0002162385,BARC,SELL,200,2.125,425.00,GBP,9.95,2.13,TXN123457,Sample Barclays sale`;
        
        // Create and download file
        const blob = new Blob([csvContent], { type: 'text/csv' });
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'trade_import_template.csv';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        window.URL.revokeObjectURL(url);
    }
    </script>
    
    <style>
    .psw-trade-import .psw-file-upload-area {
        border: 2px dashed var(--border-primary);
        border-radius: var(--border-radius-lg);
        padding: var(--spacing-6);
        text-align: center;
        cursor: pointer;
        background: var(--bg-secondary);
        transition: all 0.3s ease;
    }
    
    .psw-trade-import .psw-file-upload-area:hover,
    .psw-trade-import .psw-file-upload-area.drag-over {
        border-color: var(--primary-accent);
        background: var(--primary-accent-light);
    }
    
    .psw-trade-import .psw-file-info {
        display: flex;
        align-items: center;
        gap: var(--spacing-3);
        padding: var(--spacing-3);
        background: var(--bg-secondary);
        border-radius: var(--border-radius);
    }
    
    .psw-trade-import .psw-processing-status {
        text-align: center;
        padding: var(--spacing-6);
    }
    
    .psw-trade-import .psw-processing-spinner i {
        font-size: 3rem;
        color: var(--primary-accent);
        margin-bottom: var(--spacing-3);
    }
    
    .psw-trade-import .psw-progress-bar {
        width: 100%;
        height: 8px;
        background: var(--bg-tertiary);
        border-radius: 4px;
        margin: var(--spacing-4) 0;
        overflow: hidden;
    }
    
    .psw-trade-import .psw-progress-fill {
        height: 100%;
        background: var(--primary-accent);
        border-radius: 4px;
        transition: width 0.3s ease;
    }
    
    .psw-trade-import .psw-import-summary {
        text-align: center;
    }
    
    .psw-trade-import .psw-summary-cards {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: var(--spacing-4);
        margin: var(--spacing-4) 0;
    }
    
    .psw-trade-import .psw-summary-card {
        padding: var(--spacing-4);
        border-radius: var(--border-radius);
        text-align: center;
    }
    
    .psw-trade-import .psw-summary-card.success {
        background: var(--success-color-light);
        color: var(--success-color);
    }
    
    .psw-trade-import .psw-summary-card.error {
        background: var(--error-color-light);
        color: var(--error-color);
    }
    
    .psw-trade-import .psw-summary-number {
        font-size: 2rem;
        font-weight: 700;
        margin: var(--spacing-2) 0;
    }
    
    .psw-trade-import .psw-summary-label {
        font-size: var(--font-size-sm);
        font-weight: 500;
    }
    
    .text-success { color: var(--success-color); }
    .text-muted { color: var(--text-muted); }
    </style>
    
    <?php
    $content = ob_get_clean();
    
    // Include the base layout
    include __DIR__ . '/templates/layouts/base-redesign.php';
    
} catch (Exception $e) {
    echo "<p>Error loading import page: " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>