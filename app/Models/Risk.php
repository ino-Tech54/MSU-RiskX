<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Department;
use App\Models\SubDepartment;

class Risk extends Model
{
    use HasFactory;

    protected $table = 'risks';
    protected $primaryKey = 'id';

    protected $fillable = [
        'sn', 'date_reviewed', 'process_objective', 'risk_description', 'causes',
        'consequence', 'category', 'kra_at_risk', 'inherent_likelihood', 
        'inherent_consequence', 'inherent_risk_score', 'existing_controls', 
        'control_effectiveness', 'residual_likelihood', 'residual_consequence', 
        'residual_risk_score', 'mitigation_strategy', 'action_treatment', 
        'owner', 'resolved_by', 'method', 'status', 'department_id', 'sub_department_id',
        'likelihood_justification', 'consequence_justification',
        'approval_status', 'approved_by', 'approved_at', 'rejection_reason'
    ];

    public function controls()
    {
        return $this->hasMany(RiskControl::class, 'risk_id');
    }

    public function department()
    {
        return $this->belongsTo(Department::class, 'department_id', 'department_id');
    }

    public function subDepartment()
    {
        return $this->belongsTo(SubDepartment::class, 'sub_department_id', 'id');
    }
}
