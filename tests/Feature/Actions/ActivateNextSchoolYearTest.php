<?php

declare(strict_types=1);

use App\Actions\ActivateNextSchoolYear;
use App\Enums\StudentEnrollmentStatusEnum;
use App\Models\Branch;
use App\Models\Classroom;
use App\Models\School;
use App\Models\SchoolYear;
use App\Models\Student;
use App\Models\StudentEnrollment;
use Illuminate\Database\Eloquent\ModelNotFoundException;

beforeEach(function () {
    $this->branch = Branch::factory()->create();
    $this->school = School::factory()->for($this->branch)->create();

    $this->activeYear = SchoolYear::factory()->active()->create(['start_year' => 2025]);
    $this->nextYear = SchoolYear::factory()->inactive()->create(['start_year' => 2026]);
});

it('promotes students from non-final grade to PROMOTED', function () {
    $oldClassroom = Classroom::factory()->for($this->school)->create(['grade' => 5]); // GRADE_1
    $newClassroom = Classroom::factory()->for($this->school)->create(['grade' => 6]); // GRADE_2

    $student = Student::factory()
        ->for($this->branch)
        ->for($this->school)
        ->for($oldClassroom)
        ->create(['is_active' => true]);

    StudentEnrollment::factory()->create([
        'student_id' => $student->getKey(),
        'branch_id' => $this->branch->getKey(),
        'school_id' => $this->school->getKey(),
        'classroom_id' => $oldClassroom->getKey(),
        'school_year_id' => $this->activeYear->getKey(),
        'status' => StudentEnrollmentStatusEnum::ENROLLED,
    ]);

    StudentEnrollment::factory()->create([
        'student_id' => $student->getKey(),
        'branch_id' => $this->branch->getKey(),
        'school_id' => $this->school->getKey(),
        'classroom_id' => $newClassroom->getKey(),
        'school_year_id' => $this->nextYear->getKey(),
        'status' => StudentEnrollmentStatusEnum::DRAFT,
    ]);

    $processed = ActivateNextSchoolYear::run();

    expect($processed)->toBe(1);

    $oldEnrollment = StudentEnrollment::where('school_year_id', $this->activeYear->getKey())
        ->where('student_id', $student->getKey())
        ->first();

    $newEnrollment = StudentEnrollment::where('school_year_id', $this->nextYear->getKey())
        ->where('student_id', $student->getKey())
        ->first();

    expect($oldEnrollment->status)->toBe(StudentEnrollmentStatusEnum::PROMOTED)
        ->and($newEnrollment->status)->toBe(StudentEnrollmentStatusEnum::ENROLLED);
});

it('sets STAYED when old and new grade are the same', function () {
    $classroom = Classroom::factory()->for($this->school)->create(['grade' => 5]); // GRADE_1

    $student = Student::factory()
        ->for($this->branch)
        ->for($this->school)
        ->for($classroom)
        ->create(['is_active' => true]);

    StudentEnrollment::factory()->create([
        'student_id' => $student->getKey(),
        'branch_id' => $this->branch->getKey(),
        'school_id' => $this->school->getKey(),
        'classroom_id' => $classroom->getKey(),
        'school_year_id' => $this->activeYear->getKey(),
        'status' => StudentEnrollmentStatusEnum::ENROLLED,
    ]);

    StudentEnrollment::factory()->create([
        'student_id' => $student->getKey(),
        'branch_id' => $this->branch->getKey(),
        'school_id' => $this->school->getKey(),
        'classroom_id' => $classroom->getKey(),
        'school_year_id' => $this->nextYear->getKey(),
        'status' => StudentEnrollmentStatusEnum::DRAFT,
    ]);

    ActivateNextSchoolYear::run();

    $oldEnrollment = StudentEnrollment::where('school_year_id', $this->activeYear->getKey())
        ->where('student_id', $student->getKey())
        ->first();

    expect($oldEnrollment->status)->toBe(StudentEnrollmentStatusEnum::STAYED);
});

it('sets GRADUATED when old grade is a final year', function () {
    $finalClassroom = Classroom::factory()->for($this->school)->create(['grade' => 10]); // GRADE_6 (final year SD)

    $smpSchool = School::factory()->for($this->branch)->create();
    $smpClassroom = Classroom::factory()->for($smpSchool)->create(['grade' => 11]); // GRADE_7

    $student = Student::factory()
        ->for($this->branch)
        ->for($this->school)
        ->for($finalClassroom)
        ->create(['is_active' => true]);

    StudentEnrollment::factory()->create([
        'student_id' => $student->getKey(),
        'branch_id' => $this->branch->getKey(),
        'school_id' => $this->school->getKey(),
        'classroom_id' => $finalClassroom->getKey(),
        'school_year_id' => $this->activeYear->getKey(),
        'status' => StudentEnrollmentStatusEnum::ENROLLED,
    ]);

    StudentEnrollment::factory()->create([
        'student_id' => $student->getKey(),
        'branch_id' => $this->branch->getKey(),
        'school_id' => $smpSchool->getKey(),
        'classroom_id' => $smpClassroom->getKey(),
        'school_year_id' => $this->nextYear->getKey(),
        'status' => StudentEnrollmentStatusEnum::DRAFT,
    ]);

    ActivateNextSchoolYear::run();

    $oldEnrollment = StudentEnrollment::where('school_year_id', $this->activeYear->getKey())
        ->where('student_id', $student->getKey())
        ->first();

    expect($oldEnrollment->status)->toBe(StudentEnrollmentStatusEnum::GRADUATED);
});

it('throws when no next school year exists', function () {
    $this->nextYear->delete();

    expect(fn () => ActivateNextSchoolYear::run())
        ->toThrow(ModelNotFoundException::class, 'Tahun Ajaran Selanjutnya Tidak Ditemukan');
});

it('throws when any draft has null classroom_id', function () {
    $student = Student::factory()
        ->for($this->branch)
        ->for($this->school)
        ->create(['is_active' => true]);

    $classroom = Classroom::factory()->for($this->school)->create();

    StudentEnrollment::factory()->create([
        'student_id' => $student->getKey(),
        'branch_id' => $this->branch->getKey(),
        'school_id' => $this->school->getKey(),
        'classroom_id' => $classroom->getKey(),
        'school_year_id' => $this->activeYear->getKey(),
        'status' => StudentEnrollmentStatusEnum::ENROLLED,
    ]);

    StudentEnrollment::factory()->create([
        'student_id' => $student->getKey(),
        'branch_id' => $this->branch->getKey(),
        'school_id' => $this->school->getKey(),
        'classroom_id' => null,
        'school_year_id' => $this->nextYear->getKey(),
        'status' => StudentEnrollmentStatusEnum::DRAFT,
    ]);

    expect(fn () => ActivateNextSchoolYear::run())
        ->toThrow(InvalidArgumentException::class, 'Terdapat data draft siswa yang kelas nya masih kosong');
});

it('skips inactive students', function () {
    $oldClassroom = Classroom::factory()->for($this->school)->create(['grade' => 5]);
    $newClassroom = Classroom::factory()->for($this->school)->create(['grade' => 6]);

    $inactiveStudent = Student::factory()
        ->for($this->branch)
        ->for($this->school)
        ->for($oldClassroom)
        ->create(['is_active' => false]);

    // Inactive enrollment (not ENROLLED) so syncActiveStatus keeps student inactive
    StudentEnrollment::factory()->create([
        'student_id' => $inactiveStudent->getKey(),
        'branch_id' => $this->branch->getKey(),
        'school_id' => $this->school->getKey(),
        'classroom_id' => $oldClassroom->getKey(),
        'school_year_id' => $this->activeYear->getKey(),
        'status' => StudentEnrollmentStatusEnum::INACTIVE,
    ]);

    StudentEnrollment::factory()->create([
        'student_id' => $inactiveStudent->getKey(),
        'branch_id' => $this->branch->getKey(),
        'school_id' => $this->school->getKey(),
        'classroom_id' => $newClassroom->getKey(),
        'school_year_id' => $this->nextYear->getKey(),
        'status' => StudentEnrollmentStatusEnum::DRAFT,
    ]);

    // Confirm student is actually inactive before the action
    expect($inactiveStudent->fresh()->is_active)->toBeFalse();

    $processed = ActivateNextSchoolYear::run();

    expect($processed)->toBe(0);

    $draftEnrollment = StudentEnrollment::where('school_year_id', $this->nextYear->getKey())
        ->where('student_id', $inactiveStudent->getKey())
        ->first();

    // Draft stays as DRAFT — not enrolled
    expect($draftEnrollment->status)->toBe(StudentEnrollmentStatusEnum::DRAFT);
});

it('sets current enrollment to INACTIVE for students without next year enrollment', function () {
    $classroom = Classroom::factory()->for($this->school)->create(['grade' => 5]);

    $studentWithDraft = Student::factory()
        ->for($this->branch)
        ->for($this->school)
        ->for($classroom)
        ->create(['is_active' => true]);

    $studentWithoutDraft = Student::factory()
        ->for($this->branch)
        ->for($this->school)
        ->for($classroom)
        ->create(['is_active' => true]);

    $newClassroom = Classroom::factory()->for($this->school)->create(['grade' => 6]);

    // Both have current enrollment
    StudentEnrollment::factory()->create([
        'student_id' => $studentWithDraft->getKey(),
        'branch_id' => $this->branch->getKey(),
        'school_id' => $this->school->getKey(),
        'classroom_id' => $classroom->getKey(),
        'school_year_id' => $this->activeYear->getKey(),
        'status' => StudentEnrollmentStatusEnum::ENROLLED,
    ]);

    StudentEnrollment::factory()->create([
        'student_id' => $studentWithoutDraft->getKey(),
        'branch_id' => $this->branch->getKey(),
        'school_id' => $this->school->getKey(),
        'classroom_id' => $classroom->getKey(),
        'school_year_id' => $this->activeYear->getKey(),
        'status' => StudentEnrollmentStatusEnum::ENROLLED,
    ]);

    // Only studentWithDraft has a draft for next year
    StudentEnrollment::factory()->create([
        'student_id' => $studentWithDraft->getKey(),
        'branch_id' => $this->branch->getKey(),
        'school_id' => $this->school->getKey(),
        'classroom_id' => $newClassroom->getKey(),
        'school_year_id' => $this->nextYear->getKey(),
        'status' => StudentEnrollmentStatusEnum::DRAFT,
    ]);

    ActivateNextSchoolYear::run();

    $withoutDraftEnrollment = StudentEnrollment::where('student_id', $studentWithoutDraft->getKey())
        ->where('school_year_id', $this->activeYear->getKey())
        ->first();

    $withDraftEnrollment = StudentEnrollment::where('student_id', $studentWithDraft->getKey())
        ->where('school_year_id', $this->activeYear->getKey())
        ->first();

    expect($withoutDraftEnrollment->status)->toBe(StudentEnrollmentStatusEnum::INACTIVE)
        ->and($withDraftEnrollment->status)->toBe(StudentEnrollmentStatusEnum::PROMOTED);
});

it('only processes drafts for the next school year', function () {
    $oldClassroom = Classroom::factory()->for($this->school)->create(['grade' => 5]);
    $newClassroom = Classroom::factory()->for($this->school)->create(['grade' => 6]);

    $student = Student::factory()
        ->for($this->branch)
        ->for($this->school)
        ->for($oldClassroom)
        ->create(['is_active' => true]);

    StudentEnrollment::factory()->create([
        'student_id' => $student->getKey(),
        'branch_id' => $this->branch->getKey(),
        'school_id' => $this->school->getKey(),
        'classroom_id' => $oldClassroom->getKey(),
        'school_year_id' => $this->activeYear->getKey(),
        'status' => StudentEnrollmentStatusEnum::ENROLLED,
    ]);

    // Draft for a DIFFERENT future year (not the next year)
    $farFutureYear = SchoolYear::factory()->create(['start_year' => 2027, 'is_active' => false]);

    StudentEnrollment::factory()->create([
        'student_id' => $student->getKey(),
        'branch_id' => $this->branch->getKey(),
        'school_id' => $this->school->getKey(),
        'classroom_id' => $newClassroom->getKey(),
        'school_year_id' => $farFutureYear->getKey(),
        'status' => StudentEnrollmentStatusEnum::DRAFT,
    ]);

    $processed = ActivateNextSchoolYear::run();

    // Should not process the far-future draft
    expect($processed)->toBe(0);

    $farFutureDraft = StudentEnrollment::where('school_year_id', $farFutureYear->getKey())
        ->where('student_id', $student->getKey())
        ->first();

    expect($farFutureDraft->status)->toBe(StudentEnrollmentStatusEnum::DRAFT);
});

it('returns zero when no drafts exist for next school year', function () {
    $processed = ActivateNextSchoolYear::run();

    expect($processed)->toBe(0);
});

it('activates the next school year and deactivates the current one', function () {
    ActivateNextSchoolYear::run();

    expect($this->nextYear->fresh()->is_active)->toBeTrue()
        ->and($this->activeYear->fresh()->is_active)->toBeFalse();
});
