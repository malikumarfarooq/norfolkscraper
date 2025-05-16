@extends('layouts.app')

@section('title', 'NORFOLKAI - ADDRESS INFORMATION RESOURCE')

@section('content')
    <div class="container my-5">
        <div class="text-center mb-5">
            <h1 class="display-4 fw-bold">Norfolk Scraper</h1>
            <h2 class="h3 text-muted">Extract Property Information Resource</h2>
        </div>

        <div class="row justify-content-center mb-4">
            <div class="col-md-8 col-lg-6">
                <div class="card shadow-sm">
                    <div class="card-body">
                        <div class="d-flex mb-3">
                            <button class="btn btn-sm btn-primary me-2 active">All</button>
                            <button class="btn btn-sm btn-outline-secondary">Map</button>
                        </div>

                        <div class="search-container position-relative">
                            <div class="input-group mb-3">
                                <input type="text" id="search-input" class="form-control form-control-lg"
                                       placeholder="Enter an address, tax account, GPIN" autocomplete="off">
                                <button class="btn btn-primary" type="button" id="search-button">
                                    <span class="d-none spinner-border spinner-border-sm" id="search-spinner"></span>
                                    <span id="search-text">Search</span>
                                </button>
                            </div>

                            <div id="suggestions-container" class="list-group position-absolute w-100 d-none">
                                <!-- Suggestions will appear here -->
                            </div>
                        </div>

                        <div class="d-flex flex-wrap gap-2 mt-3">
                            <select class="form-select form-select-sm" style="width: auto;">
                                <option selected>Filter by...</option>
                                <option>Property Type</option>
                                <option>Location</option>
                                <option>Tax Status</option>
                            </select>
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
            const searchInput = document.getElementById('search-input');
            const searchButton = document.getElementById('search-button');
            const searchSpinner = document.getElementById('search-spinner');
            const searchText = document.getElementById('search-text');
            const suggestionsContainer = document.getElementById('suggestions-container');
            let abortController = new AbortController();

            // Debounce function
            const debounce = (func, delay) => {
                let timeoutId;
                return (...args) => {
                    clearTimeout(timeoutId);
                    timeoutId = setTimeout(() => func.apply(this, args), delay);
                };
            };

            const fetchSuggestions = async (query) => {
                try {
                    abortController.abort(); // Cancel previous request
                    abortController = new AbortController();

                    const response = await fetch('/search/suggestions', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                        },
                        body: JSON.stringify({ query }),
                        signal: abortController.signal
                    });

                    if (!response.ok) throw new Error('Network response was not ok');

                    const data = await response.json();
                    displaySuggestions(data);
                } catch (error) {
                    if (error.name !== 'AbortError') {
                        console.error('Error fetching suggestions:', error);
                        suggestionsContainer.classList.add('d-none');
                    }
                }
            };

            const displaySuggestions = (suggestions) => {
                if (!suggestions || !Array.isArray(suggestions) || suggestions.length === 0) {
                    suggestionsContainer.classList.add('d-none');
                    return;
                }

                suggestionsContainer.innerHTML = '';

                suggestions.forEach(suggestion => {
                    const item = document.createElement('a');
                    item.href = '#';
                    item.className = 'list-group-item list-group-item-action';
                    item.textContent = suggestion.text || suggestion.address || suggestion.value;

                    item.addEventListener('click', (e) => {
                        e.preventDefault();
                        searchInput.value = item.textContent;
                        suggestionsContainer.classList.add('d-none');
                        performSearch(); // Auto-search when suggestion is clicked
                    });

                    suggestionsContainer.appendChild(item);
                });

                suggestionsContainer.classList.remove('d-none');
            };

            const performSearch = () => {
                const query = searchInput.value.trim();
                if (!query) return;

                // Show loading state
                searchSpinner.classList.remove('d-none');
                searchText.textContent = 'Searching...';
                searchButton.disabled = true;

                // Here you would implement your actual search functionality
                console.log('Searching for:', query);

                // In a real implementation, you would:
                // 1. Make an API call to your search endpoint
                // 2. Handle the response
                // 3. Update the UI with results

                // For now, we'll simulate a search
                setTimeout(() => {
                    searchSpinner.classList.add('d-none');
                    searchText.textContent = 'Search';
                    searchButton.disabled = false;

                    // Hide suggestions after search
                    suggestionsContainer.classList.add('d-none');

                    // Show results (you would replace this with actual result display)
                    alert(`Search functionality for "${query}" would display results here`);
                }, 1000);
            };

            // Event listeners
            searchInput.addEventListener('input', debounce(() => {
                const query = searchInput.value.trim();
                if (query.length >= 2) {
                    fetchSuggestions(query);
                } else {
                    suggestionsContainer.classList.add('d-none');
                }
            }, 300));

            searchButton.addEventListener('click', performSearch);

            searchInput.addEventListener('keypress', (e) => {
                if (e.key === 'Enter') {
                    performSearch();
                }
            });

            document.addEventListener('click', (e) => {
                if (!searchInput.contains(e.target) && !suggestionsContainer.contains(e.target)) {
                    suggestionsContainer.classList.add('d-none');
                }
            });

            // Focus the search input on page load
            searchInput.focus();
        });
    </script>
@endpush
