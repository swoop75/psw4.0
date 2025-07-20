<?php
// c:/Users/laoan/Documents/GitHub/psw/psw4.0/app/controllers/Pages.php

class Pages {
    public function __construct(){
        // This is where you would load a model if needed
    }

    public function index(){
        $data = [
            'title' => 'Welcome to PSW 4.0',
            'description' => 'A modern, informative, and business-like dashboard to track your dividend investing journey.'
        ];

        // This is a placeholder for a view loading function
        require_once APPROOT . '/templates/pages/index.php';
    }
}