<?php

namespace App\Http\Controllers;

class CourseController extends Controller
{
    public function index()
    {
        if (view()->exists('courses.index')) {
            return view('courses.index');
        }

        abort(404);
    }

    public function englishKids()
    {
        if (view()->exists('courses.english-kids')) {
            return view('courses.english-kids');
        }

        abort(404);
    }

    public function englishAdults()
    {
        if (view()->exists('courses.english-adults')) {
            return view('courses.english-adults');
        }

        abort(404);
    }

    public function english()
    {
        if (view()->exists('courses.english')) {
            return view('courses.english');
        }

        abort(404);
    }

    public function kids()
    {
        if (view()->exists('courses.kids')) {
            return view('courses.kids');
        }

        abort(404);
    }

    public function corporate()
    {
        if (view()->exists('courses.corporate')) {
            return view('courses.corporate');
        }

        abort(404);
    }
}