<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EcommerceUser extends Model
{
    protected $table = 'ecommerce_users';

    protected $fillable = [
        'erp_cliente_id',
        'name',
        'email',
        'phone',
        'password',
        'status',
        'admin_notes',
        'approved_at',
        'approved_by',
        'rejected_at',
        'rejection_reason',
        'last_login',
    ];

    protected $hidden = ['password'];

    protected $casts = [
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'last_login' => 'datetime',
    ];

    public function cliente()
    {
        return $this->belongsTo(Cliente::class, 'erp_cliente_id');
    }

    public function aprovador()
    {
        return $this->belongsTo(Usuario::class, 'approved_by');
    }

    public static function statusLabels(): array
    {
        return [
            'pending' => 'Pendente',
            'active' => 'Ativo',
            'rejected' => 'Reprovado',
            'blocked' => 'Bloqueado',
            'inactive' => 'Inativo',
        ];
    }
}
