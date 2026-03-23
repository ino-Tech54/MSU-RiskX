<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
        'owner', 'resolved_by', 'method', 'status', 'department_id'
    ];
}
