<?php

declare(strict_types=1);

use App\Enums\GradeEnum;
use App\Models\Classroom;
use App\Models\School;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// ============================================================
// RELATIONSHIPS
// ============================================================

describe('Classroom Relationships', function () {

    describe('school()', function () {
        it('has correct relationship type', function () {
            $classroom = Classroom::factory()->make();

            expect($classroom->school())->toBeInstanceOf(BelongsTo::class);
        });

        it('returns the school this classroom belongs to', function () {
            $school = School::factory()->create();
            $classroom = Classroom::factory()->create(['school_id' => $school->id]);

            expect($classroom->school)
                ->toBeInstanceOf(School::class)
                ->id->toBe($school->id);
        });

        it('does not return a school from another classroom', function () {
            $schoolA = School::factory()->create();
            $schoolB = School::factory()->create();

            $classroom = Classroom::factory()->create(['school_id' => $schoolA->id]);

            expect($classroom->school->id)->not->toBe($schoolB->id);
        });
    });
});

// ============================================================
// ATTRIBUTES / CASTS
// ============================================================

describe('Classroom Attributes', function () {

    it('uses ulid as primary key', function () {
        $classroom = Classroom::factory()->create();

        expect($classroom->id)
            ->toBeString()
            ->toHaveLength(26)
            ->toMatch('/^[0-9a-hjkmnp-tv-z]{26}$/');
    });

    it('casts created_at and updated_at as Carbon', function () {
        $classroom = Classroom::factory()->create();

        expect($classroom->created_at)->toBeInstanceOf(Illuminate\Support\Carbon::class);
        expect($classroom->updated_at)->toBeInstanceOf(Illuminate\Support\Carbon::class);
    });

    it('casts grade as GradeEnum', function () {
        $classroom = Classroom::factory()->create(['grade' => GradeEnum::GRADE_1]);

        expect($classroom->grade)->toBeInstanceOf(GradeEnum::class);
    });

    it('casts is_moving_class as boolean', function () {
        $classroom = Classroom::factory()->create(['is_moving_class' => true]);

        expect($classroom->is_moving_class)->toBeBool()->toBeTrue();
    });

    it('allows nullable phase', function () {
        $classroom = Classroom::factory()->create(['phase' => null]);

        expect($classroom->phase)->toBeNull();
    });

    it('allows nullable legacy_old_id', function () {
        $classroom = Classroom::factory()->create(['legacy_old_id' => null]);

        expect($classroom->legacy_old_id)->toBeNull();
    });
});

// ============================================================
// SCOPES
// ============================================================

describe('Classroom::excludeFinalYears()', function () {
    it('excludes kindergarten B classrooms', function () {
        Classroom::factory()->create(['grade' => GradeEnum::KINDERGARTEN_B]);

        expect(Classroom::excludeFinalYears()->get())->toHaveCount(0);
    });

    it('excludes grade 6 classrooms', function () {
        Classroom::factory()->create(['grade' => GradeEnum::GRADE_6]);

        expect(Classroom::excludeFinalYears()->get())->toHaveCount(0);
    });

    it('excludes grade 9 classrooms', function () {
        Classroom::factory()->create(['grade' => GradeEnum::GRADE_9]);

        expect(Classroom::excludeFinalYears()->get())->toHaveCount(0);
    });

    it('excludes grade 12 classrooms', function () {
        Classroom::factory()->create(['grade' => GradeEnum::GRADE_12]);

        expect(Classroom::excludeFinalYears()->get())->toHaveCount(0);
    });

    it('excludes all final year grades at once', function () {
        foreach (GradeEnum::finalYears() as $finalGrade) {
            Classroom::factory()->create(['grade' => $finalGrade]);
        }

        expect(Classroom::excludeFinalYears()->get())->toHaveCount(0);
    });

    it('includes non-final year classrooms', function () {
        $nonFinalGrades = array_filter(
            GradeEnum::cases(),
            fn (GradeEnum $grade) => ! $grade->isFinalYear()
        );

        foreach ($nonFinalGrades as $grade) {
            Classroom::factory()->create(['grade' => $grade]);
        }

        expect(Classroom::excludeFinalYears()->get())->toHaveCount(count($nonFinalGrades));
    });

    it('returns only non-final year classrooms when mixed grades exist', function () {
        $nonFinalGrades = [GradeEnum::GRADE_1, GradeEnum::GRADE_7, GradeEnum::GRADE_10];
        $finalGrades = GradeEnum::finalYears();

        foreach ($nonFinalGrades as $grade) {
            Classroom::factory()->create(['grade' => $grade]);
        }
        foreach ($finalGrades as $grade) {
            Classroom::factory()->create(['grade' => $grade]);
        }

        expect(Classroom::excludeFinalYears()->get())->toHaveCount(count($nonFinalGrades));
    });
});
