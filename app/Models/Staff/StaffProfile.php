<?php

namespace App\Models\Staff;

use App\Enums\Shared\Gender;
use App\Enums\Staff\StaffStatus;
use App\Models\User;
use App\Support\Tenancy\BelongsToSchool;
use Database\Factories\Staff\StaffProfileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['user_id', 'employee_id', 'first_name', 'last_name', 'gender', 'date_of_birth', 'designation', 'department', 'qualification', 'phone', 'email', 'address', 'joined_on', 'left_on', 'status', 'photo_file_id'])]
class StaffProfile extends Model
{
    /** @use HasFactory<StaffProfileFactory> */
    use BelongsToSchool, HasFactory;

    protected function casts(): array
    {
        return [
            'gender' => Gender::class,
            'status' => StaffStatus::class,
            'date_of_birth' => 'date',
            'joined_on' => 'date',
            'left_on' => 'date',
        ];
    }

    public function fullName(): string
    {
        return trim($this->first_name.' '.$this->last_name);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<TeacherAssignment, $this> */
    public function assignments(): HasMany
    {
        return $this->hasMany(TeacherAssignment::class);
    }
}
