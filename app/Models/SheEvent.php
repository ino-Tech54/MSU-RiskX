<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SheEvent extends Model
{
    use HasFactory;

    protected $table = 'she_events';
    protected $primaryKey = 'id';

    protected $fillable = [
        'action_id', 'date', 'activity_category', 'location', 'department', 
        'staff_group', 'description', 'reference_id', 'observations', 
        'recommendations', 'priority', 'owner', 'quarter', 'status', 
        'evidence', 'verification', 'comments'
    ];
}
