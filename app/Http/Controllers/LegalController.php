<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class LegalController extends Controller
{
    public function terms(): View
    {
        return view('legal.terms');
    }

    public function privacy(): View
    {
        return view('legal.privacy');
    }

    public function refund(): View
    {
        return view('legal.refund');
    }

    public function faq(): View
    {
        return view('support.faq');
    }

    public function contact(): View
    {
        return view('support.contact');
    }
}
