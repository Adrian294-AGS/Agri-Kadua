<?php

namespace App\Http\Controllers;

use App\Models\CropRate;
use Illuminate\View\View;

class RateController extends Controller
{
    public function index(): View
    {
        $rates = CropRate::query()
            ->with('variety.crop')
            ->where('effective_from', '<=', today())
            ->where(fn ($query) => $query->whereNull('effective_to')->orWhere('effective_to', '>=', today()))
            ->orderBy('variety_id')
            ->orderByDesc('effective_from')
            ->get();

        return view('rates.index', ['rates' => $rates]);
    }
}
