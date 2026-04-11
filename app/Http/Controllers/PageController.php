<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class PageController extends Controller
{
    public function privacy()
    {
        if (view()->exists('privacy')) {
            return view('privacy');
        }

        return response('Privacy policy', 200)->header('Content-Type', 'text/plain');
    }

    public function cookies()
    {
        if (view()->exists('cookies')) {
            return view('cookies');
        }

        return response('Cookies page', 200)->header('Content-Type', 'text/plain');
    }

    public function careers()
    {
        if (view()->exists('careers')) {
            return view('careers');
        }

        return response('Careers', 200)->header('Content-Type', 'text/plain');
    }
}
