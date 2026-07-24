<div class="space-y-2">
    @if($rules->isEmpty())
        <div class="text-center py-10 text-gray-500 dark:text-gray-400">
            <p class="text-sm">No rules yet.</p>
            <p class="text-xs mt-1 opacity-70">Assign a category to transactions to create rules automatically.</p>
        </div>
    @else
        <div class="divide-y divide-gray-100 dark:divide-gray-800">
            @foreach($rules as $rule)
                <div class="py-3 px-1">

                    {{-- Deleting confirmation --}}
                    @if($deletingRuleId === $rule->id)
                        <div class="rounded-lg bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 p-3 space-y-2">
                            <p class="text-xs font-medium text-red-700 dark:text-red-300">
                                Delete rule: <span class="font-mono">{{ Str::limit($rule->keyword, 50) }}</span>
                            </p>
                            <p class="text-xs text-red-600 dark:text-red-400">
                                Do you also want to reset categories on all matching transactions?
                            </p>
                            <div class="flex items-center gap-2 pt-1 flex-wrap">
                                <button
                                        wire:click="deleteRule(false)"
                                        class="text-xs px-3 py-1.5 rounded-lg bg-red-100 dark:bg-red-900 text-red-700 dark:text-red-300 hover:bg-red-200 transition-colors"
                                >
                                    Delete rule only
                                </button>
                                <button
                                        wire:click="deleteRule(true)"
                                        class="text-xs px-3 py-1.5 rounded-lg bg-red-600 text-white hover:bg-red-700 font-medium transition-colors"
                                >
                                    Delete + Reset transactions
                                </button>
                                <button
                                        wire:click="cancelDelete"
                                        class="text-xs px-3 py-1.5 rounded-lg bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 hover:bg-gray-200 transition-colors ml-auto"
                                >
                                    Cancel
                                </button>
                            </div>
                        </div>

                        {{-- Editing mode --}}
                    @elseif($editingRuleId === $rule->id)
                        <div class="flex items-center gap-3">
                            <select
                                    wire:model="editingCategory"
                                    class="shrink-0 w-36 text-xs rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-800 dark:text-gray-200 px-2 py-1.5 focus:ring-2 focus:ring-primary-500"
                            >
                                @foreach($categories as $value => $label)
                                    <option value="{{ $value }}" @selected($editingCategory === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                            <span class="flex-1 text-xs text-gray-500 dark:text-gray-400 font-mono truncate">
                                {{ $rule->keyword }}
                            </span>
                            <div class="shrink-0 flex items-center gap-2">
                                <button
                                        wire:click="saveRule"
                                        class="text-xs px-3 py-1.5 rounded-lg bg-primary-600 text-white hover:bg-primary-700 font-medium transition-colors"
                                >
                                    Save
                                </button>
                                <button
                                        wire:click="cancelEdit"
                                        class="text-xs px-3 py-1.5 rounded-lg bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 hover:bg-gray-200 transition-colors"
                                >
                                    Cancel
                                </button>
                            </div>
                        </div>

                        {{-- Normal view --}}
                    @else
                        <div class="flex items-center gap-3">
                            <span class="shrink-0 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 w-36 justify-center">
                                {{ $rule->category->label() }}
                            </span>
                            <span class="flex-1 text-xs text-gray-700 dark:text-gray-300 font-mono truncate" title="{{ $rule->keyword }}">
                                {{ $rule->keyword }}
                            </span>
                            <div class="shrink-0 flex items-center gap-1">
                                {{-- Edit --}}
                                <button
                                        wire:click="startEditRule({{ $rule->id }}, '{{ $rule->category->value }}')"
                                        title="Edit category"
                                        class="p-1.5 rounded text-gray-400 hover:text-primary-600 hover:bg-primary-50 dark:hover:bg-primary-900/30 transition-colors"
                                >
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931z"/>
                                    </svg>
                                </button>
                                {{-- Delete --}}
                                <button
                                        wire:click="startDeleteRule({{ $rule->id }})"
                                        title="Delete rule"
                                        class="p-1.5 rounded text-gray-400 hover:text-red-600 hover:bg-red-50 dark:hover:bg-red-900/30 transition-colors"
                                >
                                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0"/>
                                    </svg>
                                </button>
                            </div>
                        </div>
                    @endif

                </div>
            @endforeach
        </div>
    @endif
</div>