<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('iss_issue_types', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 36)->unique();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('code', 50);
            $table->string('name', 255);
            $table->string('category', 100)->nullable();
            $table->boolean('requires_return')->default(true);
            $table->boolean('is_active')->default(true);
            $table->json('field_definitions')->nullable();
            $table->boolean('requires_signature')->default(false);
            $table->timestamps();

            $table->unique(['team_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('iss_issue_types');
    }
};
