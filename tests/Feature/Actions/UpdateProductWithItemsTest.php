<?php

declare(strict_types=1);

use App\Actions\CreateProductWithItems;
use App\Actions\UpdateProductWithItems;
use App\Enums\LevelEnum;
use App\Models\Branch;
use App\Models\ProductCategory;
use App\Models\ProductItem;
use App\Models\ProductStockMovement;
use App\Models\ProductVariation;
use App\Models\ProductVariationOption;
use App\Models\Supplier;
use Illuminate\Validation\ValidationException;

test('can update product attributes', function () {
    // Arrange
    $category = ProductCategory::factory()->create(['code' => 'BK']);
    $product = CreateProductWithItems::run([
        'supplier_id' => Supplier::factory()->create()->getKey(),
        'product_category_id' => $category->getKey(),
        'level' => LevelEnum::ELEMENTARY->value,
        'name' => 'Original',
        'purchase_price' => 25000,
        'sale_price' => 40000,
    ]);
    $newSupplier = Supplier::factory()->create();

    // Act
    $updated = UpdateProductWithItems::run($product, [
        'supplier_id' => $newSupplier->getKey(),
        'product_category_id' => $category->getKey(),
        'level' => LevelEnum::ELEMENTARY->value,
        'name' => 'Updated Name',
        'description' => 'New description',
        'purchase_price' => 30000,
        'sale_price' => 45000,
    ]);

    // Assert
    expect($updated)
        ->name->toBe('Updated Name')
        ->description->toBe('New description')
        ->supplier_id->toBe($newSupplier->getKey());
});

test('can add new variant items during update', function () {
    // Arrange
    $supplier = Supplier::factory()->create();
    $category = ProductCategory::factory()->create(['code' => 'SRG']);
    $variation = ProductVariation::factory()->create(['product_category_id' => $category->getKey(), 'name' => 'Ukuran']);
    $optionS = ProductVariationOption::factory()->create(['product_variation_id' => $variation->getKey(), 'name' => 'S']);
    $optionM = ProductVariationOption::factory()->create(['product_variation_id' => $variation->getKey(), 'name' => 'M']);

    $product = CreateProductWithItems::run([
        'supplier_id' => $supplier->getKey(),
        'product_category_id' => $category->getKey(),
        'level' => LevelEnum::ELEMENTARY->value,
        'name' => 'Kemeja',
        'purchase_price' => 50000,
        'sale_price' => 75000,
        'variations' => [
            str()->uuid()->toString() => [
                'variation_id' => $variation->getKey(),
                'variation_name' => 'Ukuran',
                'selected_options' => [$optionS->getKey()],
            ],
        ],
    ]);

    expect($product->items)->toHaveCount(1);

    // Act — update with both S and M options
    $updated = UpdateProductWithItems::run($product, [
        'supplier_id' => $supplier->getKey(),
        'product_category_id' => $category->getKey(),
        'level' => LevelEnum::ELEMENTARY->value,
        'name' => 'Kemeja',
        'purchase_price' => 50000,
        'sale_price' => 75000,
        'variations' => [
            str()->uuid()->toString() => [
                'variation_id' => $variation->getKey(),
                'variation_name' => 'Ukuran',
                'selected_options' => [$optionS->getKey(), $optionM->getKey()],
            ],
        ],
    ]);

    // Assert
    expect($updated->items)->toHaveCount(2);
});

test('removes items not present in submitted data', function () {
    // Arrange
    $supplier = Supplier::factory()->create();
    $category = ProductCategory::factory()->create(['code' => 'SRG']);
    $variation = ProductVariation::factory()->create(['product_category_id' => $category->getKey(), 'name' => 'Ukuran']);
    $optionS = ProductVariationOption::factory()->create(['product_variation_id' => $variation->getKey(), 'name' => 'S']);
    $optionM = ProductVariationOption::factory()->create(['product_variation_id' => $variation->getKey(), 'name' => 'M']);

    $product = CreateProductWithItems::run([
        'supplier_id' => $supplier->getKey(),
        'product_category_id' => $category->getKey(),
        'level' => LevelEnum::ELEMENTARY->value,
        'name' => 'Kemeja',
        'purchase_price' => 50000,
        'sale_price' => 75000,
        'variations' => [
            str()->uuid()->toString() => [
                'variation_id' => $variation->getKey(),
                'variation_name' => 'Ukuran',
                'selected_options' => [$optionS->getKey(), $optionM->getKey()],
            ],
        ],
    ]);

    expect($product->items)->toHaveCount(2);

    // Act — update with only S option
    UpdateProductWithItems::run($product, [
        'supplier_id' => $supplier->getKey(),
        'product_category_id' => $category->getKey(),
        'level' => LevelEnum::ELEMENTARY->value,
        'name' => 'Kemeja',
        'purchase_price' => 50000,
        'sale_price' => 75000,
        'variations' => [
            str()->uuid()->toString() => [
                'variation_id' => $variation->getKey(),
                'variation_name' => 'Ukuran',
                'selected_options' => [$optionS->getKey()],
            ],
        ],
    ]);

    // Assert
    expect(ProductItem::where('product_id', $product->getKey())->count())->toBe(1);
});

test('syncs variation option configurations on update', function () {
    // Arrange
    $supplier = Supplier::factory()->create();
    $category = ProductCategory::factory()->create(['code' => 'SRG']);
    $variation = ProductVariation::factory()->create(['product_category_id' => $category->getKey(), 'name' => 'Ukuran']);
    $optionS = ProductVariationOption::factory()->create(['product_variation_id' => $variation->getKey(), 'name' => 'S']);
    $optionM = ProductVariationOption::factory()->create(['product_variation_id' => $variation->getKey(), 'name' => 'M']);

    $product = CreateProductWithItems::run([
        'supplier_id' => $supplier->getKey(),
        'product_category_id' => $category->getKey(),
        'level' => LevelEnum::ELEMENTARY->value,
        'name' => 'Kemeja',
        'purchase_price' => 50000,
        'sale_price' => 75000,
        'variations' => [
            str()->uuid()->toString() => [
                'variation_id' => $variation->getKey(),
                'variation_name' => 'Ukuran',
                'selected_options' => [$optionS->getKey()],
            ],
        ],
    ]);

    // Act — change option from S to M
    $updated = UpdateProductWithItems::run($product, [
        'supplier_id' => $supplier->getKey(),
        'product_category_id' => $category->getKey(),
        'level' => LevelEnum::ELEMENTARY->value,
        'name' => 'Kemeja',
        'purchase_price' => 50000,
        'sale_price' => 75000,
        'variations' => [
            str()->uuid()->toString() => [
                'variation_id' => $variation->getKey(),
                'variation_name' => 'Ukuran',
                'selected_options' => [$optionM->getKey()],
            ],
        ],
    ]);

    // Assert
    expect($updated->items)->toHaveCount(1);

    $item = $updated->items->first();
    $item->load('variationOptions');

    expect($item->variationOptions)->toHaveCount(1);
    expect($item->variationOptions->first()->getKey())->toBe($optionM->getKey());
});

test('can update single item prices', function () {
    // Arrange
    $category = ProductCategory::factory()->create(['code' => 'BK']);
    $product = CreateProductWithItems::run([
        'supplier_id' => Supplier::factory()->create()->getKey(),
        'product_category_id' => $category->getKey(),
        'level' => LevelEnum::ELEMENTARY->value,
        'name' => 'Matematika',
        'purchase_price' => 25000,
        'sale_price' => 40000,
    ]);

    // Act
    UpdateProductWithItems::run($product, [
        'supplier_id' => $product->supplier_id,
        'product_category_id' => $product->product_category_id,
        'level' => $product->level?->value,
        'name' => $product->name,
        'purchase_price' => 30000,
        'sale_price' => 50000,
    ]);

    // Assert
    $item = $product->refresh()->items->first();
    expect($item)
        ->purchase_price->toBe('30000.00')
        ->sale_price->toBe('50000.00');
});

test('update creates a stock record per branch for each new item', function () {
    // Arrange — two branches, a product with one item
    $branches = Branch::factory()->count(2)->create();
    $category = ProductCategory::factory()->create(['code' => 'BK']);
    $product = CreateProductWithItems::run([
        'supplier_id' => Supplier::factory()->create()->getKey(),
        'product_category_id' => $category->getKey(),
        'name' => 'Buku Tulis',
        'purchase_price' => 5000,
        'sale_price' => 8000,
    ]);

    // Act — update name only (same structure, new item gets created)
    $updated = UpdateProductWithItems::run($product, [
        'supplier_id' => $product->supplier_id,
        'product_category_id' => $category->getKey(),
        'name' => 'Buku Tulis Updated',
        'purchase_price' => 5000,
        'sale_price' => 8000,
    ]);

    // Assert — new single item has one stock record per branch
    $item = $updated->items->first();
    expect($item->stocks)->toHaveCount($branches->count());

    $branchIds = $item->stocks->pluck('branch_id')->sort()->values()->all();
    expect($branchIds)->toBe($branches->pluck('id')->sort()->values()->all());
});

test('update removes old stock records when items are replaced', function () {
    // Arrange — one branch, product with two variant items (each gets one stock row)
    Branch::factory()->create();
    $category = ProductCategory::factory()->create(['code' => 'SRG']);
    $variation = ProductVariation::factory()->create(['product_category_id' => $category->getKey(), 'name' => 'Ukuran']);
    $optionS = ProductVariationOption::factory()->create(['product_variation_id' => $variation->getKey(), 'name' => 'S']);
    $optionM = ProductVariationOption::factory()->create(['product_variation_id' => $variation->getKey(), 'name' => 'M']);

    $product = CreateProductWithItems::run([
        'supplier_id' => Supplier::factory()->create()->getKey(),
        'product_category_id' => $category->getKey(),
        'name' => 'Kemeja',
        'purchase_price' => 50000,
        'sale_price' => 75000,
        'variations' => [
            str()->uuid()->toString() => [
                'variation_id' => $variation->getKey(),
                'variation_name' => 'Ukuran',
                'selected_options' => [$optionS->getKey(), $optionM->getKey()],
            ],
        ],
    ]);

    $oldItemIds = $product->items->pluck('id')->all();

    // Act — update drops to one variant
    $updated = UpdateProductWithItems::run($product, [
        'supplier_id' => $product->supplier_id,
        'product_category_id' => $category->getKey(),
        'name' => 'Kemeja',
        'purchase_price' => 50000,
        'sale_price' => 75000,
        'variations' => [
            str()->uuid()->toString() => [
                'variation_id' => $variation->getKey(),
                'variation_name' => 'Ukuran',
                'selected_options' => [$optionS->getKey()],
            ],
        ],
    ]);

    // Assert — old stocks gone, new item has fresh stock record
    expect(App\Models\ProductStock::whereIn('product_item_id', $oldItemIds)->count())->toBe(0);
    expect($updated->items)->toHaveCount(1);
    expect($updated->items->first()->stocks)->toHaveCount(1);
});

test('cannot update product when an item has recorded stock movements', function () {
    // Arrange — product with one item that already has a movement record
    $category = ProductCategory::factory()->create(['code' => 'BK']);
    $product = CreateProductWithItems::run([
        'supplier_id' => Supplier::factory()->create()->getKey(),
        'product_category_id' => $category->getKey(),
        'name' => 'Buku',
        'purchase_price' => 10000,
        'sale_price' => 15000,
    ]);

    $item = $product->items->first();
    ProductStockMovement::factory()->create([
        'product_item_id' => $item->getKey(),
        'branch_id' => Branch::factory()->create()->getKey(),
    ]);

    // Act & Assert — update must be rejected
    expect(fn () => UpdateProductWithItems::run($product, [
        'supplier_id' => $product->supplier_id,
        'product_category_id' => $category->getKey(),
        'name' => 'Buku Renamed',
        'purchase_price' => 10000,
        'sale_price' => 15000,
    ]))->toThrow(ValidationException::class);
});
