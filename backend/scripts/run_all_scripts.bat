@echo off
echo Starting PSW 4.0 Data Import Scripts...
echo.

cd /d "C:\Users\laoan\Documents\GitHub\psw\psw4.0\backend\scripts"

echo [1/5] Running global_instruments.py...
python global_instruments.py
if %errorlevel% neq 0 (
    echo ERROR: global_instruments.py failed with exit code %errorlevel%
    pause
    exit /b %errorlevel%
)

echo [2/5] Running global_latest_prices.py...
python global_latest_prices.py
if %errorlevel% neq 0 (
    echo ERROR: global_latest_prices.py failed with exit code %errorlevel%
    pause
    exit /b %errorlevel%
)

echo [3/5] Running nordic_instruments.py...
python nordic_instruments.py
if %errorlevel% neq 0 (
    echo ERROR: nordic_instruments.py failed with exit code %errorlevel%
    pause
    exit /b %errorlevel%
)

echo [4/5] Running nordic_latest_prices.py...
python nordic_latest_prices.py
if %errorlevel% neq 0 (
    echo ERROR: nordic_latest_prices.py failed with exit code %errorlevel%
    pause
    exit /b %errorlevel%
)

echo [5/6] Running country_info.py...
python country_info.py
if %errorlevel% neq 0 (
    echo ERROR: country_info.py failed with exit code %errorlevel%
    pause
    exit /b %errorlevel%
)

echo [6/6] Running mysql_db_overview.py...
python mysql_db_overview.py
if %errorlevel% neq 0 (
    echo ERROR: mysql_db_overview.py failed with exit code %errorlevel%
    pause
    exit /b %errorlevel%
)

echo.
echo All scripts completed successfully!
echo Check logs in: p:\logs
pause