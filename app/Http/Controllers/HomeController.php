<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class HomeController extends Controller
{
    public function index()
    {
        if (view()->exists('welcome')) {
            return view('welcome');
        }

        return response('Home', 200)->header('Content-Type', 'text/plain');
    }
}
