<div class="space-y-4">
    {{-- Existing comments --}}
    @if($comments->isEmpty())
        <p class="text-sm text-gray-500 dark:text-gray-400 text-center py-4">No comments yet.</p>
    @else
        <div class="space-y-3">
            @foreach($comments as $comment)
                <div class="flex gap-3">
                    <div class="shrink-0 w-7 h-7 rounded-full bg-primary-100 dark:bg-primary-900 flex items-center justify-center text-xs font-medium text-primary-700 dark:text-primary-300">
                        {{ mb_substr($comment->user->name, 0, 1) }}
                    </div>
                    <div class="flex-1">
                        <div class="flex items-baseline gap-2">
                            <span class="text-xs font-medium text-gray-700 dark:text-gray-300">{{ $comment->user->name }}</span>
                            <span class="text-xs text-gray-400">{{ $comment->created_at->format('d.m.Y H:i') }}</span>
                        </div>
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">{{ $comment->body }}</p>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    {{-- Divider before form --}}
    @if($comments->isNotEmpty())
        <div class="border-t border-gray-100 dark:border-gray-800 pt-2"></div>
    @endif
</div>
