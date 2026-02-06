<?php

namespace Platform\Issuance\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MigrateFromHcm extends Command
{
    protected $signature = 'issuance:migrate-from-hcm {--dry-run : Nur anzeigen, was transferiert würde}';
    protected $description = 'Transferiert Ausgaben-Daten von den alten HCM-Tabellen in die neuen Issuance-Tabellen';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        if (!Schema::hasTable('hcm_employee_issue_types') || !Schema::hasTable('hcm_employee_issues')) {
            $this->error('Die alten HCM-Tabellen (hcm_employee_issue_types, hcm_employee_issues) existieren nicht.');
            return self::FAILURE;
        }

        if (!Schema::hasTable('iss_issue_types') || !Schema::hasTable('iss_issues')) {
            $this->error('Die neuen Issuance-Tabellen (iss_issue_types, iss_issues) existieren nicht. Bitte zuerst php artisan migrate ausführen.');
            return self::FAILURE;
        }

        // Zählen
        $typeCount = DB::table('hcm_employee_issue_types')->count();
        $issueCount = DB::table('hcm_employee_issues')->count();

        $this->info("Gefunden: {$typeCount} Ausgabe-Typen, {$issueCount} Ausgaben");

        if ($typeCount === 0 && $issueCount === 0) {
            $this->info('Keine Daten zum Transferieren gefunden.');
            return self::SUCCESS;
        }

        if ($dryRun) {
            $this->warn('[DRY-RUN] Keine Daten werden transferiert.');
            return self::SUCCESS;
        }

        if (!$this->confirm('Möchtest du die Daten jetzt transferieren?')) {
            $this->info('Abgebrochen.');
            return self::SUCCESS;
        }

        DB::beginTransaction();

        try {
            // ID-Mapping für Issue-Types (alte ID -> neue ID)
            $typeIdMap = [];

            // 1. Issue-Types transferieren
            $this->info('Transferiere Ausgabe-Typen...');
            $types = DB::table('hcm_employee_issue_types')->get();

            foreach ($types as $type) {
                // Prüfen ob schon existiert (anhand team_id + code)
                $existing = DB::table('iss_issue_types')
                    ->where('team_id', $type->team_id)
                    ->where('code', $type->code)
                    ->first();

                if ($existing) {
                    $typeIdMap[$type->id] = $existing->id;
                    $this->line("  Überspringe Typ '{$type->code}' (existiert bereits als ID {$existing->id})");
                    continue;
                }

                $newId = DB::table('iss_issue_types')->insertGetId([
                    'uuid' => $type->uuid,
                    'team_id' => $type->team_id,
                    'created_by_user_id' => $type->created_by_user_id,
                    'code' => $type->code,
                    'name' => $type->name,
                    'category' => $type->category,
                    'requires_return' => $type->requires_return,
                    'is_active' => $type->is_active,
                    'field_definitions' => $type->field_definitions,
                    'requires_signature' => $type->requires_signature ?? false,
                    'created_at' => $type->created_at,
                    'updated_at' => $type->updated_at,
                ]);

                $typeIdMap[$type->id] = $newId;
                $this->line("  Typ '{$type->code}' transferiert (alt: {$type->id} -> neu: {$newId})");
            }

            // 2. Issues transferieren
            $this->info('Transferiere Ausgaben...');
            $issues = DB::table('hcm_employee_issues')->get();
            $transferred = 0;
            $skipped = 0;

            foreach ($issues as $issue) {
                // Prüfen ob Issue-Type gemappt wurde
                if (!isset($typeIdMap[$issue->issue_type_id])) {
                    $this->warn("  Überspringe Ausgabe ID {$issue->id}: Issue-Type ID {$issue->issue_type_id} nicht gefunden im Mapping");
                    $skipped++;
                    continue;
                }

                // Prüfen ob schon existiert (anhand uuid)
                $existing = DB::table('iss_issues')
                    ->where('uuid', $issue->uuid)
                    ->first();

                if ($existing) {
                    $this->line("  Überspringe Ausgabe UUID '{$issue->uuid}' (existiert bereits)");
                    $skipped++;
                    continue;
                }

                DB::table('iss_issues')->insert([
                    'uuid' => $issue->uuid,
                    'team_id' => $issue->team_id,
                    'created_by_user_id' => $issue->created_by_user_id,
                    'recipient_type' => 'hcm_employee',
                    'recipient_id' => $issue->employee_id,
                    'issue_type_id' => $typeIdMap[$issue->issue_type_id],
                    'title' => $issue->title ?? null,
                    'description' => $issue->description ?? null,
                    'identifier' => $issue->identifier,
                    'status' => $issue->status,
                    'issued_at' => $issue->issued_at,
                    'returned_at' => $issue->returned_at,
                    'metadata' => $issue->metadata,
                    'notes' => $issue->notes,
                    'signature_data' => $issue->signature_data ?? null,
                    'signed_at' => $issue->signed_at ?? null,
                    'created_at' => $issue->created_at,
                    'updated_at' => $issue->updated_at,
                ]);

                $transferred++;
            }

            DB::commit();

            $this->newLine();
            $this->info("Transfer abgeschlossen!");
            $this->info("  Ausgabe-Typen: " . count($typeIdMap) . " transferiert");
            $this->info("  Ausgaben: {$transferred} transferiert, {$skipped} übersprungen");
            $this->newLine();
            $this->warn("Die alten Tabellen (hcm_employee_issue_types, hcm_employee_issues) wurden NICHT gelöscht.");
            $this->warn("Du kannst sie manuell löschen, wenn alles korrekt transferiert wurde.");

            return self::SUCCESS;
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error("Fehler beim Transfer: " . $e->getMessage());
            $this->error($e->getTraceAsString());
            return self::FAILURE;
        }
    }
}
