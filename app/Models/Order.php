<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Traits\BelongsToSchoolYear;
use App\Models\Traits\BelongsToSupplier;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $supplier_id
 * @property string $school_year_id
 * @property \Illuminate\Support\Carbon $ordered_at
 * @property int $discount_percentage
 * @property int $total_items
 * @property numeric $grand_total
 * @property string $coordinator
 * @property string $person_in_charge
 * @property string|null $notes
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, OrderItem> $items
 * @property-read int|null $items_count
 * @property-read SchoolYear $schoolYear
 * @property-read Supplier $supplier
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order activeYear()
 * @method static \Database\Factories\OrderFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereCoordinator($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereDiscountPercentage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereGrandTotal($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereOrderedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order wherePersonInCharge($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereSchoolYearId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereSupplierId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereTotalItems($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Order whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class Order extends Model
{
    use BelongsToSchoolYear;
    use BelongsToSupplier;

    /** @use HasFactory<\Database\Factories\OrderFactory> */
    use HasFactory;

    use HasUlids;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'ordered_at' => 'date',
            'grand_total' => 'decimal:2',
            'total_items' => 'integer',
            'discount_percentage' => 'integer',
        ];
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class, 'order_id');
    }
}
