<?php

namespace App\Exports;

use App\Models\Contact;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class ContactsExport implements FromCollection, WithHeadings, WithMapping
{
    public function __construct(private readonly Collection $contacts) {}

    public function collection(): Collection
    {
        return $this->contacts;
    }

    public function headings(): array
    {
        return [
            'Nom', 'Téléphone', 'WhatsApp', 'Statut WhatsApp', 'Compte CONTROOL',
            'Statut commercial', 'E-mail', 'Ville', 'Entreprise', 'Source',
            "Date d'importation", 'Dernière relance',
        ];
    }

    public function map($contact): array
    {
        return [
            $contact->nom,
            $contact->telephone,
            $contact->whatsapp,
            Contact::libellesStatutWhatsapp()[$contact->statut_whatsapp] ?? $contact->statut_whatsapp,
            $contact->compte_lie ? 'Oui' : 'Non',
            Contact::libellesStatutCommercial()[$contact->statut_commercial] ?? $contact->statut_commercial,
            $contact->email,
            $contact->ville,
            $contact->entreprise,
            $contact->source,
            $contact->created_at?->format('d/m/Y'),
            $contact->dernier_contact_a?->format('d/m/Y H:i'),
        ];
    }
}
