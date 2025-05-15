@extends('layouts.app')

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

                        <div class="input-group mb-3">
                            <input type="text" class="form-control form-control-lg"
                                   placeholder="Enter an address, tax account, GPIN">
                            <button class="btn btn-primary" type="button">Search</button>
                        </div>

                        <div class="d-flex flex-wrap gap-2">
                            <select class="form-select form-select-sm" style="width: auto;">
                                <option selected>Filter by...</option>
                                <option>Property Type</option>
                                <option>Location</option>
                                <option>Tax Status</option>
                            </select>

                            <div class="input-group input-group-sm" style="width: auto;">
                                <button class="btn btn-primary" type="button">Generate CSV</button>
                            </div>

                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
