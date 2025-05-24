@extends('layouts.app')

@section('title', 'NORFOLKAI - ADDRESS INFORMATION RESOURCE')

@section('content')
    <div class="container my-5">
        <div class="text-center mb-5">
            <h1 class="display-4 fw-bold" style="color:dodgerblue">Norfolk Scraper</h1>
            <h2 class="h3 text-muted">Extract Property Information Resource</h2>
        </div>

        <div class="row justify-content-center mb-4">
            <div class="col-md-8 col-lg-6">
                <div class="card shadow-sm">
                    <div class="card-body">
                        <label for="primary_search" style="margin-left: 0.25em;">Search</label>
                        <label for="primary_search" style="margin-left: 14.25em;">Filter</label>
                        <div class="search-container position-relative">
                            <div class="input-group mb-3">
                                <input type="text" id="search-input" class="form-control form-control-lg"
                                       placeholder="Enter an address, tax account" autocomplete="off">



                                <!-- Category dropdown - Improved structure -->
                                <div class="search-bar-button-container position-relative">
                                    <div class="dropdown">
                                        <!-- Dropdown toggle button -->
                                        <button class="btn btn-outline-secondary dropdown-toggle" type="button"
                                                id="search-category-dropdown" data-bs-toggle="dropdown"
                                                style="    margin-left: 3px; height: 48px;width: 83px;"
                                                aria-expanded="false">
                                            <span id="selected-category">All</span>
                                        </button>
                                        <!-- Dropdown menu -->
                                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="search-category-dropdown">
                                            <li><a class="dropdown-item" href="#" data-value="All">All</a></li>
                                            <li><a class="dropdown-item" href="#" data-value="GPIN">Account</a></li>
{{--                                            <li><a class="dropdown-item" href="#" data-value="GPIN">GPIN</a></li>--}}
                                            <li><a class="dropdown-item" href="#" data-value="Address">Address</a></li>
                                        </ul>
                                    </div>
                                </div>



                                <button class="btn btn-success" type="button" id="search-button" style="margin-left: 1px;">
                                    <span class="d-none spinner-border spinner-border-sm" id="search-spinner"></span>
                                    <span id="search-text">Search</span>
                                </button>


                                <a href="{{ route('parcels.fetch') }}" class="btn btn-primary" style="margin-left: 1px;">
                                    Scrap it
                                </a>


                            </div>

                            <div id="suggestions-container" class="list-group position-absolute w-100 d-none">
                                <!-- Loader shown while fetching -->
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
    </div>

@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const input = document.getElementById('search-input');
        const suggestionsContainer = document.getElementById('suggestions-container');
        const loader = document.getElementById('suggestion-loader');
        let currentSelection = 'All'; // Track the current category selection

        // Initialize dropdown selection
        const dropdownItems = document.querySelectorAll('.dropdown-item');
        const selectedCategory = document.getElementById('selected-category');

        // Handle dropdown item selection
        dropdownItems.forEach(item => {
            item.addEventListener('click', function(e) {
                e.preventDefault();
                currentSelection = this.getAttribute('data-value');
                selectedCategory.textContent = this.textContent;

                // Close the dropdown
                const dropdown = bootstrap.Dropdown.getInstance(document.getElementById('search-category-dropdown'));
                dropdown.hide();

                // If there's text in the input, refresh suggestions
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
            // Show loader
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

            // Group suggestions by type if showing all
            if (currentSelection === 'All') {
                const grouped = {};

                suggestions.forEach(item => {
                    const type = item.type || 'Address'; // Default to Address if type not provided
                    if (!grouped[type]) {
                        grouped[type] = [];
                    }
                    grouped[type].push(item);
                });

                // Add section headers and items
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
                // Just show all suggestions without grouping
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
    });

</script>

@endpush
