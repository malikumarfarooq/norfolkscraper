@extends('layouts.app')

@section('content')
    <div class="container">
        <div class="row justify-content-center" style="margin-top: 100px">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">Fetch Property Records</div>

                    <div class="card-body">
                        <div class="mb-4">
                            <h5>Fetch Progress</h5>
                            <div class="progress mb-2" style="height: 25px;">
                                <div id="fetch-progress-bar"
                                     class="progress-bar progress-bar-striped progress-bar-animated bg-primary"
                                     role="progressbar"
                                     style="width: 0%"
                                     aria-valuenow="0"
                                     aria-valuemin="0"
                                     aria-valuemax="100">
                                    <span id="progress-percentage">0%</span>
                                </div>
                            </div>
                            <div id="progress-text">
                                Current ID: {{ $progress->current_id }}<br>
                                Status: <span id="status-text">{{ $progress->is_running ? 'Running' : 'Stopped' }}</span>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="start-id" class="form-label">Start ID:</label>
                            <input type="number" class="form-control" id="start-id" value="{{ $progress->current_id }}" min="10000001" required>
                        </div>

                        <div class="mb-3">
                            <label for="max-id" class="form-label">Maximum ID (optional):</label>
                            <input type="number" class="form-control" id="max-id" value="{{ $progress->max_id ?? '' }}" placeholder="Leave blank to fetch until stopped">
                        </div>

                        <div class="d-flex gap-2">
                            <button id="start-btn" class="btn btn-primary" {{ $progress->is_running ? 'disabled' : '' }}>
                                <span id="start-text">Start Fetching</span>
                                <span id="start-spinner" class="spinner-border spinner-border-sm d-none" role="status"></span>
                            </button>
                            <button id="stop-btn" class="btn btn-danger" {{ !$progress->is_running ? 'disabled' : '' }}>
                                Stop Fetching
                            </button>

                            <button onclick="window.location.href='{{ route('export.csv') }}'" class="btn btn-primary">
                                Download CSV
                            </button>

                            <a href="{{ route('parcels.export.by-sale-groups') }}" class="btn btn-info">
                                <i class="fas fa-file-csv me-2"></i> Export by Sale Groups
                            </a>

                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('styles')
    <style>
        .progress {
            background-color: #e9ecef;
            border-radius: 4px;
        }
        .progress-bar {
            transition: width 0.6s ease;
            position: relative;
        }
        #progress-percentage {
            position: absolute;
            left: 50%;
            transform: translateX(-50%);
            color: white;
            font-weight: bold;
            text-shadow: 0 0 2px rgba(0,0,0,0.5);
        }
        .bg-primary {
            background-color: #0d6efd !important;
        }
    </style>
@endpush

@push('scripts')
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        $(document).ready(function() {
            console.log('[DEBUG] Document ready - fetch script loaded');

            // Setup CSRF token for all AJAX requests
            $.ajaxSetup({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                }
            });

            let intervalId;
            let isFetching = {{ $progress->is_running ? 'true' : 'false' }};
            console.log('[DEBUG] Initial fetch state:', isFetching);

            // Start button click handler
            $('#start-btn').click(function(e) {
                e.preventDefault();
                console.log('[DEBUG] Start button clicked');

                const $btn = $(this);
                const $spinner = $('#start-spinner');
                const $text = $('#start-text');
                const $progressBar = $('#fetch-progress-bar');

                // Show loading state
                $btn.prop('disabled', true);
                $text.text('Starting...');
                $spinner.removeClass('d-none');

                // Reset and initialize progress bar
                $progressBar.css('width', '0%')
                    .attr('aria-valuenow', 0)
                    .addClass('progress-bar-animated progress-bar-striped')
                    .find('#progress-percentage').text('0%');

                const startId = $('#start-id').val();
                const maxId = $('#max-id').val();
                console.log('[DEBUG] Start ID:', startId, 'Max ID:', maxId);

                $.ajax({
                    url: '{{ route("parcels.fetch.start") }}',
                    method: 'POST',
                    data: {
                        start_id: startId,
                        max_id: maxId
                    },
                    success: function(response) {
                        console.log('[DEBUG] AJAX success:', response);

                        isFetching = true;
                        $btn.prop('disabled', true);
                        $('#stop-btn').prop('disabled', false);
                        $('#status-text').text('Running');
                        startProgressUpdates();
                    },
                    error: function(xhr, status, error) {
                        console.error('[ERROR] AJAX error:', error);
                        alert('Error starting fetch: ' + (xhr.responseJSON?.message || error));

                        // Reset button state
                        $btn.prop('disabled', false);
                        $text.text('Start Fetching');
                        $spinner.addClass('d-none');

                        // Reset progress bar
                        $progressBar.removeClass('progress-bar-animated progress-bar-striped')
                            .css('width', '0%');
                    },
                    complete: function() {
                        console.log('[DEBUG] AJAX request completed');
                        $text.text('Start Fetching');
                        $spinner.addClass('d-none');
                    }
                });
            });

            // Stop button click handler
            $('#stop-btn').click(function(e) {
                e.preventDefault();
                console.log('[DEBUG] Stop button clicked');

                const $btn = $(this);
                $btn.prop('disabled', true);
                $btn.text('Stopping...');

                $.ajax({
                    url: '{{ route("parcels.fetch.stop") }}',
                    method: 'POST',
                    success: function(response) {
                        console.log('[DEBUG] Stop success:', response);
                        $('#status-text').text('Stopping...');
                        $btn.text('Stopped');
                    },
                    error: function(xhr, status, error) {
                        console.error('[ERROR] Stop failed:', error);
                        alert('Error stopping fetch: ' + (xhr.responseJSON?.message || error));
                        $btn.prop('disabled', false);
                        $btn.text('Stop Fetching');
                    }
                });
            });

            // Start progress updates
            function startProgressUpdates() {
                console.log('[DEBUG] Starting progress updates');
                clearInterval(intervalId); // Clear any existing interval
                intervalId = setInterval(updateProgress, 1000); // Update more frequently (every 1 second)
                updateProgress(); // Run immediately
            }

            // Update progress function
            function updateProgress() {
                console.log('[DEBUG] Updating progress...');
                $.get('{{ route("parcels.fetch.progress") }}')
                    .done(function(data) {
                        console.log('[DEBUG] Progress data:', data);
                        $('#progress-text').html('Current ID: ' + data.current_id + '<br>Status: ' + (data.is_running ? 'Running' : 'Stopped'));

                        const $progressBar = $('#fetch-progress-bar');
                        const $percentage = $('#progress-percentage');

                        if (data.max_id) {
                            const startId = parseInt($('#start-id').val()) || 10000001;
                            const progress = Math.min(100, ((data.current_id - startId) / (data.max_id - startId)) * 100);
                            const roundedProgress = Math.round(progress);

                            $progressBar.css('width', progress + '%')
                                .attr('aria-valuenow', progress);
                            $percentage.text(roundedProgress + '%');
                        }

                        if (!data.is_running) {
                            console.log('[DEBUG] Fetching stopped, clearing interval');
                            clearInterval(intervalId);
                            isFetching = false;
                            $('#start-btn').prop('disabled', false);
                            $('#stop-btn').prop('disabled', true).text('Stop Fetching');
                            $('#status-text').text('Stopped');
                            $progressBar.removeClass('progress-bar-animated progress-bar-striped');
                        }
                    })
                    .fail(function(xhr, status, error) {
                        console.error('[ERROR] Progress update failed:', error);
                    });
            }

            // Initialize if already running
            if (isFetching) {
                console.log('[DEBUG] Fetching already running, starting progress updates');
                $('#fetch-progress-bar').addClass('progress-bar-animated progress-bar-striped bg-primary');
                startProgressUpdates();
            }
        });
    </script>
@endpush
