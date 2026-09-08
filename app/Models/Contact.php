<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Contact extends Model
{
    public const STATUT_WHATSAPP_INCONNU = 'inconnu';

    public const STATUT_WHATSAPP_SUR_WHATSAPP = 'sur_whatsapp';

    public const STATUT_WHATSAPP_PAS_SUR_WHATSAPP = 'pas_sur_whatsapp';

    public const STATUT_WHATSAPP_NUMERO_INVALIDE = 'numero_invalide';

    public const STATUTS_WHATSAPP = [
        self::STATUT_WHATSAPP_INCONNU,
        self::STATUT_WHATSAPP_SUR_WHATSAPP,
        self::STATUT_WHATSAPP_PAS_SUR_WHATSAPP,
        self::STATUT_WHATSAPP_NUMERO_INVALIDE,
    ];

    public const STATUT_COMMERCIAL_NOUVEAU = 'nouveau';

    public const STATUT_COMMERCIAL_A_CONTACTER = 'a_contacter';

    public const STATUT_COMMERCIAL_CONTACTE = 'contacte';

    public const STATUT_COMMERCIAL_A_REPONDU = 'a_repondu';

    public const STATUT_COMMERCIAL_INTERESSE = 'interesse';

    public const STATUT_COMMERCIAL_PAS_INTERESSE = 'pas_interesse';

    public const STATUT_COMMERCIAL_COMPTE_CREE = 'compte_cree';

    public const STATUT_COMMERCIAL_CONVERTI = 'converti';

    public const STATUT_COMMERCIAL_NE_PLUS_CONTACTER = 'ne_plus_contacter';

    public const STATUT_COMMERCIAL_ARCHIVE = 'archive';

    // Ordre croissant d'avancement — utilisé pour ne jamais rétrograder un statut lors du
    // rapprochement automatique (voir UserObserver).
    public const STATUTS_COMMERCIAUX = [
        self::STATUT_COMMERCIAL_NOUVEAU,
        self::STATUT_COMMERCIAL_A_CONTACTER,
        self::STATUT_COMMERCIAL_CONTACTE,
        self::STATUT_COMMERCIAL_A_REPONDU,
        self::STATUT_COMMERCIAL_INTERESSE,
        self::STATUT_COMMERCIAL_PAS_INTERESSE,
        self::STATUT_COMMERCIAL_COMPTE_CREE,
        self::STATUT_COMMERCIAL_CONVERTI,
        self::STATUT_COMMERCIAL_NE_PLUS_CONTACTER,
        self::STATUT_COMMERCIAL_ARCHIVE,
    ];

    protected $fillable = [
        'nom',
        'prenom',
        'nom_famille',
        'telephone',
        'whatsapp',
        'telephones_secondaires',
        'numero_normalise',
        'statut_whatsapp',
        'statut_commercial',
        'email',
        'emails_secondaires',
        'ville',
        'entreprise',
        'poste',
        'adresse',
        'region',
        'pays',
        'code_postal',
        'date_anniversaire',
        'categorie',
        'source',
        'notes',
        'dernier_contact_a',
        'utilisateur_id',
        'lie_a',
        'created_by',
        'import_id',
    ];

    protected $appends = ['compte_lie'];

    protected function casts(): array
    {
        return [
            'dernier_contact_a' => 'datetime',
            'lie_a' => 'datetime',
            'date_anniversaire' => 'date',
            'telephones_secondaires' => 'array',
            'emails_secondaires' => 'array',
        ];
    }

    public function utilisateur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'utilisateur_id');
    }

    public function import(): BelongsTo
    {
        return $this->belongsTo(ContactImport::class, 'import_id');
    }

    public function createur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function whatsappLogs(): HasMany
    {
        return $this->hasMany(WhatsappContactLog::class)->orderByDesc('ouvert_a');
    }

    protected function compteLie(): Attribute
    {
        return Attribute::get(fn () => $this->utilisateur_id !== null);
    }

    public static function libellesStatutWhatsapp(): array
    {
        return [
            self::STATUT_WHATSAPP_INCONNU => 'À vérifier',
            self::STATUT_WHATSAPP_SUR_WHATSAPP => 'Sur WhatsApp',
            self::STATUT_WHATSAPP_PAS_SUR_WHATSAPP => 'Pas sur WhatsApp',
            self::STATUT_WHATSAPP_NUMERO_INVALIDE => 'Numéro invalide',
        ];
    }

    public static function libellesStatutCommercial(): array
    {
        return [
            self::STATUT_COMMERCIAL_NOUVEAU => 'Nouveau',
            self::STATUT_COMMERCIAL_A_CONTACTER => 'À contacter',
            self::STATUT_COMMERCIAL_CONTACTE => 'Contacté',
            self::STATUT_COMMERCIAL_A_REPONDU => 'A répondu',
            self::STATUT_COMMERCIAL_INTERESSE => 'Intéressé',
            self::STATUT_COMMERCIAL_PAS_INTERESSE => 'Pas intéressé',
            self::STATUT_COMMERCIAL_COMPTE_CREE => 'Compte créé',
            self::STATUT_COMMERCIAL_CONVERTI => 'Converti',
            self::STATUT_COMMERCIAL_NE_PLUS_CONTACTER => 'Ne plus contacter',
            self::STATUT_COMMERCIAL_ARCHIVE => 'Archivé',
        ];
    }
}
