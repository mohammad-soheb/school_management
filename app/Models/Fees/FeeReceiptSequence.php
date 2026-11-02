<?php

namespace App\Models\Fees;

use App\Support\Tenancy\BelongsToSchool;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/** Receipt counter per school and scope ('2026-27' or 'all'); read FOR UPDATE inside the payment transaction. */
#[Fillable(['scope_key'])]
class FeeReceiptSequence extends Model
{
    use BelongsToSchool;

    protected function casts(): array
    {
        return ['last_number' => 'integer'];
    }
}
