<?php

namespace Platform\Issuance\Livewire\Issues;

use Livewire\Component;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Platform\Issuance\Models\IssIssue;
use Platform\Issuance\Models\IssIssueType;
use Platform\Hcm\Models\HcmEmployee;
use Platform\Hcm\Models\HcmEmployer;

class Index extends Component
{

    public $search = '';
    public $filterStatus = 'all';
    public $filterType = '';
    public $filterEmployer = '';

    // Modal state
    public $showModal = false;
    public $editingIssue = null;

    // Form fields
    public $modal_employer_id = '';
    public $recipient_id = '';
    public $issue_type_id = '';
    public $issued_at = '';
    public $returned_at = '';
    public $notes = '';
    public $metadata = [];
    public $signature_data = null;

    protected $listeners = ['open-create-issue-modal' => 'openCreateModal', 'edit-issue' => 'openEditModal'];

    #[Computed]
    public function issues()
    {
        return IssIssue::where('team_id', auth()->user()->currentTeam->id)
            ->when($this->search, function ($q) {
                $q->where(function ($query) {
                    $query->where('identifier', 'like', '%' . $this->search . '%')
                        ->orWhere('title', 'like', '%' . $this->search . '%')
                        ->orWhere('description', 'like', '%' . $this->search . '%')
                        ->orWhere('notes', 'like', '%' . $this->search . '%')
                        ->orWhereHas('type', fn($q) => $q->where('name', 'like', '%' . $this->search . '%'));
                });
            })
            ->when($this->filterStatus === 'issued', fn($q) => $q->whereNotNull('issued_at')->whereNull('returned_at'))
            ->when($this->filterStatus === 'returned', fn($q) => $q->whereNotNull('returned_at'))
            ->when($this->filterStatus === 'pending', fn($q) => $q->whereNull('issued_at'))
            ->when($this->filterType, fn($q) => $q->where('issue_type_id', $this->filterType))
            ->when($this->filterEmployer, fn($q) => $q->where('recipient_type', 'hcm_employee')
                ->whereHas('recipient', fn($q2) => $q2->where('employer_id', $this->filterEmployer)))
            ->with(['type', 'recipient'])
            ->orderBy('issued_at', 'desc')
            ->get();
    }

    #[Computed]
    public function issueTypes()
    {
        return IssIssueType::where('team_id', auth()->user()->currentTeam->id)
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function employers()
    {
        return HcmEmployer::where('team_id', auth()->user()->currentTeam->id)
            ->where('is_active', true)
            ->orderBy('employer_number')
            ->get()
            ->sortBy('display_name')
            ->values();
    }

    #[Computed]
    public function modalEmployees()
    {
        return HcmEmployee::where('team_id', auth()->user()->currentTeam->id)
            ->when($this->modal_employer_id, fn($q) => $q->where('employer_id', $this->modal_employer_id))
            ->orderBy('employee_number')
            ->get()
            ->map(function ($employee) {
                return [
                    'id' => $employee->id,
                    'label' => ($employee->getContact()?->full_name ?? $employee->employee_number) . ' (' . $employee->employee_number . ')'
                ];
            });
    }

    #[Computed]
    public function selectedIssueType()
    {
        if (!$this->issue_type_id) {
            return null;
        }
        return IssIssueType::find($this->issue_type_id);
    }

    public function updatedModalEmployerId()
    {
        $this->recipient_id = '';
    }

    public function openCreateModal()
    {
        $this->resetForm();
        $this->showModal = true;
    }

    public function openEditModal($id)
    {
        $issue = IssIssue::find($id);
        if (!$issue) {
            return;
        }

        $this->editingIssue = $issue;
        $this->issue_type_id = $issue->issue_type_id;
        $this->issued_at = $issue->issued_at?->format('Y-m-d');
        $this->returned_at = $issue->returned_at?->format('Y-m-d');
        $this->notes = $issue->notes;
        $this->metadata = $issue->metadata ?? [];
        $this->signature_data = $issue->signature_data;

        // Empfänger laden
        if ($issue->recipient_type === 'hcm_employee' && $issue->recipient_id) {
            $employee = HcmEmployee::find($issue->recipient_id);
            if ($employee) {
                $this->modal_employer_id = $employee->employer_id;
                $this->recipient_id = $employee->id;
            }
        }

        $this->showModal = true;
    }

    public function updatedIssueTypeId()
    {
        $this->metadata = [];
    }

    public function save()
    {
        $rules = [
            'recipient_id' => 'required|exists:hcm_employees,id',
            'issue_type_id' => 'required|exists:iss_issue_types,id',
            'issued_at' => 'nullable|date',
            'returned_at' => 'nullable|date|after_or_equal:issued_at',
            'notes' => 'nullable|string',
        ];

        // Dynamische Validierung basierend auf Felddefinitionen
        $issueType = IssIssueType::find($this->issue_type_id);
        if ($issueType && $issueType->field_definitions) {
            foreach ($issueType->field_definitions as $field) {
                $rule = match ($field['type'] ?? 'text') {
                    'date' => 'date',
                    'checkbox' => 'boolean',
                    default => 'string|max:1000',
                };
                $rules['metadata.' . $field['key']] = ($field['required'] ?? false) ? "required|{$rule}" : "nullable|{$rule}";
            }
        }

        $this->validate($rules);

        $signatureJustAdded = $this->signature_data &&
            (!$this->editingIssue || !$this->editingIssue->signature_data);

        $data = [
            'team_id' => auth()->user()->currentTeam->id,
            'created_by_user_id' => auth()->id(),
            'recipient_type' => 'hcm_employee',
            'recipient_id' => $this->recipient_id,
            'issue_type_id' => $this->issue_type_id,
            'issued_at' => $this->issued_at ?: null,
            'returned_at' => $this->returned_at ?: null,
            'notes' => $this->notes,
            'metadata' => $this->metadata,
            'signature_data' => $this->signature_data,
            'signed_at' => $signatureJustAdded ? now() : ($this->editingIssue?->signed_at),
            'status' => $this->returned_at ? 'returned' : ($this->issued_at ? 'issued' : 'pending'),
        ];

        if ($this->editingIssue) {
            $this->editingIssue->update($data);
            session()->flash('success', 'Ausgabe erfolgreich aktualisiert!');
        } else {
            IssIssue::create($data);
            session()->flash('success', 'Ausgabe erfolgreich erstellt!');
        }

        $this->closeModal();
    }

    public function closeModal()
    {
        $this->showModal = false;
        $this->resetForm();
    }

    public function resetForm()
    {
        $this->editingIssue = null;
        $this->modal_employer_id = '';
        $this->recipient_id = '';
        $this->issue_type_id = '';
        $this->issued_at = '';
        $this->returned_at = '';
        $this->notes = '';
        $this->metadata = [];
        $this->signature_data = null;
    }

    public function render()
    {
        return view('issuance::livewire.issues.index')
            ->layout('platform::layouts.app');
    }
}
