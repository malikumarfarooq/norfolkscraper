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
        document.addEventListener('DOMContentLoaded', function () {
            const input = document.getElementById('search-input');
            const suggestionsContainer = document.getElementById('suggestions-container');
            const loader = document.getElementById('suggestion-loader');

            let debounceTimeout;

            input.addEventListener('input', function () {
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
                    body: JSON.stringify({ query }),
                })
                    .then(response => response.json())
                    .then(data => {
                        loader.classList.add('d-none');

                        if (data.success && data.suggestions && data.suggestions.length) {
                            showSuggestions(data.suggestions);
                        } else {
                            suggestionsContainer.innerHTML = '';
                            suggestionsContainer.classList.add('d-none');
                        }
                    })
                    .catch(() => {
                        loader.classList.add('d-none');
                        suggestionsContainer.innerHTML = '';
                        suggestionsContainer.classList.add('d-none');
                    });
            }

            function showSuggestions(suggestions) {
                suggestionsContainer.innerHTML = '';
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
