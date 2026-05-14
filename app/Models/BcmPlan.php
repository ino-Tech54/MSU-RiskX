<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BcmPlan extends Model
{
    use HasFactory;

    protected $table = 'bcm_plans';
    protected $primaryKey = 'plan_id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'plan_id',
        'plan_reference',
        'plan_name',
        'scope_type',
        'critical_process',
        'dependencies',
        'department_id',
        'risk_id',
        'she_event_id',
        'rto_hours',
        'rpo_hours',
        'plan_status',
        'owner_id',
        'readiness_score',
        'scenario_test_notes',
        'last_tested',
        'next_test_date',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'last_tested' => 'date',
        'next_test_date' => 'date',
        'approved_at' => 'datetime',
        'readiness_score' => 'integer',
    ];

    public function risk()
    {
        return $this->belongsTo(Risk::class, 'risk_id', 'id');
    }

    public function sheEvent()
    {
        return $this->belongsTo(SheEvent::class, 'she_event_id', 'id');
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id', 'user_id');
    }
}
