<?php
session_start();
require_once 'config/config.php';
require_once 'config/database.php';
require_once 'src/middleware/Auth.php';
require_once 'src/utils/Security.php';

// Set page title
$pageTitle = 'Dividend Import';

require_once 'templates/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Dividend CSV Import</h3>
                </div>
                <div class="card-body">
                    
                    <!-- Step 1: File Upload -->
                    <div id="upload-section">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="broker-select">Select Broker:</label>
                                    <select id="broker-select" class="form-control" required>
                                        <option value="">Loading brokers...</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="csv-file">Select CSV File:</label>
                                    <input type="file" id="csv-file" class="form-control-file" accept=".csv,.xlsx" required>
                                    <small class="form-text text-muted">Supported formats: CSV, Excel (.xlsx)</small>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row mt-3">
                            <div class="col-12">
                                <button id="upload-btn" class="btn btn-primary" disabled>
                                    <i class="fas fa-upload"></i> Upload and Parse File
                                </button>
                                <div id="upload-progress" class="progress mt-2" style="display: none;">
                                    <div class="progress-bar" role="progressbar" style="width: 0%"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Step 2: Preview and Validation -->
                    <div id="preview-section" style="display: none;">
                        <hr>
                        <h4>Import Preview</h4>
                        
                        <div id="validation-alerts"></div>
                        
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <div class="info-box">
                                    <span class="info-box-icon bg-info"><i class="fas fa-file-csv"></i></span>
                                    <div class="info-box-content">
                                        <span class="info-box-text">Total Rows</span>
                                        <span class="info-box-number" id="total-rows">0</span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="info-box">
                                    <span class="info-box-icon bg-warning"><i class="fas fa-exclamation-triangle"></i></span>
                                    <div class="info-box-content">
                                        <span class="info-box-text">Warnings</span>
                                        <span class="info-box-number" id="warning-count">0</span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="info-box">
                                    <span class="info-box-icon bg-danger"><i class="fas fa-times-circle"></i></span>
                                    <div class="info-box-content">
                                        <span class="info-box-text">Errors</span>
                                        <span class="info-box-number" id="error-count">0</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Preview Table -->
                        <div class="table-responsive">
                            <table id="preview-table" class="table table-bordered table-striped">
                                <thead class="thead-dark">
                                    <tr>
                                        <th>Payment Date</th>
                                        <th>ISIN</th>
                                        <th>Ticker</th>
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
                            <h5>Validation Issues</h5>
                            <div class="accordion" id="validation-accordion">
                                <div class="card">
                                    <div class="card-header" id="errors-heading">
                                        <h6 class="mb-0">
                                            <button class="btn btn-link text-danger" type="button" data-toggle="collapse" data-target="#errors-collapse">
                                                <i class="fas fa-times-circle"></i> Errors (<span id="error-detail-count">0</span>)
                                            </button>
                                        </h6>
                                    </div>
                                    <div id="errors-collapse" class="collapse" data-parent="#validation-accordion">
                                        <div class="card-body">
                                            <ul id="error-list" class="list-unstyled"></ul>
                                        </div>
                                    </div>
                                </div>
                                <div class="card">
                                    <div class="card-header" id="warnings-heading">
                                        <h6 class="mb-0">
                                            <button class="btn btn-link text-warning" type="button" data-toggle="collapse" data-target="#warnings-collapse">
                                                <i class="fas fa-exclamation-triangle"></i> Warnings (<span id="warning-detail-count">0</span>)
                                            </button>
                                        </h6>
                                    </div>
                                    <div id="warnings-collapse" class="collapse" data-parent="#validation-accordion">
                                        <div class="card-body">
                                            <ul id="warning-list" class="list-unstyled"></ul>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Import Controls -->
                        <div class="mt-4">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="ignore-duplicates">
                                <label class="form-check-label" for="ignore-duplicates">
                                    Ignore duplicate entries (skip existing dividends)
                                </label>
                            </div>
                            
                            <div class="mt-3">
                                <button id="import-btn" class="btn btn-success" disabled>
                                    <i class="fas fa-download"></i> Import Dividends
                                </button>
                                <button id="cancel-btn" class="btn btn-secondary ml-2">
                                    <i class="fas fa-times"></i> Cancel
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Step 3: Import Results -->
                    <div id="results-section" style="display: none;">
                        <hr>
                        <h4>Import Results</h4>
                        <div id="import-results"></div>
                        <button id="new-import-btn" class="btn btn-primary mt-3">
                            <i class="fas fa-plus"></i> Start New Import
                        </button>
                    </div>
                    
                </div>
            </div>
        </div>
    </div>
</div>

<!-- jQuery and Bootstrap JS -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
$(document).ready(function() {
    let importData = null;
    
    // Load brokers on page load
    loadBrokers();
    
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
        $.get('get_brokers.php')
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
                console.error('Failed to load brokers:', status, error);
                // Fallback to hardcoded brokers
                const select = $('#broker-select');
                select.empty();
                select.append('<option value="">Select a broker...</option>');
                select.append('<option value="1">Broker 1</option>');
                select.append('<option value="2">Broker 2</option>');
                select.append('<option value="3">Broker 3</option>');
                select.append('<option value="4">Broker 4</option>');
                select.append('<option value="5">Broker 5</option>');
                updateUploadButton();
                showAlert('warning', 'Using fallback broker list');
            });
    }
    
    function updateUploadButton() {
        const brokerSelected = $('#broker-select').val() !== '';
        const fileSelected = $('#csv-file')[0].files.length > 0;
        $('#upload-btn').prop('disabled', !(brokerSelected && fileSelected));
    }
    
    function showBrokerInfo() {
        const brokerId = $('#broker-select').val();
        if (brokerId) {
            // Show broker-specific format information
            showAlert('info', `Selected broker ${brokerId}. Please ensure your CSV file matches the expected format for this broker.`);
        }
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
        
        // Show progress
        $('#upload-progress').show();
        $('#upload-btn').prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Processing...');
        
        $.ajax({
            url: 'proper_csv_upload.php',
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
            const statusBadge = dividend.is_complete ? 
                '<span class="badge badge-success">Complete</span>' : 
                '<span class="badge badge-warning">Incomplete</span>';
            
            const row = `
                <tr>
                    <td>${dividend.payment_date}</td>
                    <td>${dividend.isin}</td>
                    <td>${dividend.ticker || '-'}</td>
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
        
        $.post('src/controllers/DividendImportController.php?action=import', {
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
            <div class="alert alert-${alertClass} alert-dismissible fade show" role="alert">
                ${message}
                <button type="button" class="close" data-dismiss="alert">
                    <span>&times;</span>
                </button>
            </div>
        `;
        $('#validation-alerts').append(alert);
    }
});
</script>

<?php require_once 'templates/footer.php'; ?>