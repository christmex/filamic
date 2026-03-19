<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Traits\BelongsToBranch;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $order_id
 * @property string $product_item_id
 * @property string $branch_id
 * @property numeric $old_purchase_price
 * @property numeric $old_sale_price
 * @property numeric $new_purchase_price
 * @property numeric $new_sale_price
 * @property int $old_stock
 * @property int $qty
 * @property int $discount_percentage
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Branch $branch
 * @property-read ProductItem $item
 * @property-read Order $order
 *
 * @method static \Database\Factories\OrderItemFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderItem newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderItem newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderItem query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderItem whereBranchId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderItem whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderItem whereDiscountPercentage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderItem whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderItem whereNewPurchasePrice($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderItem whereNewSalePrice($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderItem whereOldPurchasePrice($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderItem whereOldSalePrice($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderItem whereOldStock($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderItem whereOrderId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderItem whereProductItemId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderItem whereQty($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|OrderItem whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class OrderItem extends Model
{
    use BelongsToBranch;

    /** @use HasFactory<\Database\Factories\OrderItemFactory> */
    use HasFactory;

    use HasUlids;

    protected $guarded = ['id'];

    protected function casts()
    {
        return [
            'old_purchase_price' => 'decimal:2',
            'old_sale_price' => 'decimal:2',
            'new_purchase_price' => 'decimal:2',
            'new_sale_price' => 'decimal:2',
            'old_stock' => 'integer',
            'qty' => 'integer',
            'discount_percentage' => 'integer',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(ProductItem::class, 'product_item_id');
    }
}
