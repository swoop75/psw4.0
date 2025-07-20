// c:/Users/laoan/Documents/GitHub/psw/psw4.0/public/js/main.js

document.addEventListener('DOMContentLoaded', function() {

    // --- Login Dropdown Toggle ---
    const loginToggle = document.getElementById('login-toggle');
    const loginDropdown = document.getElementById('login-dropdown');

    if (loginToggle && loginDropdown) {
        loginToggle.addEventListener('click', function(e) {
            e.preventDefault();
            loginDropdown.classList.toggle('show');
        });

        // Close dropdown if clicking outside of it
        document.addEventListener('click', function(e) {
            if (!loginToggle.contains(e.target) && !loginDropdown.contains(e.target)) {
                loginDropdown.classList.remove('show');
            }
        });
    }

    // --- Keyboard Shortcuts (Placeholder) ---
    // TODO: Implement spacebar to open search field
});