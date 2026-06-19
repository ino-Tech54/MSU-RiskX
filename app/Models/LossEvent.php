<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LossEvent extends Model
{
    use HasFactory;

    protected $table = 'loss_events';
    protected $primaryKey = 'loss_id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'loss_id',
        'loss_reference',
        'record_type',
        'risk_id',
        'she_event_id',
        'department_id',
        'reported_by',
        'loss_date',
        'event_title',
        'description',
        'financial_impact',
        'non_financial_impact',
        'root_cause',
        'status',
        'evidence',
        'case_number',
        'priority_level',
        'complainant',
        'accused_person',
        'time_of_occurrence',
        'case_against',
        'police_ref',
        'case_category',
        'location',
        'property_involved',
        'estimate_value',
        'corrective_action',
        'corrective_action_recommendation',
        'corrective_action_taken',
        'action_owner',
        'quarter',
        'police_report_file',
        'estimated_loss_value',
        'estimated_recovery_value',
        'misconduct_type',
        'case_prefix',
    ];

    protected $casts = [
        'loss_date' => 'datetime',
        'financial_impact' => 'decimal:2',
    ];

    public function risk()
    {
        return $this->belongsTo(Risk::class, 'risk_id', 'id');
    }

    public function sheEvent()
    {
        return $this->belongsTo(SheEvent::class, 'she_event_id', 'id');
    }
}
