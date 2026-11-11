@if ($paginator->hasPages())
  <div class="pager">
    @if ($paginator->onFirstPage())
      <span class="btn sm o off">Previous</span>
    @else
      <a class="btn sm o" href="{{ $paginator->previousPageUrl() }}">Previous</a>
    @endif

    <span class="sub">Page {{ $paginator->currentPage() }}</span>

    @if ($paginator->hasMorePages())
      <a class="btn sm o" href="{{ $paginator->nextPageUrl() }}">Next</a>
    @else
      <span class="btn sm o off">Next</span>
    @endif
  </div>
@endif