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
                            <div class="progress mb-2">
                                <div id="fetch-progress-bar" class="progress-bar" role="progressbar" style="width: 0%"></div>
                            </div>
                            <div id="progress-text">
                                Current ID: {{ $progress->current_id }}<br>
                                Status: <span id="status-text">{{ $progress->is_running ? 'Running' : 'Stopped' }}</span>
                            </div>
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
            height: 20px;
        }
        .progress-bar {
            transition: width 0.5s ease;
        }
    </style>
@endpush

@push('scripts')
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        $(document).ready(function() {
            console.log('[DEBUG] Document ready - fetch script loaded');

            // ✅ Setup CSRF token for all AJAX requests
            $.ajaxSetup({
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                }
            });

            let intervalId;
            let isFetching = {{ $progress->is_running ? 'true' : 'false' }};
            console.log('[DEBUG] Initial fetch state:', isFetching);

            // Verify critical elements
            console.log('[DEBUG] CSRF Token:', '{{ csrf_token() }}');
            console.log('[DEBUG] Start Route:', '{{ route("parcels.fetch.start") }}');
            console.log('[DEBUG] Stop Route:', '{{ route("parcels.fetch.stop") }}');
            console.log('[DEBUG] Progress Route:', '{{ route("parcels.fetch.progress") }}');

            // Start button click handler
            $('#start-btn').click(function(e) {
                e.preventDefault();
                console.log('[DEBUG] Start button clicked');

                const $btn = $(this);
                const $spinner = $('#start-spinner');
                const $text = $('#start-text');

                // Show loading state
                $btn.prop('disabled', true);
                $text.text('Starting...');
                $spinner.removeClass('d-none');

                const maxId = $('#max-id').val();
                console.log('[DEBUG] Max ID value:', maxId);

                $.ajax({
                    url: '{{ route("parcels.fetch.start") }}',
                    method: 'POST',
                    data: {
                        max_id: maxId
                        // ✅ Removed _token
                    },
                    beforeSend: function() {
                        console.log('[DEBUG] AJAX request initiating');
                    },
                    success: function(response, status, xhr) {
                        console.log('[DEBUG] AJAX success:', response);

                        isFetching = true;
                        $btn.prop('disabled', true);
                        $('#stop-btn').prop('disabled', false);
                        $('#status-text').text('Running');
                        startProgressUpdates();
                    },
                    error: function(xhr, status, error) {
                        console.error('[ERROR] AJAX error:', error);
                        console.error('[ERROR] Status:', status);
                        console.error('[ERROR] Full response:', xhr.responseText);

                        alert('Error starting fetch: ' + (xhr.responseJSON?.message || error));

                        // Reset button state
                        $btn.prop('disabled', false);
                        $text.text('Start Fetching');
                        $spinner.addClass('d-none');
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
                    data: {
                        // ✅ Removed _token
                    },
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
                intervalId = setInterval(updateProgress, 3000);
                updateProgress(); // Run immediately
            }

            // Update progress function
            function updateProgress() {
                console.log('[DEBUG] Updating progress...');
                $.get('{{ route("parcels.fetch.progress") }}')
                    .done(function(data) {
                        console.log('[DEBUG] Progress data:', data);
                        $('#progress-text').html('Current ID: ' + data.current_id + '<br>Status: ' + (data.is_running ? 'Running' : 'Stopped'));

                        if (data.max_id) {
                            const progress = Math.min(100, ((data.current_id - 10000001) / (data.max_id - 10000001)) * 100);
                            $('#fetch-progress-bar').css('width', progress + '%').attr('aria-valuenow', progress);
                        }

                        if (!data.is_running) {
                            console.log('[DEBUG] Fetching stopped, clearing interval');
                            clearInterval(intervalId);
                            isFetching = false;
                            $('#start-btn').prop('disabled', false);
                            $('#stop-btn').prop('disabled', true).text('Stop Fetching');
                            $('#status-text').text('Stopped');
                        }
                    })
                    .fail(function(xhr, status, error) {
                        console.error('[ERROR] Progress update failed:', error);
                    });
            }

            // Initialize if already running
            if (isFetching) {
                console.log('[DEBUG] Fetching already running, starting progress updates');
                startProgressUpdates();
            }
        });

    </script>

@endpush
