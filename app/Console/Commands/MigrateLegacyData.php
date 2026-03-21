<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\GenderEnum;
use App\Enums\GradeEnum;
use App\Enums\InvoiceStatusEnum;
use App\Enums\InvoiceTypeEnum;
use App\Enums\LevelEnum;
use App\Enums\StatusInFamilyEnum;
use App\Enums\StudentEnrollmentStatusEnum;
use App\Enums\UserTypeEnum;
use App\Models\Branch;
use App\Models\Classroom;
use App\Models\Invoice;
use App\Models\School;
use App\Models\SchoolTerm;
use App\Models\SchoolYear;
use App\Models\Student;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class MigrateLegacyData extends Command
{
    protected $signature = 'app:migrate-legacy-data';

    protected $description = 'Migrate data from legacy database to new database';

    private const array SKIPPED_LEGACY_STUDENT_IDS = [233, 2007];

    private const array GRADUATING_GRADE_LEVELS = [6, 9, 12];

    private const int GRADUATION_SCHOOL_YEAR = 2024;

    private Collection $legacyClassrooms;

    private Collection $legacySchoolYears;

    private array $skippedStudents = [];

    private array $studentsWithoutClassroom = [];

    public function handle(): void
    {
        $this->info('Memulai migrasi...');

        $this->migrateMasterData();
        $this->cacheLegacyLookups();

        $legacyStudents = DB::connection('legacy')->table('students')->get();

        $this->info('Migrasi data siswa...');
        $this->withProgressBar($legacyStudents, function (object $oldStudent): void {
            if (in_array($oldStudent->id, self::SKIPPED_LEGACY_STUDENT_IDS)) {
                return;
            }

            DB::transaction(function () use ($oldStudent): void {
                $this->migrateStudent($oldStudent);
            });
        });
        $this->newLine(2);

        $this->info('Migrasi tagihan siswa...');
        $this->migrateStudentBills($legacyStudents);

        $this->outputSummary();
    }

    private function cacheLegacyLookups(): void
    {
        $this->legacyClassrooms = DB::connection('legacy')
            ->table('classrooms')
            ->get()
            ->keyBy('id');

        $this->legacySchoolYears = DB::connection('legacy')
            ->table('school_years')
            ->get()
            ->keyBy('id');
    }

    private function migrateStudent(object $oldStudent): void
    {
        $legacyStudentClassrooms = DB::connection('legacy')
            ->table('student_classrooms')
            ->where('student_id', $oldStudent->id)
            ->get();

        $legacyStudentSppBills = DB::connection('legacy')
            ->table('student_spp_bill')
            ->where('student_id', $oldStudent->id)
            ->orderBy('id')
            ->get();

        if ($legacyStudentClassrooms->isEmpty() && $legacyStudentSppBills->isEmpty()) {
            $this->skippedStudents[] = $oldStudent->name;

            return;
        }

        [$activeClassroomId, $activeSchoolId, $activeBranchId, $isActive] = $this->resolveActiveClassroom($legacyStudentClassrooms);

        $student = Student::firstOrCreate([
            'legacy_old_id' => $oldStudent->id,
            'name' => $oldStudent->name,
            'branch_id' => $activeBranchId,
            'school_id' => $activeSchoolId,
            'classroom_id' => $activeClassroomId,
            'nisn' => $oldStudent->nisn,
            'nis' => $oldStudent->nis,
            'gender' => $oldStudent->sex === 1 ? GenderEnum::MALE : GenderEnum::FEMALE,
            'birth_place' => $oldStudent->born_place,
            'birth_date' => $oldStudent->born_date,
            'previous_education' => $oldStudent->previous_education,
            'joined_at_class' => $oldStudent->joined_at_class,
            'sibling_order_in_family' => $oldStudent->sibling_order_in_family,
            'status_in_family' => $this->mapStatusInFamily($oldStudent->status_in_family),
            'religion' => $oldStudent->religion_id,
            'is_active' => $isActive,
            'father_name' => $oldStudent->father_name,
            'mother_name' => $oldStudent->mother_name,
            'parent_address' => $oldStudent->parent_address,
            'parent_phone' => $oldStudent->parent_phone,
            'father_job' => $oldStudent->father_job,
            'mother_job' => $oldStudent->mother_job,
            'guardian_name' => $oldStudent->guardian_name,
            'guardian_phone' => $oldStudent->guardian_phone,
            'guardian_address' => $oldStudent->guardian_address,
            'guardian_job' => $oldStudent->guardian_job,
        ]);

        $this->migrateEnrollments($student, $legacyStudentClassrooms);
        $this->migratePaymentAccounts($student, $oldStudent->id);
    }

    /**
     * @return array{string|null, string|null, string|null, bool}
     */
    private function resolveActiveClassroom(Collection $legacyStudentClassrooms): array
    {
        $activeClassroomId = null;
        $activeSchoolId = null;
        $activeBranchId = null;
        $isActive = false;

        foreach ($legacyStudentClassrooms as $legacyStudentClassroom) {
            $legacyClassroom = $this->legacyClassrooms->get($legacyStudentClassroom->classroom_id);
            $school = School::where('legacy_old_id', $legacyStudentClassroom->school_id)->first();

            $classroom = Classroom::firstOrCreate([
                'legacy_old_id' => $legacyClassroom->id,
                'school_id' => $school->getKey(),
                'name' => $legacyClassroom->name,
                'grade' => $this->mapGrade($legacyClassroom->level),
                'phase' => $legacyClassroom->fase,
                'is_moving_class' => $legacyClassroom->is_moving_class,
            ]);

            if ($legacyStudentClassroom->active) {
                $isActive = true;
            }

            $isLastClassroom = $legacyStudentClassrooms->last()->id === $legacyStudentClassroom->id;

            if ($legacyStudentClassroom->active || $isLastClassroom) {
                $activeClassroomId = $classroom->getKey();
                $activeSchoolId = $school->getKey();
                $activeBranchId = $school->branch_id;
            }
        }

        return [$activeClassroomId, $activeSchoolId, $activeBranchId, $isActive];
    }

    private function migrateEnrollments(Student $student, Collection $legacyStudentClassrooms): void
    {
        $graduationSchoolYear = SchoolYear::where('start_year', self::GRADUATION_SCHOOL_YEAR)->first();

        foreach ($legacyStudentClassrooms as $legacyStudentClassroom) {
            $legacyClassroom = $this->legacyClassrooms->get($legacyStudentClassroom->classroom_id);
            $legacySchoolYear = $this->legacySchoolYears->get($legacyStudentClassroom->school_year_id);
            $schoolYear = SchoolYear::where('legacy_old_id', $legacySchoolYear->id)->first();
            $classroom = Classroom::where('legacy_old_id', $legacyClassroom->id)->first();

            $status = $this->resolveEnrollmentStatus(
                $legacyStudentClassroom,
                $legacyClassroom,
                $schoolYear,
                $graduationSchoolYear,
            );

            $existingEnrollment = $student->enrollments()
                ->where('branch_id', $student->branch_id)
                ->where('school_id', $student->school_id)
                ->where('classroom_id', $classroom->getKey())
                ->where('school_year_id', $schoolYear->getKey())
                ->first();

            if ($existingEnrollment) {
                if ($status === StudentEnrollmentStatusEnum::ENROLLED) {
                    $existingEnrollment->delete();
                }

                continue;
            }

            $student->enrollments()->createQuietly([
                'legacy_old_id' => $legacyStudentClassroom->id,
                'branch_id' => $student->branch_id,
                'school_id' => $student->school_id,
                'classroom_id' => $classroom->getKey(),
                'school_year_id' => $schoolYear->getKey(),
                'status' => $status,
            ]);
        }
    }

    private function resolveEnrollmentStatus(
        object $legacyStudentClassroom,
        object $legacyClassroom,
        ?SchoolYear $schoolYear,
        ?SchoolYear $graduationSchoolYear,
    ): StudentEnrollmentStatusEnum {
        if ($legacyStudentClassroom->active) {
            return StudentEnrollmentStatusEnum::ENROLLED;
        }

        $isGraduatingGrade = in_array($legacyClassroom->level, self::GRADUATING_GRADE_LEVELS);
        $isGraduationYear = $graduationSchoolYear
            && $schoolYear
            && $schoolYear->getKey() === $graduationSchoolYear->getKey();

        if ($isGraduatingGrade && $isGraduationYear) {
            return StudentEnrollmentStatusEnum::GRADUATED;
        }

        return StudentEnrollmentStatusEnum::INACTIVE;
    }

    private function migratePaymentAccounts(Student $student, int $legacyStudentId): void
    {
        $legacyPaymentAccounts = DB::connection('legacy')
            ->table('student_payment_details')
            ->where('student_id', $legacyStudentId)
            ->orderByDesc('id')
            ->get()
            ->unique(fn (object $item): int => $item->school_id);

        foreach ($legacyPaymentAccounts as $legacyPaymentAccount) {
            $school = School::where('legacy_old_id', $legacyPaymentAccount->school_id)->first();

            $existingAccount = $student->paymentAccounts()
                ->where('school_id', $school->getKey())
                ->first();

            if ($existingAccount) {
                $existingAccount->delete();
            }

            $student->paymentAccounts()->createQuietly([
                'legacy_old_id' => $legacyPaymentAccount->id,
                'school_id' => $school->getKey(),
                'monthly_fee_virtual_account' => $legacyPaymentAccount->spp_va,
                'book_fee_virtual_account' => $legacyPaymentAccount->book_va,
                'monthly_fee_amount' => $legacyPaymentAccount->spp_cost ?? 0,
                'book_fee_amount' => $legacyPaymentAccount->book_cost ?? 0,
            ]);
        }
    }

    private function migrateStudentBills(Collection $legacyStudents): void
    {
        $this->withProgressBar($legacyStudents, function (object $oldStudent): void {
            $student = Student::where('legacy_old_id', $oldStudent->id)->first();

            if (! $student) {
                return;
            }

            $legacyInvoices = DB::connection('legacy')
                ->table('student_spp_bill')
                ->where('student_id', $oldStudent->id)
                ->get();

            $this->migrateStudentSppBills($legacyInvoices, $student);

            $legacyBookInvoices = DB::connection('legacy')
                ->table('student_book_bill')
                ->where('student_id', $oldStudent->id)
                ->get();

            $this->migrateStudentBookBills($legacyBookInvoices, $student);
        });
        $this->newLine(2);
    }

    private function migrateStudentSppBills(Collection $legacyInvoices, Student $student): void
    {
        foreach ($legacyInvoices as $oldInv) {
            if (Invoice::where('legacy_old_id', $oldInv->id)->exists()) {
                continue;
            }

            $schoolYear = SchoolYear::where('legacy_old_id', $oldInv->school_year_id)->first();
            $branch = Branch::where('legacy_old_id', $oldInv->team_id)->first();

            $classroom = Classroom::whereIn('school_id', $branch->schools()->pluck('id'))
                ->where('legacy_old_id', $oldInv->classroom_id)
                ->first();

            if (blank($classroom)) {
                $this->studentsWithoutClassroom[] = $student->name;

                continue;
            }

            Invoice::createQuietly([
                'id' => str()->ulid(),
                'legacy_old_id' => $oldInv->id,
                'fingerprint' => Invoice::generateFingerprint([
                    'type' => InvoiceTypeEnum::MONTHLY_FEE->value,
                    'student_id' => $student->getKey(),
                    'school_year_id' => $schoolYear->getKey(),
                    'month' => $oldInv->month_id,
                ]),
                'reference_number' => Invoice::generateReferenceNumber(),

                'branch_id' => $branch->getKey(),
                'school_id' => $classroom->school_id,
                'classroom_id' => $classroom->id,
                'school_year_id' => $schoolYear->id,
                'student_id' => $student->id,

                'branch_name' => $classroom->school->branch->name,
                'school_name' => $classroom->school->name,
                'classroom_name' => $classroom->name,
                'school_year_name' => $schoolYear->name,
                'student_name' => $student->name,

                'type' => InvoiceTypeEnum::MONTHLY_FEE,
                'month' => $oldInv->month_id,

                'amount' => $oldInv->cost,
                'fine' => $oldInv->fine,
                'discount' => $oldInv->discount,
                'total_amount' => $oldInv->cost,
                'status' => $this->mapInvoiceStatus($oldInv),
                'payment_method' => $oldInv->payment_method_id,

                'due_date' => $oldInv->end_date,
                'issued_at' => $oldInv->start_date,
                'paid_at' => $oldInv->paid_date,
                'description' => $oldInv->description,
                'created_at' => $oldInv->created_at,
                'updated_at' => $oldInv->updated_at,
            ]);
        }
    }

    private function migrateStudentBookBills(Collection $legacyBookInvoices, Student $student): void
    {
        foreach ($legacyBookInvoices as $oldInv) {
            if (Invoice::where('legacy_old_id', $oldInv->id)->exists()) {
                continue;
            }

            $schoolYear = SchoolYear::where('legacy_old_id', $oldInv->school_year_id)->first();
            $branch = Branch::where('legacy_old_id', $oldInv->team_id)->first();

            $classroom = Classroom::whereIn('school_id', $branch->schools()->pluck('id'))
                ->where('legacy_old_id', $oldInv->classroom_id)
                ->first();

            if (blank($classroom)) {
                $this->studentsWithoutClassroom[] = $student->name;

                continue;
            }

            Invoice::createQuietly([
                'id' => str()->ulid(),
                'legacy_old_id' => $oldInv->id,
                'fingerprint' => Invoice::generateFingerprint([
                    'type' => InvoiceTypeEnum::BOOK_FEE->value,
                    'student_id' => $student->getKey(),
                    'school_year_id' => $schoolYear->getKey(),
                ]),
                'reference_number' => Invoice::generateReferenceNumber(),

                'branch_id' => $branch->getKey(),
                'school_id' => $classroom->school_id,
                'classroom_id' => $classroom->id,
                'school_year_id' => $schoolYear->id,
                'student_id' => $student->id,

                'branch_name' => $classroom->school->branch->name,
                'school_name' => $classroom->school->name,
                'classroom_name' => $classroom->name,
                'school_year_name' => $schoolYear->name,
                'student_name' => $student->name,

                'type' => InvoiceTypeEnum::BOOK_FEE,

                'amount' => $oldInv->cost,
                'discount' => $oldInv->discount,
                'total_amount' => $oldInv->cost,
                'status' => $this->mapBookInvoiceStatus($oldInv),
                'payment_method' => $oldInv->payment_method_id,

                'due_date' => $oldInv->end_date,
                'issued_at' => $oldInv->start_date,
                'paid_at' => $oldInv->paid_date,
                'description' => $oldInv->description,
                'created_at' => $oldInv->created_at,
                'updated_at' => $oldInv->updated_at,
            ]);
        }
    }

    private function migrateMasterData(): void
    {
        $legacySchoolYears = DB::connection('legacy')->table('school_years')->get();

        foreach ($legacySchoolYears as $legacySchoolYear) {
            $year = explode('/', $legacySchoolYear->name);
            SchoolYear::firstOrCreate([
                'legacy_old_id' => $legacySchoolYear->id,
                'start_year' => $year[0],
                'end_year' => $year[1],
                'is_active' => $legacySchoolYear->status,
            ]);
        }

        SchoolTerm::firstOrCreate([
            'legacy_old_id' => 1,
            'name' => 1,
            'is_active' => false,
        ]);
        SchoolTerm::firstOrCreate([
            'legacy_old_id' => 2,
            'name' => 2,
            'is_active' => true,
        ]);

        Branch::firstOrCreate(['legacy_old_id' => 1, 'name' => 'Batam Center']);
        Branch::firstOrCreate(['legacy_old_id' => 2, 'name' => 'Batu Aji']);

        $user = User::factory()->create([
            'name' => 'Super Admin',
            'email' => 'super@admin.com',
            'user_type' => UserTypeEnum::EMPLOYEE,
            'password' => bcrypt(config('setting.default_password')),
        ]);

        $user->branches()->sync(Branch::all());

        $legacySchools = DB::connection('legacy')->table('schools')->get();

        foreach ($legacySchools as $legacySchool) {
            School::firstOrCreate([
                'legacy_old_id' => $legacySchool->id,
                'branch_id' => Branch::where('legacy_old_id', $legacySchool->team_id)->first()->getKey(),
                'name' => $legacySchool->name,
                'level' => $this->mapLevel($legacySchool->name),
                'address' => $legacySchool->address,
                'npsn' => $legacySchool->npsn,
                'nis_nss_nds' => $legacySchool->nis_nss_nds,
                'telp' => $legacySchool->telp,
                'postal_code' => $legacySchool->postal_code,
                'village' => $legacySchool->village,
                'subdistrict' => $legacySchool->subdistrict,
                'city' => $legacySchool->city,
                'province' => $legacySchool->province,
                'website' => $legacySchool->website,
                'email' => $legacySchool->email,
            ]);
        }
    }

    private function outputSummary(): void
    {
        if (blank($this->studentsWithoutClassroom)) {
            $this->info('Tidak ada data siswa yang tidak memiliki kelas');
        } else {
            $this->warn('Data siswa yang tidak memiliki kelas: ' . implode(', ', array_unique($this->studentsWithoutClassroom)));
        }

        if (blank($this->skippedStudents)) {
            $this->info('Tidak ada data siswa yang dibatalkan');
        } else {
            $this->warn('Data siswa yang dibatalkan: ' . implode(', ', $this->skippedStudents));
        }

        $this->info('Migrasi selesai!');
    }

    private function mapGrade(int $oldGrade): ?int
    {
        return match ($oldGrade) {
            0 => GradeEnum::KINDERGARTEN_B->value,
            1 => GradeEnum::GRADE_1->value,
            2 => GradeEnum::GRADE_2->value,
            3 => GradeEnum::GRADE_3->value,
            4 => GradeEnum::GRADE_4->value,
            5 => GradeEnum::GRADE_5->value,
            6 => GradeEnum::GRADE_6->value,
            7 => GradeEnum::GRADE_7->value,
            8 => GradeEnum::GRADE_8->value,
            9 => GradeEnum::GRADE_9->value,
            10 => GradeEnum::GRADE_10->value,
            11 => GradeEnum::GRADE_11->value,
            12 => GradeEnum::GRADE_12->value,
            default => null,
        };
    }

    private function mapLevel(string $schoolName): ?int
    {
        return match (true) {
            str_contains($schoolName, 'TK') => LevelEnum::KINDERGARTEN->value,
            str_contains($schoolName, 'SD') => LevelEnum::ELEMENTARY->value,
            str_contains($schoolName, 'SMP') => LevelEnum::JUNIOR_HIGH->value,
            str_contains($schoolName, 'SMA') => LevelEnum::SENIOR_HIGH->value,
            default => null,
        };
    }

    private function mapStatusInFamily(?string $status): ?StatusInFamilyEnum
    {
        return match ($status) {
            'Kandung', 'Anak Kandung', 'Anak' => StatusInFamilyEnum::BIOLOGICAL_CHILD,
            'Tiri' => StatusInFamilyEnum::STEP_CHILD,
            'Angkat' => StatusInFamilyEnum::ADOPTED_CHILD,
            'Asuh' => StatusInFamilyEnum::FOSTER_CHILD,
            default => null,
        };
    }

    private function mapInvoiceStatus(object $oldInv): InvoiceStatusEnum
    {
        if (blank($oldInv->is_active)) {
            return InvoiceStatusEnum::VOID;
        }

        $isPaid = $oldInv->paid_date !== null && $oldInv->payment_method_id !== null;

        if ($isPaid) {
            return InvoiceStatusEnum::PAID;
        }

        if ($oldInv->is_active === 1 && $oldInv->paid_date === null && $oldInv->payment_method_id === null) {
            return InvoiceStatusEnum::UNPAID;
        }

        return InvoiceStatusEnum::VOID;
    }

    private function mapBookInvoiceStatus(object $oldInv): InvoiceStatusEnum
    {
        $isPaid = $oldInv->paid_date !== null && $oldInv->payment_method_id !== null;

        if ($isPaid) {
            return InvoiceStatusEnum::PAID;
        }

        if ($oldInv->paid_date === null) {
            return InvoiceStatusEnum::UNPAID;
        }

        return InvoiceStatusEnum::VOID;
    }
}
