<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class InsuranceClaim extends Model
{
    use HasFactory;

    protected $table = 'insurance_claims';
    protected $primaryKey = 'claim_id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'claim_id',
        'claim_number',
        'date_received',
        'claim_type',
        'claim_description',
        'quotation_1',
        'quotation_2',
        'quotation_3',
        'police_report',
        'drivers_licence',
        'pictures',
        'release_form',
        'status',
        'pop',
        'department_id',
        'claimant_name',
        'claim_value',
        'notes',
        'reported_by',
    ];

    protected $casts = [
        'date_received' => 'date',
        'claim_value' => 'decimal:2',
    ];

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function ($model) {
            if (empty($model->claim_id)) {
                $model->claim_id = (string) Str::uuid();
            }
        });
    }

    public function documents()
    {
        return $this->hasMany(InsuranceClaimDocument::class, 'claim_id', 'claim_id');
    }

    public function department()
    {
        return $this->belongsTo(Department::class, 'department_id', 'department_id');
    }

    public function reporter()
    {
        return $this->belongsTo(User::class, 'reported_by', 'user_id');
    }
}
