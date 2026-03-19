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
use Illuminate\Support\Facades\DB;

class MigrateLegacyData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:migrate-legacy-data';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migrate data from legacy database to new database';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Memulai migrasi...');

        $studentCancel = [];
        $studentNoClassroom = [];

        $legacySchoolYears = $this->migrateMasterData();

        // 4. data siswa
        $legacyStudents = DB::connection('legacy')->table('students')->get();

        foreach ($legacyStudents as $oldStudent) {
            DB::transaction(function () use ($oldStudent, $legacySchoolYears, $studentCancel) {

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
                    $studentCancel[] = $oldStudent->name;

                    return;
                }

                $activeClassroomId = null;
                $activeSchoolId = null;
                $activeBranchId = null;
                $isActive = false;

                foreach ($legacyStudentClassrooms as $legacyStudentClassroom) {

                    $legacyClassroom = DB::connection('legacy')
                        ->table('classrooms')
                        ->where('id', $legacyStudentClassroom->classroom_id)
                        ->first();

                    $school = School::where('legacy_old_id', $legacyStudentClassroom->school_id)->first();

                    $classroom = Classroom::firstOrCreate([
                        'legacy_old_id' => $legacyClassroom->id,
                        'school_id' => $school->getKey(),
                        'name' => $legacyClassroom->name,
                        'grade' => $this->getGrade($legacyClassroom->level),
                        'phase' => $legacyClassroom->fase,
                        'is_moving_class' => $legacyClassroom->is_moving_class,
                    ]);

                    if ($legacyStudentClassroom->active) {
                        $isActive = true;
                    }

                    if ($legacyStudentClassroom->active || $legacyStudentClassrooms->last()->id === $legacyStudentClassroom->id) {
                        $activeClassroomId = $classroom->getKey();
                        $activeSchoolId = $school->getKey();
                        $activeBranchId = $school->branch_id;
                    }
                }

                $this->info('Migrasi siswa ' . $oldStudent->name);

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
                    'status_in_family' => $this->getStudentStatusInFamily($oldStudent),
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

                // $this->generateStudentGuardians($student, $oldStudent);

                foreach ($legacyStudentClassrooms as $legacyStudentClassroom) {
                    $status = StudentEnrollmentStatusEnum::INACTIVE;

                    if ($legacyStudentClassroom->active) {
                        $status = StudentEnrollmentStatusEnum::ENROLLED;
                    }

                    $legacyClassroom = DB::connection('legacy')
                        ->table('classrooms')
                        ->where('id', $legacyStudentClassroom->classroom_id)
                        ->first();

                    // jika dia kelas 6 sd/3smp/3sma di tahun ajaran lalu set jadi graduated
                    if (
                        ($legacyClassroom->level === 6 && $legacyStudentClassroom->school_year_id === SchoolYear::where('start_year', 2024)->first()->getKey()) ||
                        ($legacyClassroom->level === 9 && $legacyStudentClassroom->school_year_id === SchoolYear::where('start_year', 2024)->first()->getKey()) ||
                        ($legacyClassroom->level === 12 && $legacyStudentClassroom->school_year_id === SchoolYear::where('start_year', 2024)->first()->getKey())
                    ) {
                        $status = StudentEnrollmentStatusEnum::GRADUATED;
                    }

                    $legacySchoolYear = $legacySchoolYears->where('id', $legacyStudentClassroom->school_year_id)->first();

                    $schoolYear = SchoolYear::where('legacy_old_id', $legacySchoolYear->id)->first();

                    $classroom = Classroom::where('legacy_old_id', $legacyClassroom->id)->first();

                    if (
                        $student->enrollments()
                            ->where('branch_id', $student->branch_id)
                            ->where('school_id', $student->school_id)
                            ->where('classroom_id', $classroom->getKey())
                            ->where('school_year_id', $schoolYear->getKey())
                            ->exists()) {
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

                $legacyPaymentAccounts = DB::connection('legacy')
                    ->table('student_payment_details')
                    ->where('student_id', $oldStudent->id)
                    ->get();

                foreach ($legacyPaymentAccounts as $legacyPaymentAccount) {

                    $school = School::where('legacy_old_id', $legacyPaymentAccount->school_id)->first();

                    if (
                        $student->paymentAccounts()
                            ->where('school_id', $school->getKey())
                            ->exists()) {
                        continue;
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
            });

        }

        $this->migrateStudentBiils($legacyStudents);

        if (blank($studentNoClassroom)) {
            $this->info('Tidak ada data siswa yang tidak memiliki kelas');
        } else {
            $this->info('Data siswa yang tidak memiliki kelas: ' . implode(', ', $studentNoClassroom));
        }

        if (blank($studentCancel)) {
            $this->info('Tidak ada data siswa yang dibatalkan');
        } else {
            $this->info('Data siswa yang dibatalkan: ' . implode(', ', $studentCancel));
        }

        $this->info('Migrasi selesai!');
    }

    protected function getBookInvoiceStatus($oldInv)
    {
        if ($oldInv->paid_date !== null && $oldInv->payment_method_id !== null) {
            return InvoiceStatusEnum::PAID;
        }

        if ($oldInv->paid_date === null) {
            return InvoiceStatusEnum::UNPAID;
        }

        return InvoiceStatusEnum::VOID;
    }

    protected function getInvoiceStatus($oldInv)
    {
        if (
            $oldInv->is_active === 1 && $oldInv->paid_date !== null && $oldInv->payment_method_id !== null
        ) {
            return InvoiceStatusEnum::PAID;
        }

        if ($oldInv->is_active === 0 && $oldInv->paid_date !== null && $oldInv->payment_method_id !== null) {
            return InvoiceStatusEnum::PAID;
        }

        if (
            $oldInv->is_active === 1 && $oldInv->paid_date === null && $oldInv->payment_method_id === null
        ) {
            return InvoiceStatusEnum::UNPAID;
        }

        if (
            $oldInv->is_active === 0 && $oldInv->paid_date === null && $oldInv->payment_method_id === null
        ) {
            return InvoiceStatusEnum::VOID;
        }

        if (
            $oldInv->is_active === 0 && $oldInv->paid_date === null && $oldInv->payment_method_id !== null
        ) {
            return InvoiceStatusEnum::VOID;
        }

        if(blank($oldInv->is_active)){
            return InvoiceStatusEnum::VOID;
        }

    }

    protected function getStudentStatusInFamily($oldStudent)
    {
        if ($oldStudent->status_in_family === 'Kandung' || $oldStudent->status_in_family === 'Anak Kandung' || $oldStudent->status_in_family === 'Anak') {
            return StatusInFamilyEnum::BIOLOGICAL_CHILD;
        }

        if ($oldStudent->status_in_family === 'Tiri') {
            return StatusInFamilyEnum::STEP_CHILD;
        }

        if ($oldStudent->status_in_family === 'Angkat') {
            return StatusInFamilyEnum::ADOPTED_CHILD;
        }

        if ($oldStudent->status_in_family === 'Asuh') {
            return StatusInFamilyEnum::FOSTER_CHILD;
        }

        return null;
    }

    protected function getBranch(int $id, $returnObject = false): string | Branch
    {
        if ($returnObject) {
            if ($id === 1) {
                return Branch::where('name', 'Batam Center')->first();
            }

            return Branch::where('name', 'Batu Aji')->first();
        }

        if ($id === 1) {
            return Branch::where('name', 'Batam Center')->first()->getKey();
        }

        return Branch::where('name', 'Batu Aji')->first()->getKey();
    }

    protected function getLevel(string $name): ?int
    {
        if (str_contains($name, 'TK')) {
            return LevelEnum::KINDERGARTEN->value;
        }
        if (str_contains($name, 'SD')) {
            return LevelEnum::ELEMENTARY->value;
        }
        if (str_contains($name, 'SMP')) {
            return LevelEnum::JUNIOR_HIGH->value;
        }
        if (str_contains($name, 'SMA')) {
            return LevelEnum::SENIOR_HIGH->value;
        }

        return null;
    }

    protected function getGrade(int $oldGrade): ?int
    {
        if ($oldGrade === 0) {
            // TODO: make it dynamic using age calculation
            return GradeEnum::KINDERGARTEN_B->value;
        }
        if ($oldGrade === 1) {
            return GradeEnum::GRADE_1->value;
        }
        if ($oldGrade === 2) {
            return GradeEnum::GRADE_2->value;
        }
        if ($oldGrade === 3) {
            return GradeEnum::GRADE_3->value;
        }
        if ($oldGrade === 4) {
            return GradeEnum::GRADE_4->value;
        }
        if ($oldGrade === 5) {
            return GradeEnum::GRADE_5->value;
        }
        if ($oldGrade === 6) {
            return GradeEnum::GRADE_6->value;
        }
        if ($oldGrade === 7) {
            return GradeEnum::GRADE_7->value;
        }
        if ($oldGrade === 8) {
            return GradeEnum::GRADE_8->value;
        }
        if ($oldGrade === 9) {
            return GradeEnum::GRADE_9->value;
        }
        if ($oldGrade === 10) {
            return GradeEnum::GRADE_10->value;
        }
        if ($oldGrade === 11) {
            return GradeEnum::GRADE_11->value;
        }
        if ($oldGrade === 12) {
            return GradeEnum::GRADE_12->value;
        }

        return null;
    }

    protected function migrateMasterData()
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

        // 2. data branch dan data sekolah
        Branch::firstOrCreate([
            'legacy_old_id' => 1,
            'name' => 'Batam Center',
        ]);

        Branch::firstOrCreate([
            'legacy_old_id' => 2,
            'name' => 'Batu Aji',
        ]);

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
                'branch_id' => $this->getBranch($legacySchool->team_id),
                'name' => $legacySchool->name,
                'level' => $this->getLevel($legacySchool->name),
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

        return $legacySchoolYears;
    }

    protected function migrateStudentBiils($legacyStudents)
    {

        foreach ($legacyStudents as $oldStudent) {

            $student = Student::where('legacy_old_id', $oldStudent->id)->first();

            $legacyInvoices = DB::connection('legacy')
                ->table('student_spp_bill')
                ->where('student_id', $oldStudent->id)
                ->get();

            $this->migrateStudentSppBills($legacyInvoices, $student);

            // $legacyBookInvoices = DB::connection('legacy')
            //     ->table('student_book_bill')
            //     ->where('student_id', $oldStudent->id)
            //     ->get();

            // $this->migrateStudentBookBills($legacyBookInvoices, $student);
        }
    }

    protected function migrateStudentSppBills($legacyInvoices, $student)
    {
        foreach ($legacyInvoices as $oldInv) {
            $schoolYear = SchoolYear::where('legacy_old_id', $oldInv->school_year_id)->first();

            $prepareFingerprint = [
                'type' => InvoiceTypeEnum::MONTHLY_FEE->value,
                'student_id' => $student->getKey(),
                'school_year_id' => $schoolYear->getKey(),
                'month' => $oldInv->month_id,
            ];

            $branch = Branch::where('legacy_old_id', $oldInv->team_id)->first();

            $classroom = Classroom::whereIn('school_id', $branch->schools()->pluck('id'))
                ->where('legacy_old_id', $oldInv->classroom_id)
                ->first();

            if (Invoice::where('legacy_old_id', $oldInv->id)->exists()) {
                continue;
            }

            if (blank($classroom)) {
                $studentNoClassroom[] = $student->name;

                continue;
            }

            $this->info('Buat invoice spp ' . $student->name . ' dengan id:' . $oldInv->id);

            Invoice::createQuietly([
                'id' => str()->ulid(),
                'legacy_old_id' => $oldInv->id,
                'fingerprint' => Invoice::generateFingerprint($prepareFingerprint),
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
                'status' => $this->getInvoiceStatus($oldInv),
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

    protected function migrateStudentBookBills($legacyBookInvoices, $student)
    {
        foreach ($legacyBookInvoices as $legacyBookInvoice) {
            $schoolYear = SchoolYear::where('legacy_old_id', $legacyBookInvoice->school_year_id)->first();

            $prepareFingerprint = [
                'type' => InvoiceTypeEnum::BOOK_FEE->value,
                'student_id' => $student->getKey(),
                'school_year_id' => $schoolYear->getKey(),
            ];

            $branch = Branch::where('legacy_old_id', $legacyBookInvoice->team_id)->first();

            $classroom = Classroom::whereIn('school_id', $branch->schools()->pluck('id'))
                ->where('legacy_old_id', $legacyBookInvoice->classroom_id)
                ->first();

            if (Invoice::where('legacy_old_id', $legacyBookInvoice->id)->exists()) {
                continue;
            }

            if (blank($classroom)) {
                $studentNoClassroom[] = $student->name;

                continue;
            }

            $this->info('Buat invoice buku ' . $student->name . 'dengan id' . $legacyBookInvoice->id);

            Invoice::createQuietly([
                'id' => str()->ulid(),
                'legacy_old_id' => $legacyBookInvoice->id,
                'fingerprint' => Invoice::generateFingerprint($prepareFingerprint),
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

                'amount' => $legacyBookInvoice->cost,
                'discount' => $legacyBookInvoice->discount,
                'total_amount' => $legacyBookInvoice->cost,
                'status' => $this->getBookInvoiceStatus($legacyBookInvoice),
                'payment_method' => $legacyBookInvoice->payment_method_id,

                'due_date' => $legacyBookInvoice->end_date,
                'issued_at' => $legacyBookInvoice->start_date,
                'paid_at' => $legacyBookInvoice->paid_date,
                'description' => $legacyBookInvoice->description,
                'created_at' => $legacyBookInvoice->created_at,
                'updated_at' => $legacyBookInvoice->updated_at,
            ]);
        }
    }
}
