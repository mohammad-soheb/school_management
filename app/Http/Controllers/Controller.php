<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

/**
 * Base controller. Controllers stay thin (plan.md §2.2): receive the validated request, call ONE service method,
 * return a view / redirect / download. Business rules, transactions and audit logging belong in app/Services.
 *
 * Flash message keys understood by the layouts: status (success), error, warning, info.
 */
abstract class Controller
{
    use AuthorizesRequests;
}
