<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Services\Platform\SchoolRegistryService;
use Illuminate\Contracts\View\View;

class DashboardController extends Controller
{
    public function __invoke(SchoolRegistryService $registry): View
    {
        return view('platform.dashboard', [
            'stats' => $registry->stats(),
            'recentSchools' => $registry->paginate(perPage: 5),
        ]);
    }
}
