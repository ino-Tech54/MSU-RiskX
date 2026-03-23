<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Permission extends Model
{
    use HasFactory;

    protected $table = 'permissions';
    protected $primaryKey = 'permission_id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'permission_id', 'entity_id', 'entity_type', 'section_name', 
        'can_view', 'can_add', 'can_edit', 'can_delete'
    ];
}
