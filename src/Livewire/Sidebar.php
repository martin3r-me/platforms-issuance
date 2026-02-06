<?php

namespace Platform\Issuance\Livewire;

use Livewire\Component;
use Livewire\Attributes\Computed;
use Platform\Issuance\Models\IssIssue;
use Platform\Issuance\Models\IssIssueType;

class Sidebar extends Component
{
    #[Computed]
    public function stats()
    {
        $teamId = auth()->user()->currentTeam->id;

        $totalIssues = IssIssue::where('team_id', $teamId)->count();
        $issuedCount = IssIssue::where('team_id', $teamId)->whereNotNull('issued_at')->whereNull('returned_at')->count();
        $returnedCount = IssIssue::where('team_id', $teamId)->whereNotNull('returned_at')->count();
        $typeCount = IssIssueType::where('team_id', $teamId)->count();

        return [
            'total_issues' => $totalIssues,
            'issued' => $issuedCount,
            'returned' => $returnedCount,
            'total_types' => $typeCount,
        ];
    }

    public function render()
    {
        return view('issuance::livewire.sidebar');
    }
}
