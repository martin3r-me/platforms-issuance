<div>
    {{-- Modul Header --}}
    <div x-show="!collapsed" class="p-3 text-sm italic text-[var(--ui-secondary)] uppercase border-b border-[var(--ui-border)] mb-2">
        Ausgaben
    </div>

    {{-- Navigation --}}
    <x-ui-sidebar-list label="Verwaltung">
        <x-ui-sidebar-item :href="route('issuance.issues.index')">
            @svg('heroicon-o-archive-box', 'w-4 h-4 text-[var(--ui-secondary)]')
            <span class="ml-2 text-sm">Ausgaben</span>
        </x-ui-sidebar-item>
        <x-ui-sidebar-item :href="route('issuance.issue-types.index')">
            @svg('heroicon-o-tag', 'w-4 h-4 text-[var(--ui-secondary)]')
            <span class="ml-2 text-sm">Ausgabe-Typen</span>
        </x-ui-sidebar-item>
    </x-ui-sidebar-list>

    {{-- Collapsed: Icons-only --}}
    <div x-show="collapsed" class="px-2 py-2 border-b border-[var(--ui-border)]">
        <div class="flex flex-col gap-2">
            <a href="{{ route('issuance.issues.index') }}" wire:navigate class="flex items-center justify-center p-2 rounded-md text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)]" title="Ausgaben">
                @svg('heroicon-o-archive-box', 'w-5 h-5')
            </a>
            <a href="{{ route('issuance.issue-types.index') }}" wire:navigate class="flex items-center justify-center p-2 rounded-md text-[var(--ui-secondary)] hover:bg-[var(--ui-muted-5)]" title="Ausgabe-Typen">
                @svg('heroicon-o-tag', 'w-5 h-5')
            </a>
        </div>
    </div>

    {{-- Statistiken --}}
    <div x-show="!collapsed" class="mt-4 p-3 bg-[var(--ui-muted-5)] rounded-lg border border-[var(--ui-border)]/40">
        <div class="text-xs font-semibold text-[var(--ui-secondary)] uppercase tracking-wider mb-2">Übersicht</div>
        <div class="space-y-2">
            <div class="flex justify-between items-center text-sm">
                <span class="text-[var(--ui-muted)]">Ausgaben</span>
                <span class="font-medium text-[var(--ui-secondary)]">{{ $this->stats['total_issues'] }}</span>
            </div>
            <div class="flex justify-between items-center text-sm">
                <span class="text-[var(--ui-muted)]">Ausgegeben</span>
                <span class="font-medium text-orange-500">{{ $this->stats['issued'] }}</span>
            </div>
            <div class="flex justify-between items-center text-sm">
                <span class="text-[var(--ui-muted)]">Zurückgegeben</span>
                <span class="font-medium text-green-600">{{ $this->stats['returned'] }}</span>
            </div>
            <div class="flex justify-between items-center text-sm">
                <span class="text-[var(--ui-muted)]">Typen</span>
                <span class="font-medium text-[var(--ui-secondary)]">{{ $this->stats['total_types'] }}</span>
            </div>
        </div>
    </div>
</div>
