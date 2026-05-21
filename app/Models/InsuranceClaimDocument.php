<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class InsuranceClaimDocument extends Model
{
    use HasFactory;

    protected $table = 'insurance_claim_documents';
    protected $primaryKey = 'document_id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'document_id',
        'claim_id',
        'document_type',
        'file_name',
        'file_path',
        'mime_type',
        'file_size',
        'uploaded_by',
        'description',
    ];

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function ($model) {
            if (empty($model->document_id)) {
                $model->document_id = (string) Str::uuid();
            }
        });
    }

    public function claim()
    {
        return $this->belongsTo(InsuranceClaim::class, 'claim_id', 'claim_id');
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by', 'user_id');
    }
}
