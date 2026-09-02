<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBoutique;
use Illuminate\Database\Eloquent\Model;

class CompteurFacture extends Model
{
    use BelongsToBoutique;

    protected $table = 'compteurs_facture';

    protected $fillable = [
        'boutique_id',
        'annee',
        'dernier_numero',
    ];
}
