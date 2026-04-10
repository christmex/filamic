<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\GenderEnum;
use App\Enums\GradeEnum;
use App\Enums\ReligionEnum;
use App\Enums\StatusInFamilyEnum;
use App\Enums\StudentEnrollmentStatusEnum;
use App\Models\Traits\BelongsToBranch;
use App\Models\Traits\BelongsToClassroom;
use App\Models\Traits\BelongsToSchool;
use App\Models\Traits\BelongsToUser;
use App\Models\Traits\HasActiveState;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Support\Collection;
use Illuminate\Support\Number;

/**
 * @property string $id
 * @property int|null $legacy_old_id
 * @property string $name
 * @property string|null $branch_id
 * @property string|null $school_id
 * @property string|null $classroom_id
 * @property string|null $user_id
 * @property string|null $nisn
 * @property string|null $nis
 * @property GenderEnum $gender
 * @property string|null $birth_place
 * @property string|null $birth_date
 * @property string|null $previous_education
 * @property string|null $joined_at_class
 * @property int|null $sibling_order_in_family
 * @property StatusInFamilyEnum|null $status_in_family
 * @property ReligionEnum|null $religion
 * @property bool $is_active
 * @property string|null $notes
 * @property array<array-key, mixed>|null $metadata
 * @property string|null $father_name
 * @property string|null $mother_name
 * @property string|null $parent_address
 * @property string|null $parent_phone
 * @property string|null $father_job
 * @property string|null $mother_job
 * @property string|null $guardian_name
 * @property string|null $guardian_phone
 * @property string|null $guardian_address
 * @property string|null $guardian_job
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Branch|null $branch
 * @property-read Classroom|null $classroom
 * @property-read Classroom|null $currentClassroom
 * @property-read StudentEnrollment|null $currentEnrollment
 * @property-read mixed $display_name
 * @property-read \Illuminate\Database\Eloquent\Collection<int, StudentEnrollment> $enrollments
 * @property-read int|null $enrollments_count
 * @property-read mixed $formatted_nisn
 * @property-read mixed $initials
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Invoice> $invoices
 * @property-read int|null $invoices_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Invoice> $paidMonthlyFee
 * @property-read int|null $paid_monthly_fee_count
 * @property-read School|null $school
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Invoice> $unpaidBookFee
 * @property-read int|null $unpaid_book_fee_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Invoice> $unpaidInvoices
 * @property-read int|null $unpaid_invoices_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Invoice> $unpaidMonthlyFee
 * @property-read int|null $unpaid_monthly_fee_count
 * @property-read User|null $user
 *
 * @method static Builder<static>|Student active()
 * @method static Builder<static>|Student doesntHaveDraftEnrollmentForNextSchoolYear()
 * @method static \Database\Factories\StudentFactory factory($count = null, $state = [])
 * @method static Builder<static>|Student inactive()
 * @method static Builder<static>|Student newModelQuery()
 * @method static Builder<static>|Student newQuery()
 * @method static Builder<static>|Student notInFinalYears()
 * @method static Builder<static>|Student query()
 * @method static Builder<static>|Student whereBirthDate($value)
 * @method static Builder<static>|Student whereBirthPlace($value)
 * @method static Builder<static>|Student whereBranchId($value)
 * @method static Builder<static>|Student whereClassroomId($value)
 * @method static Builder<static>|Student whereCreatedAt($value)
 * @method static Builder<static>|Student whereFatherJob($value)
 * @method static Builder<static>|Student whereFatherName($value)
 * @method static Builder<static>|Student whereGender($value)
 * @method static Builder<static>|Student whereGuardianAddress($value)
 * @method static Builder<static>|Student whereGuardianJob($value)
 * @method static Builder<static>|Student whereGuardianName($value)
 * @method static Builder<static>|Student whereGuardianPhone($value)
 * @method static Builder<static>|Student whereId($value)
 * @method static Builder<static>|Student whereIsActive($value)
 * @method static Builder<static>|Student whereJoinedAtClass($value)
 * @method static Builder<static>|Student whereLegacyOldId($value)
 * @method static Builder<static>|Student whereMetadata($value)
 * @method static Builder<static>|Student whereMotherJob($value)
 * @method static Builder<static>|Student whereMotherName($value)
 * @method static Builder<static>|Student whereName($value)
 * @method static Builder<static>|Student whereNis($value)
 * @method static Builder<static>|Student whereNisn($value)
 * @method static Builder<static>|Student whereNotes($value)
 * @method static Builder<static>|Student whereParentAddress($value)
 * @method static Builder<static>|Student whereParentPhone($value)
 * @method static Builder<static>|Student wherePreviousEducation($value)
 * @method static Builder<static>|Student whereReligion($value)
 * @method static Builder<static>|Student whereSchoolId($value)
 * @method static Builder<static>|Student whereSiblingOrderInFamily($value)
 * @method static Builder<static>|Student whereStatusInFamily($value)
 * @method static Builder<static>|Student whereUpdatedAt($value)
 * @method static Builder<static>|Student whereUserId($value)
 *
 * @mixin \Eloquent
 */
class Student extends Model
{
    use BelongsToBranch;
    use BelongsToClassroom;
    use BelongsToSchool;
    use BelongsToUser;
    use HasActiveState;

    /** @use HasFactory<\Database\Factories\StudentFactory> */
    use HasFactory;

    use HasUlids;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'gender' => GenderEnum::class,
            'status_in_family' => StatusInFamilyEnum::class,
            'religion' => ReligionEnum::class,
            'metadata' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $student): void {
            // TODO: validate if the classroom_id belongsto school_id and the school_id belongsto branch_id
        });

        static::saved(function (self $student): void {
            $student->syncActiveStatus();
        });
    }

    /**
     * @return HasMany<Invoice, $this>
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class)->orderBy('month');
    }

    /**
     * @return HasMany<Invoice, $this>
     */
    public function unpaidMonthlyFee(): HasMany
    {
        return $this->hasMany(Invoice::class)->unpaidMonthlyFee();
    }

    /**
     * @return HasMany<Invoice, $this>
     */
    public function unpaidBookFee(): HasMany
    {
        return $this->hasMany(Invoice::class)->unpaidBookFee();
    }

    /**
     * @return HasMany<Invoice, $this>
     */
    public function unpaidInvoices(): HasMany
    {
        return $this->hasMany(Invoice::class)->unpaid()->orderBy('school_year_id')->orderBy('type');
    }

    public function hasUnpaidInvoice(): bool
    {
        return $this->unpaidInvoices()->exists();
    }

    /**
     * @return HasMany<Invoice, $this>
     */
    public function paidMonthlyFee(): HasMany
    {
        return $this->hasMany(Invoice::class)->paidMonthlyFee();
    }

    /**
     * @return HasMany<StudentEnrollment, $this>
     */
    public function enrollments(): HasMany
    {
        return $this->hasMany(StudentEnrollment::class);
    }

    /**
     * @return HasOne<StudentEnrollment, $this>
     */
    public function currentEnrollment(): HasOne
    {
        return $this->hasOne(StudentEnrollment::class)->active();
    }

    /**
     * @return HasOneThrough<Classroom, StudentEnrollment, $this>
     */
    public function currentClassroom(): HasOneThrough
    {
        return $this->hasOneThrough(Classroom::class, StudentEnrollment::class, 'student_id', 'id', 'id', 'classroom_id');
    }

    #[Scope]
    protected function notInFinalYears(Builder $query): Builder
    {
        return $query->whereDoesntHave('classroom', function (Builder $query) {
            $query->whereIn('grade', GradeEnum::finalYears());
        });
    }

    #[Scope]
    protected function doesntHaveDraftEnrollmentForNextSchoolYear(Builder $query): Builder
    {
        return $query->whereDoesntHave('enrollments', function (Builder $query) {
            $query->where('status', StudentEnrollmentStatusEnum::DRAFT)
                ->where('school_year_id', SchoolYear::getNextSchoolYear()?->getKey());
        });
    }

    // TODO: Restore when student_payment_accounts table is implemented.
    // #[Scope]
    // protected function eligibleForMonthlyFee(Builder $query): Builder
    // {
    //     return $query
    //         ->whereNotNull('monthly_fee_virtual_account')
    //         ->where('monthly_fee_amount', '>', 0);
    // }

    // TODO: Restore when student_payment_accounts table is implemented.
    // #[Scope]
    // protected function notEligibleForMonthlyFee(Builder $query): Builder
    // {
    //     return $query->where(function (Builder $q): void {
    //         $q->whereNull($q->qualifyColumn('monthly_fee_virtual_account'))
    //             ->orWhere($q->qualifyColumn('monthly_fee_amount'), '=', 0);
    //     });
    // }

    // TODO: Restore when student_payment_accounts table is implemented.
    // #[Scope]
    // protected function eligibleForBookFee(Builder $query): Builder
    // {
    //     return $query
    //         ->whereNotNull('book_fee_virtual_account')
    //         ->where('book_fee_amount', '>', 0);
    // }

    // TODO: Restore when student_payment_accounts table is implemented.
    // #[Scope]
    // protected function hasProblems(Builder $query): Builder
    // {
    //     return $query->whereDoesntHave('currentEnrollment')
    //         ->orWhere(function (Builder $q): void {
    //             $q->whereNull('monthly_fee_virtual_account')
    //                 ->orWhere('monthly_fee_amount', '=', 0);
    //         });
    // }

    // TODO: Restore when student_payment_accounts table is implemented.
    // #[Scope]
    // protected function hasNoProblems(Builder $query): Builder
    // {
    //     return $query
    //         ->whereHas('currentEnrollment')
    //         ->whereNotNull('monthly_fee_virtual_account')
    //         ->where('monthly_fee_amount', '>', 0);
    // }

    protected function initials(): Attribute
    {
        return Attribute::get(function () {
            $words = collect(explode(' ', $this->name))->filter();

            if ($words->isEmpty()) {
                return '??';
            }

            $firstInitial = str()->substr($words->first(), 0, 1);
            $lastInitial = $words->count() > 1
                ? str()->substr($words->last(), 0, 1)
                : '';

            return str($firstInitial . $lastInitial)->upper();
        });
    }

    protected function formattedNisn(): Attribute
    {
        return Attribute::get(fn () => 'NISN: ' . (blank($this->nisn) ? '-' : $this->nisn));
    }

    protected function displayName(): Attribute
    {
        return Attribute::get(function () {
            $words = str($this->name)->explode(' ')->filter();
            $count = $words->count();

            if ($count <= 2) {
                return $this->name;
            }

            $middle = $words->slice(1, $count - 2)
                ->map(fn ($word) => str($word)->substr(0, 1)->upper()->append('.'))
                ->implode(' ');

            return "{$words->first()} {$middle} {$words->last()}";
        });
    }

    public function isInFinalYears(): bool
    {
        return $this->classroom?->grade?->isFinalYear() ?? false;
    }

    public function isNotInFinalYears(): bool
    {
        return ! ($this->classroom?->grade?->isFinalYear() ?? false);
    }

    public function canBeDelete(): bool
    {
        return $this->enrollments()->doesntExist();
    }

    public function getMissingData(): Collection
    {
        $missing = collect();

        if ($this->currentEnrollment()->doesntExist()) {
            $missing->push('Peserta Didik Belum Memiliki Data Di Tahun Ajaran Aktif');
        }

        // TODO: Re-add payment account warnings when student_payment_accounts is implemented.

        return $missing;
    }

    public function getTotalUnpaidMonthlyFee(bool $formatted = false): int | string
    {
        $total = $this->unpaidMonthlyFee()->sum('total_amount');

        if ($formatted) {
            return Number::currency((int) $total, 'IDR', 'id', 0);
        }

        return (int) $total;
    }

    public function getTotalUnpaidBookFee(bool $formatted = false): int | string
    {
        $total = $this->unpaidBookFee()->sum('total_amount');

        if ($formatted) {
            return Number::currency((int) $total, 'IDR', 'id', 0);
        }

        return (int) $total;
    }

    public function syncActiveStatus(): void
    {
        // Use the query builder directly to avoid returning a stale cached
        // relationship value — critical when called from a StudentEnrollment
        // saved hook where the same Student instance may already have
        // currentEnrollment loaded with its previous state.
        $enrollment = $this->currentEnrollment()->first();

        $this->updateQuietly([
            'is_active' => filled($enrollment),
            // @phpstan-ignore nullsafe.neverNull (Larastan infers HasOne result as non-nullable; $enrollment is StudentEnrollment|null at runtime)
            'branch_id' => $enrollment?->branch_id ?? $this->branch_id,
            // @phpstan-ignore nullsafe.neverNull
            'school_id' => $enrollment?->school_id ?? $this->school_id,
            // @phpstan-ignore nullsafe.neverNull
            'classroom_id' => $enrollment?->classroom_id ?? $this->classroom_id,
        ]);
    }
}
