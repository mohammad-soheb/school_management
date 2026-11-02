<?php

namespace App\Http\Controllers\School;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

/**
 * The school home page. Phase 13 (reports) replaces the placeholder numbers with real aggregates through
 * a DashboardService; until then it shows a welcome and shortcuts only.
 */
class DashboardController extends Controller
{
    public function __invoke(): View
    {
        return view('school.dashboard');
    }
}
