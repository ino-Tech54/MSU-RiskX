<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Department extends Model
{
    use HasFactory;

    protected $table = 'departments';
    protected $primaryKey = 'department_id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'department_id', 'department_name', 'department_code', 'manager_id', 'type'
    ];

    public function users()
    {
        return $this->hasMany(User::class, 'department_id', 'department_id');
    }

    public function subDepartments()
    {
        return $this->hasMany(SubDepartment::class, 'department_id', 'department_id')
                    ->orderBy('name');
    }
}
