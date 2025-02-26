<?php

namespace App\Controllers;

class Frontpage extends BaseController
{
    public function index(): string
    {
        return view('frontpage');
    }
}
