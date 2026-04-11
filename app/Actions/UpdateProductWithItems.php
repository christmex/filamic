<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\GradeEnum;
use App\Enums\LevelEnum;
use App\Models\Branch;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductItem;
use App\Models\ProductVariationOption;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

class UpdateProductWithItems
{
    use AsAction;

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Product $product, array $data): Product
    {
        Validator::make($data, [
            'supplier_id' => ['required', 'exists:suppliers,id'],
            'product_category_id' => ['required', 'exists:product_categories,id'],
            'level' => ['nullable', Rule::enum(LevelEnum::class)],
            'grade' => ['nullable', Rule::enum(GradeEnum::class)],
            'name' => ['required', 'string'],
            'description' => ['nullable', 'string'],
            'purchase_price' => ['required', 'numeric', 'min:0'],
            'sale_price' => ['required', 'numeric', 'min:0'],
        ])->validate();

        return DB::transaction(function () use ($product, $data): Product {
            $product->update([
                'supplier_id' => $data['supplier_id'],
                'product_category_id' => $data['product_category_id'],
                'level' => $data['level'] ?? null,
                'grade' => $data['grade'] ?? null,
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
            ]);

            $category = ProductCategory::findOrFail($data['product_category_id']);

            $variations = collect($data['variations'] ?? [])
                ->filter(fn (array $row): bool => ! empty($row['selected_options']));

            $product->load('items');

            foreach ($product->items as $item) {
                if ($item->movements()->exists()) {
                    throw ValidationException::withMessages([
                        'items' => "Item [{$item->sku}] has recorded stock movements and cannot be replaced.",
                    ]);
                }

                $item->stocks()->delete();
                $item->variationOptions()->detach();
                $item->delete();
            }

            if ($variations->isNotEmpty()) {
                $this->createVariantItems($product, $category, $variations->all(), $data);
            } else {
                $this->createSingleItem($product, $category, $data);
            }

            return $product->refresh();
        });
    }

    /**
     * @param  array<string, array<string, mixed>>  $variations
     * @param  array<string, mixed>  $data
     */
    private function createVariantItems(Product $product, ProductCategory $category, array $variations, array $data): void
    {
        $optionArrays = [];
        foreach ($variations as $row) {
            $optionArrays[] = $row['selected_options'];
        }

        $allOptionIds = collect($optionArrays)->flatten()->unique()->values()->all();
        $optionNameMap = ProductVariationOption::whereIn('id', $allOptionIds)
            ->pluck('name', 'id');

        $combinations = $this->cartesianProduct($optionArrays);

        foreach ($combinations as $optionIds) {
            $optionNames = collect($optionIds)
                ->map(fn (string $id): string => $optionNameMap->get($id, ''))
                ->all();

            $sku = Product::generateSku($category->code, $product->name, $optionNames);

            /** @var ProductItem $productItem */
            $productItem = $product->items()->create([
                'sku' => $sku,
                'purchase_price' => $data['purchase_price'],
                'sale_price' => $data['sale_price'],
                'is_active' => true,
            ]);

            $productItem->variationOptions()->attach($optionIds);
            $this->createStock($productItem);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function createSingleItem(Product $product, ProductCategory $category, array $data): void
    {
        $sku = Product::generateSku($category->code, $product->name);

        /** @var ProductItem $productItem */
        $productItem = $product->items()->create([
            'sku' => $sku,
            'purchase_price' => $data['purchase_price'],
            'sale_price' => $data['sale_price'],
            'is_active' => true,
        ]);

        $this->createStock($productItem);
    }

    private function createStock(ProductItem $productItem): void
    {
        $branches = Branch::all();

        foreach ($branches as $branch) {
            $productItem->stocks()->create([
                'branch_id' => $branch->getKey(),
                'quantity' => 0,
            ]);
        }
    }

    /**
     * @param  array<int, array<int, string>>  $arrays
     * @return array<int, array<int, string>>
     */
    private function cartesianProduct(array $arrays): array
    {
        $result = [[]];

        foreach ($arrays as $options) {
            $append = [];

            foreach ($result as $combination) {
                foreach ($options as $option) {
                    $append[] = [...$combination, $option];
                }
            }

            $result = $append;
        }

        return $result;
    }
}
