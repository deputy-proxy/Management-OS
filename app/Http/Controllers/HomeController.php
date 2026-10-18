<?php

namespace App\Http\Controllers;

class HomeController extends Controller
{
    public function index()
    {
        if (view()->exists('welcome')) {
            return view('welcome');
        }

        abort(404);
    }
}