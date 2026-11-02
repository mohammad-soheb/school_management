<?php

namespace App\Models\Communication;

use App\Enums\Communication\NoticeAudienceType;
use App\Models\Identity\Role;
use App\Models\SchoolSetup\SchoolClass;
use App\Support\Tenancy\BelongsToSchool;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One audience target of a notice: the whole school, one class, or one role. */
#[Fillable(['notice_id', 'type', 'school_class_id', 'role_id'])]
class NoticeAudience extends Model
{
    use BelongsToSchool;

    protected function casts(): array
    {
        return ['type' => NoticeAudienceType::class];
    }

    /** @return BelongsTo<Notice, $this> */
    public function notice(): BelongsTo
    {
        return $this->belongsTo(Notice::class);
    }

    /** @return BelongsTo<SchoolClass, $this> */
    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'school_class_id');
    }

    /** @return BelongsTo<Role, $this> */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /** Eager load schoolClass and role first. */
    public function label(): string
    {
        return match ($this->type) {
            NoticeAudienceType::School, NoticeAudienceType::Staff, NoticeAudienceType::Parents => $this->type->label(),
            NoticeAudienceType::SchoolClass => $this->schoolClass?->name ?? 'Class',
            NoticeAudienceType::Role => $this->role?->name ?? 'Role',
        };
    }
}
