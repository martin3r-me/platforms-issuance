<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('iss_issues', function (Blueprint $table) {
            $table->id();
            $table->string('uuid', 36)->unique();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->nullableMorphs('recipient');
            $table->foreignId('issue_type_id')->constrained('iss_issue_types')->cascadeOnDelete();
            $table->string('title', 255)->nullable();
            $table->text('description')->nullable();
            $table->string('identifier', 100)->nullable();
            $table->string('status', 30)->default('issued');
            $table->date('issued_at')->nullable();
            $table->date('returned_at')->nullable();
            $table->json('metadata')->nullable();
            $table->text('notes')->nullable();
            $table->text('signature_data')->nullable();
            $table->timestamp('signed_at')->nullable();
            $table->timestamps();

            $table->index(['team_id', 'recipient_type', 'recipient_id'], 'iss_issues_team_recipient_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('iss_issues');
    }
};
