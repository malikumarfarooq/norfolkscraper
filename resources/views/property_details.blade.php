@extends('layouts.app')

@section('title', 'Property Details')

@section('content')
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
