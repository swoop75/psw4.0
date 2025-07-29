# PSW 4.0 - Complete Design Documentation

## Table of Contents
1. [Overview](#overview)
2. [Design System](#design-system)
3. [Typography](#typography)
4. [Color Palette](#color-palette)
5. [Iconography](#iconography)
6. [Layout & Spacing](#layout--spacing)
7. [Component Library](#component-library)
8. [Page Specifications](#page-specifications)
9. [Responsive Design](#responsive-design)
10. [Design Patterns](#design-patterns)

---

## Overview

PSW 4.0 (Pengemaskinen Sverige + Worldwide) is a modern financial portfolio management application with a beautiful, professional design inspired by Avanza.se and Google Finance. The design emphasizes clean aesthetics, excellent usability, and sophisticated visual hierarchy.

### Design Philosophy
- **Modern & Professional**: Clean, sophisticated interface suitable for financial applications
- **User-Centric**: Intuitive navigation and clear information hierarchy
- **Beautiful**: Elegant gradients, smooth animations, and thoughtful typography
- **Responsive**: Seamless experience across all device sizes
- **Accessible**: High contrast ratios and clear visual indicators

---

## Design System

### CSS Custom Properties (CSS Variables)
The application uses a comprehensive design system with CSS custom properties for consistency:

```css
:root {
    /* Brand Colors - Primary Palette */
    --primary-color: #00C896;          /* Avanza green */
    --primary-dark: #00A682;           /* Darker green */
    --primary-light: #E6F9F5;          /* Very light green */
    
    /* Secondary Colors */
    --secondary-color: #1A73E8;        /* Google blue */
    --secondary-dark: #1557B0;
    --secondary-light: #E3F2FD;
    
    /* Accent Colors */
    --accent-red: #EA4335;             /* Google red for losses */
    --accent-green: #34A853;           /* Google green for gains */
    --accent-yellow: #FBBC04;          /* Google yellow for warnings */
    --accent-orange: #FF6D01;          /* Accent orange */
    
    /* Neutral Colors */
    --text-primary: #1F2937;           /* Dark gray */
    --text-secondary: #6B7280;         /* Medium gray */
    --text-muted: #9CA3AF;             /* Light gray */
    --text-light: #F9FAFB;             /* Very light */
    
    /* Background Colors */
    --bg-primary: #FFFFFF;             /* White */
    --bg-secondary: #F8FAFC;           /* Light gray background */
    --bg-tertiary: #F1F5F9;            /* Slightly darker gray */
    --bg-dark: #0F172A;                /* Dark mode background */
}
```

---

## Typography

### Font Stack
- **Primary**: Inter, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif
- **Monospace**: 'SF Mono', Monaco, 'Cascadia Code', 'Roboto Mono', Consolas, 'Courier New', monospace

### Font Sizes
- **--text-xs**: 0.75rem (12px)
- **--text-sm**: 0.875rem (14px)
- **--text-base**: 1rem (16px) - Base size
- **--text-lg**: 1.125rem (18px)
- **--text-xl**: 1.25rem (20px)
- **--text-2xl**: 1.5rem (24px)
- **--text-3xl**: 1.875rem (30px)
- **--text-4xl**: 2.25rem (36px)

### Font Weights
- **--font-light**: 300
- **--font-normal**: 400 - Default
- **--font-medium**: 500
- **--font-semibold**: 600
- **--font-bold**: 700

### Typography Usage Guidelines
- **Headers (h1-h6)**: Use semibold weight (600) with appropriate sizes
- **Body Text**: Regular weight (400) with text-base size (16px)
- **Small Text**: text-sm (14px) for secondary information
- **Monospace**: Used for ISINs, tickers, and financial data
- **Line Height**: 1.6 for body text, 1.25 for headers

---

## Color Palette

### Primary Colors
- **Primary Green (#00C896)**: Main brand color, buttons, highlights
- **Primary Dark (#00A682)**: Hover states, darker accents
- **Primary Light (#E6F9F5)**: Backgrounds, subtle highlights

### Secondary Colors
- **Secondary Blue (#1A73E8)**: Secondary actions, links
- **Secondary Dark (#1557B0)**: Hover states
- **Secondary Light (#E3F2FD)**: Backgrounds

### Functional Colors
- **Success/Gains (#34A853)**: Positive financial data, success states
- **Error/Losses (#EA4335)**: Negative financial data, error states
- **Warning (#FBBC04)**: Cautions, pending states
- **Info (#1A73E8)**: Information, neutral states

### Text Colors
- **Primary Text (#1F2937)**: Main content, headers
- **Secondary Text (#6B7280)**: Supporting text, labels
- **Muted Text (#9CA3AF)**: Subtle text, placeholders
- **Light Text (#F9FAFB)**: Text on dark backgrounds

### Background Colors
- **Primary Background (#FFFFFF)**: Main content areas
- **Secondary Background (#F8FAFC)**: Page backgrounds
- **Tertiary Background (#F1F5F9)**: Subtle sections
- **Card Backgrounds**: Often use white with subtle transparency

---

## Iconography

### Icon Library
- **Font Awesome 6.4.0**: Primary icon library
- **Icon Style**: Solid style for primary actions, Regular for secondary
- **Icon Sizes**: 
  - Small: 14px (text-sm)
  - Default: 16px (text-base)
  - Large: 20px (text-xl)
  - XL: 24px (text-2xl)

### Common Icons Usage
- **Dashboard**: `fas fa-chart-area`
- **Portfolio**: `fas fa-briefcase`
- **Companies**: `fas fa-building`
- **Dividends**: `fas fa-coins`
- **User Management**: `fas fa-users`
- **Settings**: `fas fa-cog`
- **Search**: `fas fa-search`
- **Add/Create**: `fas fa-plus`
- **Edit**: `fas fa-edit`
- **Delete**: `fas fa-trash`
- **Export**: `fas fa-download`
- **Import**: `fas fa-upload`

### Icon Guidelines
- Icons should be semantically meaningful
- Consistent size within context (all toolbar icons same size)
- Proper color contrast (min 3:1 ratio)
- Use filled icons for primary actions, outlined for secondary

---

## Layout & Spacing

### Spacing Scale
- **--space-1**: 0.25rem (4px)
- **--space-2**: 0.5rem (8px)
- **--space-3**: 0.75rem (12px)
- **--space-4**: 1rem (16px) - Base unit
- **--space-5**: 1.25rem (20px)
- **--space-6**: 1.5rem (24px)
- **--space-8**: 2rem (32px)
- **--space-10**: 2.5rem (40px)
- **--space-12**: 3rem (48px)
- **--space-16**: 4rem (64px)
- **--space-20**: 5rem (80px)

### Border Radius
- **--radius-sm**: 0.25rem (4px)
- **--radius-md**: 0.375rem (6px)
- **--radius-lg**: 0.5rem (8px)
- **--radius-xl**: 0.75rem (12px)
- **--radius-2xl**: 1rem (16px)
- **--radius-full**: 9999px (full circle)

### Container Sizes
- **Desktop Container**: 80vw max-width (optimized for modern displays)
- **Mobile Container**: Full width with padding
- **Content Cards**: Max-width with auto margins for centering

### Layout Patterns
- **Grid Systems**: CSS Grid for main layouts, Flexbox for components
- **Responsive**: Mobile-first approach with progressive enhancement
- **Spacing**: 8px base unit system for consistent spacing

---

## Component Library

### Buttons

#### Primary Button
```css
.btn-primary {
    background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
    color: white;
    padding: var(--space-3) var(--space-6);
    border-radius: var(--radius-lg);
    font-weight: var(--font-medium);
    transition: all var(--transition-normal);
}
```

#### Secondary Button
```css
.btn-secondary {
    background: var(--text-secondary);
    color: white;
    /* Similar styling with different colors */
}
```

#### Button Sizes
- **Default**: 12px vertical, 24px horizontal padding
- **Small (.btn-sm)**: 8px vertical, 16px horizontal padding
- **Large (.btn-lg)**: 16px vertical, 32px horizontal padding

### Cards

#### Standard Card
```css
.card {
    background: var(--bg-primary);
    border-radius: var(--radius-2xl);
    box-shadow: var(--shadow-md);
    border: 1px solid var(--border-light);
    overflow: hidden;
}
```

#### Metric Card (Dashboard)
- Elevated shadow on hover
- Color-coded left border
- Icon with gradient background
- Large numeric display with monospace font

### Forms

#### Input Fields
```css
.form-control {
    padding: var(--space-4);
    border: 2px solid var(--border-light);
    border-radius: var(--radius-lg);
    font-size: var(--text-base);
    transition: all var(--transition-normal);
}

.form-control:focus {
    border-color: var(--primary-color);
    box-shadow: 0 0 0 3px rgba(0, 200, 150, 0.1);
}
```

#### Form Labels
- Font weight: 500 (medium)
- Color: Primary text
- Margin bottom: 8px

### Tables

#### Data Table
```css
.data-table {
    width: 100%;
    border-collapse: collapse;
    font-size: var(--text-sm);
}

.data-table th {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: var(--space-5);
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.data-table tbody tr:hover {
    background: rgba(102, 126, 234, 0.08);
    transform: translateY(-2px);
}
```

### Navigation

#### Unified Header
- Fixed position at top
- Backdrop blur effect
- Logo with gradient text effect
- Dropdown navigation menus
- User authentication area

#### Main Navigation
- Sticky below header
- Horizontal scrolling on mobile
- Active state indicators
- Dropdown submenus

---

## Page Specifications

### Landing Page
**File**: `beautiful_landing_perfect.html`

#### Layout
- **Hero Section**: Centered content with animated logo
- **Features Section**: Three-column feature grid
- **CTA Section**: Centered call-to-action

#### Visual Elements
- **Logo**: Circular gradient background with rotation animation
- **Title**: Large gradient text (4.5rem) with letter spacing
- **Features**: Icon circles with hover animations
- **Colors**: Primary green to secondary blue gradients

#### Typography
- **Main Title**: 4.5rem, font-weight 700, gradient text
- **Subtitle**: 2.25rem, font-weight 400, italic
- **Feature Text**: 1.5rem headings, 1.1rem body text

---

### Dashboard Page
**Files**: `dashboard.php`, `improved-dashboard.css`

#### Layout Structure
- **Header**: Gradient background with title and last updated info
- **Metrics Grid**: 4-column responsive grid of metric cards
- **Content Grid**: 2-column layout (main content + sidebar)
- **Widgets**: Modular content blocks

#### Design Elements
- **Metric Cards**: 
  - White background with blur effect
  - Left-colored border (4px)
  - Icon with gradient background
  - Large number display (3xl, bold, monospace)
  - Hover animations (translateY, scale)

- **Charts**: 
  - Canvas elements with rounded corners
  - Custom color schemes matching brand
  - Interactive hover states

#### Color Usage
- **Headers**: Primary to secondary gradient
- **Cards**: White with subtle shadows
- **Metrics**: Monospace font for numbers
- **Status**: Color-coded (green=positive, red=negative)

---

### User Management Page
**Files**: `user_management.php`, `improved-user-management.css`

#### Layout
- **Page Header**: Gradient background with user info
- **Stats Grid**: User statistics cards
- **Tabs Container**: Tabbed interface for different functions
- **Data Tables**: User listing with action buttons

#### Design Features
- **Tab Navigation**: 
  - Horizontal tabs with active states
  - Bottom border indicators
  - Smooth transitions

- **User Cards**: 
  - Avatar with gradient background
  - Role badges with specific colors
  - Status indicators with dots

- **Action Buttons**: 
  - Icon-only buttons for space efficiency
  - Hover states with color changes
  - Consistent sizing (32px × 32px)

---

### Buylist Management Page
**Files**: `buylist_management.php`, `improved-buylist-management.css`

#### Design Features
- **Company Information**: 
  - Company name with ticker badge
  - ISIN in monospace font
  - Country and exchange information
  - Masterlist badges

- **Priority System**: 
  - Color-coded priority badges (1-4 levels)
  - Visual hierarchy with different colors
  - Consistent badge sizing

- **Search and Filter**: 
  - Advanced search box with icon
  - Dropdown filters with checkboxes
  - Real-time filtering capabilities

---

### Company Detail Page
**Files**: `company_detail.php`, `company-detail.css`

#### Layout
- **Header**: Gradient background with breadcrumb navigation
- **Info Cards**: Grid layout of information sections
- **Action Buttons**: Grid layout of available actions

#### Visual Elements
- **Breadcrumbs**: White text with separators
- **Status Badges**: Color-coded status indicators
- **Data Display**: Organized in info grids
- **Empty States**: Centered with large icons

---

### Dividend Pages
**Files**: `dividend_estimate.php`, `dividend-estimate.css`, `dividend_logs.php`

#### Design Elements
- **Summary Cards**: Financial metric displays
- **Quarterly Grid**: 2×2 grid for quarterly data
- **Charts**: Line and bar charts for trends
- **Growth Metrics**: Progress bars and indicators

#### Color Coding
- **Completed Data**: Green background (#e8f5e8)
- **Estimated Data**: Yellow background (#fff3cd)
- **Growth Positive**: Green text (#28a745)
- **Growth Negative**: Red text (#dc3545)

---

## Responsive Design

### Breakpoints
- **Mobile**: ≤ 480px
- **Tablet**: 481px - 768px
- **Desktop**: 769px - 1024px
- **Large Desktop**: ≥ 1025px

### Mobile Adaptations
- **Navigation**: Collapsible hamburger menu
- **Tables**: Horizontal scrolling or stacked layout
- **Cards**: Single column layout
- **Text Sizes**: Reduced for smaller screens
- **Touch Targets**: Minimum 44px × 44px

### Tablet Adaptations
- **Grid Layouts**: Reduced column counts
- **Sidebar**: Full-width on smaller tablets
- **Forms**: Single column layout
- **Tables**: Scrollable with sticky headers

### Desktop Optimizations
- **Container Width**: 80vw for optimal readability
- **Multi-column Layouts**: Full utilization of screen space
- **Hover States**: Rich interactive feedback
- **Typography**: Larger sizes for comfortable reading

---

## Design Patterns

### Animation and Transitions
- **Standard Duration**: 0.3s ease
- **Fast Interactions**: 0.15s ease
- **Slow Animations**: 0.5s ease
- **Hover Effects**: translateY(-2px) with shadow increase
- **Loading States**: Spinner animations with brand colors

### Shadow System
```css
--shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
--shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
--shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
--shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
```

### Gradient Patterns
- **Primary Gradient**: 135deg, primary-color to primary-dark
- **Secondary Gradient**: 135deg, secondary-color to secondary-dark
- **Hero Gradient**: 135deg, primary-color to secondary-color
- **Background Gradient**: 135deg, light grays for subtle texture

### Accessibility Features
- **Color Contrast**: Minimum 4.5:1 for normal text, 3:1 for large text
- **Focus States**: Clear focus indicators with brand colors
- **Alt Text**: Descriptive alt text for all images
- **Semantic HTML**: Proper heading hierarchy and landmark elements
- **Keyboard Navigation**: Full keyboard accessibility

### Loading States
- **Skeleton Loading**: Gray placeholder boxes
- **Spinner**: Brand-colored rotating icon
- **Progress Bars**: Animated progress with gradient fills
- **Empty States**: Descriptive messages with appropriate icons

---

## File Structure

### CSS Architecture
```
assets/css/
├── improved-main.css           # Main stylesheet with design system
├── improved-dashboard.css      # Dashboard-specific styles
├── improved-buylist-management.css  # Buylist page styles
├── improved-user-management.css     # User management styles
├── dividend-estimate.css       # Dividend estimation page
├── company-detail.css         # Company detail page
├── tooltip.css               # Tooltip components
└── main.css                  # Legacy/fallback styles
```

### Template Structure
```
templates/
├── header.php               # Common header template
├── footer.php              # Common footer template
└── layouts/
    └── base.php            # Base layout template
```

### Asset Organization
```
assets/
├── css/                    # Stylesheets
├── js/                     # JavaScript files
└── img/                    # Images and icons
    └── psw-logo.svg       # Main logo file
```

---

## Best Practices

### CSS Guidelines
1. Use CSS custom properties for consistency
2. Follow BEM naming convention where applicable
3. Mobile-first responsive design
4. Minimize use of !important declarations
5. Use semantic class names

### Performance
1. Optimize images and use modern formats (WebP, SVG)
2. Minimize CSS and JavaScript files
3. Use efficient selectors
4. Implement proper caching strategies
5. Lazy load non-critical resources

### Maintenance
1. Regular design system updates
2. Cross-browser testing
3. Accessibility audits
4. Performance monitoring
5. User feedback integration

---

*This documentation covers the complete design system for PSW 4.0. It should be updated as the design evolves and new components are added.*

**Last Updated**: July 29, 2025
**Version**: 4.0
**Author**: Claude Code Assistant