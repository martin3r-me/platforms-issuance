<?php

namespace Platform\Issuance\Http\Controllers;

use Barryvdh\DomPDF\Facade\Pdf;
use Platform\Issuance\Models\IssIssue;

class IssuePdfController
{
    public function __invoke(IssIssue $issue)
    {
        if (!auth()->check()) {
            abort(401, 'Nicht authentifiziert');
        }

        $teamId = auth()->user()->currentTeam?->id;
        if (!$teamId || $issue->team_id !== $teamId) {
            abort(403, 'Zugriff verweigert');
        }

        $issue->load(['recipient', 'type']);

        $html = view('issuance::pdf.issue', ['issue' => $issue])->render();

        $recipientNumber = 'UNK';
        if ($issue->recipient_type === 'hcm_employee' && $issue->recipient) {
            $recipientNumber = $issue->recipient->employee_number ?? 'UNK';
        }

        $filename = sprintf(
            'Ausgabe_%s_%s_%s.pdf',
            $issue->type?->code ?? 'UNK',
            $recipientNumber,
            $issue->issued_at?->format('Y-m-d') ?? now()->format('Y-m-d')
        );

        return Pdf::loadHTML($html)
            ->setPaper('a4')
            ->download($filename);
    }
}
