# 🔵 Nordnet.se - Complete Design & Layout Analysis

## Executive Summary

Nordnet.se features a sophisticated dark-themed design with professional typography using "Nordnet Sans Mono" font. The platform emphasizes high-density data presentation with excellent readability and a comprehensive responsive grid system.

## Layout Structure

### ASCII Layout Diagram
```
┌─────────────────────────────────────────────────────────────────┐
│ [LOGO] [Marknaden|Sparande|Handel] [Search] [Logga in]         │ 48px
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│ ┌─────────────────────────────────────────────────────────────┐ │
│ │                   Market Dashboard                          │ │
│ │ ┌─────────────┬─────────────┬─────────────┬─────────────┐   │ │
│ │ │    OMX30    │   DAX       │   S&P 500   │   NASDAQ    │   │ │
│ │ │   2,245.50  │  15,832.10  │  4,567.80   │  14,234.60  │   │ │
│ │ │   +12.35    │   -45.22    │   +23.45    │   +67.89    │   │ │
│ │ └─────────────┴─────────────┴─────────────┴─────────────┘   │ │
│ └─────────────────────────────────────────────────────────────┘ │
│                                                                 │
│ ┌─────────────┐ ┌─────────────────────────────────────────────┐ │
│ │   Filters   │ │              Stock List                     │ │
│ │ ┌─────────┐ │ │ ┌─────────┬─────────┬─────────┬─────────┐   │ │
│ │ │ Sektor  │ │ │ │ Name    │ Price   │ Change  │ Volume  │   │ │
│ │ └─────────┘ │ │ ├─────────┼─────────┼─────────┼─────────┤   │ │
│ │ ┌─────────┐ │ │ │ Volvo   │ 234.50  │ +1.2%   │ 1.2M    │   │ │
│ │ │ Marknad │ │ │ │ H&M     │ 156.80  │ -0.8%   │ 890K    │   │ │
│ │ └─────────┘ │ │ │ SEB     │ 142.30  │ +2.1%   │ 2.1M    │   │ │
│ └─────────────┘ └─────────────────────────────────────────────┘ │
│     200px                      Flexible Content                 │
└─────────────────────────────────────────────────────────────────┘
```

## Design System Details

### Color Scheme
- **Primary Background**: Black (#000000)
- **Secondary Background**: Dark Gray (#121212)
- **Accent Color**: Blue (#336BFF)
- **Text Colors**: White, Light Gray (#1c1c1c)
- **Success/Error**: Green/Red variants

### Typography System
- **Primary Font Family**: "Nordnet Sans Mono"
- **Fallback**: System fonts (San Francisco, Segoe UI, Roboto)
- **Font Weights**: 
  - Regular: 400
  - Bold: 700
  - Extra Bold: 800

#### Font Size Scale
- **Main Headlines**: 72px desktop / 48px mobile (weight: 800, line-height: 80px/56px)
- **Section Headers**: 32-48px
- **Body Text**: 16px base / 20px larger screens (line-height: 24px)
- **Navigation**: 14-16px
- **Data Tables**: 13-14px
- **Small Text/Annotations**: 12px

### Layout Specifications

#### Header/Navigation
- **Height**: 48px desktop / 40px mobile
- **Background**: Black (#000000)
- **Position**: Sticky positioning
- **Logo Area**: ~140px width
- **Navigation Menu**: Horizontal with dropdowns
- **Search Area**: ~200px width
- **User Actions**: Login button on right

#### Filter Sidebar (Left)
- **Width**: 200px
- **Background**: Dark gray variant
- **Content**: Sector filters, market selection, sort options
- **Scroll**: Independent vertical scroll
- **Collapse**: Mobile transforms to overlay

#### Main Content Grid
- **Layout**: CSS Grid based
- **Max Width**: Responsive containers
  - 768px (mobile)
  - 992px (tablet)
  - 1280px (desktop)
  - 1680px (large desktop)
- **Padding**: 16px mobile / 24px desktop

### Responsive Breakpoints
- **Small**: < 768px
- **Medium**: 768px - 992px
- **Large**: 992px - 1280px
- **Extra Large**: 1280px - 1680px
- **XXL**: > 1680px

### Functional Components

#### Market Dashboard Cards
- **Size**: Equal width grid, ~300px each
- **Height**: ~120px
- **Border Radius**: 8px
- **Padding**: 16px
- **Content**: Index name, value, change with color coding
- **Hover**: Subtle elevation effect

#### Data Tables
- **Row Height**: 44px data rows / 48px headers
- **Cell Padding**: 12px horizontal / 8px vertical
- **Font**: 13px data / 12px headers (bold)
- **Borders**: 1px bottom borders
- **Hover**: Row highlight effect
- **Sorting**: Clickable headers with indicators

#### Filter Components
- **Dropdown Height**: 36px
- **Button Style**: Rounded corners (4px)
- **Active State**: Blue accent color
- **Multi-select**: Checkbox style selections
- **Clear Filters**: Dedicated reset button

#### Search Functionality
- **Input Style**: Rounded, dark background
- **Height**: 36px
- **Width**: Full width on mobile, fixed on desktop
- **Placeholder**: Light gray text
- **Results**: Overlay dropdown with instant results

### Interactive Elements

#### Buttons
- **Primary**: Blue (#336BFF), 36-40px height
- **Secondary**: Outlined white/gray, same height
- **Border Radius**: 100px (fully rounded)
- **Padding**: 12px horizontal
- **Hover/Active**: Color variations and slight elevation

#### Form Controls
- **Input Height**: 36-40px
- **Select Height**: 36px
- **Checkbox Size**: 16x16px
- **Radio Button Size**: 16px diameter
- **Label Spacing**: 8px from control

### Data Visualization

#### Charts & Graphs
- **Mini Charts**: 60x30px sparklines in table cells
- **Color Coding**: Green for positive, red for negative
- **Hover Info**: Tooltip overlays
- **Time Series**: Line charts with gradient fills

#### Performance Indicators
- **Percentage Change**: Color-coded (+green, -red)
- **Volume Bars**: Relative width indicators
- **Trend Arrows**: Directional change indicators

### Technical Implementation

#### CSS Grid System
```css
.nordnet-grid {
  display: grid;
  grid-template-columns: 200px 1fr;
  grid-template-rows: 48px 1fr;
  grid-template-areas: 
    "header header"
    "sidebar main";
  min-height: 100vh;
}
```

#### Responsive Behavior
- **Mobile**: Sidebar becomes overlay
- **Tablet**: Reduced sidebar width
- **Desktop**: Full layout with all components
- **Large**: Increased content max-width

### Accessibility Features
- **High Contrast**: White text on dark backgrounds
- **Keyboard Navigation**: Full tab order support
- **Focus Indicators**: Clear blue outlines
- **Screen Reader**: Proper semantic markup
- **Text Scaling**: Responsive font sizes

### Performance Optimizations
- **Lazy Loading**: Table rows load on scroll
- **Virtual Scrolling**: Large datasets handled efficiently
- **Image Optimization**: Responsive images with multiple sources
- **Caching**: Smart data caching for market updates

### Key Design Principles
1. **High Density**: Maximum information per screen space
2. **Professional Aesthetic**: Dark theme for focused trading
3. **Consistency**: Unified spacing and typography scale
4. **Performance**: Optimized for real-time data updates
5. **Accessibility**: Inclusive design with high contrast

### Unique Features
- **Monospace Font**: Excellent for financial data alignment
- **Dark Theme**: Reduces eye strain for long trading sessions
- **Modular Cards**: Flexible dashboard components
- **Real-time Updates**: Live data refresh without page reload

### Recommendations for PSW 4.0
1. Consider dark theme option for professional appearance
2. Implement similar high-density table layouts
3. Use their responsive grid system approach
4. Adopt monospace fonts for numerical data
5. Follow their button styling with rounded corners
6. Implement similar filter sidebar pattern
7. Use color coding for positive/negative values consistently