<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Language;

class LanguageController extends Controller
{
    public function index()
    {
        return Language::select('id', 'code', 'name')->orderBy('id')->get();
    }
}