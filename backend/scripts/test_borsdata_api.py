#!/usr/bin/env python3
"""
Test script for Börsdata API connection
Quick verification that the API key and endpoint are working
"""

import requests
import json
from datetime import datetime

# Börsdata API configuration
API_BASE_URL = 'https://apiservice.borsdata.se'
API_ENDPOINT = '/v1/instruments/kpis/metadata'
AUTH_KEY = '55d57eb27768456b9aa975e158d12898'

def test_borsdata_api():
    """Test Börsdata API connection and response"""
    print("=" * 50)
    print("BÖRSDATA API CONNECTION TEST")
    print("=" * 50)
    print(f"Timestamp: {datetime.now()}")
    print(f"API URL: {API_BASE_URL}{API_ENDPOINT}")
    print(f"Auth Key: {AUTH_KEY[:10]}...")
    print()
    
    try:
        # Make API request
        url = f"{API_BASE_URL}{API_ENDPOINT}"
        params = {'authKey': AUTH_KEY}
        headers = {'accept': 'application/json'}
        
        print("Making API request...")
        response = requests.get(url, params=params, headers=headers, timeout=10)
        
        print(f"Status Code: {response.status_code}")
        print(f"Response Headers: {dict(response.headers)}")
        print()
        
        if response.status_code == 200:
            data = response.json()
            print("✅ API request successful!")
            print(f"Response structure: {type(data)}")
            
            if isinstance(data, dict):
                print(f"Response keys: {list(data.keys())}")
                
                if 'kpiHistoryMetadatas' in data:
                    kpi_list = data['kpiHistoryMetadatas']
                    print(f"✅ Found 'kpiHistoryMetadatas' with {len(kpi_list)} items")
                    
                    # Show first few items
                    for i, kpi in enumerate(kpi_list[:3]):
                        print(f"\nKPI {i+1}:")
                        print(f"  ID: {kpi.get('kpiId')}")
                        print(f"  Swedish Name: {kpi.get('nameSv')}")
                        print(f"  English Name: {kpi.get('nameEn')}")
                        print(f"  Format: {kpi.get('format')}")
                        print(f"  Is String: {kpi.get('isString')}")
                    
                    if len(kpi_list) > 3:
                        print(f"\n... and {len(kpi_list) - 3} more KPIs")
                        
                else:
                    print("❌ 'kpiHistoryMetadatas' not found in response")
                    print("Available keys:", list(data.keys()))
            else:
                print(f"❌ Unexpected response type: {type(data)}")
                
        else:
            print(f"❌ API request failed with status code: {response.status_code}")
            print(f"Response: {response.text[:500]}")
            
    except requests.exceptions.Timeout:
        print("❌ API request timed out")
    except requests.exceptions.ConnectionError:
        print("❌ Failed to connect to API endpoint")
    except requests.exceptions.RequestException as e:
        print(f"❌ API request failed: {e}")
    except json.JSONDecodeError as e:
        print(f"❌ Failed to parse API response as JSON: {e}")
    except Exception as e:
        print(f"❌ Unexpected error: {e}")
    
    print("\n" + "=" * 50)
    print("TEST COMPLETED")
    print("=" * 50)

if __name__ == "__main__":
    test_borsdata_api()