<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\LevelEnum;
use App\Enums\UserTypeEnum;
use App\Models\Branch;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use App\Models\Classroom;
use App\Models\Curriculum;
use App\Models\Position;
use App\Models\ProductCategory;
use App\Models\School;
use App\Models\SchoolEvent;
use App\Models\SchoolTerm;
use App\Models\SchoolYear;
use App\Models\Subject;
use App\Models\SubjectCategory;
use App\Models\Supplier;
use App\Models\Teacher;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        if (app()->environment('local')) {

            $this->createBranches();
            $this->createSchoolYear();
            $this->createSchoolTerm();
            $this->createPosition();
            $this->createSchools();
            // $this->createCurriculum();
            // $this->createSubjectCategories();

            $this->setupSupplyHub();

            $user = User::factory()->create([
                'name' => 'Super Admin',
                'email' => 'super@admin.com',
                'user_type' => UserTypeEnum::EMPLOYEE,
                'password' => bcrypt('mantapjiwa00'),
            ]);

            $user->branches()->sync(Branch::all());

            $this->createClassrooms();

            // $this->createSubjects();
            // $this->createTeachers();
            // $this->createSchoolEvents();
        }
    }

    public function createBranches(): void
    {
        Branch::factory(2)
            ->forEachSequence([
                'name' => 'Batam Center',
                'phone' => '(0778) 460817',
                'whatsapp' => '+6281275402543',
                'address' => 'Jalan laksamana Kawasan Industri No.1, Baloi Permai, Batam Center, Kota Batam, Kepulauan Riau 29444',
            ], [
                'name' => 'Batu Aji',
                'phone' => '(0778) 3850886',
                'address' => 'Perumahan Marsyeba Indah, Bukit Tempayan, Kec. Batu Aji, Kota Batam, Kepulauan Riau 29425',
            ])
            ->create();
    }

    public function createSchoolYear(): void
    {
        $data = collect(range(2023, 2027))
            ->map(fn ($year) => [
                'start_year' => $year,
                'end_year' => $year + 1,
            ])->toArray();

        SchoolYear::factory(count($data))
            ->forEachSequence(...$data)
            ->inactive()
            ->create();
    }

    public function createSchoolTerm(): void
    {
        SchoolTerm::factory(2)
            ->forEachSequence(['name' => 1], ['name' => 2])
            ->inactive()
            ->create();
    }

    public function createPosition(): void
    {
        Position::factory()
            ->state(['name' => 'Administrator'])
            ->create();
    }

    public function createCurriculum(): void
    {
        Curriculum::factory()
            ->state(['name' => 'Basic Curriculum'])
            ->active()
            ->create();
    }

    public function createSchools(): void
    {
        $branch = Branch::first();

        context()->add('branch', $branch);

        $school = School::factory()
            ->state([
                'branch_id' => $branch,
                'name' => 'SD BASIC 1',
                'level' => LevelEnum::ELEMENTARY,
            ])
            ->create();

        context()->add('school', $school);
    }

    public function createClassrooms(): void
    {
        Classroom::factory(10)->create();
    }

    public function createSubjectCategories(): void
    {
        SubjectCategory::factory()
            ->state([
                'name' => 'General Subject',
                'sort_order' => 1,
            ])
            ->create();
    }

    public function createSubjects(): void
    {
        Subject::factory()->state([
            'subject_category_id' => SubjectCategory::first()->getRouteKey(),
            'name' => 'Mathematics',
            'sort_order' => 1,
        ])->create();

        // Subject::factory(10)->create();
    }

    // public function createTeachers(): void
    // {
    //     Teacher::factory(5)->create();
    // }

    public function createSchoolEvents(): void
    {
        SchoolEvent::factory(5)->create();
    }

    public function setupSupplyHub(): void
    {
        Supplier::create([
            'name' => 'Delta Utama',
            'contact_person' => 'Pak Budi',
            'phone' => '08123456789',
        ]);

        $uniformCategory = ProductCategory::create([
            'name' => 'Seragam Sekolah',
            'code' => 'SRG',
        ]);

        $uniformVariation = $uniformCategory->variations()->create([
            'name' => 'Ukuran',
        ]);

        $uniformVariation->options()->create([
            'name' => 'S',
        ]);

        $uniformVariation->options()->create([
            'name' => 'M',
        ]);

        $uniformVariation->options()->create([
            'name' => 'L',
        ]);

        $uniformVariation->options()->create([
            'name' => 'XL',
        ]);

        $uniformVariation->options()->create([
            'name' => 'XXL',
        ]);

        ProductCategory::create([
            'name' => 'Buku Pelajaran',
            'code' => 'BK',
        ]);
    }
}
