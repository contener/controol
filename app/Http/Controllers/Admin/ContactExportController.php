<?php

namespace App\Http\Controllers\Admin;

use App\Exports\ContactsExport;
use App\Http\Controllers\Controller;
use App\Models\AdminAudit;
use App\Models\Contact;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ContactExportController extends Controller
{
    public function export(Request $request): BinaryFileResponse
    {
        $contacts = Contact::query()
            ->when($request->string('recherche')->toString(), function ($query, $recherche) {
                $query->where(function ($q) use ($recherche) {
                    $q->where('nom', 'like', "%{$recherche}%")
                        ->orWhere('telephone', 'like', "%{$recherche}%")
                        ->orWhere('whatsapp', 'like', "%{$recherche}%")
                        ->orWhere('email', 'like', "%{$recherche}%");
                });
            })
            ->when($request->string('statutWhatsapp')->toString(), fn ($q, $v) => $q->where('statut_whatsapp', $v))
            ->when($request->string('statutCommercial')->toString(), fn ($q, $v) => $q->where('statut_commercial', $v))
            ->when($request->filled('compteLie'), fn ($q) => $request->boolean('compteLie') ? $q->whereNotNull('utilisateur_id') : $q->whereNull('utilisateur_id'))
            ->when($request->filled('ids'), fn ($q) => $q->whereIn('id', explode(',', (string) $request->string('ids'))))
            ->orderByDesc('created_at')
            ->get();

        AdminAudit::create([
            'admin_id' => $request->user()->id,
            'action' => 'contacts_exportes',
            'resource' => 'contacts',
            'resource_id' => null,
            'ancienne_valeur' => null,
            'nouvelle_valeur' => ['filtres' => $request->only(['recherche', 'statutWhatsapp', 'statutCommercial', 'compteLie']), 'nombre' => $contacts->count()],
            'ip_address' => $request->ip(),
        ]);

        return Excel::download(new ContactsExport($contacts), 'contacts-controool-'.now()->format('Y-m-d-His').'.xlsx');
    }
}
