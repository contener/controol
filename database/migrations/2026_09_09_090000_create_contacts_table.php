<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contacts', function (Blueprint $table) {
            $table->id();
            $table->string('nom')->nullable();
            $table->string('telephone')->nullable();
            $table->string('whatsapp')->nullable();
            $table->string('numero_normalise')->nullable()->index();
            $table->string('statut_whatsapp')->default('inconnu');
            $table->string('statut_commercial')->default('nouveau');
            $table->string('email')->nullable();
            $table->string('ville')->nullable();
            $table->string('entreprise')->nullable();
            $table->string('categorie')->nullable();
            $table->string('source')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('dernier_contact_a')->nullable();
            $table->foreignId('utilisateur_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('lie_a')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('import_id')->nullable()->constrained('contact_imports')->nullOnDelete();
            $table->timestamps();

            $table->index(['statut_whatsapp', 'utilisateur_id']);
            $table->index('statut_commercial');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contacts');
    }
};
