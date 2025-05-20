@extends('layouts.app')

@section('title', 'Property Details')

@section('content')
{{--    Search functionality is here please --}}

<div class="container my-5">
    {{-- Add the search section at the top --}}
    <div class="row justify-content-center mb-4">
        <div class="col-md-10">
            <div class="card shadow-sm">
                <div class="card-body">
{{--                    <label for="primary_search" style="margin-left: 0.25em;">Search Another Property</label>--}}
                    <div class="search-container position-relative">
                        <div class="input-group mb-3">
                            <input type="text" id="search-input" class="form-control form-control-lg"
                                   placeholder="Enter an address, tax account" autocomplete="off">

                            <!-- Category dropdown -->
                            <div class="search-bar-button-container position-relative">
                                <div class="dropdown">
                                    <button class="btn btn-outline-secondary dropdown-toggle" type="button"
                                            id="search-category-dropdown" data-bs-toggle="dropdown"
                                            style="margin-left: 3px; height: 48px;width: 83px;"
                                            aria-expanded="false">
                                        <span id="selected-category">All</span>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="search-category-dropdown">
                                        <li><a class="dropdown-item" href="#" data-value="All">All</a></li>
                                        <li><a class="dropdown-item" href="#" data-value="GPIN">Account</a></li>
                                        <li><a class="dropdown-item" href="#" data-value="Address">Address</a></li>
                                    </ul>
                                </div>
                            </div>

                            <button class="btn btn-success" type="button" id="search-button" style="margin-left: 1px;">
                                <span class="d-none spinner-border spinner-border-sm" id="search-spinner"></span>
                                <span id="search-text">Search</span>
                            </button>
                        </div>

                        <div id="suggestions-container" class="list-group position-absolute w-100 d-none">
                            <div id="suggestion-loader" class="list-group-item text-center text-muted d-none">
                                <div class="spinner-border spinner-border-sm me-2" role="status"></div>
                                Loading...
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>


{{--    Search functionality end of the search--}}
    <div class="container my-5">
        <h1 class="mb-4">Property Details</h1>

        {{-- Basic Property Info --}}
        <div class="card mb-4">
            <div class="card-body">
                <h5 class="card-title">Basic Information</h5>
                <p><strong>Property ID:</strong> {{ $property['id'] }}</p>
                <p><strong>City Latitude:</strong> {{ $property['cty'] }}</p>
                <p><strong>City Longitude:</strong> {{ $property['ctx'] }}</p>
                <p><strong>Active:</strong> {{ $property['active'] ? 'Yes' : 'No' }}</p>
            </div>
        </div>

        {{-- Parcel Header --}}
        <div class="card mb-4">
            <div class="card-body">
                <h5 class="card-title">Parcel Header</h5>
                @php $header = $property['parcel']['header']; @endphp
                <ul class="list-unstyled">
                    <li><strong>Street:</strong> {{ $header['PropertyStreet'] }}</li>
                    <li><strong>Total Value:</strong> {{ $header['total_value'] }}</li>
                    <li><strong>Mailing Address:</strong> {{ $header['MailingAddress'] }}</li>
                    <li><strong>GPIN:</strong> {{ $header['GPIN'] }}</li>
                </ul>
            </div>
        </div>

        {{-- Owner & Legal Info --}}
        @php $section0 = $property['parcel']['sections']['0'][0][0] ?? null; @endphp
        @if($section0)
            <div class="card mb-4">
                <div class="card-body">
                    <h5 class="card-title">Ownership and Legal Info</h5>
                    <ul class="list-unstyled">
                        <li><strong>Owner Name:</strong> {{ $section0['OwnerName'] }}</li>
                        <li><strong>Legal Description:</strong> {{ $section0['LegalDescription'] }}</li>
                        <li><strong>Parcel Area:</strong> {{ $section0['ParcelAreaSF'] }} ({{ $section0['ParcelAcreage'] }})</li>
                        <li><strong>Neighborhood:</strong> {{ $section0['Neighborhood'] }}</li>
                    </ul>
                </div>
            </div>
        @endif

        {{-- Building Info --}}
        @php $building = $property['parcel']['sections']['0'][1][0] ?? null; @endphp
        @if($building)
            <div class="card mb-4">
                <div class="card-body">
                    <h5 class="card-title">Building Details</h5>
                    <ul class="list-unstyled">
                        <li><strong>Type:</strong> {{ $building['BuildingType'] }}</li>
                        <li><strong>Year Built:</strong> {{ $building['YearBuilt'] }}</li>
                        <li><strong>Stories:</strong> {{ $building['NumberofStories'] }}</li>
                        <li><strong>Bedrooms:</strong> {{ $building['Bedrooms'] }}</li>
                        <li><strong>Baths:</strong> {{ $building['FullBaths'] }} Full / {{ $building['HalfBaths'] }} Half</li>
                        <li><strong>Area:</strong> {{ $building['FinishedLivingArea'] }}</li>
                        <li><strong>Heating:</strong> {{ $building['Heating'] }}</li>
                        <li><strong>Cooling:</strong> {{ $building['Cooling'] }}</li>
                    </ul>
                </div>
            </div>
        @endif

        {{-- Sales History --}}
        @php $sales = $property['parcel']['sections']['1'][0] ?? []; @endphp
        @if(count($sales))
            <div class="card mb-4">
                <div class="card-body">
                    <h5 class="card-title">Sales History</h5>
                    <table class="table table-bordered">
                        <thead>
                        <tr>
                            <th>Owner</th>
                            <th>Sale Date</th>
                            <th>Price</th>
                            <th>Doc #</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($sales as $sale)
                            <tr>
                                <td>{{ $sale['owners'] }}</td>
                                <td>{{ $sale['saledate'] }}</td>
                                <td>{{ $sale['saleprice'] }}</td>
                                <td>{{ $sale['docnum'] }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        {{-- Assessment History --}}
        @php $assessments = $property['parcel']['sections']['1'][1] ?? []; @endphp
        @if(count($assessments))
            <div class="card mb-4">
                <div class="card-body">
                    <h5 class="card-title">Assessment History</h5>
                    <table class="table table-bordered">
                        <thead>
                        <tr>
                            <th>Effective Year</th>
                            <th>Land Value</th>
                            <th>Improvement Value</th>
                            <th>Total Value</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($assessments as $assess)
                            <tr>
                                <td>{{ $assess['eff_year'] }}</td>
                                <td>{{ $assess['land_market_value'] }}</td>
                                <td>{{ $assess['imp_val'] }}</td>
                                <td>{{ $assess['total_value'] }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        {{-- Add this at the bottom of your content section --}}
        <div class="text-center mt-4">
            <a href="{{ route('property.export', $property['id']) }}" class="btn btn-success">
                <i class="fas fa-file-csv me-2"></i> Export to CSV
            </a>
        </div>


        {{-- Add this at the bottom of your content section --}}
        <div class="text-center mt-4">
            <form action="{{ route('property.export', $property['id']) }}" method="GET" class="row g-3 justify-content-center">
                <div class="col-md-3">
                    <label for="start_date" class="form-label">Start Date</label>
                    <input type="date" class="form-control" id="start_date" name="start_date">
                </div>
                <div class="col-md-3">
                    <label for="end_date" class="form-label">End Date</label>
                    <input type="date" class="form-control" id="end_date" name="end_date">
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-success w-100">
                        <i class="fas fa-file-csv me-2"></i> Export to CSV
                    </button>
                </div>
            </form>
        </div>
    </div>
@endsection





@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Reuse the same search script from your main page
            const input = document.getElementById('search-input');
            const suggestionsContainer = document.getElementById('suggestions-container');
            const loader = document.getElementById('suggestion-loader');
            let currentSelection = 'All';

            const dropdownItems = document.querySelectorAll('.dropdown-item');
            const selectedCategory = document.getElementById('selected-category');

            dropdownItems.forEach(item => {
                item.addEventListener('click', function(e) {
                    e.preventDefault();
                    currentSelection = this.getAttribute('data-value');
                    selectedCategory.textContent = this.textContent;

                    const dropdown = bootstrap.Dropdown.getInstance(document.getElementById('search-category-dropdown'));
                    dropdown.hide();

                    if (input.value.trim().length >= 2) {
                        fetchSuggestions(input.value.trim());
                    }
                });
            });

            let debounceTimeout;

            input.addEventListener('input', function() {
                clearTimeout(debounceTimeout);
                const query = this.value.trim();

                if (query.length < 2) {
                    suggestionsContainer.innerHTML = '';
                    suggestionsContainer.classList.add('d-none');
                    return;
                }

                debounceTimeout = setTimeout(() => {
                    fetchSuggestions(query);
                }, 300);
            });

            function fetchSuggestions(query) {
                suggestionsContainer.innerHTML = '';
                loader.classList.remove('d-none');
                suggestionsContainer.appendChild(loader);
                suggestionsContainer.classList.remove('d-none');

                fetch("{{ route('suggestions') }}", {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        query: query,
                        category: currentSelection
                    }),
                })
                    .then(response => response.json())
                    .then(data => {
                        loader.classList.add('d-none');

                        if (data.success && data.suggestions && data.suggestions.length) {
                            showSuggestions(data.suggestions);
                        } else {
                            showNoResults();
                        }
                    })
                    .catch(() => {
                        showNoResults();
                    });
            }

            function showSuggestions(suggestions) {
                suggestionsContainer.innerHTML = '';

                if (currentSelection === 'All') {
                    const grouped = {};

                    suggestions.forEach(item => {
                        const type = item.type || 'Address';
                        if (!grouped[type]) {
                            grouped[type] = [];
                        }
                        grouped[type].push(item);
                    });

                    Object.keys(grouped).forEach(type => {
                        const header = document.createElement('div');
                        header.className = 'list-group-item list-group-item-secondary small fw-bold';
                        header.textContent = type === 'account' ? 'Account Numbers' : type.charAt(0).toUpperCase() + type.slice(1);
                        suggestionsContainer.appendChild(header);

                        grouped[type].forEach(item => {
                            const div = document.createElement('button');
                            div.type = 'button';
                            div.className = 'list-group-item list-group-item-action';
                            div.textContent = item.suggest;
                            div.addEventListener('click', () => {
                                window.location.href = `/property-details/${item.id}`;
                            });
                            suggestionsContainer.appendChild(div);
                        });
                    });
                } else {
                    suggestions.forEach(item => {
                        const div = document.createElement('button');
                        div.type = 'button';
                        div.className = 'list-group-item list-group-item-action';
                        div.textContent = item.suggest;
                        div.addEventListener('click', () => {
                            window.location.href = `/property-details/${item.id}`;
                        });
                        suggestionsContainer.appendChild(div);
                    });
                }

                suggestionsContainer.classList.remove('d-none');
            }

            function showNoResults() {
                suggestionsContainer.innerHTML = '';
                const noResults = document.createElement('div');
                noResults.className = 'list-group-item text-muted';
                noResults.textContent = 'No suggestions found';
                suggestionsContainer.appendChild(noResults);
                suggestionsContainer.classList.remove('d-none');
            }

            document.addEventListener('click', (e) => {
                if (!e.target.closest('.search-container')) {
                    suggestionsContainer.innerHTML = '';
                    suggestionsContainer.classList.add('d-none');
                }
            });

            // Add search button functionality
            document.getElementById('search-button').addEventListener('click', function() {
                const query = input.value.trim();
                if (query.length >= 2) {
                    fetchSuggestions(query);
                }
            });
        });
    </script>
@endpush
