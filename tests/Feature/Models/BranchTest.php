<?php

declare(strict_types=1);

use App\Models\Branch;
use App\Models\Classroom;
use App\Models\Invoice;
use App\Models\ProductItem;
use App\Models\ProductStock;
use App\Models\ProductStockMovement;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

describe('Branch Relationships', function () {

    describe('schools()', function () {
        it('has correct relationship type', function () {
            $branch = Branch::factory()->make();

            expect($branch->schools())->toBeInstanceOf(HasMany::class);
        });

        it('returns schools that belong to this branch', function () {
            $branch = Branch::factory()->create();
            School::factory()->count(3)->create(['branch_id' => $branch->id]);

            expect($branch->schools)
                ->toHaveCount(3)
                ->each->toBeInstanceOf(School::class);
        });

        it('does not return schools from other branches', function () {
            $branch = Branch::factory()->create();
            $otherBranch = Branch::factory()->create();

            School::factory()->count(2)->create(['branch_id' => $otherBranch->id]);

            expect($branch->schools)->toHaveCount(0);
        });
    });

    describe('classrooms()', function () {
        it('has correct relationship type', function () {
            $branch = Branch::factory()->make();

            expect($branch->classrooms())->toBeInstanceOf(HasManyThrough::class);
        });

        it('returns classrooms through schools', function () {
            $branch = Branch::factory()->create();
            $school = School::factory()->create(['branch_id' => $branch->id]);
            Classroom::factory()->count(3)->create(['school_id' => $school->id]);

            expect($branch->classrooms)
                ->toHaveCount(3)
                ->each->toBeInstanceOf(Classroom::class);
        });

        it('aggregates classrooms across multiple schools', function () {
            $branch = Branch::factory()->create();
            $schoolA = School::factory()->create(['branch_id' => $branch->id]);
            $schoolB = School::factory()->create(['branch_id' => $branch->id]);

            Classroom::factory()->count(2)->create(['school_id' => $schoolA->id]);
            Classroom::factory()->count(3)->create(['school_id' => $schoolB->id]);

            expect($branch->classrooms)->toHaveCount(5);
        });

        it('does not return classrooms from other branches', function () {
            $branch = Branch::factory()->create();
            $otherBranch = Branch::factory()->create();
            $otherSchool = School::factory()->create(['branch_id' => $otherBranch->id]);

            Classroom::factory()->count(2)->create(['school_id' => $otherSchool->id]);

            expect($branch->classrooms)->toHaveCount(0);
        });
    });

    describe('students()', function () {
        it('has correct relationship type', function () {
            $branch = Branch::factory()->make();

            expect($branch->students())->toBeInstanceOf(HasMany::class);
        });

        it('returns students that belong to this branch', function () {
            $branch = Branch::factory()->create();
            Student::factory()->count(5)->create(['branch_id' => $branch->id]);

            expect($branch->students)
                ->toHaveCount(5)
                ->each->toBeInstanceOf(Student::class);
        });

        it('does not return students from other branches', function () {
            $branch = Branch::factory()->create();
            $otherBranch = Branch::factory()->create();

            Student::factory()->count(3)->create(['branch_id' => $otherBranch->id]);

            expect($branch->students)->toHaveCount(0);
        });
    });

    describe('invoices()', function () {
        it('has correct relationship type', function () {
            $branch = Branch::factory()->make();

            expect($branch->invoices())->toBeInstanceOf(HasMany::class);
        });

        it('returns invoices that belong to this branch', function () {
            $branch = Branch::factory()->create();
            Invoice::factory()->count(4)->create(['branch_id' => $branch->id]);

            expect($branch->invoices)
                ->toHaveCount(4)
                ->each->toBeInstanceOf(Invoice::class);
        });

        it('does not return invoices from other branches', function () {
            $branch = Branch::factory()->create();
            $otherBranch = Branch::factory()->create();

            Invoice::factory()->count(2)->create(['branch_id' => $otherBranch->id]);

            expect($branch->invoices)->toHaveCount(0);
        });
    });

    describe('productStocks()', function () {
        it('has correct relationship type', function () {
            $branch = Branch::factory()->make();

            expect($branch->productStocks())->toBeInstanceOf(HasMany::class);
        });

        it('returns product stocks that belong to this branch', function () {
            $branch = Branch::factory()->create();
            ProductStock::factory()->count(3)->create(['branch_id' => $branch->id]);

            expect($branch->productStocks)
                ->toHaveCount(3)
                ->each->toBeInstanceOf(ProductStock::class);
        });

        it('does not return product stocks from other branches', function () {
            $branch = Branch::factory()->create();
            $otherBranch = Branch::factory()->create();

            ProductStock::factory()->count(2)->create(['branch_id' => $otherBranch->id]);

            expect($branch->productStocks)->toHaveCount(0);
        });
    });

    describe('stockMovements()', function () {
        it('has correct relationship type', function () {
            $branch = Branch::factory()->make();

            expect($branch->stockMovements())->toBeInstanceOf(HasMany::class);
        });

        it('returns stock movements that belong to this branch', function () {
            $branch = Branch::factory()->create();
            ProductStockMovement::factory()->count(3)->create(['branch_id' => $branch->id]);

            expect($branch->stockMovements)
                ->toHaveCount(3)
                ->each->toBeInstanceOf(ProductStockMovement::class);
        });

        it('does not return stock movements from other branches', function () {
            $branch = Branch::factory()->create();
            $otherBranch = Branch::factory()->create();

            ProductStockMovement::factory()->count(2)->create(['branch_id' => $otherBranch->id]);

            expect($branch->stockMovements)->toHaveCount(0);
        });
    });

    describe('users()', function () {
        it('has correct relationship type', function () {
            $branch = Branch::factory()->make();

            expect($branch->users())->toBeInstanceOf(BelongsToMany::class);
        });

        it('returns users attached to this branch', function () {
            $branch = Branch::factory()->create();
            $users = User::factory()->count(3)->create();
            $branch->users()->attach($users->pluck('id'));

            expect($branch->users)
                ->toHaveCount(3)
                ->each->toBeInstanceOf(User::class);
        });

        it('does not return users from other branches', function () {
            $branch = Branch::factory()->create();
            $otherBranch = Branch::factory()->create();
            $users = User::factory()->count(2)->create();

            $otherBranch->users()->attach($users->pluck('id'));

            expect($branch->users)->toHaveCount(0);
        });

        it('uses the branch_user pivot table', function () {
            $branch = Branch::factory()->make();

            expect($branch->users()->getTable())->toBe('branch_user');
        });
    });
});

// ============================================================
// ATTRIBUTES / CASTS
// ============================================================

describe('Branch Attributes', function () {

    it('uses ulid as primary key', function () {
        $branch = Branch::factory()->create();

        expect($branch->id)
            ->toBeString()
            ->toHaveLength(26)
            ->toMatch('/^[0-9a-hjkmnp-tv-z]{26}$/');
    });

    it('casts created_at and updated_at as Carbon', function () {
        $branch = Branch::factory()->create();

        expect($branch->created_at)->toBeInstanceOf(Illuminate\Support\Carbon::class);
        expect($branch->updated_at)->toBeInstanceOf(Illuminate\Support\Carbon::class);
    });

    it('allows nullable phone', function () {
        $branch = Branch::factory()->create(['phone' => null]);

        expect($branch->phone)->toBeNull();
    });

    it('allows nullable whatsapp', function () {
        $branch = Branch::factory()->create(['whatsapp' => null]);

        expect($branch->whatsapp)->toBeNull();
    });

    it('allows nullable address', function () {
        $branch = Branch::factory()->create(['address' => null]);

        expect($branch->address)->toBeNull();
    });

    it('enforces unique name at database level', function () {
        Branch::factory()->create(['name' => 'Main Branch']);

        expect(fn () => Branch::factory()->create(['name' => 'Main Branch']))
            ->toThrow(Illuminate\Database\UniqueConstraintViolationException::class);
    });
});

// ============================================================
// METHODS
// ============================================================

// describe('Branch::stockFor()', function () {
//     it('returns the product stock for a given product item', function () {
//         $branch = Branch::factory()->create();
//         $productItem = ProductItem::factory()->create();
//         $stock = ProductStock::factory()->create([
//             'branch_id' => $branch->id,
//             'product_item_id' => $productItem->id,
//         ]);

//         $result = $branch->stockFor($productItem);

//         expect($result)
//             ->toBeInstanceOf(ProductStock::class)
//             ->id->toBe($stock->id);
//     });

//     it('returns null when no stock exists for the product item', function () {
//         $branch = Branch::factory()->create();
//         $productItem = ProductItem::factory()->create();

//         expect($branch->stockFor($productItem))->toBeNull();
//     });

//     it('does not return stock from a different branch', function () {
//         $branch = Branch::factory()->create();
//         $otherBranch = Branch::factory()->create();
//         $productItem = ProductItem::factory()->create();

//         ProductStock::factory()->create([
//             'branch_id' => $otherBranch->id,
//             'product_item_id' => $productItem->id,
//         ]);

//         expect($branch->stockFor($productItem))->toBeNull();
//     });
// });

describe('Branch::updateStock()', function () {
    it('creates a new product stock record when none exists', function () {
        $branch = Branch::factory()->create();
        $productItem = ProductItem::factory()->create();

        $branch->updateStock($productItem, 50);

        $this->assertDatabaseHas('product_stocks', [
            'branch_id' => $branch->id,
            'product_item_id' => $productItem->id,
            'quantity' => 50,
        ]);
    });

    it('updates quantity when product stock already exists', function () {
        $branch = Branch::factory()->create();
        $productItem = ProductItem::factory()->create();

        ProductStock::factory()->create([
            'branch_id' => $branch->id,
            'product_item_id' => $productItem->id,
            'quantity' => 10,
        ]);

        $branch->updateStock($productItem, 99);

        $this->assertDatabaseHas('product_stocks', [
            'branch_id' => $branch->id,
            'product_item_id' => $productItem->id,
            'quantity' => 99,
        ]);

        expect(
            ProductStock::where('branch_id', $branch->id)
                ->where('product_item_id', $productItem->id)
                ->count()
        )->toBe(1);
    });

    it('does not affect stock of other branches', function () {
        $branch = Branch::factory()->create();
        $otherBranch = Branch::factory()->create();
        $productItem = ProductItem::factory()->create();

        ProductStock::factory()->create([
            'branch_id' => $otherBranch->id,
            'product_item_id' => $productItem->id,
            'quantity' => 30,
        ]);

        $branch->updateStock($productItem, 100);

        $this->assertDatabaseHas('product_stocks', [
            'branch_id' => $otherBranch->id,
            'product_item_id' => $productItem->id,
            'quantity' => 30,
        ]);
    });

    it('can set stock quantity to zero', function () {
        $branch = Branch::factory()->create();
        $productItem = ProductItem::factory()->create();

        ProductStock::factory()->create([
            'branch_id' => $branch->id,
            'product_item_id' => $productItem->id,
            'quantity' => 20,
        ]);

        $branch->updateStock($productItem, 0);

        $this->assertDatabaseHas('product_stocks', [
            'branch_id' => $branch->id,
            'product_item_id' => $productItem->id,
            'quantity' => 0,
        ]);
    });

    // it('stockFor reflects updated quantity after updateStock', function () {
    //     $branch = Branch::factory()->create();
    //     $productItem = ProductItem::factory()->create();

    //     $branch->updateStock($productItem, 42);

    //     $stock = $branch->stockFor($productItem);

    //     expect($stock)
    //         ->toBeInstanceOf(ProductStock::class)
    //         ->quantity->toBe(42);
    // });
});
