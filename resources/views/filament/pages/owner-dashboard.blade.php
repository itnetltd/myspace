<x-filament-panels::page>
    @if($account?->isPropertyManagementCompany())
        <div class="max-w-md">
            <label class="mb-1 block text-sm font-medium">Property Owner / Client</label>
            <select wire:model.live="ownerId" class="w-full rounded-lg border-gray-300 dark:border-gray-700 dark:bg-gray-900">
                @foreach($owners as $availableOwner)
                    <option value="{{ $availableOwner->id }}">{{ $availableOwner->name }}</option>
                @endforeach
            </select>
        </div>
    @endif

    @if($owner && $summary)
        <div>
            <h2 class="text-xl font-semibold">{{ $owner->name }}</h2>
            <p class="text-sm text-gray-500">Cash-basis financial position as of today</p>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            @foreach([
                'Properties' => $summary['properties'],
                'Units' => $summary['units'],
                'Occupied Units' => $summary['occupied_units'],
                'Vacant Units' => $summary['vacant_units'],
                'Occupancy' => $summary['occupancy_percent'].'%',
                'Rent Collected This Month' => number_format((float) $summary['rent_collected'], 2).' '.$account->currency,
                'Late Fees This Month' => number_format((float) $summary['late_fees_collected'], 2).' '.$account->currency,
                'Expenses This Month' => number_format((float) $summary['expenses'], 2).' '.$account->currency,
                'Owner Disbursements This Month' => number_format((float) $summary['owner_disbursements'], 2).' '.$account->currency,
                'Current Owner Balance' => number_format((float) $summary['current_balance'], 2).' '.$account->currency,
            ] as $label => $value)
                <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                    <div class="text-sm text-gray-500">{{ $label }}</div>
                    <div class="mt-1 text-xl font-semibold">{{ $value }}</div>
                </div>
            @endforeach
            @if($account->isPropertyManagementCompany())
                <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                    <div class="text-sm text-gray-500">Management Fees Earned</div>
                    <div class="mt-1 text-xl font-semibold">{{ number_format((float) $summary['management_fees'], 2) }} {{ $account->currency }}</div>
                </div>
            @endif
        </div>

        <div class="grid gap-6 lg:grid-cols-3">
            <div class="rounded-xl bg-white p-4 shadow-sm dark:bg-gray-900">
                <h3 class="mb-3 font-semibold">Recent Financial Activity</h3>
                @forelse($summary['recent_activity'] as $entry)
                    <div class="flex justify-between border-b py-2 text-sm dark:border-gray-800">
                        <span>{{ $entry->occurred_on->format('d M') }} — {{ $entry->description }}</span>
                        <span>{{ $entry->direction === 'credit' ? '+' : '-' }}{{ number_format((float) $entry->amount, 2) }}</span>
                    </div>
                @empty
                    <p class="text-sm text-gray-500">No financial activity yet.</p>
                @endforelse
            </div>
            <div class="rounded-xl bg-white p-4 shadow-sm dark:bg-gray-900">
                <h3 class="mb-3 font-semibold">Recent Expenses</h3>
                @forelse($summary['recent_expenses'] as $expense)
                    <div class="flex justify-between border-b py-2 text-sm dark:border-gray-800">
                        <span>{{ $expense->occurred_on->format('d M') }} - {{ $expense->description }}</span>
                        <span>{{ number_format((float) $expense->amount, 2) }} {{ $expense->currency }}</span>
                    </div>
                @empty
                    <p class="text-sm text-gray-500">No expenses recorded yet.</p>
                @endforelse
            </div>
            <div class="rounded-xl bg-white p-4 shadow-sm dark:bg-gray-900">
                <h3 class="mb-3 font-semibold">Statements</h3>
                @forelse($summary['statements'] as $statement)
                    <a class="block border-b py-2 text-sm text-primary-600 dark:border-gray-800"
                       href="{{ \App\Filament\Resources\OwnerStatementResource::getUrl('view', ['record' => $statement]) }}">
                        {{ $statement->statement_number }} — {{ $statement->closing_balance }} {{ $statement->currency }}
                    </a>
                @empty
                    <p class="text-sm text-gray-500">No statements generated yet.</p>
                @endforelse
            </div>
        </div>
    @else
        <div class="rounded-xl bg-white p-6 text-gray-500 shadow-sm dark:bg-gray-900">
            No PropertyOwner is available in this Account.
        </div>
    @endif
</x-filament-panels::page>
