<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EventCategory;

class EventCategoryController extends Controller
{
    public function index()
    {
        return response()->json(
            EventCategory::with('translations')->orderBy('id')->get()
        );
    }
}