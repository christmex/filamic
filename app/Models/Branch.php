<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

/**
 * @property string $id
 * @property int|null $legacy_old_id
 * @property string $name
 * @property string|null $phone
 * @property string|null $whatsapp
 * @property string|null $address
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Classroom> $classrooms
 * @property-read int|null $classrooms_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Invoice> $invoices
 * @property-read int|null $invoices_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, ProductStock> $productStocks
 * @property-read int|null $product_stocks_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, School> $schools
 * @property-read int|null $schools_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, ProductStockMovement> $stockMovements
 * @property-read int|null $stock_movements_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Student> $students
 * @property-read int|null $students_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, User> $users
 * @property-read int|null $users_count
 *
 * @method static \Database\Factories\BranchFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Branch newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Branch newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Branch query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Branch whereAddress($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Branch whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Branch whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Branch whereLegacyOldId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Branch whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Branch wherePhone($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Branch whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Branch whereWhatsapp($value)
 *
 * @mixin \Eloquent
 */
class Branch extends Model
{
    /** @use HasFactory<\Database\Factories\BranchFactory> */
    use HasFactory;

    use HasUlids;

    protected $guarded = ['id'];

    public function schools(): HasMany
    {
        return $this->hasMany(School::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }

    public function classrooms(): HasManyThrough
    {
        return $this->hasManyThrough(Classroom::class, School::class);
    }

    public function students(): HasMany
    {
        return $this->hasMany(Student::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(ProductStockMovement::class);
    }

    public function productStocks(): HasMany
    {
        return $this->hasMany(ProductStock::class);
    }

    public function stockFor(ProductItem $productItem)
    {
        return $this->productStocks()->where('product_item_id', $productItem->getKey())->first();
    }

    public function updateStock(ProductItem $productItem, int $quantity): void
    {
        $this->productStocks()->updateOrCreate(
            [
                'product_item_id' => $productItem->getKey(),
                'branch_id' => $this->getKey(),
            ],
            ['quantity' => $quantity]
        );
    }
}
