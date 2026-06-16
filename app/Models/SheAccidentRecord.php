<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class SheAccidentRecord extends Model
{
    protected $table = 'she_accident_records';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'iod_number',
        'name_of_injured',
        'day_of_week',
        'date_of_injury',
        'time_of_injury',
        'age',
        'designation',
        'employment_status',
        'nssa_claim_number',
        'description_of_events',
        'department',
        'department_id',
        'sub_department_id',
        'manager_supervisor',
        'source_of_injury',
        'location_work_area',
        'part_of_body_injured',
        'nature_of_injury',
        'days_lost',
        'medical_treatment',
        'corrective_action',
    ];

    protected static function booted(): void
    {
        static::creating(function ($model) {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
        });
    }
}
