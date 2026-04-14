<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

/**
 * @property string $id
 * @property int|null $legacy_old_id
 * @property int $start_year
 * @property int $end_year
 * @property Carbon|null $start_date
 * @property Carbon|null $end_date
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read string $name
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SchoolYear active()
 * @method static \Database\Factories\SchoolYearFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SchoolYear inactive()
 * @method static Builder<static>|SchoolYear newModelQuery()
 * @method static Builder<static>|SchoolYear newQuery()
 * @method static Builder<static>|SchoolYear query()
 * @method static Builder<static>|SchoolYear whereCreatedAt($value)
 * @method static Builder<static>|SchoolYear whereEndDate($value)
 * @method static Builder<static>|SchoolYear whereEndYear($value)
 * @method static Builder<static>|SchoolYear whereId($value)
 * @method static Builder<static>|SchoolYear whereIsActive($value)
 * @method static Builder<static>|SchoolYear whereLegacyOldId($value)
 * @method static Builder<static>|SchoolYear whereStartDate($value)
 * @method static Builder<static>|SchoolYear whereStartYear($value)
 * @method static Builder<static>|SchoolYear whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class SchoolYear extends AcademicPeriod
{
    /** @use HasFactory<\Database\Factories\SchoolYearFactory> */
    use HasFactory;

    use HasUlids;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'start_year' => 'integer',
            'end_year' => 'integer',
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    protected static function booted(): void
    {
        parent::booted();

        static::creating(function (self $model): void {
            if (blank($model->start_year)) {
                throw new InvalidArgumentException('start_year is required');
            }

            $model->end_year = $model->start_year + 1;

            if ($model->start_date) {
                $model->start_date = Carbon::parse($model->start_date)->year($model->start_year);
            }

            if ($model->end_date) {
                $model->end_date = Carbon::parse($model->end_date)->year($model->end_year);
            }
        });

        static::updating(function (self $model): void {
            if ($model->isDirty('start_year')) {
                $model->end_year = $model->start_year + 1;
            }

            if ($model->isDirty(['start_year', 'start_date']) && $model->start_date) {
                $model->start_date = Carbon::parse($model->start_date)->year($model->start_year);
            }

            if ($model->isDirty(['start_year', 'end_year', 'end_date']) && $model->end_date) {
                $model->end_date = Carbon::parse($model->end_date)->year($model->end_year);
            }
        });
    }

    protected function name(): Attribute
    {
        return Attribute::make(
            get: fn (): string => "{$this->start_year}/{$this->end_year}",
        );
    }

    public static function getNextSchoolYear(): ?self
    {
        $activeSchoolYear = self::getActive();

        if (blank($activeSchoolYear)) {
            return null;
        }

        return self::where('start_year', $activeSchoolYear->end_year)->first();
    }

    /**
     * @throws ModelNotFoundException
     */
    public static function getNextSchoolYearOrFail(): self
    {
        $nextSchoolYear = self::getNextSchoolYear();

        if (blank($nextSchoolYear)) {
            throw new ModelNotFoundException('Tahun Ajaran Selanjutnya Tidak Ditemukan');
        }

        return $nextSchoolYear;
    }
}
