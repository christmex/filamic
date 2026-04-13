<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\GradeEnum;
use App\Models\Traits\BelongsToSchool;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property int|null $legacy_old_id
 * @property string $school_id
 * @property string $name
 * @property GradeEnum $grade
 * @property int|null $identifier
 * @property string|null $phase
 * @property bool $is_moving_class
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read School $school
 *
 * @method static Builder<static>|Classroom excludeFinalYears()
 * @method static \Database\Factories\ClassroomFactory factory($count = null, $state = [])
 * @method static Builder<static>|Classroom newModelQuery()
 * @method static Builder<static>|Classroom newQuery()
 * @method static Builder<static>|Classroom onlyInFinalYears(array $exclude = [])
 * @method static Builder<static>|Classroom onlyInFirstYears()
 * @method static Builder<static>|Classroom query()
 * @method static Builder<static>|Classroom whereCreatedAt($value)
 * @method static Builder<static>|Classroom whereGrade($value)
 * @method static Builder<static>|Classroom whereId($value)
 * @method static Builder<static>|Classroom whereIdentifier($value)
 * @method static Builder<static>|Classroom whereIsMovingClass($value)
 * @method static Builder<static>|Classroom whereLegacyOldId($value)
 * @method static Builder<static>|Classroom whereName($value)
 * @method static Builder<static>|Classroom wherePhase($value)
 * @method static Builder<static>|Classroom whereSchoolId($value)
 * @method static Builder<static>|Classroom whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class Classroom extends Model
{
    use BelongsToSchool;

    /** @use HasFactory<\Database\Factories\ClassroomFactory> */
    use HasFactory;

    use HasUlids;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'grade' => GradeEnum::class,
            'is_moving_class' => 'boolean',
        ];
    }

    #[Scope]
    protected function excludeFinalYears(Builder $query): Builder
    {
        return $query->whereNotIn('grade', GradeEnum::finalYears());
    }

    #[Scope]
    protected function onlyInFinalYears(Builder $query, array $exclude = []): Builder
    {
        return $query->whereIn('grade', GradeEnum::finalYears())
            ->when($exclude, fn (Builder $q) => $q->whereNotIn('grade', $exclude));
    }

    #[Scope]
    protected function onlyInFirstYears(Builder $query): Builder
    {
        return $query->whereIn('grade', GradeEnum::firstYears());
    }
}
