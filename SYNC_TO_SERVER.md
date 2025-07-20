# Files to Sync to Server

## Updated Files Need Syncing! ⚠️

The beautiful design changes for masterlist and buylist pages are on the laptop (C: drive) but need to be copied to the server (D: drive) to be visible.

## From Laptop (C: drive) to Server (D: drive)

Copy these UPDATED files from:
`C:\Users\laoan\Documents\GitHub\psw\psw4.0\`

To your server at:
`D:\github\psw\psw4.0\`

## 🎨 UPDATED FILES WITH BEAUTIFUL DESIGN:

### 1. **Landing Page Template (PHP)**
- `templates/pages/landing.php` ⭐ (Updated with beautiful design)

### 2. **Masterlist Management Files**
- `public/masterlist_management.php` (Updated with Inter font)
- `assets/css/masterlist-management.css` ⭐ (Updated with beautiful styling)

### 3. **Buylist Management Files**  
- `public/buylist_management.php` (Updated with Inter font)
- `assets/css/improved-buylist-management.css` ⭐ (Complete beautiful design system)

### 4. **Landing Page HTML**
- `public/beautiful_landing_original_logo.html` (Fixed spelling: Pengamaskinen)

## Quick Sync Commands (run on server):

```bash
# Copy the main application files (templates, assets, public)
robocopy "C:\Users\laoan\Documents\GitHub\psw\psw4.0" "D:\github\psw\psw4.0" /E /XD .git

# Or copy specific folders
xcopy "C:\Users\laoan\Documents\GitHub\psw\psw4.0\templates" "D:\github\psw\psw4.0\templates" /E /Y
xcopy "C:\Users\laoan\Documents\GitHub\psw\psw4.0\assets" "D:\github\psw\psw4.0\assets" /E /Y
xcopy "C:\Users\laoan\Documents\GitHub\psw\psw4.0\public" "D:\github\psw\psw4.0\public" /E /Y
```

## After Syncing, Test These URLs:

1. **http://100.117.171.98/** ⭐ (Main landing page)
2. **http://100.117.171.98/masterlist_management.php** (Beautiful masterlist design)
3. **http://100.117.171.98/buylist_management.php** (Beautiful buylist design)
4. **http://100.117.171.98/beautiful_landing_original_logo.html** (Fixed spelling)

## What You'll See After Syncing:

✅ **Landing Page**: Pengamaskinen (corrected spelling) with beautiful design
✅ **Masterlist Page**: Beautiful green-blue gradients, Inter font, glass morphism cards
✅ **Buylist Page**: Complete design system with custom properties, beautiful animations
✅ **Consistent Design**: All pages share the same modern, professional styling

## 🔥 Beautiful Design Features Applied:

- **Colors**: #00C896 (Avanza green) to #1A73E8 (Google blue) gradients
- **Typography**: Inter font family with proper weight scales  
- **Effects**: Glass morphism with backdrop blur
- **Animations**: Smooth hover effects with scale and shadows
- **Buttons**: Rounded corners (50px) with beautiful hover states
- **Cards**: 20px border radius with floating animations
- **Shadows**: Beautiful depth with rgba(0, 200, 150, 0.3) colors