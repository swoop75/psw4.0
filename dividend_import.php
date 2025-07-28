<?php
/**
 * File: dividend_import.php
 * Description: Dividend CSV import interface for PSW 4.0
 */

// Start session and include required files
session_start();

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/src/middleware/Auth.php';
require_once __DIR__ . '/src/utils/Security.php';
require_once __DIR__ . '/src/utils/Logger.php';

// Require authentication
Auth::requireAuth();

try {
    // Set page variables
    $pageTitle = 'Dividend Import - ' . APP_NAME;
    $pageDescription = 'Import dividend data from CSV files';
    $additionalCSS = [ASSETS_URL . '/css/dividend-import.css?v=' . time()];
    $additionalJS = ['https://code.jquery.com/jquery-3.6.0.min.js'];
    
    // Prepare content
    ob_start();
?>

<div class="container">
    <!-- Page Header -->
    <div class="page-header">
        <div class="header-content">
            <div class="header-left">
                <h1><i class="fas fa-file-csv"></i> Dividend Import</h1>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="content-wrapper">
        <div class="dashboard-card full-width">
            <div class="card-header">
                <h2 class="card-title">
                    <i class="fas fa-upload"></i>
                    CSV Import Tool
                </h2>
                <p class="card-subtitle">Upload and process dividend data files</p>
            </div>
            <div class="card-content">
                    
                    <!-- Step 1: File Upload -->
                    <div id="upload-section">
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="broker-select" class="form-label">
                                    <i class="fas fa-building"></i>
                                    Select Broker
                                </label>
                                <select id="broker-select" class="form-select" required>
                                    <option value="">Loading brokers...</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="account-group-select" class="form-label">
                                    <i class="fas fa-folder"></i>
                                    Portfolio Account Group
                                </label>
                                <select id="account-group-select" class="form-select">
                                    <option value="">Loading account groups...</option>
                                </select>
                                <small class="form-help">Optional: Override CSV account group column</small>
                            </div>
                            
                            <div class="form-group">
                                <label for="csv-file" class="form-label">
                                    <i class="fas fa-file-csv"></i>
                                    Select CSV File
                                </label>
                                <input type="file" id="csv-file" class="form-file" accept=".csv,.xlsx" required>
                                <small class="form-help">Supported formats: CSV, Excel (.xlsx)</small>
                            </div>
                        </div>
                        
                        <div class="button-group">
                            <button id="upload-btn" class="btn btn-primary" disabled>
                                <i class="fas fa-upload"></i>
                                Upload and Parse File
                            </button>
                            <div id="upload-progress" class="progress-bar-container" style="display: none;">
                                <div class="progress-bar" style="width: 0%"></div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Step 2: Preview and Validation -->
                    <div id="preview-section" style="display: none;">
                        <div class="section-divider"></div>
                        
                        <div class="section-header">
                            <h3 class="section-title">
                                <i class="fas fa-eye"></i>
                                Import Preview
                            </h3>
                        </div>
                        
                        <div id="validation-alerts"></div>
                        
                        <!-- Stats Cards -->
                        <div class="stats-grid">
                            <div class="stat-card info">
                                <div class="stat-icon">
                                    <i class="fas fa-file-csv"></i>
                                </div>
                                <div class="stat-content">
                                    <div class="stat-number" id="total-rows">0</div>
                                    <div class="stat-label">Total Rows</div>
                                </div>
                            </div>
                            
                            <div class="stat-card warning">
                                <div class="stat-icon">
                                    <i class="fas fa-exclamation-triangle"></i>
                                </div>
                                <div class="stat-content">
                                    <div class="stat-number" id="warning-count">0</div>
                                    <div class="stat-label">Warnings</div>
                                </div>
                            </div>
                            
                            <div class="stat-card error">
                                <div class="stat-icon">
                                    <i class="fas fa-times-circle"></i>
                                </div>
                                <div class="stat-content">
                                    <div class="stat-number" id="error-count">0</div>
                                    <div class="stat-label">Errors</div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Preview Table -->
                        <div class="data-table-container">
                            <table id="preview-table" class="data-table">
                                <thead>
                                    <tr>
                                        <th>Payment Date</th>
                                        <th>ISIN</th>
                                        <th>Ticker</th>
                                        <th>Broker</th>
                                        <th>Account</th>
                                        <th>Shares</th>
                                        <th>Dividend (Local)</th>
                                        <th>Currency</th>
                                        <th>Dividend (SEK)</th>
                                        <th>Tax (SEK)</th>
                                        <th>Net (SEK)</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                        
                        <!-- Error/Warning Details -->
                        <div id="error-details" style="display: none;">
                            <div class="section-header">
                                <h4 class="section-title">
                                    <i class="fas fa-exclamation-triangle"></i>
                                    Validation Issues
                                </h4>
                            </div>
                            
                            <div class="validation-details">
                                <div class="validation-group">
                                    <div class="validation-header" onclick="toggleValidationSection('errors')">
                                        <i class="fas fa-times-circle text-danger"></i>
                                        <span>Errors (<span id="error-detail-count">0</span>)</span>
                                        <i class="fas fa-chevron-down toggle-icon"></i>
                                    </div>
                                    <div id="errors-collapse" class="validation-content">
                                        <ul id="error-list" class="validation-list"></ul>
                                    </div>
                                </div>
                                
                                <div class="validation-group">
                                    <div class="validation-header" onclick="toggleValidationSection('warnings')">
                                        <i class="fas fa-exclamation-triangle text-warning"></i>
                                        <span>Warnings (<span id="warning-detail-count">0</span>)</span>
                                        <i class="fas fa-chevron-down toggle-icon"></i>
                                    </div>
                                    <div id="warnings-collapse" class="validation-content">
                                        <ul id="warning-list" class="validation-list"></ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Import Controls -->
                        <div class="import-controls">
                            <div class="control-option">
                                <label class="checkbox-label">
                                    <input type="checkbox" id="ignore-duplicates">
                                    <span class="checkmark"></span>
                                    <span class="checkbox-text">
                                        <i class="fas fa-copy"></i>
                                        Ignore duplicate entries (skip existing dividends)
                                    </span>
                                </label>
                            </div>
                            
                            <div class="button-group">
                                <button id="import-btn" class="btn btn-success" disabled>
                                    <i class="fas fa-download"></i>
                                    Import Dividends
                                </button>
                                <button id="cancel-btn" class="btn btn-secondary">
                                    <i class="fas fa-times"></i>
                                    Cancel
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Step 3: Import Results -->
                    <div id="results-section" style="display: none;">
                        <div class="section-divider"></div>
                        
                        <div class="section-header">
                            <h3 class="section-title">
                                <i class="fas fa-check-circle"></i>
                                Import Results
                            </h3>
                        </div>
                        
                        <div id="import-results"></div>
                        
                        <div class="button-group">
                            <button id="new-import-btn" class="btn btn-primary">
                                <i class="fas fa-plus"></i>
                                Start New Import
                            </button>
                        </div>
                    </div>
                    
            </div>
        </div>
    </div>
</div>

<!-- Load jQuery directly before our script -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<script>
console.log('Script loading...');

// Simple initialization - no jQuery waiting
document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM loaded, checking jQuery...');
    
    if (typeof jQuery === 'undefined') {
        console.error('jQuery not loaded!');
        return;
    }
    
    console.log('jQuery available, version:', jQuery.fn.jquery);
    
    // Initialize immediately
    $(function() {
        let importData = null;
        
        console.log('Document ready, loading dropdowns...');
        console.log('Broker select element found:', $('#broker-select').length > 0);
        console.log('Account group select element found:', $('#account-group-select').length > 0);
        
        // Test basic functionality
        if ($('#broker-select').length === 0) {
            console.error('Broker select element not found in DOM!');
            return;
        }
        
        // Load brokers and account groups on page load
        loadBrokers();
        loadAccountGroups();
    
    // File input change handler
    $('#csv-file').on('change', function() {
        updateUploadButton();
    });
    
    // Broker select change handler
    $('#broker-select').on('change', function() {
        updateUploadButton();
        showBrokerInfo();
    });
    
    // Upload button click handler
    $('#upload-btn').on('click', function() {
        uploadFile();
    });
    
    // Import button click handler
    $('#import-btn').on('click', function() {
        importDividends();
    });
    
    // Cancel button click handler
    $('#cancel-btn').on('click', function() {
        resetForm();
    });
    
    // New import button click handler
    $('#new-import-btn').on('click', function() {
        resetForm();
    });
    
    function loadBrokers() {
        console.log('Loading brokers...');
        $.get('./get_brokers.php')
            .done(function(data) {
                console.log('Broker data received:', data);
                const select = $('#broker-select');
                select.empty();
                select.append('<option value="">Select a broker...</option>');
                
                // Use database brokers if available, otherwise use config brokers
                const brokers = data.brokers && data.brokers.length > 0 ? data.brokers : 
                    Object.entries(data.config_brokers || {}).map(([id, name]) => ({broker_id: id, broker_name: name}));
                
                console.log('Processed brokers:', brokers);
                
                brokers.forEach(function(broker) {
                    select.append(`<option value="${broker.broker_id}">${broker.broker_name}</option>`);
                });
                
                updateUploadButton();
            })
            .fail(function(xhr, status, error) {
                console.error('Broker loading failed:');
                console.error('Status:', status);
                console.error('Error:', error);
                console.error('Response:', xhr.responseText);
                console.error('Status Code:', xhr.status);
                
                // Fallback to hardcoded brokers
                const select = $('#broker-select');
                select.empty();
                select.append('<option value="">Select a broker...</option>');
                select.append('<option value="minimal">Minimal Format (PSW Standard)</option>');
                select.append('<option value="1">Avanza</option>');
                select.append('<option value="2">Nordnet</option>');
                updateUploadButton();
                showAlert('warning', 'Could not load brokers from database. Using fallback list.');
            });
    }
    
    function loadAccountGroups() {
        console.log('Loading account groups from: ./get_account_groups.php');
        
        $.get('./get_account_groups.php')
            .done(function(data, textStatus, xhr) {
                console.log('Account groups AJAX success:', textStatus);
                console.log('Account group data received:', data);
                console.log('Response type:', typeof data);
                
                const select = $('#account-group-select');
                select.empty();
                select.append('<option value="">Use CSV column or none</option>');
                
                // Handle both JSON object and string responses
                let groupData = data;
                if (typeof data === 'string') {
                    try {
                        groupData = JSON.parse(data);
                    } catch (e) {
                        console.error('Failed to parse account group JSON:', e, data);
                        throw new Error('Invalid JSON response');
                    }
                }
                
                if (groupData.success && groupData.account_groups) {
                    console.log('Processing account groups:', groupData.account_groups);
                    groupData.account_groups.forEach(function(group) {
                        select.append(`<option value="${group.portfolio_account_group_id}">${group.portfolio_group_name}</option>`);
                    });
                    console.log('Account groups loaded successfully');
                } else {
                    console.warn('Account groups data structure invalid or empty');
                    // This is not an error - just means no account groups configured
                }
            })
            .fail(function(xhr, status, error) {
                console.error('Account group loading failed:');
                console.error('Status:', status);
                console.error('Error:', error);
                console.error('Response:', xhr.responseText);
                console.error('Status Code:', xhr.status);
                
                const select = $('#account-group-select');
                select.empty();
                select.append('<option value="">Use CSV column or none</option>');
                select.append('<option value="4">PSW Sverige</option>');
                select.append('<option value="5">PSW Worldwide</option>');
                showAlert('warning', 'Could not load account groups from database. Using fallback list.');
            });
    }
    
    function updateUploadButton() {
        const brokerSelected = $('#broker-select').val() !== '';
        const fileSelected = $('#csv-file')[0].files.length > 0;
        $('#upload-btn').prop('disabled', !(brokerSelected && fileSelected));
    }
    
    function showBrokerInfo() {
        const brokerId = $('#broker-select').val();
        // Removed generic broker info message as it was not helpful
    }
    
    function uploadFile() {
        const fileInput = $('#csv-file')[0];
        const brokerId = $('#broker-select').val();
        
        if (!fileInput.files[0] || !brokerId) {
            showAlert('error', 'Please select both a broker and a file');
            return;
        }
        
        const formData = new FormData();
        formData.append('csv_file', fileInput.files[0]);
        formData.append('broker_id', brokerId);
        formData.append('default_account_group_id', $('#account-group-select').val() || '');
        
        // Show progress
        $('#upload-progress').show();
        $('#upload-btn').prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Processing...');
        
        $.ajax({
            url: './proper_csv_upload.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(data) {
                console.log('Upload response:', data);
                if (data.debug_info) {
                    console.log('Debug info:', data.debug_info);
                    // Show debug info in the UI
                    $('#validation-alerts').append(`
                        <div class="alert alert-info">
                            <strong>Debug Info:</strong><br>
                            ${data.debug_info.join('<br>')}
                        </div>
                    `);
                }
                if (data.success) {
                    importData = data;
                    showPreview(data);
                } else {
                    showAlert('error', data.error || 'Upload failed');
                }
            },
            error: function(xhr) {
                const response = xhr.responseJSON || {};
                showAlert('error', response.error || 'Upload failed');
            },
            complete: function() {
                $('#upload-progress').hide();
                $('#upload-btn').prop('disabled', false).html('<i class="fas fa-upload"></i> Upload and Parse File');
            }
        });
    }
    
    function showPreview(data) {
        // Update summary statistics
        $('#total-rows').text(data.total_rows);
        $('#error-count').text(data.errors.length);
        $('#warning-count').text(data.warnings.length);
        
        // Show validation alerts
        if (data.errors.length > 0) {
            showAlert('danger', `Found ${data.errors.length} errors that must be fixed before importing`);
            $('#import-btn').prop('disabled', true);
        } else if (data.warnings.length > 0) {
            showAlert('warning', `Found ${data.warnings.length} warnings. Review before importing`);
            $('#import-btn').prop('disabled', false);
        } else {
            showAlert('success', 'File validation passed. Ready to import');
            $('#import-btn').prop('disabled', false);
        }
        
        // Populate preview table
        const tbody = $('#preview-table tbody');
        tbody.empty();
        
        data.preview_data.forEach(function(dividend) {
            // Traffic light colors: Green = Complete, Yellow = Incomplete, Red = Error
            let statusBadge;
            if (dividend.is_complete === 1) {
                statusBadge = '<span class="badge" style="background-color: #28a745; color: white;">Complete</span>';
            } else if (dividend.is_complete === 0) {
                statusBadge = '<span class="badge" style="background-color: #ffc107; color: black;">Incomplete</span>';
            } else {
                statusBadge = '<span class="badge" style="background-color: #dc3545; color: white;">Error</span>';
            }
            
            const row = `
                <tr>
                    <td>${dividend.payment_date}</td>
                    <td>${dividend.isin}</td>
                    <td>${dividend.ticker || '-'}</td>
                    <td>${dividend.broker || '-'}</td>
                    <td>${dividend.portfolio_account_group || '-'}</td>
                    <td>${parseFloat(dividend.shares_held).toFixed(4)}</td>
                    <td>${parseFloat(dividend.dividend_amount_local || 0).toFixed(4)}</td>
                    <td>${dividend.currency_local || '-'}</td>
                    <td>${parseFloat(dividend.dividend_amount_sek || 0).toFixed(4)}</td>
                    <td>${parseFloat(dividend.tax_amount_sek || 0).toFixed(4)}</td>
                    <td>${parseFloat(dividend.net_dividend_sek || 0).toFixed(4)}</td>
                    <td>${statusBadge}</td>
                </tr>
            `;
            tbody.append(row);
        });
        
        // Show error/warning details
        if (data.errors.length > 0 || data.warnings.length > 0) {
            showValidationDetails(data.errors, data.warnings);
        }
        
        // Show preview section
        $('#preview-section').show();
    }
    
    function showValidationDetails(errors, warnings) {
        $('#error-detail-count').text(errors.length);
        $('#warning-detail-count').text(warnings.length);
        
        const errorList = $('#error-list');
        errorList.empty();
        errors.forEach(function(error) {
            errorList.append(`<li class="text-danger"><i class="fas fa-times-circle"></i> ${error}</li>`);
        });
        
        const warningList = $('#warning-list');
        warningList.empty();
        warnings.forEach(function(warning) {
            warningList.append(`<li class="text-warning"><i class="fas fa-exclamation-triangle"></i> ${warning}</li>`);
        });
        
        $('#error-details').show();
    }
    
    function importDividends() {
        $('#import-btn').prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Importing...');
        
        const ignoreDuplicates = $('#ignore-duplicates').is(':checked');
        
        $.post('./import_dividends.php', {
            ignore_duplicates: ignoreDuplicates
        })
        .done(function(data) {
            if (data.success) {
                showImportResults(data);
            } else if (data.duplicates_found) {
                showDuplicateWarning(data);
            } else {
                showAlert('error', data.error || 'Import failed');
            }
        })
        .fail(function(xhr) {
            const response = xhr.responseJSON || {};
            showAlert('error', response.error || 'Import failed');
        })
        .always(function() {
            $('#import-btn').prop('disabled', false).html('<i class="fas fa-download"></i> Import Dividends');
        });
    }
    
    function showImportResults(data) {
        const results = `
            <div class="alert alert-success">
                <h5><i class="fas fa-check-circle"></i> Import Completed Successfully</h5>
                <ul class="mb-0">
                    <li><strong>${data.imported}</strong> dividends imported</li>
                    ${data.skipped > 0 ? `<li><strong>${data.skipped}</strong> duplicate entries skipped</li>` : ''}
                    <li><strong>${data.total_processed}</strong> total records processed</li>
                </ul>
            </div>
        `;
        
        $('#import-results').html(results);
        $('#preview-section').hide();
        $('#results-section').show();
    }
    
    function showDuplicateWarning(data) {
        let duplicateList = '<ul>';
        data.duplicates.forEach(function(dup) {
            duplicateList += `<li>${dup.isin} - ${dup.payment_date} (${dup.shares_held} shares)</li>`;
        });
        duplicateList += '</ul>';
        
        const warning = `
            <div class="alert alert-warning">
                <h5><i class="fas fa-exclamation-triangle"></i> Duplicate Entries Found</h5>
                <p>The following dividends already exist in the database:</p>
                ${duplicateList}
                <p class="mb-0">Check "Ignore duplicate entries" to skip these records and import the rest.</p>
            </div>
        `;
        
        $('#import-results').html(warning);
    }
    
    function resetForm() {
        $('#upload-section').show();
        $('#preview-section').hide();
        $('#results-section').hide();
        $('#csv-file').val('');
        $('#broker-select').val('');
        $('#ignore-duplicates').prop('checked', false);
        $('#validation-alerts').empty();
        updateUploadButton();
        importData = null;
    }
    
    function showAlert(type, message) {
        const alertClass = type === 'error' ? 'danger' : type;
        const alert = `
            <div class="alert alert-${alertClass}">
                ${message}
                <button type="button" class="alert-close" onclick="this.parentElement.remove()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        `;
        $('#validation-alerts').append(alert);
    }
    
    // Toggle validation sections
    function toggleValidationSection(section) {
        const content = document.getElementById(section + '-collapse');
        const icon = content.previousElementSibling.querySelector('.toggle-icon');
        
        if (content.style.display === 'none' || content.style.display === '') {
            content.style.display = 'block';
            icon.style.transform = 'rotate(180deg)';
        } else {
            content.style.display = 'none';
            icon.style.transform = 'rotate(0deg)';
        }
    }
});

// Make toggle function globally available
window.toggleValidationSection = function(section) {
    const content = document.getElementById(section + '-collapse');
    const icon = content.previousElementSibling.querySelector('.toggle-icon');
    
    if (content.style.display === 'none' || content.style.display === '') {
        content.style.display = 'block';
        icon.style.transform = 'rotate(180deg)';
    } else {
        content.style.display = 'none';
        icon.style.transform = 'rotate(0deg)';
    }
    
    }); // End jQuery ready function
}); // End DOMContentLoaded
</script>

<?php
    $content = ob_get_clean();
    
    // Include base layout
    include __DIR__ . '/templates/layouts/base.php';
    
    // Log page access
    Logger::logUserAction('dividend_import_viewed', 'User accessed dividend import page');
    
} catch (Exception $e) {
    Logger::error('Dividend import page error: ' . $e->getMessage());
    
    $pageTitle = 'Import Error - ' . APP_NAME;
    $content = '
        <div class="error-container text-center">
            <h1>Import Page Error</h1>
            <p>We apologize, but there was an error loading the dividend import page.</p>
            <p class="text-muted">Please try refreshing the page or contact support if the problem persists.</p>
        </div>
    ';
    
    if (defined('APP_DEBUG') && APP_DEBUG) {
        $content .= '<div class="alert alert-error mt-3"><strong>Debug:</strong> ' . $e->getMessage() . '</div>';
    }
    
    include __DIR__ . '/templates/layouts/base.php';
}
?>