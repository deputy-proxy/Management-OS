<?php

namespace App\Http\Controllers;

class AboutController extends Controller
{
    public function index()
    {
        if (view()->exists('about')) {
            return view('about');
        }

        abort(404);
    }
}