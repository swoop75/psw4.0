<?php
/**
 * File: dividend_import.php
 * Description: Dividend CSV import interface for PSW 4.0 - Redesigned
 */

// Start session and include required files
session_start();

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/src/middleware/Auth.php';

// Require authentication
Auth::requireAuth();

// Set page variables
$pageTitle = 'Dividend Import - PSW 4.0';
$pageDescription = 'Upload and process dividend data files';

try {
    // Prepare content
    ob_start();
    ?>
    
    <div class="psw-dividend-import">
        <!-- Page Header -->
        <div class="psw-card psw-mb-6">
            <div class="psw-card-header">
                <h1 class="psw-card-title">
                    <i class="fas fa-file-csv psw-card-title-icon"></i>
                    CSV Import Tool
                </h1>
                <p class="psw-card-subtitle">Upload and process dividend data files</p>
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
                                <option value="">Loading brokers...</option>
                            </select>
                        </div>
                        
                        <div class="psw-form-group">
                            <label for="account-group-select" class="psw-form-label">
                                <i class="fas fa-folder"></i>
                                Portfolio Account Group
                            </label>
                            <select id="account-group-select" class="psw-form-input">
                                <option value="">Loading account groups...</option>
                            </select>
                            <small style="color: var(--text-muted); font-size: var(--font-size-sm);">Optional: Override CSV account group column</small>
                        </div>
                        
                        <div class="psw-form-group">
                            <label for="csv-file" class="psw-form-label">
                                <i class="fas fa-file-csv"></i>
                                Select CSV File
                            </label>
                            <input type="file" id="csv-file" class="psw-form-input" accept=".csv,.xlsx" required>
                            <small style="color: var(--text-muted); font-size: var(--font-size-sm);">Supported formats: CSV, Excel (.xlsx)</small>
                        </div>
                    </div>
                    
                    <div style="display: flex; gap: var(--spacing-4); align-items: center;">
                        <button id="upload-btn" class="psw-btn psw-btn-primary" disabled>
                            <i class="fas fa-upload psw-btn-icon"></i>
                            Upload and Parse File
                        </button>
                        <div id="upload-progress" style="display: none; flex: 1; height: 6px; background-color: var(--bg-secondary); border-radius: var(--radius-sm); overflow: hidden;">
                            <div style="height: 100%; background-color: var(--primary-accent); width: 0%; transition: width var(--transition-base);"></div>
                        </div>
                    </div>
                </div>
                
                <!-- Step 2: Preview and Validation -->
                <div id="preview-section" style="display: none;">
                    <div style="border-top: 1px solid var(--border-primary); margin: var(--spacing-6) 0; padding-top: var(--spacing-6);"></div>
                    
                    <div style="margin-bottom: var(--spacing-6);">
                        <h3 style="font-size: var(--font-size-xl); font-weight: 700; color: var(--text-primary); margin-bottom: var(--spacing-2);">
                            <i class="fas fa-eye" style="color: var(--primary-accent); margin-right: var(--spacing-2);"></i>
                            Import Preview
                        </h3>
                    </div>
                    
                    <div id="validation-alerts" style="margin-bottom: var(--spacing-4);"></div>
                    
                    <!-- Stats Cards -->
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: var(--spacing-4); margin-bottom: var(--spacing-6);">
                        <div class="psw-card" style="text-align: center;">
                            <div class="psw-card-content">
                                <i class="fas fa-file-csv" style="color: var(--info-color); font-size: var(--font-size-2xl); margin-bottom: var(--spacing-2);"></i>
                                <div style="font-size: var(--font-size-2xl); font-weight: 700; color: var(--text-primary);" id="total-rows">0</div>
                                <div style="color: var(--text-muted);">Total Rows</div>
                            </div>
                        </div>
                        
                        <div class="psw-card" style="text-align: center;">
                            <div class="psw-card-content">
                                <i class="fas fa-exclamation-triangle" style="color: var(--warning-color); font-size: var(--font-size-2xl); margin-bottom: var(--spacing-2);"></i>
                                <div style="font-size: var(--font-size-2xl); font-weight: 700; color: var(--text-primary);" id="warning-count">0</div>
                                <div style="color: var(--text-muted);">Warnings</div>
                            </div>
                        </div>
                        
                        <div class="psw-card" style="text-align: center;">
                            <div class="psw-card-content">
                                <i class="fas fa-times-circle" style="color: var(--error-color); font-size: var(--font-size-2xl); margin-bottom: var(--spacing-2);"></i>
                                <div style="font-size: var(--font-size-2xl); font-weight: 700; color: var(--text-primary);" id="error-count">0</div>
                                <div style="color: var(--text-muted);">Errors</div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Preview Table -->
                    <div style="overflow-x: auto; margin-bottom: var(--spacing-6);">
                        <table id="preview-table" class="psw-table">
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
                    
                    <!-- Import Controls -->
                    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: var(--spacing-4);">
                        <label style="display: flex; align-items: center; gap: var(--spacing-2); cursor: pointer;">
                            <input type="checkbox" id="ignore-duplicates" style="margin-right: var(--spacing-2);">
                            <i class="fas fa-copy" style="color: var(--primary-accent);"></i>
                            <span>Ignore duplicate entries (skip existing dividends)</span>
                        </label>
                        
                        <div style="display: flex; gap: var(--spacing-3);">
                            <button id="import-btn" class="psw-btn psw-btn-primary" disabled>
                                <i class="fas fa-download psw-btn-icon"></i>
                                Import Dividends
                            </button>
                            <button id="cancel-btn" class="psw-btn psw-btn-secondary">
                                <i class="fas fa-times psw-btn-icon"></i>
                                Cancel
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Step 3: Import Results -->
                <div id="results-section" style="display: none;">
                    <div style="border-top: 1px solid var(--border-primary); margin: var(--spacing-6) 0; padding-top: var(--spacing-6);"></div>
                    
                    <div style="margin-bottom: var(--spacing-6);">
                        <h3 style="font-size: var(--font-size-xl); font-weight: 700; color: var(--text-primary); margin-bottom: var(--spacing-2);">
                            <i class="fas fa-check-circle" style="color: var(--success-color); margin-right: var(--spacing-2);"></i>
                            Import Results
                        </h3>
                    </div>
                    
                    <div id="import-results" style="margin-bottom: var(--spacing-6);"></div>
                    
                    <div>
                        <button id="new-import-btn" class="psw-btn psw-btn-primary">
                            <i class="fas fa-plus psw-btn-icon"></i>
                            Start New Import
                        </button>
                    </div>
                </div>
                
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        console.log('Dividend import page loaded');
        
        $(document).ready(function() {
            console.log('jQuery ready, initializing dividend import...');
            
            let importData = null;
            
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
                        
                        if (data.success && data.brokers) {
                            data.brokers.forEach(function(broker) {
                                select.append(`<option value="${broker.broker_id}">${broker.broker_name}</option>`);
                            });
                        }
                        
                        updateUploadButton();
                    })
                    .fail(function(xhr, status, error) {
                        console.error('Broker loading failed:', status, error, xhr.responseText);
                        const select = $('#broker-select');
                        select.empty();
                        select.append('<option value="">Select a broker...</option>');
                        select.append('<option value="minimal">Minimal Format (PSW Standard)</option>');
                        select.append('<option value="1">Avanza</option>');
                        select.append('<option value="2">Nordnet</option>');
                        updateUploadButton();
                    });
            }
            
            function loadAccountGroups() {
                console.log('Loading account groups...');
                $.get('./get_account_groups.php')
                    .done(function(data) {
                        console.log('Account group data received:', data);
                        const select = $('#account-group-select');
                        select.empty();
                        select.append('<option value="">Use CSV column or none</option>');
                        
                        if (data.success && data.account_groups) {
                            data.account_groups.forEach(function(group) {
                                select.append(`<option value="${group.portfolio_account_group_id}">${group.portfolio_group_name}</option>`);
                            });
                        }
                    })
                    .fail(function(xhr, status, error) {
                        console.error('Account group loading failed:', status, error, xhr.responseText);
                        const select = $('#account-group-select');
                        select.empty();
                        select.append('<option value="">Use CSV column or none</option>');
                        select.append('<option value="4">PSW Sverige</option>');
                        select.append('<option value="5">PSW Worldwide</option>');
                    });
            }
            
            function updateUploadButton() {
                const brokerSelected = $('#broker-select').val() !== '';
                const fileSelected = $('#csv-file')[0].files.length > 0;
                $('#upload-btn').prop('disabled', !(brokerSelected && fileSelected));
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
                $('#upload-btn').prop('disabled', true).html('<i class="fas fa-spinner fa-spin psw-btn-icon"></i> Processing...');
                
                $.ajax({
                    url: './proper_csv_upload.php',
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(data) {
                        console.log('Upload response:', data);
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
                        $('#upload-btn').prop('disabled', false).html('<i class="fas fa-upload psw-btn-icon"></i> Upload and Parse File');
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
                    showAlert('error', `Found ${data.errors.length} errors that must be fixed before importing`);
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
                        statusBadge = '<span style="background-color: var(--success-color); color: var(--text-inverse); padding: var(--spacing-1) var(--spacing-2); border-radius: var(--radius-sm); font-size: var(--font-size-xs);">Complete</span>';
                    } else if (dividend.is_complete === 0) {
                        statusBadge = '<span style="background-color: var(--warning-color); color: var(--text-inverse); padding: var(--spacing-1) var(--spacing-2); border-radius: var(--radius-sm); font-size: var(--font-size-xs);">Incomplete</span>';
                    } else {
                        statusBadge = '<span style="background-color: var(--error-color); color: var(--text-inverse); padding: var(--spacing-1) var(--spacing-2); border-radius: var(--radius-sm); font-size: var(--font-size-xs);">Error</span>';
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
                
                // Show preview section
                $('#preview-section').show();
            }
            
            function importDividends() {
                $('#import-btn').prop('disabled', true).html('<i class="fas fa-spinner fa-spin psw-btn-icon"></i> Importing...');
                
                const ignoreDuplicates = $('#ignore-duplicates').is(':checked');
                
                $.post('./import_dividends.php', {
                    ignore_duplicates: ignoreDuplicates
                })
                .done(function(data) {
                    if (data.success) {
                        showImportResults(data);
                    } else {
                        showAlert('error', data.error || 'Import failed');
                    }
                })
                .fail(function(xhr) {
                    const response = xhr.responseJSON || {};
                    showAlert('error', response.error || 'Import failed');
                })
                .always(function() {
                    $('#import-btn').prop('disabled', false).html('<i class="fas fa-download psw-btn-icon"></i> Import Dividends');
                });
            }
            
            function showImportResults(data) {
                const results = `
                    <div class="psw-alert psw-alert-success">
                        <h5><i class="fas fa-check-circle"></i> Import Completed Successfully</h5>
                        <div style="margin-top: var(--spacing-2);">
                            <div><strong>${data.imported}</strong> dividends imported</div>
                            ${data.skipped > 0 ? `<div><strong>${data.skipped}</strong> duplicate entries skipped</div>` : ''}
                            <div><strong>${data.total_processed}</strong> total records processed</div>
                        </div>
                    </div>
                `;
                
                $('#import-results').html(results);
                $('#preview-section').hide();
                $('#results-section').show();
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
                const alertClass = type === 'error' ? 'error' : type;
                const alert = `
                    <div class="psw-alert psw-alert-${alertClass}" style="position: relative;">
                        ${message}
                        <button type="button" style="position: absolute; top: var(--spacing-2); right: var(--spacing-2); background: none; border: none; color: inherit; cursor: pointer;" onclick="this.parentElement.remove()">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                `;
                $('#validation-alerts').append(alert);
            }
        });
    </script>

    <?php
    $content = ob_get_clean();
    
    // Include redesigned base layout
    include __DIR__ . '/templates/layouts/base-redesign.php';
    
} catch (Exception $e) {
    $pageTitle = 'Import Error - ' . APP_NAME;
    $content = '
        <div class="psw-card">
            <div class="psw-card-content" style="text-align: center; padding: var(--spacing-8);">
                <i class="fas fa-exclamation-triangle" style="font-size: var(--font-size-4xl); color: var(--error-color); margin-bottom: var(--spacing-4);"></i>
                <h1 style="color: var(--text-primary); margin-bottom: var(--spacing-4);">Import Error</h1>
                <p style="color: var(--text-secondary);">There was an error loading the import page.</p>
            </div>
        </div>
    ';
    
    include __DIR__ . '/templates/layouts/base-redesign.php';
}
?>
