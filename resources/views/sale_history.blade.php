@extends('layouts.app')

@section('content')
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h3 class="mb-0">Sale History</h3>
            @if($sales->total() > 0)
                <div class="badge bg-primary rounded-pill">
                    {{ $sales->total() }} {{ Str::plural('record', $sales->total()) }} found
                </div>
            @endif
        </div>

        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h5 class="mb-0">Recent Sales within 1 mile</h5>

                <div class="d-flex align-items-center gap-2">
                    <!-- Download CSV Button -->
                    <a href="{{ route('sales-history.export', $currentId) }}"
                       class="btn btn-sm btn-success"
                       download>
                        <i class="fas fa-download me-1"></i> Download All Sales Data
                    </a>

{{--                    0$ sale history--}}
                    <a href="{{ route('sale.history.export.zero', $currentId) }}"
                       class="btn btn-danger me-2">
                        <i class="fas fa-file-csv me-2"></i> Export $0 Sales
                    </a>
{{--                    End of 0$ sale button--}}

                <form method="GET" action="{{ request()->url() }}" class="d-flex align-items-center gap-2">
                    @if(request()->has('page'))
                        <input type="hidden" name="page" value="{{ request('page') }}">
                    @endif

                    <div class="input-group input-group-sm" style="width: 120px;">
                        <span class="input-group-text">Show</span>
                        <select class="form-select form-select-sm" name="per_page" onchange="this.form.submit()">
                            @foreach([10, 25, 50, 100] as $perPage)
                                <option value="{{ $perPage }}" {{ request('per_page', 10) == $perPage ? 'selected' : '' }}>
                                    {{ $perPage }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </form>
            </div>

            @if($sales->isEmpty())
                <div class="card-body text-center py-5">
                    <div class="text-muted mb-3">
                        <i class="fas fa-folder-open fa-3x"></i>
                    </div>
                    <h5>No sales records found</h5>
                    <p class="text-muted">Try adjusting your search criteria</p>
                </div>
            @else
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped table-hover mb-0">
                            <thead class="thead-light">
                            <tr>
                                @foreach(['GPIN', 'Account', 'Address', 'Property Type', 'Sale Date', 'Sale Price',
//                                    'Sale Type', 'Neighborhood', 'Distance'
                                    ] as $header)

                                    <th>{{ $header }}</th>
                                @endforeach
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($sales as $sale)
                                @php
                                    $displayData = collect($sale['display'])->keyBy('title');
                                @endphp
                                <tr>
                                    <td>{{ $displayData['GPIN']['value'] ?? '' }}</td>
                                    <td>{{ $displayData['Account']['value'] ?? '' }}</td>
                                    <td>{{ $displayData['Address']['value'] ?? '' }}</td>
                                    <td>{{ $displayData['Property Type']['value'] ?? '' }}</td>
                                    <td>{{ $displayData['Sale Date']['value'] ?? '' }}</td>
                                    <td>
                                        {{ ($displayData['Sale Price']['prefix'] ?? '') . ($displayData['Sale Price']['value'] ?? '') }}
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="card-footer d-flex justify-content-between align-items-center flex-wrap gap-2 py-2">
                    <div class="text-muted small">
                        Showing <span class="fw-semibold">{{ $sales->firstItem() }}</span> to
                        <span class="fw-semibold">{{ $sales->lastItem() }}</span> of
                        <span class="fw-semibold">{{ $sales->total() }}</span> results
                    </div>

                    <nav aria-label="Page navigation">
                        {{ $sales->appends(['per_page' => request('per_page', 10)])->onEachSide(1)->links('pagination.custom') }}
                    </nav>
                </div>
            @endif
        </div>
    </div>
@endsection

@section('styles')
    <style>
        .pagination {
            margin-bottom: 0;
        }
        .pagination .page-item {
            margin: 0 2px;
        }
        .pagination .page-item.active .page-link {
            background-color: var(--bs-primary);
            border-color: var(--bs-primary);
            color: white;
        }
        .pagination .page-link {
            color: var(--bs-primary);
            border-radius: 4px;
            padding: 0.375rem 0.75rem;
            border: 1px solid #dee2e6;
        }
        .pagination .page-link:hover {
            background-color: #f8f9fa;
        }
        .table-responsive {
            min-height: 300px;
        }
        .table th {
            white-space: nowrap;
            position: relative;
            background-color: #f8f9fa;
        }
        .table th:after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 1px;
            background-color: rgba(0,0,0,0.1);
        }
        .card-footer {
            background-color: rgba(0,0,0,0.03);
            border-top: 1px solid rgba(0,0,0,0.125);
        }
        .badge {
            font-size: 0.85rem;
            padding: 0.35em 0.65em;
        }
    </style>
@endsection

@section('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Tooltip initialization if needed
            const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
        });
    </script>
@endsection
