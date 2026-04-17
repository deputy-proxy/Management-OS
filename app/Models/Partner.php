<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Relaticle\Comments\Concerns\HasComments;
use Relaticle\Comments\Contracts\Commentable;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class Partner extends Model implements Commentable
{
    use LogsActivity;
    use HasFactory;
    use SoftDeletes;
    use HasComments;

    /** @var array<int, string> */
    protected $fillable = [
        'name', 'site', 'contact_person_name', 'contact_person_email', 'contact_person_phone', 'collaborations'
    ];

    protected function casts(): array
    {
        return [
            'collaborations' => 'array',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logAll();
    }

}