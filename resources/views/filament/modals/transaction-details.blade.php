<div class="space-y-3 text-sm">
    <div class="grid grid-cols-2 gap-3">
        <div>
            <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Datum</p>
            <p class="mt-1 font-medium">{{ $transaction->date->format('d.m.Y') }}</p>
        </div>

        <div>
            <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Banka</p>
            <p class="mt-1 font-medium">{{ $transaction->bank->label() }}</p>
        </div>

        <div>
            <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Tip transakcije</p>
            <p class="mt-1">{{ $transaction->type ?: '—' }}</p>
        </div>

        <div>
            <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Iznos</p>
            <p class="mt-1 font-semibold {{ $transaction->amount >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                {{ $transaction->amount >= 0 ? '+' : '-' }}
                {{ number_format(abs($transaction->amount), 2, ',', '.') }}
                {{ $transaction->currency }}
            </p>
        </div>
    </div>

    <div>
        <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Opis</p>
        <p class="mt-1">{{ $transaction->description ?: '—' }}</p>
    </div>

    @if($transaction->raw)
    <div class="pt-2 border-t border-gray-200 dark:border-gray-700">
        <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Originalna linija iz fajla</p>
        <p class="mt-1 font-mono text-xs text-gray-500 break-all">{{ $transaction->raw }}</p>
    </div>
    @endif
</div>
