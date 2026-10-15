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
        'tipo_pessoa',
        'documento',
        'razao_social',
        'aceita_email',
        'aceita_whatsapp',
        'consentimento_em',
        'consentimento_ip',
        'email_verificado_em',
    ];

    protected $hidden = ['password'];

    protected $casts = [
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'last_login' => 'datetime',
        'aceita_email' => 'boolean',
        'aceita_whatsapp' => 'boolean',
        'consentimento_em' => 'datetime',
        'email_verificado_em' => 'datetime',
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

    /**
     * Cadastro aprovado (pendente/recusado → ativo), por qualquer caminho do ERP
     * ("Aprovar e vincular" ou "Criar cliente e aprovar"): e-mail de boas-vindas.
     * Reativar um bloqueado não reenvia.
     */
    protected static function booted()
    {
        static::saved(function (EcommerceUser $u) {
            if (!$u->wasChanged('status') || $u->status !== 'active') {
                return;
            }
            if (!in_array($u->getOriginal('status'), ['pending', 'rejected', null, ''], true)) {
                return;
            }
            \App\Services\Notificacao::registrar('cadastro_aprovado', [
                'ecommerce_user_id' => $u->id,
                'cliente_id' => $u->erp_cliente_id,
                'email' => $u->email,
                'telefone' => $u->phone,
            ], ['nome' => $u->name]);
        });
    }
}
