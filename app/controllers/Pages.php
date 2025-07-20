<?php
// c:/Users/laoan/Documents/GitHub/psw/psw4.0/app/controllers/Pages.php

class Pages extends Controller {
    public function __construct(){
        // This is where you would load a model if needed
    }

    public function index(){
        $data = [
            'title' => 'Welcome to Pengamaskinen Sverige + Worldwide',
            'description' => 'A modern, informative, and business-like dashboard to track your dividend investing journey.'
        ];

        // Load the view using the new method from the base Controller
        // This is more robust and correctly handles paths and data.
        $this->view('pages/index', $data);
    }
}