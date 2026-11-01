<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Budget extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'created_by'];

    /** @return HasMany<BudgetVersion, $this> */
    public function versions(): HasMany
    {
        return $this->hasMany(BudgetVersion::class)->orderBy('version');
    }

    /** @return HasOne<BudgetVersion, $this> */
    public function latestVersion(): HasOne
    {
        return $this->hasOne(BudgetVersion::class)->ofMany('version', 'max');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
