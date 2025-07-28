-- Setup Yield KPI Tracking
-- This script ensures yield KPIs are properly tracked in our system

USE psw_marketdata;

-- First, let's identify yield-related KPIs
-- Common yield KPI IDs from Börsdata (these may need to be verified):
-- KPI ID 1: Often represents dividend yield
-- We need to verify the actual KPI ID for yield in your system

-- Check existing yield-related KPIs
SELECT kpi_id, name_en, name_sv, format 
FROM kpi_metadata 
WHERE name_en LIKE '%yield%' 
   OR name_en LIKE '%dividend%' 
   OR name_sv LIKE '%yield%' 
   OR name_sv LIKE '%utdel%'
   OR name_sv LIKE '%dividend%';

-- If no yield KPIs exist, we may need to add them manually
-- This would typically be done by your data import scripts

-- Example: Insert yield KPI metadata if it doesn't exist
-- (Uncomment and adjust the KPI ID based on Börsdata documentation)
/*
INSERT IGNORE INTO kpi_metadata (kpi_id, name_en, name_sv, format, is_string) 
VALUES 
(1, 'Dividend Yield', 'Direktavkastning', 'percentage', 0),
(2, 'Dividend Yield TTM', 'Direktavkastning TTM', 'percentage', 0);
*/

-- Check what yield data already exists in KPI tables
SELECT 
    kg.kpi_id,
    km.name_en,
    kg.group_period,
    kg.calculation,
    COUNT(*) as record_count,
    MIN(kg.numeric_value) as min_yield,
    MAX(kg.numeric_value) as max_yield,
    AVG(kg.numeric_value) as avg_yield
FROM kpi_global kg
JOIN kpi_metadata km ON kg.kpi_id = km.kpi_id
WHERE km.name_en LIKE '%yield%' OR km.name_en LIKE '%dividend%'
GROUP BY kg.kpi_id, km.name_en, kg.group_period, kg.calculation
ORDER BY kg.kpi_id, kg.group_period, kg.calculation;

-- Check Nordic yield data
SELECT 
    kn.kpi_id,
    km.name_en,
    kn.group_period,
    kn.calculation,
    COUNT(*) as record_count,
    MIN(kn.numeric_value) as min_yield,
    MAX(kn.numeric_value) as max_yield,
    AVG(kn.numeric_value) as avg_yield
FROM kpi_nordic kn
JOIN kpi_metadata km ON kn.kpi_id = km.kpi_id
WHERE km.name_en LIKE '%yield%' OR km.name_en LIKE '%dividend%'
GROUP BY kn.kpi_id, km.name_en, kn.group_period, kn.calculation
ORDER BY kn.kpi_id, kn.group_period, kn.calculation;