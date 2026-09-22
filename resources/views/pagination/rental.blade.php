@if ($paginator->hasPages())
    <nav role="navigation" aria-label="Navigasi halaman" class="flex items-center justify-between gap-3">
        @if ($paginator->onFirstPage())
            <span class="btn btn-outline btn-sm opacity-40" aria-disabled="true">&lsaquo; Sebelumnya</span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="btn btn-outline btn-sm">&lsaquo; Sebelumnya</a>
        @endif

        @if (method_exists($paginator, 'lastPage'))
            <span class="text-sm text-muted">Halaman {{ $paginator->currentPage() }} dari {{ $paginator->lastPage() }}</span>
        @else
            <span class="text-sm text-muted">Halaman {{ $paginator->currentPage() }}</span>
        @endif

        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="btn btn-outline btn-sm">Berikutnya &rsaquo;</a>
        @else
            <span class="btn btn-outline btn-sm opacity-40" aria-disabled="true">Berikutnya &rsaquo;</span>
        @endif
    </nav>
@endif
