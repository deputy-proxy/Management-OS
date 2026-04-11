<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class AboutController extends Controller
{
    public function index()
    {
        if (view()->exists('about')) {
            return view('about');
        }

        return response('About page', 200)->header('Content-Type', 'text/plain');
    }
}
