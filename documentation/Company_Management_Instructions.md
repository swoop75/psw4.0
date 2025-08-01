# PSW 4.0 Company Management Instructions

**Version**: 1.0  
**Date**: August 2025  
**Purpose**: Comprehensive guide for managing companies not supported by Börsdata API

---

## 📋 Table of Contents

1. [Overview](#overview)
2. [Company Categories](#company-categories)
3. [Standard Operating Procedures](#standard-operating-procedures)
4. [Monthly/Quarterly Routines](#monthlyquarterly-routines)
5. [Troubleshooting](#troubleshooting)
6. [Data Quality Checks](#data-quality-checks)

---

## 🎯 Overview

The PSW 4.0 system integrates data from multiple sources:
- **Börsdata API**: Primary source for Nordic, European, and North American markets
- **Manual Company Data**: Companies not covered by Börsdata
- **Masterlist**: Historical and delisted company information
- **Alternative Price Feeds**: For non-Börsdata companies

---

## 🏷️ Company Categories

### Category 1: Non-Börsdata Companies (Active Holdings)
**Identification**: Companies in your portfolio/trades that don't exist in Börsdata Nordic or Global databases.

**Characteristics**:
- Typically from: Austria, Czech Republic, some UK closed-end funds, specific Canadian/US instruments
- Currently held in portfolio
- Need manual data entry for company information
- Require alternative price feeds

**Examples**: CZ0008019106 (Czech), IE0003290289 (Ireland), GB0001990497 (UK), CA33843T1084 (Canada)

### Category 2: Delisted Companies (Still Held)
**Identification**: Companies marked as delisted in masterlist but still appear in portfolio/trades.

**Characteristics**:
- Previously covered by Börsdata
- Delisted from exchanges but still held
- Historical data available in system
- No current price feeds available

**Examples**: SE0000105199 (Haldex AB), SE0015812219 (Swedish Match)

### Category 3: Delisted Companies (No Longer Held)
**Identification**: Delisted companies not in current portfolio but with historical trades/dividends.

**Characteristics**:
- Historical holdings only
- Complete data available in masterlist
- Important for historical performance analysis
- No action required unless data gaps found

---

## 📝 Standard Operating Procedures

### SOP-001: Adding Non-Börsdata Companies

**Frequency**: As needed when new unsupported companies are identified  
**Responsible**: Portfolio Administrator  
**Tools Required**: Admin Company Management interface

#### Prerequisites
1. Verify company is not in Börsdata (run company analysis)
2. Confirm company is actively held in portfolio
3. Gather company information (name, country, sector, exchange)

#### Step-by-Step Process

**Step 1: Access Admin Interface**
1. Navigate to `http://100.117.171.98/admin_company_management.php`
2. Login with admin credentials
3. Verify system access and data loading

**Step 2: Identify Unsupported Companies**
1. Click "Find Unsupported Companies" button
2. Review analysis results:
   - **Red Section**: Truly unsupported companies (action required)
   - **Blue Section**: Delisted companies (informational only)
3. Note ISINs requiring manual entry

**Step 3: Research Company Information**
For each unsupported ISIN, gather:
- **Company Name**: Official registered name
- **Country**: Country of incorporation/domicile
- **Sector**: Business sector (e.g., Real Estate, Energy, Financial Services)
- **Branch**: Industry sub-sector if known
- **Exchange**: Primary trading exchange
- **Currency**: Trading currency
- **Company Type**: Stock, ETF, Closed-End Fund, REIT, Other
- **Dividend Frequency**: Annual, Quarterly, Monthly, Irregular, None

**Research Sources**:
- Company official website
- Exchange websites (LSE, TSE, Vienna Stock Exchange, etc.)
- Financial data providers (Yahoo Finance, Google Finance)
- Regulatory filings

**Step 4: Add Company Data**
1. Click "Add Manual Entry" button for the company
2. Fill in the modal form with researched information:
   - ISIN (pre-filled, read-only)
   - Ticker (if available)
   - Company Name (required)
   - Country (required)
   - All other available fields
3. Add notes with research sources
4. Click "Save Company"

**Step 5: Verify Addition**
1. Confirm company appears in manual companies table
2. Test unified view query: 
   ```sql
   SELECT * FROM psw_foundation.vw_unified_companies WHERE isin = 'ISIN_CODE';
   ```
3. Verify portfolio overview page displays company correctly

#### Data Quality Standards
- **ISIN**: Must be valid 12-character format
- **Company Name**: Use official name, not trading name
- **Country**: Use full country name, not abbreviations
- **Currency**: Use 3-letter ISO codes (EUR, GBP, USD, CAD, CZK, etc.)
- **Notes**: Always document data sources

#### Common Issues
- **Duplicate ISIN**: Check if already exists in manual_company_data
- **Invalid ISIN Format**: Verify 12-character ISIN structure
- **Missing Company Information**: Research thoroughly before adding placeholder data
- **Currency Mismatch**: Ensure currency matches trading exchange

---

### SOP-002: Handling Delisted Companies

**Frequency**: Quarterly review  
**Responsible**: Portfolio Administrator  
**Tools Required**: Admin interface, SQL access

#### Process
1. **Identification**:
   - Run company analysis to identify delisted companies
   - Review delisted companies section in analysis results

2. **Active Holdings Review**:
   - For delisted companies still in portfolio: **NO ACTION REQUIRED**
   - Historical data remains available through masterlist
   - Monitor for any missing trade/dividend data

3. **Historical Data Verification**:
   - Verify all historical trades are imported
   - Verify all historical dividends are imported
   - Check for data gaps in critical periods

4. **Documentation**:
   - Note any data gaps in system notes
   - Update delisted status if needed
   - Maintain audit trail

---

### SOP-003: Monthly Data Sync Verification

**Frequency**: Monthly (after Börsdata sync)  
**Responsible**: System Administrator  
**Tools Required**: Admin interface, log files

#### Process
1. **Sync Status Check**:
   - Review Börsdata sync logs
   - Verify sync completion times
   - Check for sync errors

2. **New Unsupported Companies**:
   - Run "Find Unsupported Companies" analysis
   - Compare with previous month's results
   - Identify any new companies requiring manual entry

3. **Missing Companies Alert**:
   - Check for previously supported companies that disappeared
   - Investigate companies missing for 3+ days
   - Review notification queue for alerts

4. **Data Quality Review**:
   - Verify price feeds for manual companies
   - Check portfolio calculation accuracy
   - Review dashboard metrics consistency

---

### SOP-004: Quarterly System Health Check

**Frequency**: Quarterly  
**Responsible**: Portfolio Administrator  
**Tools Required**: Full system access

#### Process
1. **Complete Data Audit**:
   - Review all manual company entries
   - Verify company information accuracy
   - Update outdated information

2. **Portfolio Reconciliation**:
   - Cross-reference portfolio positions with broker statements
   - Identify any missing companies or positions
   - Verify trade and dividend completeness

3. **System Performance Review**:
   - Review query performance on unified views
   - Check database indexes effectiveness
   - Optimize slow-running queries

4. **Documentation Update**:
   - Update this instruction manual
   - Document any process improvements
   - Record lessons learned

---

## 🔄 Monthly/Quarterly Routines

### Monthly Checklist
- [ ] Verify Börsdata sync completion
- [ ] Run unsupported companies analysis
- [ ] Check notification queue
- [ ] Review new portfolio positions
- [ ] Update manual company data if needed
- [ ] Verify alternative price feeds operational

### Quarterly Checklist
- [ ] Complete system health check (SOP-004)
- [ ] Review all manual company entries for accuracy
- [ ] Portfolio reconciliation with broker statements
- [ ] Update company information (sector changes, etc.)
- [ ] Clean up inactive/sold positions
- [ ] Review and update this instruction manual

### Annual Checklist
- [ ] Complete audit of all company data
- [ ] Review masterlist for outdated entries
- [ ] System performance optimization
- [ ] Backup verification and restore testing
- [ ] User access review and updates

---

## 🔧 Troubleshooting

### Issue: Company Not Found in Analysis
**Symptoms**: Company exists in portfolio but not found by analysis
**Causes**: 
- ISIN format inconsistency
- Company exists in Börsdata but not matched
- Data sync issues

**Resolution**:
1. Verify ISIN format in portfolio vs Börsdata
2. Check for collation issues in database queries
3. Manual search in both Nordic and Global instruments
4. Review sync logs for errors

### Issue: Duplicate Company Entries
**Symptoms**: Same company appears in multiple data sources
**Causes**: 
- Company added manually then appeared in Börsdata
- ISIN format variations
- Corporate actions (mergers, acquisitions)

**Resolution**:
1. Identify primary data source (Börsdata preferred)
2. Remove duplicate from manual_company_data
3. Update portfolio calculations
4. Document in system notes

### Issue: Missing Price Data
**Symptoms**: Manual companies showing no price updates
**Causes**: 
- Alternative price feed issues
- API key problems
- Company not covered by price provider

**Resolution**:
1. Check alternative API status and keys
2. Verify company ticker with price provider
3. Consider additional data sources
4. Manual price updates if necessary

### Issue: Portfolio Calculations Incorrect
**Symptoms**: Dashboard shows wrong totals or percentages
**Causes**: 
- Unified view not updating
- Price calculation errors
- Currency conversion issues

**Resolution**:
1. Refresh unified view: `SELECT * FROM psw_foundation.vw_unified_companies`
2. Verify price and FX rate data
3. Recalculate portfolio manually
4. Check for database locks or sync issues

---

## 📊 Data Quality Checks

### Daily Automated Checks
- Price feed connectivity
- Database sync status
- Critical error notifications

### Weekly Manual Checks
- New unsupported companies
- Portfolio value consistency
- Price data accuracy spot checks

### Monthly Comprehensive Checks
- Complete data reconciliation
- System performance metrics
- User access and security review

### Quarterly Deep Dive
- Full audit of manual entries
- Comparison with external sources
- Process improvement opportunities

---

## 📞 Escalation Procedures

### Level 1: Standard Issues
**Handler**: Portfolio Administrator  
**Examples**: Adding new companies, updating information, routine maintenance

### Level 2: Technical Issues
**Handler**: System Administrator  
**Examples**: Database errors, API failures, sync problems  
**Escalation Trigger**: Issues not resolved within 2 hours

### Level 3: Critical System Issues
**Handler**: Development Team  
**Examples**: Data corruption, security breaches, system unavailability  
**Escalation Trigger**: System downtime > 30 minutes or data integrity issues

---

## 📝 Change Log

| Version | Date | Changes | Author |
|---------|------|---------|--------|
| 1.0 | Aug 2025 | Initial version with core procedures | Claude Code |

---

## 📚 References

- PSW 4.0 Database Schema Documentation
- Börsdata API Documentation
- Alternative Price Feed API Documentation  
- MySQL Optimization Best Practices

---

**End of Instructions Manual v1.0**