<?php

namespace App\Http\Controllers\School\Identity;

use App\Http\Controllers\Controller;
use App\Services\Audit\AuditQueryService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class AuditController extends Controller
{
    public function __invoke(Request $request, AuditQueryService $audit): View
    {
        $filters = $request->validate([
            'event' => ['nullable', 'string', 'max:100'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        return view('school.access.audit', [
            'events' => $audit->paginate($filters),
            'filters' => $filters,
        ]);
    }
}
