/**
 * File: assets/js/date-range-picker.js
 * Description: Central date range picker component for PSW 4.0
 * Provides consistent date range functionality across the application
 */

class DateRangePicker {
    constructor(containerId, options = {}) {
        this.container = document.getElementById(containerId);
        if (!this.container) {
            throw new Error(`Container with ID '${containerId}' not found`);
        }
        
        this.options = {
            defaultMonthsBack: 3,
            format: 'YYYY-MM-DD',
            autoApply: false,
            showPresets: true,
            onApply: null,
            ...options
        };
        
        this.isOpen = false;
        this.currentFromDate = null;
        this.currentToDate = null;
        this.tempFromDate = null;
        this.tempToDate = null;
        
        this.init();
    }
    
    init() {
        this.setDefaultDates();
        this.render();
        this.bindEvents();
    }
    
    setDefaultDates() {
        const today = new Date();
        const currentMonth = new Date(today.getFullYear(), today.getMonth(), 1);
        const monthsBack = new Date(today.getFullYear(), today.getMonth() - this.options.defaultMonthsBack, 1);
        const lastDayOfCurrentMonth = new Date(today.getFullYear(), today.getMonth() + 1, 0);
        
        this.currentFromDate = this.formatDate(monthsBack);
        this.currentToDate = this.formatDate(lastDayOfCurrentMonth);
        this.tempFromDate = this.currentFromDate;
        this.tempToDate = this.currentToDate;
    }
    
    render() {
        this.container.innerHTML = `
            <div class="date-range-picker">
                <div class="date-range-display" data-toggle="date-range">
                    <i class="fas fa-calendar-alt"></i>
                    <span class="date-range-text">${this.currentFromDate} - ${this.currentToDate}</span>
                    <i class="fas fa-chevron-down"></i>
                </div>
                <div class="date-range-overlay" style="display: none;">
                    <div class="date-range-header">
                        <h4>Date Range Selector</h4>
                        <button class="close-btn" data-action="close">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    <div class="date-range-content">
                        <div class="date-range-panels">
                            <div class="date-panel from-panel">
                                <h5>From Date</h5>
                                <input type="text" class="date-input from-input" value="${this.tempFromDate}" placeholder="YYYY-MM-DD">
                                <div class="calendar-container from-calendar"></div>
                            </div>
                            <div class="date-panel to-panel">
                                <h5>To Date</h5>
                                <input type="text" class="date-input to-input" value="${this.tempToDate}" placeholder="YYYY-MM-DD">
                                <div class="calendar-container to-calendar"></div>
                            </div>
                            <div class="presets-panel">
                                <h5>Quick Presets</h5>
                                <div class="presets-grid">
                                    <button class="preset-btn" data-preset="today">Today</button>
                                    <button class="preset-btn" data-preset="yesterday">Yesterday</button>
                                    <button class="preset-btn" data-preset="thisWeek">This Week</button>
                                    <button class="preset-btn" data-preset="prevWeek">Previous Week</button>
                                    <button class="preset-btn" data-preset="thisMonth">This Month</button>
                                    <button class="preset-btn" data-preset="prevMonth">Previous Month</button>
                                    <button class="preset-btn" data-preset="thisYear">This Year</button>
                                    <button class="preset-btn" data-preset="prevYear">Previous Year</button>
                                    <button class="preset-btn" data-preset="sinceStart">Since Start</button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="date-range-footer">
                        <button class="btn btn-outline cancel-btn" data-action="cancel">Cancel</button>
                        <button class="btn btn-primary apply-btn" data-action="apply">Apply</button>
                    </div>
                </div>
            </div>
        `;
        
        this.renderCalendars();
    }
    
    renderCalendars() {
        const fromCalendar = this.container.querySelector('.from-calendar');
        const toCalendar = this.container.querySelector('.to-calendar');
        
        const fromDate = new Date(this.tempFromDate);
        const toDate = new Date(this.tempToDate);
        
        fromCalendar.innerHTML = this.generateCalendar(fromDate, 'from');
        toCalendar.innerHTML = this.generateCalendar(toDate, 'to');
    }
    
    generateCalendar(date, type) {
        const year = date.getFullYear();
        const month = date.getMonth();
        const today = new Date();
        
        const firstDay = new Date(year, month, 1);
        const lastDay = new Date(year, month + 1, 0);
        const startDate = new Date(firstDay);
        startDate.setDate(startDate.getDate() - firstDay.getDay());
        
        const monthNames = [
            'January', 'February', 'March', 'April', 'May', 'June',
            'July', 'August', 'September', 'October', 'November', 'December'
        ];
        
        let html = `
            <div class="calendar-header">
                <button class="nav-btn prev-month" data-action="prevMonth" data-type="${type}">
                    <i class="fas fa-chevron-left"></i>
                </button>
                <div class="month-year">
                    <select class="month-select" data-type="${type}">
                        ${monthNames.map((name, idx) => 
                            `<option value="${idx}" ${idx === month ? 'selected' : ''}>${name}</option>`
                        ).join('')}
                    </select>
                    <select class="year-select" data-type="${type}">
                        ${this.generateYearOptions(year)}
                    </select>
                </div>
                <button class="nav-btn next-month" data-action="nextMonth" data-type="${type}">
                    <i class="fas fa-chevron-right"></i>
                </button>
            </div>
            <div class="calendar-grid">
                <div class="weekdays">
                    <div class="weekday">Su</div>
                    <div class="weekday">Mo</div>
                    <div class="weekday">Tu</div>
                    <div class="weekday">We</div>
                    <div class="weekday">Th</div>
                    <div class="weekday">Fr</div>
                    <div class="weekday">Sa</div>
                </div>
                <div class="days">
        `;
        
        const currentDate = new Date(startDate);
        for (let i = 0; i < 42; i++) {
            const dayOfMonth = currentDate.getDate();
            const isCurrentMonth = currentDate.getMonth() === month;
            const isToday = currentDate.toDateString() === today.toDateString();
            const isSelected = this.isDateSelected(currentDate, type);
            
            const classes = [
                'day',
                isCurrentMonth ? 'current-month' : 'other-month',
                isToday ? 'today' : '',
                isSelected ? 'selected' : ''
            ].filter(Boolean).join(' ');
            
            html += `<div class="${classes}" data-date="${this.formatDate(currentDate)}" data-action="selectDate" data-type="${type}">${dayOfMonth}</div>`;
            
            currentDate.setDate(currentDate.getDate() + 1);
        }
        
        html += `
                </div>
            </div>
        `;
        
        return html;
    }
    
    generateYearOptions(currentYear) {
        const startYear = currentYear - 10;
        const endYear = currentYear + 10;
        let options = '';
        
        for (let year = startYear; year <= endYear; year++) {
            options += `<option value="${year}" ${year === currentYear ? 'selected' : ''}>${year}</option>`;
        }
        
        return options;
    }
    
    isDateSelected(date, type) {
        const dateStr = this.formatDate(date);
        if (type === 'from') {
            return dateStr === this.tempFromDate;
        } else {
            return dateStr === this.tempToDate;
        }
    }
    
    bindEvents() {
        // Toggle overlay
        this.container.addEventListener('click', (e) => {
            if (e.target.closest('[data-toggle="date-range"]')) {
                this.toggle();
            }
        });
        
        // Close overlay
        this.container.addEventListener('click', (e) => {
            if (e.target.closest('[data-action="close"]') || e.target.closest('[data-action="cancel"]')) {
                this.close();
            }
        });
        
        // Apply changes
        this.container.addEventListener('click', (e) => {
            if (e.target.closest('[data-action="apply"]')) {
                this.apply();
            }
        });
        
        // Preset buttons
        this.container.addEventListener('click', (e) => {
            const presetBtn = e.target.closest('.preset-btn');
            if (presetBtn) {
                const preset = presetBtn.dataset.preset;
                this.applyPreset(preset);
            }
        });
        
        // Calendar navigation
        this.container.addEventListener('click', (e) => {
            const action = e.target.closest('[data-action]')?.dataset.action;
            const type = e.target.closest('[data-type]')?.dataset.type;
            
            if (action === 'prevMonth') {
                this.navigateMonth(type, -1);
            } else if (action === 'nextMonth') {
                this.navigateMonth(type, 1);
            } else if (action === 'selectDate') {
                const date = e.target.dataset.date;
                this.selectDate(date, type);
            }
        });
        
        // Month/Year dropdowns
        this.container.addEventListener('change', (e) => {
            if (e.target.classList.contains('month-select') || e.target.classList.contains('year-select')) {
                const type = e.target.dataset.type;
                this.updateCalendar(type);
            }
        });
        
        // Manual date input
        this.container.addEventListener('change', (e) => {
            if (e.target.classList.contains('date-input')) {
                const type = e.target.classList.contains('from-input') ? 'from' : 'to';
                const date = e.target.value;
                if (this.isValidDate(date)) {
                    this.selectDate(date, type);
                }
            }
        });
        
        // Close on outside click
        document.addEventListener('click', (e) => {
            if (this.isOpen && !this.container.contains(e.target)) {
                this.close();
            }
        });
    }
    
    toggle() {
        if (this.isOpen) {
            this.close();
        } else {
            this.open();
        }
    }
    
    open() {
        this.isOpen = true;
        this.tempFromDate = this.currentFromDate;
        this.tempToDate = this.currentToDate;
        
        const overlay = this.container.querySelector('.date-range-overlay');
        overlay.style.display = 'block';
        
        this.updateInputs();
        this.renderCalendars();
    }
    
    close() {
        this.isOpen = false;
        const overlay = this.container.querySelector('.date-range-overlay');
        overlay.style.display = 'none';
    }
    
    apply() {
        if (this.isValidDateRange()) {
            this.currentFromDate = this.tempFromDate;
            this.currentToDate = this.tempToDate;
            
            this.updateDisplay();
            this.close();
            
            if (this.options.onApply) {
                this.options.onApply({
                    from: this.currentFromDate,
                    to: this.currentToDate
                });
            }
        }
    }
    
    applyPreset(preset) {
        const today = new Date();
        let fromDate, toDate;
        
        switch (preset) {
            case 'today':
                fromDate = toDate = new Date();
                break;
            case 'yesterday':
                fromDate = toDate = new Date(today.getTime() - 24 * 60 * 60 * 1000);
                break;
            case 'thisWeek':
                fromDate = new Date(today);
                fromDate.setDate(today.getDate() - today.getDay());
                toDate = new Date(fromDate);
                toDate.setDate(fromDate.getDate() + 6);
                break;
            case 'prevWeek':
                toDate = new Date(today);
                toDate.setDate(today.getDate() - today.getDay() - 1);
                fromDate = new Date(toDate);
                fromDate.setDate(toDate.getDate() - 6);
                break;
            case 'thisMonth':
                fromDate = new Date(today.getFullYear(), today.getMonth(), 1);
                toDate = new Date(today.getFullYear(), today.getMonth() + 1, 0);
                break;
            case 'prevMonth':
                fromDate = new Date(today.getFullYear(), today.getMonth() - 1, 1);
                toDate = new Date(today.getFullYear(), today.getMonth(), 0);
                break;
            case 'thisYear':
                fromDate = new Date(today.getFullYear(), 0, 1);
                toDate = new Date(today.getFullYear(), 11, 31);
                break;
            case 'prevYear':
                fromDate = new Date(today.getFullYear() - 1, 0, 1);
                toDate = new Date(today.getFullYear() - 1, 11, 31);
                break;
            case 'sinceStart':
                fromDate = new Date(2020, 0, 1); // Adjust based on your data start date
                toDate = new Date();
                break;
        }
        
        this.tempFromDate = this.formatDate(fromDate);
        this.tempToDate = this.formatDate(toDate);
        
        this.updateInputs();
        this.renderCalendars();
    }
    
    navigateMonth(type, direction) {
        const calendar = this.container.querySelector(`.${type}-calendar`);
        const currentDate = type === 'from' ? new Date(this.tempFromDate) : new Date(this.tempToDate);
        
        currentDate.setMonth(currentDate.getMonth() + direction);
        
        if (type === 'from') {
            calendar.innerHTML = this.generateCalendar(currentDate, 'from');
        } else {
            calendar.innerHTML = this.generateCalendar(currentDate, 'to');
        }
    }
    
    updateCalendar(type) {
        const monthSelect = this.container.querySelector(`.month-select[data-type="${type}"]`);
        const yearSelect = this.container.querySelector(`.year-select[data-type="${type}"]`);
        
        const month = parseInt(monthSelect.value);
        const year = parseInt(yearSelect.value);
        
        const newDate = new Date(year, month, 1);
        const calendar = this.container.querySelector(`.${type}-calendar`);
        
        calendar.innerHTML = this.generateCalendar(newDate, type);
    }
    
    selectDate(dateStr, type) {
        if (type === 'from') {
            this.tempFromDate = dateStr;
        } else {
            this.tempToDate = dateStr;
        }
        
        this.updateInputs();
        this.renderCalendars();
    }
    
    updateInputs() {
        const fromInput = this.container.querySelector('.from-input');
        const toInput = this.container.querySelector('.to-input');
        
        if (fromInput) fromInput.value = this.tempFromDate;
        if (toInput) toInput.value = this.tempToDate;
    }
    
    updateDisplay() {
        const displayText = this.container.querySelector('.date-range-text');
        displayText.textContent = `${this.currentFromDate} - ${this.currentToDate}`;
    }
    
    isValidDate(dateStr) {
        const date = new Date(dateStr);
        return date instanceof Date && !isNaN(date);
    }
    
    isValidDateRange() {
        const fromDate = new Date(this.tempFromDate);
        const toDate = new Date(this.tempToDate);
        
        return this.isValidDate(this.tempFromDate) && 
               this.isValidDate(this.tempToDate) && 
               fromDate <= toDate;
    }
    
    formatDate(date) {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    }
    
    // Public API methods
    getDateRange() {
        return {
            from: this.currentFromDate,
            to: this.currentToDate
        };
    }
    
    setDateRange(from, to) {
        if (this.isValidDate(from) && this.isValidDate(to)) {
            this.currentFromDate = from;
            this.currentToDate = to;
            this.tempFromDate = from;
            this.tempToDate = to;
            this.updateDisplay();
            this.updateInputs();
        }
    }
    
    reset() {
        this.setDefaultDates();
        this.updateDisplay();
    }
}

// Initialize date range pickers on page load
document.addEventListener('DOMContentLoaded', function() {
    // Auto-initialize any elements with data-date-range-picker attribute
    const pickers = document.querySelectorAll('[data-date-range-picker]');
    pickers.forEach(picker => {
        const options = picker.dataset.dateRangeOptions ? 
            JSON.parse(picker.dataset.dateRangeOptions) : {};
        new DateRangePicker(picker.id, options);
    });
});

// Export for module usage
if (typeof module !== 'undefined' && module.exports) {
    module.exports = DateRangePicker;
}