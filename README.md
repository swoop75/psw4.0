# PSW 4.0 - Pengamaskinen Sverige + Worldwide

**Professional Dividend Portfolio Management & Analysis Platform**

A comprehensive web-based dividend portfolio tracking system built with PHP, MySQL, and modern web technologies. Designed for serious dividend investors who value detailed analytics, professional reporting, and secure data management.

## Features

### 🎯 Core Functionality
- **Portfolio Tracking**: Monitor dividend portfolios across multiple brokers and accounts
- **Dividend Analytics**: Comprehensive dividend income tracking with YTD and all-time statistics
- **Company Research**: Detailed company information with masterlist database integration
- **Professional Dashboard**: Real-time metrics and interactive visualizations
- **Multi-Currency Support**: Handle international investments with automatic SEK conversion

### 📊 Dashboard Highlights
- Portfolio value estimation with daily change tracking
- YTD and all-time dividend income statistics
- Recent dividend payments with company details
- Upcoming ex-dividend dates calendar
- Portfolio allocation by country and currency
- Quick stats and performance indicators

### 🔐 Security Features
- Role-based access control (Administrator/User)
- CSRF protection and input validation
- Rate limiting for login attempts
- Comprehensive audit logging
- Secure session management

## Technical Architecture

### Backend
- **Language**: PHP 8.0+
- **Framework**: Custom MVC architecture
- **Database**: MySQL with multi-database support
- **Authentication**: Session-based with role management

### Frontend
- **Styling**: Custom CSS inspired by Avanza.se and Google Finance
- **JavaScript**: Vanilla JS with interactive dashboard components
- **Responsive**: Mobile-friendly design
- **Charts**: Ready for Chart.js integration

### Database Structure
- **psw_foundation**: User management, masterlist, reference data
- **psw_marketdata**: Market data, pricing, external information
- **psw_portfolio**: Portfolio holdings, transaction logs, dividend data

## Installation & Setup

### Prerequisites
- PHP 8.0 or higher
- MySQL 8.0 or higher
- Web server (Apache/Nginx)
- Composer (for future dependencies)

### Database Configuration

1. **Update database connection settings** in `config/database.php`:
```php
private static $config = [
    'foundation' => [
        'host' => 'your_host',
        'dbname' => 'psw_foundation',
        'username' => 'your_username',
        'password' => 'your_password',
        'charset' => 'utf8mb4'
    ],
    'marketdata' => [
        'host' => 'your_host',
        'dbname' => 'psw_marketdata',
        'username' => 'your_username',
        'password' => 'your_password',
        'charset' => 'utf8mb4'
    ],
    'portfolio' => [
        'host' => 'your_host',
        'dbname' => 'psw_portfolio',
        'username' => 'your_username',
        'password' => 'your_password',
        'charset' => 'utf8mb4'
    ]
];
```

2. **Configure application settings** in `config/config.php`:
   - Update `BASE_URL` to match your domain
   - Set `APP_DEBUG` to `false` for production
   - Adjust session timeout and security settings

### Web Server Setup

Point your web server document root to the `public/` directory.

**Apache Example** (`.htaccess` recommended):
```apache
DocumentRoot /path/to/psw4.0/public
<Directory /path/to/psw4.0/public>
    AllowOverride All
    Require all granted
</Directory>
```

**Nginx Example**:
```nginx
root /path/to/psw4.0/public;
index index.php;

location / {
    try_files $uri $uri/ /index.php?$query_string;
}

location ~ \.php$ {
    fastcgi_pass unix:/var/run/php/php8.0-fpm.sock;
    fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
    include fastcgi_params;
}
```

## Database Requirements

### Required Tables
The application currently uses these existing tables:

**psw_foundation:**
- `users` - User accounts and authentication
- `roles` - User role definitions
- `masterlist` - Master company database (ISIN-based)
- `countries` - Country reference data
- `currencies` - Currency definitions
- `share_types` - Share type classifications

**psw_portfolio:**
- `log_dividends` - Complete dividend transaction history

### Sample Data
The dashboard will display real data from your existing `log_dividends` and `masterlist` tables. Ensure these tables contain data for full functionality.

## Usage

### First Time Setup
1. Access the application via web browser
2. Create an administrator user account (see User Management below)
3. Login with admin credentials
4. Dashboard will display real dividend data from your database

### User Management
Currently, users need to be created directly in the database. Add to the `users` table:
```sql
INSERT INTO users (username, email, password_hash, role_id, created_at) 
VALUES ('admin', 'admin@yourcompany.com', '$2y$10$hash', 1, NOW());
```

Role IDs:
- `1` = Administrator (full access)
- `2` = User (limited access)

### Navigation Structure
- **Dashboard**: Portfolio overview and key metrics
- **Portfolio** (#): Company lists and detailed views (Admin only)
- **Allocation** (#): Portfolio allocation analysis (Admin only)
- **Dividend Estimate**: Income projections and forecasts
- **Logs**: Transaction and dividend histories
- **Buying** (#): Buy lists and research tools (Admin only)
- **Rules** (#): Investment rules and guidelines (Admin only)
- **Administration** (#): User and system management (Admin only)

*# denotes Administrator-only access*

## Development

### Project Structure
```
psw4.0/
├── assets/                 # Static assets (CSS, JS, images)
├── config/                 # Configuration files
├── src/
│   ├── controllers/        # MVC Controllers
│   ├── models/            # Data models
│   ├── middleware/        # Authentication & middleware
│   └── utils/             # Utility classes
├── public/                # Web root
│   ├── index.php          # Main entry point
│   └── api/              # API endpoints
├── templates/             # HTML templates
├── storage/              # Logs and exports
└── documentation/        # Project documentation
```

### Current Implementation Status

**✅ Completed:**
- Foundation architecture (MVC, routing, authentication)
- Landing page with professional design
- User authentication and session management
- Dashboard with real dividend data integration
- Database models for dividends, companies, portfolio
- API endpoints for dashboard data
- Responsive design with Avanza.se-inspired styling

**🔄 In Progress:**
- Portfolio holdings management (waiting for holdings tables)
- Chart.js integration for visualizations
- Additional page implementations

**📋 Planned:**
- Complete menu system implementation
- Advanced reporting and export features
- API integrations for market data
- Mobile app considerations

### Adding New Features

1. **Models**: Add new models in `src/models/`
2. **Controllers**: Create controllers in `src/controllers/`
3. **Views**: Add templates in `templates/pages/`
4. **Routes**: Add new pages in `public/`
5. **Database**: Update models to use real database queries

### Logging
Application logs are stored in `storage/logs/` with daily rotation. Check logs for debugging and monitoring.

## Contributing

This is a personal portfolio management system. For customization:

1. Fork the repository
2. Create feature branches
3. Follow existing code patterns and naming conventions
4. Update the development log in `developing/develop_log.txt`
5. Test thoroughly with real data

## License

Private project - All rights reserved.

## Support

For issues or questions:
1. Check application logs in `storage/logs/`
2. Review database connections and permissions
3. Verify web server configuration
4. Check PHP error logs

---

**Built with professional dividend investing in mind.**  
*Version 4.0.0 - Professional PHP/MySQL Architecture*