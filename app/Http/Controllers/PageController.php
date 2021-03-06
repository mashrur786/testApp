<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class PageController extends Controller
{
    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Http\Response
     */
    public function about(){
        return view('about');
    }

    public function blog(){
        return view('blog');
    }

    public function partner_programme(){
        return view('partner-programme');
    }

}