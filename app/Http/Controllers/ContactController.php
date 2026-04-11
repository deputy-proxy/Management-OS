<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ContactController extends Controller
{
    public function index()
    {
        if (view()->exists('contact')) {
            return view('contact');
        }

        return response('Contact page', 200)->header('Content-Type', 'text/plain');
    }
}
