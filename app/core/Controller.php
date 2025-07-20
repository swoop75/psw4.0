<?php
// c:/Users/laoan/Documents/GitHub/psw/psw4.0/app/core/Controller.php

/**
 * Base Controller
 * Loads the models and views
 */
class Controller {
    // Load view and pass data
    public function view($view, $data = []) {
        // Check for the view file, noting that templates are inside the public folder
        $viewPath = APPROOT . '/public/templates/' . $view . '.php';
        if (file_exists($viewPath)) {
            // The 'extract' function turns array keys into variables (e.g., $data['title'] becomes $title)
            extract($data);
            require_once $viewPath;
        } else {
            // View does not exist - die with a helpful error message
            die('Error: View does not exist at path: ' . $viewPath);
        }
    }
}