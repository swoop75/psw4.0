/**
 * Admin Interface JavaScript
 */

// Select all checkboxes in a group
function selectAllCheckboxes(groupName) {
    const checkboxList = document.querySelector(`[data-group="${groupName}"]`);
    if (!checkboxList) return;
    
    const checkboxes = checkboxList.querySelectorAll('input[type="checkbox"]');
    checkboxes.forEach(checkbox => {
        checkbox.checked = true;
    });
}

// Deselect all checkboxes in a group
function deselectAllCheckboxes(groupName) {
    const checkboxList = document.querySelector(`[data-group="${groupName}"]`);
    if (!checkboxList) return;
    
    const checkboxes = checkboxList.querySelectorAll('input[type="checkbox"]');
    checkboxes.forEach(checkbox => {
        checkbox.checked = false;
    });
}

// Initialize admin functionality when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    console.log('Admin interface loaded');
    
    // Add confirmation for form submission
    const adminForm = document.querySelector('.admin-form');
    if (adminForm) {
        adminForm.addEventListener('submit', function(e) {
            // Count total selected items
            const allCheckboxes = adminForm.querySelectorAll('input[type="checkbox"]:checked');
            console.log(`Saving ${allCheckboxes.length} default filter selections`);
        });
    }
});