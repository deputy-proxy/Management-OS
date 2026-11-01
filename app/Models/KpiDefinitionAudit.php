<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class KpiDefinitionAudit extends Model
{
    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('KPI definition audit records are immutable.'));
        static::deleting(fn () => throw new LogicException('KPI definition audit records are immutable.'));
    }

    public $timestamps = false;

    protected $fillable = [
        'kpi_definition_id',
        'actor_id',
        'event',
        'effective_version',
        'change_summary',
        'changes',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'changes' => 'array',
            'created_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<KpiDefinition, $this> */
    public function definition(): BelongsTo
    {
        return $this->belongsTo(KpiDefinition::class, 'kpi_definition_id');
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
