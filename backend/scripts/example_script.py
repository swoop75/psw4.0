#!/usr/bin/env python3
"""
Example Python script for PSW Cron
This demonstrates a simple script that can be scheduled
"""

import sys
import time
from datetime import datetime


def main():
    print(f"Example script started at {datetime.now()}")
    
    # Simulate some work
    print("Performing some important task...")
    time.sleep(2)
    
    # Example of generating output
    print("Task completed successfully!")
    print(f"Processed 42 items")
    
    # Example of potential error handling
    try:
        # Simulate a task that might fail
        if datetime.now().second % 10 == 0:  # Fail 10% of the time for demo
            raise Exception("Simulated error for demonstration")
        
        print("All operations completed without errors")
        
    except Exception as e:
        print(f"Error occurred: {e}", file=sys.stderr)
        sys.exit(1)  # Exit with error code
    
    print(f"Example script finished at {datetime.now()}")


if __name__ == '__main__':
    main()