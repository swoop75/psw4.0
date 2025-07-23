#!/usr/bin/env python3
"""
Master script to run all PSW 4.0 data import scripts
"""
import subprocess
import sys
import os
from datetime import datetime

# List of scripts to run in order
SCRIPTS = [
    'global_instruments.py',
    'global_latest_prices.py', 
    'nordic_instruments.py',
    'nordic_latest_prices.py',
    'country_info.py',
    'mysql_db_overview.py'
]

def run_script(script_name):
    """Run a single script and return success status"""
    print(f"Running {script_name}...")
    try:
        result = subprocess.run([sys.executable, script_name], 
                              capture_output=True, 
                              text=True, 
                              timeout=300)  # 5 minute timeout
        
        if result.returncode == 0:
            print(f"✅ {script_name} completed successfully")
            return True
        else:
            print(f"❌ {script_name} failed with exit code {result.returncode}")
            if result.stderr:
                print(f"Error output: {result.stderr}")
            return False
            
    except subprocess.TimeoutExpired:
        print(f"❌ {script_name} timed out after 5 minutes")
        return False
    except Exception as e:
        print(f"❌ Error running {script_name}: {e}")
        return False

def main():
    start_time = datetime.now()
    print("🚀 Starting PSW 4.0 Data Import Scripts...")
    print(f"Start time: {start_time}")
    print("=" * 50)
    
    # Change to scripts directory
    script_dir = os.path.dirname(os.path.abspath(__file__))
    os.chdir(script_dir)
    
    success_count = 0
    failed_scripts = []
    
    for i, script in enumerate(SCRIPTS, 1):
        print(f"\n[{i}/{len(SCRIPTS)}] {script}")
        print("-" * 30)
        
        if run_script(script):
            success_count += 1
        else:
            failed_scripts.append(script)
    
    # Summary
    end_time = datetime.now()
    duration = end_time - start_time
    
    print("\n" + "=" * 50)
    print("📊 EXECUTION SUMMARY")
    print("=" * 50)
    print(f"✅ Successful: {success_count}/{len(SCRIPTS)}")
    print(f"❌ Failed: {len(failed_scripts)}/{len(SCRIPTS)}")
    print(f"⏱️  Total duration: {duration}")
    print(f"📝 Check logs in: p:\\logs")
    print(f"📄 Check documentation in: p:\\documentation\\MySQL_overview")
    
    if failed_scripts:
        print(f"\n❌ Failed scripts: {', '.join(failed_scripts)}")
        return 1
    else:
        print("\n🎉 All scripts completed successfully!")
        return 0

if __name__ == "__main__":
    exit_code = main()
    sys.exit(exit_code)