<?php
/**
 * File: config/constants.php
 * Path: C:\Users\laoan\Documents\GitHub\psw\psw4.0\config\constants.php
 * Description: Application constants for PSW 4.0
 */

// User roles
define('ROLE_ADMINISTRATOR', 1);
define('ROLE_USER', 2);

// Page access levels
define('ACCESS_PUBLIC', 0);
define('ACCESS_USER', 1);
define('ACCESS_ADMIN', 2);

// Transaction types
define('TRANSACTION_BUY', 'BUY');
define('TRANSACTION_SELL', 'SELL');
define('TRANSACTION_DIVIDEND', 'DIVIDEND');

// Portfolio status
define('PORTFOLIO_ACTIVE', 1);
define('PORTFOLIO_INACTIVE', 0);

// Company status
define('COMPANY_ACTIVE', 1);
define('COMPANY_DELISTED', 0);

// Menu items and their access levels
define('MENU_STRUCTURE', [
    'dashboard' => [
        'title' => 'Dashboard',
        'access' => ACCESS_USER,
        'admin_only' => false
    ],
    'portfolio' => [
        'title' => 'Portfolio',
        'access' => ACCESS_ADMIN,
        'admin_only' => true,
        'submenu' => [
            'company_list' => ['title' => 'Company List', 'admin_only' => true],
            'company_page' => ['title' => 'Company Page', 'admin_only' => true]
        ]
    ],
    'allocation' => [
        'title' => 'Allocation',
        'access' => ACCESS_ADMIN,
        'admin_only' => true
    ],
    'dividend_estimate' => [
        'title' => 'Dividend Estimate',
        'access' => ACCESS_USER,
        'admin_only' => false,
        'submenu' => [
            'overview' => ['title' => 'Overview', 'admin_only' => false],
            'monthly_overview' => ['title' => 'Monthly Overview', 'admin_only' => false]
        ]
    ],
    'logs' => [
        'title' => 'Logs',
        'access' => ACCESS_USER,
        'admin_only' => false,
        'submenu' => [
            'dividends' => ['title' => 'Dividends', 'admin_only' => false],
            'trades' => ['title' => 'Trades', 'admin_only' => true],
            'corporate_actions' => ['title' => 'Corporate Actions', 'admin_only' => true],
            'cash_transactions' => ['title' => 'Cash Transactions', 'admin_only' => true],
            'expenses' => ['title' => 'Expenses', 'admin_only' => true]
        ]
    ],
    'buying' => [
        'title' => 'Buying',
        'access' => ACCESS_ADMIN,
        'admin_only' => true,
        'submenu' => [
            'buylist_management' => ['title' => 'Buy List', 'admin_only' => true],
            'new_companies' => ['title' => 'New Companies', 'admin_only' => true]
        ]
    ],
    'rules' => [
        'title' => 'Rules',
        'access' => ACCESS_ADMIN,
        'admin_only' => true,
        'submenu' => [
            'rulebook' => ['title' => 'Rulebook', 'admin_only' => true]
        ]
    ],
    'administration' => [
        'title' => 'Administration',
        'access' => ACCESS_ADMIN,
        'admin_only' => true,
        'submenu' => [
            'page_management' => ['title' => 'Page Management', 'admin_only' => true],
            'admin_management' => ['title' => 'Admin Management', 'admin_only' => true],
            'user_management' => ['title' => 'User Management', 'admin_only' => false],
            'masterlist_management' => ['title' => 'Masterlist Management', 'admin_only' => true]
        ]
    ]
]);

// Action icons (Font Awesome classes)
define('ICONS', [
    'add' => 'fas fa-plus-circle',
    'edit' => 'fas fa-pencil-alt',
    'delete' => 'fas fa-trash-alt',
    'save' => 'fas fa-save',
    'cancel' => 'fas fa-times',
    'view' => 'fas fa-eye',
    'export' => 'fas fa-download',
    'import' => 'fas fa-upload',
    'search' => 'fas fa-search',
    'filter' => 'fas fa-filter',
    'dashboard' => 'fas fa-tachometer-alt',
    'portfolio' => 'fas fa-briefcase',
    'allocation' => 'fas fa-chart-pie',
    'dividend' => 'fas fa-coins',
    'logs' => 'fas fa-list-alt',
    'buying' => 'fas fa-shopping-cart',
    'rules' => 'fas fa-gavel',
    'admin' => 'fas fa-cog',
    'user' => 'fas fa-user',
    'company' => 'fas fa-building'
]);

// Status messages
define('STATUS_SUCCESS', 'success');
define('STATUS_ERROR', 'error');
define('STATUS_WARNING', 'warning');
define('STATUS_INFO', 'info');