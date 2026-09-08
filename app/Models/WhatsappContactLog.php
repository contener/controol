<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsappContactLog extends Model
{
    protected $fillable = [
        'user_id',
        'admin_id',
        'numero_whatsapp',
        'message',
        'modele_cle',
        'ouvert_a',
        'confirme_a',
    ];

    protected $appends = ['confirme'];

    protected function casts(): array
    {
        return [
            'ouvert_a' => 'datetime',
            'confirme_a' => 'datetime',
        ];
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    protected function confirme(): Attribute
    {
        return Attribute::get(fn () => $this->confirme_a !== null);
    }
}
