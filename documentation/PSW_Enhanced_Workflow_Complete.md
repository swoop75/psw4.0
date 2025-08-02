# PSW 4.0 Enhanced Complete System Workflow

## 🎯 Purpose
This workflow shows **complete PSW system connections** - how all pages, databases, and components work together from investment decision through portfolio management.

## 📊 Main System Workflow

### **Phase 1: Investment Research & Company Data Management**

```
START
│
├── Research Company
│   │
│   ├── Company in Börsdata API?
│   │   ├── YES → Use Börsdata data → Continue to Investment Decision
│   │   │
│   │   └── NO → Check Manual Company Data
│   │       ├── Exists in manual_company_data table?
│   │       │   ├── YES → Use manual data → Continue to Investment Decision  
│   │       │   └── NO → ADD TO MANUAL COMPANY SYSTEM
│   │               │
│   │               ├── Access: admin_company_management.php
│   │               ├── Check if delisted (masterlist table)
│   │               ├── Validate company data (DataValidator.php)
│   │               ├── Insert into manual_company_data table
│   │               └── Continue to Investment Decision
│
└── Investment Decision Branch
```

### **Phase 2: Portfolio Position Analysis**

```
Investment Decision Branch
│
├── Do you currently own this stock?
│   │
│   ├── YES → Check current position
│   │   │   (Query: psw_portfolio.portfolio table)
│   │   │
│   │   ├── Want to increase position? → GO TO BUY BRANCH
│   │   ├── Want to maintain position? → GO TO MONITORING BRANCH  
│   │   └── Want to reduce/exit position? → GO TO SELL BRANCH
│   │
│   └── NO → Do you want to own it?
│       ├── YES → GO TO BUY BRANCH
│       └── NO → End (Do nothing)
```

### **Phase 3A: BUY BRANCH**

```
BUY BRANCH
│
├── Execute Purchase (External: Broker)
│
├── Record Transaction
│   ├── Access: trade_import.php OR manual trade entry
│   ├── Insert into: psw_portfolio.log_trades
│   ├── Data includes: ISIN, ticker, quantity, price, fees, date
│   │
│   ├── Company data source validation:
│   │   ├── Börsdata company → Link to existing data
│   │   └── Manual company → Link to manual_company_data table
│   │
│   └── Auto-calculate: total cost, cost basis
│
├── Update Portfolio Position  
│   ├── Insert/Update: psw_portfolio.portfolio table
│   ├── Calculate: new average cost, total shares
│   └── Update: position value, unrealized P&L
│
└── Unified View Update
    ├── vw_unified_companies view reflects new position
    ├── Portfolio displays updated holdings
    └── Position tracking includes manual companies
```

### **Phase 3B: SELL BRANCH**

```
SELL BRANCH
│
├── Execute Sale (External: Broker)
│
├── Record Transaction
│   ├── Access: trade_import.php OR manual trade entry  
│   ├── Insert into: psw_portfolio.log_trades (negative quantity)
│   ├── Calculate: realized gain/loss
│   │
│   └── Link to existing company data (Börsdata or manual)
│
├── Update Portfolio Position
│   ├── Update: psw_portfolio.portfolio table
│   ├── Reduce: share count, adjust average cost
│   ├── If full sale: mark position as closed
│   │
│   └── Calculate: realized P&L, remaining position
│
└── Position Reflects in System
    ├── Portfolio shows updated/closed position
    ├── Trade history shows complete transaction record
    └── P&L calculations include all manual companies
```

### **Phase 4: MONITORING BRANCH**

```
MONITORING BRANCH (Ongoing)
│
├── Regular Data Updates
│   ├── Börsdata companies: Automatic price/dividend updates
│   ├── Manual companies: Manual price updates required
│   └── Check for delisting events
│
├── Dividend Processing  
│   ├── Receive dividend (External: Broker)
│   ├── Record in: psw_portfolio.log_dividends
│   ├── Calculate: yield, annual income
│   └── Update: portfolio value
│
├── Corporate Actions (Future scope)
│   └── Stock splits, mergers, spin-offs
│
└── Company Status Monitoring
    ├── Börsdata companies: Auto-detect missing companies
    ├── Manual companies: Manual monitoring required  
    ├── Access: admin_company_management.php
    └── Check Missing Companies function
```

## 🗄️ Database Table Interactions

### **Core Tables Used:**

1. **psw_portfolio.portfolio** - Current positions
2. **psw_portfolio.log_trades** - All buy/sell transactions  
3. **psw_portfolio.log_dividends** - Dividend history
4. **psw_foundation.manual_company_data** - Non-Börsdata companies
5. **psw_foundation.masterlist** - Delisted company reference
6. **psw_marketdata.nordic_instruments** - Börsdata Nordic data
7. **psw_marketdata.global_instruments** - Börsdata Global data

### **Key Views:**

1. **vw_unified_companies** - Combined view of all companies (Börsdata + Manual)
2. **vw_all_instruments** - Combined Börsdata view
3. **combined_instruments** - Alternative combined view

## 🌐 System Page Connections

### **User-Facing Pages:**
- **Portfolio Dashboard** - Main portfolio view, position summaries
- **Trade Import** - Bulk import of broker data
- **Manual Trade Entry** - Individual transaction entry
- **Company Research** - Company data lookup and analysis

### **Admin Pages:**
- **admin_company_management.php** - Manual company data management
  - Find unsupported companies
  - Add/edit manual company data  
  - Check missing companies
  - Manage delisted companies

### **API Endpoints:**
- **Börsdata API Integration** - Automatic data sync
- **validate_company.php** - Real-time company validation
- **Data import scripts** - Scheduled data updates

## 🔄 Complete Workflow Example

### **Scenario: Adding GB0001990497 (Your UK Company)**

```
1. RESEARCH PHASE
   ├── Check Börsdata API → Not found
   ├── Access admin_company_management.php
   ├── Click "Find Unsupported Companies"  
   └── GB0001990497 appears in delisted companies

2. DATA MANAGEMENT PHASE
   ├── Click "Add Manual Entry" for GB0001990497
   ├── Form pre-fills: ISIN, country (United Kingdom), currency (GBP)
   ├── Add: Company name, sector, ticker
   ├── Validate via DataValidator.php
   └── Insert into manual_company_data table

3. INVESTMENT DECISION
   ├── Research complete → Decision to buy
   └── Proceed to BUY BRANCH

4. TRANSACTION EXECUTION  
   ├── Buy shares via broker
   ├── Record in log_trades table
   ├── Link to manual_company_data via ISIN
   └── Update portfolio table

5. SYSTEM REFLECTION
   ├── Portfolio shows new UK position
   ├── vw_unified_companies includes manual company
   ├── Position tracking works for non-Börsdata company
   └── Dividends can be recorded when received
```

## 🎯 System Integration Points

### **Critical Connections:**

1. **Company Data → Portfolio** - ISIN linking ensures positions connect to correct company data
2. **Manual Data → Unified View** - vw_unified_companies combines all data sources
3. **Trades → Positions** - log_trades updates drive portfolio calculations  
4. **Admin Tools → User Experience** - Manual company management enables complete portfolio tracking

### **Data Flow Validation:**

1. **Company Validation** - DataValidator.php ensures data quality
2. **Duplicate Prevention** - Cross-database duplicate checking
3. **Data Integrity** - Database constraints and triggers
4. **Error Handling** - Comprehensive validation and error reporting

## 📋 Workflow Decision Matrix

| Company Status | In Börsdata | In Manual DB | Action Required |
|---------------|-------------|--------------|-----------------|
| New Research | Yes | - | Use Börsdata data directly |
| New Research | No | No | Add to manual_company_data |
| New Research | No | Yes | Use existing manual data |
| Existing Holdings | Yes | - | Standard processing |
| Existing Holdings | No | Yes | Use manual data |
| Delisted | No | Check masterlist | Add to manual if not exists |

This complete workflow shows exactly how every component of your PSW system connects together!