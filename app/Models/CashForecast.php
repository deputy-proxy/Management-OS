<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CashForecast extends Model
{
    protected $fillable = ['name', 'created_by'];

    /** @return HasMany<CashForecastScenario, $this> */
    public function scenarios(): HasMany
    {
        return $this->hasMany(CashForecastScenario::class)->orderBy('scenario_name')->orderBy('version');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
