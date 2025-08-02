# 🟢 Avanza.se - Complete Design & Layout Analysis

## Executive Summary

Avanza.se employs a clean, professional design with adaptive theming (light/dark modes) and a focus on performance optimization. Their "MINT" design system provides consistency across all components with a modular, Angular-based architecture.

## Layout Structure

### ASCII Layout Diagram
```
┌─────────────────────────────────────────────────────────────────┐
│ [LOGO] [Nav: Aktier|Fonder|Sparande] [Search] [Login] [Signup]  │ 56px
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  ┌─────────────────┐  ┌─────────────────────────────────────┐   │
│  │   Quick Links   │  │         Market Overview            │   │
│  │ • Populära      │  │  ┌─────────┬─────────┬─────────┐   │   │
│  │ • Mest handlade │  │  │ Stock   │ Price   │ Change  │   │   │
│  │ • Vinnare       │  │  ├─────────┼─────────┼─────────┤   │   │
│  │ • Förlorare     │  │  │ AAPL    │ 150.25  │ +2.1%   │   │   │
│  └─────────────────┘  │  │ TSLA    │ 890.40  │ -1.5%   │   │   │
│                        │  └─────────┴─────────┴─────────┘   │   │
│  ┌─────────────────┐  └─────────────────────────────────────┘   │
│  │   Portfolio     │                                            │
│  │   Summary       │  ┌─────────────────────────────────────┐   │
│  │ Value: 250k SEK │  │            News Feed                │   │
│  │ Change: +2.5%   │  │  • Market Update...                 │   │
│  └─────────────────┘  │  • Company Earnings...              │   │
│                        └─────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────────────┘
       240px                          Flexible Content
```

## Design System Details

### Color Scheme
- **Light Theme**: Background `#fdfdfd`, Dark text
- **Dark Theme**: Background `#031d20`, Light text
- **Adaptive Switching**: Automatic based on system preferences or user selection

### Typography
- **Primary Font**: Roboto (Bold, Medium, Regular)
- **Custom Font**: "Roboto-Avanza-Slab-Bold" for branding
- **Optimization**: Preloaded fonts for performance
- **Rendering**: Smooth anti-aliasing applied

### Layout Specifications

#### Header/Navigation
- **Height**: 56px desktop / 48px mobile
- **Background**: Theme-adaptive
- **Position**: Sticky/fixed positioning
- **Components**: 
  - Logo area: ~120px width
  - Navigation menu: Horizontal layout
  - Search bar: ~250px width
  - User actions: Login/Signup buttons

#### Sidebar (Left Panel)
- **Width**: 240px (expanded)
- **Collapse**: Reduces to icon-only ~60px
- **Content**: Quick links, portfolio summary
- **Scroll**: Independent scrolling area

#### Main Content Area
- **Layout**: Flexible grid system
- **Max Width**: ~1200px centered
- **Padding**: 24px desktop / 16px mobile
- **Sections**: Market overview, data tables, news feed

### Functional Components

#### Data Tables
- **Row Height**: 40px
- **Header Height**: 48px
- **Cell Padding**: 12px horizontal / 8px vertical
- **Font Size**: 13px data / 12px headers
- **Sorting**: Click-to-sort functionality
- **Hover States**: Subtle background change

#### Market Data Cards
- **Size**: Flexible width, ~120px height
- **Padding**: 16px
- **Border Radius**: 8px
- **Content**: Index value, change percentage, chart preview

#### Search Functionality
- **Input Width**: 250px desktop / full-width mobile
- **Height**: 36px
- **Placeholder**: Dynamic suggestions
- **Results**: Dropdown overlay with instant search

### Performance Features
- **Resource Loading**: Dynamic domain-based loading
- **Font Preloading**: Critical fonts loaded immediately
- **Theme Switching**: Instant toggle without page reload
- **Lazy Loading**: Non-critical components loaded on demand

### Responsive Breakpoints
- **Mobile**: < 768px
- **Tablet**: 768px - 1024px
- **Desktop**: 1024px - 1440px
- **Large Desktop**: > 1440px

### Interactive Elements

#### Buttons
- **Primary**: Theme accent color, 36px height
- **Secondary**: Outlined style, same height
- **Hover**: Subtle elevation/color change
- **Active**: Slightly darker shade

#### Form Elements
- **Input Height**: 36px
- **Label Spacing**: 4px below label
- **Field Spacing**: 16px between fields
- **Validation**: Real-time with color indicators

### Accessibility Features
- **Keyboard Navigation**: Full tab support
- **Color Contrast**: WCAG AA compliant
- **Screen Reader**: Proper ARIA labels
- **Focus Indicators**: Clear visual focus states

### Technical Implementation
- **Framework**: Angular-based SPA
- **CSS**: CSS-in-JS with theme variables
- **State Management**: Centralized state handling
- **API Integration**: Real-time data updates

### Key Design Principles
1. **Performance First**: Optimized loading and rendering
2. **Theme Flexibility**: Light/dark mode support
3. **Consistency**: MINT design system adherence
4. **User Experience**: Intuitive navigation and interaction
5. **Accessibility**: Inclusive design practices

### Recommendations for PSW 4.0
1. Implement similar theme switching capability
2. Use consistent sidebar navigation pattern
3. Adopt their table design with proper spacing
4. Follow their button sizing and interaction patterns
5. Implement similar performance optimization techniques