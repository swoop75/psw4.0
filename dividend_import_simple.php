<?php
session_start();
require_once 'config/config.php';
require_once 'config/database.php';
require_once 'src/middleware/Auth.php';

Auth::requireAuth();

// Simple page without output buffering
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dividend Import - PSW 4.0</title>
    <link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/improved-main.css">
    <link rel="stylesheet" href="<?php echo ASSETS_URL; ?>/css/dividend-import.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <header class="unified-header">
        <div class="header-container">
            <a href="<?php echo BASE_URL; ?>" class="logo-header">
                <div class="logo-mini">
                    <i class="fas fa-chart-line"></i>
                </div>
                <span class="logo-text">PSW 4.0</span>
            </a>
            
            <div class="nav-links">
                <div class="nav-item">
                    <a href="javascript:void(0)" class="nav-link nav-dropdown-only">
                        <i class="fas fa-cogs"></i>
                        Functions
                        <i class="fas fa-chevron-down nav-arrow"></i>
                    </a>
                    <div class="submenu">
                        <a href="<?php echo BASE_URL; ?>/dashboard.php" class="submenu-link">Dashboard</a>
                        <a href="<?php echo BASE_URL; ?>/dividend_import.php" class="submenu-link">Dividend Import</a>
                    </div>
                </div>
                
                <div class="user-menu">
                    <button class="login-toggle">
                        <i class="fas fa-user"></i>
                        <?php echo Auth::getUsername(); ?>
                    </button>
                </div>
            </div>
        </div>
    </header>

    <main class="main-container">
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
                        
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        console.log('Simple dividend import page loaded');
        
        $(document).ready(function() {
            console.log('jQuery ready, loading dropdowns...');
            
            // Load brokers
            $.get('./get_brokers.php')
                .done(function(data) {
                    console.log('Broker data:', data);
                    const select = $('#broker-select');
                    select.empty().append('<option value="">Select a broker...</option>');
                    
                    if (data.success && data.brokers) {
                        data.brokers.forEach(function(broker) {
                            select.append(`<option value="${broker.broker_id}">${broker.broker_name}</option>`);
                        });
                    }
                })
                .fail(function(xhr, status, error) {
                    console.error('Broker loading failed:', status, error, xhr.responseText);
                    $('#broker-select').html('<option value="">Error loading brokers</option>');
                });
            
            // Load account groups
            $.get('./get_account_groups.php')
                .done(function(data) {
                    console.log('Account group data:', data);
                    const select = $('#account-group-select');
                    select.empty().append('<option value="">Use CSV column or none</option>');
                    
                    if (data.success && data.account_groups) {
                        data.account_groups.forEach(function(group) {
                            select.append(`<option value="${group.portfolio_account_group_id}">${group.portfolio_group_name}</option>`);
                        });
                    }
                })
                .fail(function(xhr, status, error) {
                    console.error('Account group loading failed:', status, error, xhr.responseText);
                });
        });
    </script>
</body>
</html>