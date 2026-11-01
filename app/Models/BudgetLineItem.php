<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class BudgetLineItem extends Model
{
    protected $fillable = ['budget_version_id', 'code', 'description', 'amount', 'dimensions', 'position'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'dimensions' => 'array', 'position' => 'integer'];
    }

    protected static function booted(): void
    {
        $assertDraft = static function (self $item): void {
            if ($item->version()->first()?->status !== BudgetVersion::STATUS_DRAFT) {
                throw new LogicException('Line items can only be changed while their budget version is a draft.');
            }
        };
        static::saving($assertDraft);
        static::deleting($assertDraft);
    }

    /** @return BelongsTo<BudgetVersion, $this> */
    public function version(): BelongsTo
    {
        return $this->belongsTo(BudgetVersion::class, 'budget_version_id');
    }
}
