<?php

namespace Platform\Issuance\Livewire\Employee;

use Livewire\Component;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Platform\Hcm\Models\HcmEmployee;
use Platform\Issuance\Models\IssIssue;
use Platform\Issuance\Models\IssIssueType;

class IssuesIndex extends Component
{

    public HcmEmployee $employee;
    public $search = '';
    public $filterStatus = 'all';
    public $filterType = '';

    // Modal state
    public $showModal = false;
    public $editingIssue = null;

    // Form fields
    public $issue_type_id = '';
    public $issued_at = '';
    public $returned_at = '';
    public $notes = '';
    public $metadata = [];

    protected $listeners = ['open-create-issue-modal' => 'openCreateModal', 'edit-issue' => 'openEditModal'];

    public function mount(HcmEmployee $employee)
    {
        $this->employee = $employee;
    }

    #[Computed]
    public function issues()
    {
        return IssIssue::where('recipient_type', 'hcm_employee')
            ->where('recipient_id', $this->employee->id)
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
            ->with(['type'])
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
    public function selectedIssueType()
    {
        if (!$this->issue_type_id) {
            return null;
        }
        return IssIssueType::find($this->issue_type_id);
    }

    public function openCreateModal()
    {
        $this->resetForm();
        $this->showModal = true;
    }

    public function openEditModal($id)
    {
        $issue = IssIssue::where('recipient_type', 'hcm_employee')
            ->where('recipient_id', $this->employee->id)
            ->find($id);

        if (!$issue) {
            return;
        }

        $this->editingIssue = $issue;
        $this->issue_type_id = $issue->issue_type_id;
        $this->issued_at = $issue->issued_at?->format('Y-m-d');
        $this->returned_at = $issue->returned_at?->format('Y-m-d');
        $this->notes = $issue->notes;
        $this->metadata = $issue->metadata ?? [];
        $this->showModal = true;
    }

    public function updatedIssueTypeId()
    {
        $this->metadata = [];
    }

    public function save()
    {
        $rules = [
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

        $data = [
            'team_id' => auth()->user()->currentTeam->id,
            'created_by_user_id' => auth()->id(),
            'recipient_type' => 'hcm_employee',
            'recipient_id' => $this->employee->id,
            'issue_type_id' => $this->issue_type_id,
            'issued_at' => $this->issued_at ?: null,
            'returned_at' => $this->returned_at ?: null,
            'notes' => $this->notes,
            'metadata' => $this->metadata,
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
        $this->issue_type_id = '';
        $this->issued_at = '';
        $this->returned_at = '';
        $this->notes = '';
        $this->metadata = [];
    }

    public function render()
    {
        return view('issuance::livewire.employee.issues-index')
            ->layout('platform::layouts.app');
    }
}
