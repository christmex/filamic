<?php

declare(strict_types=1);

use App\Enums\LevelEnum;
use App\Models\Branch;
use App\Models\Classroom;
use App\Models\School;
use App\Models\SchoolEvent;
use App\Models\Subject;
use App\Models\SubjectCategory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

// ============================================================
// RELATIONSHIPS
// ============================================================

describe('School Relationships', function () {

    describe('branch()', function () {
        it('has correct relationship type', function () {
            $school = School::factory()->make();

            expect($school->branch())->toBeInstanceOf(BelongsTo::class);
        });

        it('returns the branch this school belongs to', function () {
            $branch = Branch::factory()->create();
            $school = School::factory()->create(['branch_id' => $branch->id]);

            expect($school->branch)
                ->toBeInstanceOf(Branch::class)
                ->id->toBe($branch->id);
        });

        it('does not return a branch from another school', function () {
            $branchA = Branch::factory()->create();
            $branchB = Branch::factory()->create();

            $school = School::factory()->create(['branch_id' => $branchA->id]);

            expect($school->branch->id)->not->toBe($branchB->id);
        });
    });

    describe('classrooms()', function () {
        it('has correct relationship type', function () {
            $school = School::factory()->make();

            expect($school->classrooms())->toBeInstanceOf(HasMany::class);
        });

        it('returns classrooms that belong to this school', function () {
            $school = School::factory()->create();
            Classroom::factory()->count(3)->create(['school_id' => $school->id]);

            expect($school->classrooms)
                ->toHaveCount(3)
                ->each->toBeInstanceOf(Classroom::class);
        });

        it('does not return classrooms from other schools', function () {
            $school = School::factory()->create();
            $otherSchool = School::factory()->create();

            Classroom::factory()->count(2)->create(['school_id' => $otherSchool->id]);

            expect($school->classrooms)->toHaveCount(0);
        });
    });

    describe('subjectCategories()', function () {
        it('has correct relationship type', function () {
            $school = School::factory()->make();

            expect($school->subjectCategories())->toBeInstanceOf(HasMany::class);
        });

        it('returns subject categories that belong to this school', function () {
            $school = School::factory()->create();
            SubjectCategory::factory()->count(4)->create(['school_id' => $school->id]);

            expect($school->subjectCategories)
                ->toHaveCount(4)
                ->each->toBeInstanceOf(SubjectCategory::class);
        });

        it('does not return subject categories from other schools', function () {
            $school = School::factory()->create();
            $otherSchool = School::factory()->create();

            SubjectCategory::factory()->count(2)->create(['school_id' => $otherSchool->id]);

            expect($school->subjectCategories)->toHaveCount(0);
        });
    });

    describe('events()', function () {
        it('has correct relationship type', function () {
            $school = School::factory()->make();

            expect($school->events())->toBeInstanceOf(HasMany::class);
        });

        it('returns events that belong to this school', function () {
            $school = School::factory()->create();
            SchoolEvent::factory()->count(3)->create(['school_id' => $school->id]);

            expect($school->events)
                ->toHaveCount(3)
                ->each->toBeInstanceOf(SchoolEvent::class);
        });

        it('does not return events from other schools', function () {
            $school = School::factory()->create();
            $otherSchool = School::factory()->create();

            SchoolEvent::factory()->count(2)->create(['school_id' => $otherSchool->id]);

            expect($school->events)->toHaveCount(0);
        });
    });

    describe('subjects()', function () {
        it('has correct relationship type', function () {
            $school = School::factory()->make();

            expect($school->subjects())->toBeInstanceOf(HasManyThrough::class);
        });

        it('returns subjects through subject categories', function () {
            $school = School::factory()->create();
            $category = SubjectCategory::factory()->create(['school_id' => $school->id]);
            Subject::factory()->count(3)->create(['subject_category_id' => $category->id]);

            expect($school->subjects)
                ->toHaveCount(3)
                ->each->toBeInstanceOf(Subject::class);
        });

        it('aggregates subjects across multiple subject categories', function () {
            $school = School::factory()->create();
            $categoryA = SubjectCategory::factory()->create(['school_id' => $school->id]);
            $categoryB = SubjectCategory::factory()->create(['school_id' => $school->id]);

            Subject::factory()->count(2)->create(['subject_category_id' => $categoryA->id]);
            Subject::factory()->count(3)->create(['subject_category_id' => $categoryB->id]);

            expect($school->subjects)->toHaveCount(5);
        });

        it('does not return subjects from other schools', function () {
            $school = School::factory()->create();
            $otherSchool = School::factory()->create();
            $otherCategory = SubjectCategory::factory()->create(['school_id' => $otherSchool->id]);

            Subject::factory()->count(2)->create(['subject_category_id' => $otherCategory->id]);

            expect($school->subjects)->toHaveCount(0);
        });
    });
});

// ============================================================
// ATTRIBUTES / CASTS
// ============================================================

describe('School Attributes', function () {

    it('uses ulid as primary key', function () {
        $school = School::factory()->create();

        expect($school->id)
            ->toBeString()
            ->toHaveLength(26)
            ->toMatch('/^[0-9a-hjkmnp-tv-z]{26}$/');
    });

    it('casts created_at and updated_at as Carbon', function () {
        $school = School::factory()->create();

        expect($school->created_at)->toBeInstanceOf(Illuminate\Support\Carbon::class);
        expect($school->updated_at)->toBeInstanceOf(Illuminate\Support\Carbon::class);
    });

    it('casts level as LevelEnum', function () {
        $school = School::factory()->create(['level' => LevelEnum::KINDERGARTEN]);

        expect($school->level)->toBeInstanceOf(LevelEnum::class);
    });

    it('allows nullable address', function () {
        $school = School::factory()->create(['address' => null]);

        expect($school->address)->toBeNull();
    });

    it('allows nullable npsn', function () {
        $school = School::factory()->create(['npsn' => null]);

        expect($school->npsn)->toBeNull();
    });

    it('allows nullable nis_nss_nds', function () {
        $school = School::factory()->create(['nis_nss_nds' => null]);

        expect($school->nis_nss_nds)->toBeNull();
    });

    it('allows nullable telp', function () {
        $school = School::factory()->create(['telp' => null]);

        expect($school->telp)->toBeNull();
    });

    it('allows nullable postal_code', function () {
        $school = School::factory()->create(['postal_code' => null]);

        expect($school->postal_code)->toBeNull();
    });

    it('allows nullable village', function () {
        $school = School::factory()->create(['village' => null]);

        expect($school->village)->toBeNull();
    });

    it('allows nullable subdistrict', function () {
        $school = School::factory()->create(['subdistrict' => null]);

        expect($school->subdistrict)->toBeNull();
    });

    it('allows nullable city', function () {
        $school = School::factory()->create(['city' => null]);

        expect($school->city)->toBeNull();
    });

    it('allows nullable province', function () {
        $school = School::factory()->create(['province' => null]);

        expect($school->province)->toBeNull();
    });

    it('allows nullable website', function () {
        $school = School::factory()->create(['website' => null]);

        expect($school->website)->toBeNull();
    });

    it('allows nullable email', function () {
        $school = School::factory()->create(['email' => null]);

        expect($school->email)->toBeNull();
    });
});

// ============================================================
// COMPUTED ATTRIBUTES
// ============================================================

describe('School::formattedNpsn', function () {
    it('returns formatted npsn string when npsn is filled', function () {
        $school = School::factory()->make(['npsn' => '12345678']);

        expect($school->formatted_npsn)->toBe('NPSN: 12345678');
    });

    it('returns dash when npsn is null', function () {
        $school = School::factory()->make(['npsn' => null]);

        expect($school->formatted_npsn)->toBe('-');
    });

    it('returns dash when npsn is empty string', function () {
        $school = School::factory()->make(['npsn' => '']);

        expect($school->formatted_npsn)->toBe('-');
    });
});
