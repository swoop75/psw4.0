# Files to Sync to Server

## From Laptop (C: drive) to Server (D: drive)

Copy these NEW files from:
`C:\Users\laoan\Documents\GitHub\psw\psw4.0\public\`

To your server at:
`D:\github\psw\psw4.0\public\`

## New Files to Copy:

1. **beautiful_landing_final.html** ⭐ (MAIN FIXED VERSION)
2. **test_logo.html** (for logo testing)
3. **psw_philosophy.html** (your philosophy page)
4. **logo_placement_guide.html** (logo guide)
5. **logo_left_title.html** (layout demo)

## Also Copy Assets Folder:

From: `C:\Users\laoan\Documents\GitHub\psw\psw4.0\public\assets\`
To: `D:\github\psw\psw4.0\public\assets\`

This includes:
- `assets/css/improved-main.css`
- `assets/js/improved-main.js`
- `assets/img/psw-logo.png` (your logo file)

## Quick Sync Commands (run on server):

```bash
# Copy all new HTML files
copy "C:\Users\laoan\Documents\GitHub\psw\psw4.0\public\*.html" "D:\github\psw\psw4.0\public\"

# Copy assets folder
xcopy "C:\Users\laoan\Documents\GitHub\psw\psw4.0\public\assets" "D:\github\psw\psw4.0\public\assets" /E /Y

# Or use robocopy (better)
robocopy "C:\Users\laoan\Documents\GitHub\psw\psw4.0\public" "D:\github\psw\psw4.0\public" /E /XD .git
```

## After Syncing, Test These URLs:

1. http://100.117.171.98/beautiful_landing_final.html ⭐
2. http://100.117.171.98/test_logo.html
3. http://100.117.171.98/psw_philosophy.html

## All Fixes in beautiful_landing_final.html:

✅ Logo loading (with your psw-logo.png)
✅ "Access Your Portfolio" text removed
✅ Philosophy link as first feature bullet
✅ Shield icon for "Professional & Secure"