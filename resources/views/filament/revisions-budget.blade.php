<ul class="space-y-2 text-sm">
    @foreach ($revisions as $revision)
        <li class="rounded-lg bg-gray-50 p-3 dark:bg-white/5">
            <span class="font-semibold">{{ $revision->ancien !== null ? number_format($revision->ancien, 0, ',', ' ').' → ' : '' }}{{ number_format($revision->nouveau, 0, ',', ' ') }} FCFA</span>
            <span class="text-gray-500"> · {{ $revision->created_at->format('d/m/Y H:i') }} · {{ $revision->auteur->name }}</span>
            <p class="mt-1">{{ $revision->motif }}</p>
        </li>
    @endforeach
</ul>
