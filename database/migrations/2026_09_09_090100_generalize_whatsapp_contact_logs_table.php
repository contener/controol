<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_contact_logs', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable()->change();
            $table->foreignId('contact_id')->nullable()->after('user_id')->constrained('contacts')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_contact_logs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('contact_id');
            $table->unsignedBigInteger('user_id')->nullable(false)->change();
        });
    }
};
