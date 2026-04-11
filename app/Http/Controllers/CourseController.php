<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class CourseController extends Controller
{
    public function index()
    {
        if (view()->exists('courses.index')) {
            return view('courses.index');
        }

        return response('Courses index', 200)->header('Content-Type', 'text/plain');
    }

    public function englishKids()
    {
        if (view()->exists('courses.english-kids')) {
            return view('courses.english-kids');
        }

        return response('English (Kids) course', 200)->header('Content-Type', 'text/plain');
    }

    public function englishAdults()
    {
        if (view()->exists('courses.english-adults')) {
            return view('courses.english-adults');
        }

        return response('English (Adults) course', 200)->header('Content-Type', 'text/plain');
    }

    public function english()
    {
        if (view()->exists('courses.english')) {
            return view('courses.english');
        }

        return response('English course', 200)->header('Content-Type', 'text/plain');
    }

    public function kids()
    {
        if (view()->exists('courses.kids')) {
            return view('courses.kids');
        }

        return response('Kids course', 200)->header('Content-Type', 'text/plain');
    }

    public function corporate()
    {
        if (view()->exists('courses.corporate')) {
            return view('courses.corporate');
        }

        return response('Corporate course', 200)->header('Content-Type', 'text/plain');
    }
}
