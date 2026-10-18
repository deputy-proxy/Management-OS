<?php

namespace App\Http\Controllers;

class ContactController extends Controller
{
    public function index()
    {
        if (view()->exists('contact')) {
            return view('contact');
        }

        abort(404);
    }
}