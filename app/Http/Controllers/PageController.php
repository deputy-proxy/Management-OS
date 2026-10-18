<?php

namespace App\Http\Controllers;

class PageController extends Controller
{
    public function privacy()
    {
        if (view()->exists('privacy')) {
            return view('privacy');
        }

        abort(404);
    }

    public function cookies()
    {
        if (view()->exists('cookies')) {
            return view('cookies');
        }

        abort(404);
    }

    public function careers()
    {
        if (view()->exists('careers')) {
            return view('careers');
        }

        abort(404);
    }
}