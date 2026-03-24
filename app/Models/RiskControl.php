<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RiskControl extends Model
{
    use HasFactory;

    protected $table = 'risk_controls';

    protected $fillable = [
        'risk_id',
        'control_measure',
        'effectiveness',
        'strategy',
        'action',
        'owner',
        'deadline',
        'status'
    ];

    public function risk()
    {
        return $this->belongsTo(Risk::class, 'risk_id');
    }
}
