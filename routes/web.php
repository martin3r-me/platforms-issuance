<?php

use Illuminate\Support\Facades\Route;

// Root -> direkt auf Ausgaben-Übersicht
Route::get('/', fn () => redirect()->route('issuance.issues.index'))->name('issuance.index');

// Ausgaben (global)
Route::get('/issues', \Platform\Issuance\Livewire\Issues\Index::class)->name('issuance.issues.index');
Route::get('/issues/{issue}/pdf', \Platform\Issuance\Http\Controllers\IssuePdfController::class)->name('issuance.issues.pdf');

// Ausgabe-Typen
Route::get('/issue-types', \Platform\Issuance\Livewire\IssueTypes\Index::class)->name('issuance.issue-types.index');

// Mitarbeiter-spezifische Ausgaben
Route::get('/employees/{employee}/issues', \Platform\Issuance\Livewire\Employee\IssuesIndex::class)->name('issuance.employees.issues.index');
