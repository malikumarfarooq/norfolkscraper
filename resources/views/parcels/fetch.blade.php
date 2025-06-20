@extends('layouts.app')

@section('content')
    <div class="container">
        <div class="row justify-content-center mt-5">
            <div class="col-md-10">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span>Fetch Property Records</span>
                        @if($lastBatch)
                            <small class="text-muted">
                                Last run: {{ $lastBatch->created_at->diffForHumans() }}
                                ({{ $lastBatch->status }})
                            </small>
                        @endif
                    </div>

                    <div class="card-body">
                        <div class="text-center mb-5">
                            <h1 class="display-4 fw-bold" style="color:dodgerblue">Norfolk Scraper</h1>
                            <h2 class="h3 text-muted">Extract Property Information Resource</h2>
                        </div>
                        <div class="d-flex flex-wrap gap-3 mb-4" style="margin-left: 65px;">
                            <button id="start-btn" class="btn btn-primary btn-lg">
                                <span id="start-text">Start Scraping</span>
                                <span id="start-spinner" class="spinner-border spinner-border-sm d-none"></span>
                            </button>

                            <button id="stop-btn" class="btn btn-danger btn-lg" disabled>
                                <i class="fas fa-stop-circle me-2"></i> Stop Scraping
                            </button>

                            <button id="export-csv" class="btn btn-success btn-lg">
                                <i class="fas fa-file-csv me-2"></i> Export All Parcels
                            </button>

                            <button id="export-groups" class="btn btn-info btn-lg">
                                <i class="fas fa-file-csv me-2"></i> Export by 0$ Sale
                            </button>
                        </div>

                        <div id="progress-section" class="d-none">
                            <div class="progress mb-3" style="height: 25px">
                                <div id="progress-bar" class="progress-bar progress-bar-striped progress-bar-animated"
                                     role="progressbar" style="width: 0%">
                                    <span id="progress-percent">0%</span>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="d-flex justify-content-between mb-2">
                                        <span>Processed:</span>
                                        <span id="processed-jobs">0</span>
                                    </div>
                                    <div class="d-flex justify-content-between mb-2">
                                        <span>Total:</span>
                                        <span id="total-jobs">0</span>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="d-flex justify-content-between mb-2">
                                        <span>Status:</span>
                                        <span id="status-text" class="badge bg-secondary">Pending</span>
                                    </div>
                                    <div class="d-flex justify-content-between">
                                        <span>Time Remaining:</span>
                                        <span id="time-remaining">calculating...</span>
                                    </div>
                                </div>
                            </div>

                            <div id="batch-errors" class="mt-3 alert alert-danger d-none">
                                <h5 class="alert-heading">Errors Encountered</h5>
                                <div id="error-list" class="mb-0"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('styles')
    <style>


        #stop-btn:not(:disabled) {
            background-color: #dc3545;
            border-color: #dc3545;
            box-shadow: 0 0 0 0.25rem rgba(220, 53, 69, 0.5);
        }

        #stop-btn:disabled {
            opacity: 0.65;
        }



        #progress-section {
            transition: all 0.3s ease;
            padding: 20px;
            background-color: #f8f9fa;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        #progress-bar {
            font-weight: 500;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .badge-processing {
            background-color: #0dcaf0;
        }

        .badge-completed {
            background-color: #198754;
        }

        .badge-failed {
            background-color: #dc3545;
        }

        .badge-cancelled {
            background-color: #6c757d;
        }
    </style>
@endpush

@push('scripts')
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        $(document).ready(function() {
            let batchId = null;
            let startTime = null;
            let progressInterval = null;

            // Initialize elements
            const $startBtn = $('#start-btn');
            const $stopBtn = $('#stop-btn');
            const $startText = $('#start-text');
            const $startSpinner = $('#start-spinner');
            const $progressSection = $('#progress-section');
            const $progressBar = $('#progress-bar');
            const $progressPercent = $('#progress-percent');
            const $processedJobs = $('#processed-jobs');
            const $totalJobs = $('#total-jobs');
            const $statusText = $('#status-text');
            const $timeRemaining = $('#time-remaining');
            const $batchErrors = $('#batch-errors');
            const $errorList = $('#error-list');

            // Initialize stop button state
            $stopBtn.prop('disabled', true);

            // Button click handlers
            $('#export-csv').click(() => window.location.href = '{{ route("export.csv") }}');
            $('#export-groups').click(() => window.location.href = '{{ route("parcels.export.by-sale-groups") }}');

            $startBtn.click(startBatchProcessing);
            $stopBtn.click(stopBatchProcessing);

            function startBatchProcessing(e) {
                e.preventDefault();
                // Reset UI
                resetProgressUI();
                showLoadingState(true);

                $.ajax({
                    url: '{{ route("parcels.fetch.start") }}',
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                    success: handleStartSuccess,
                    error: handleStartError,
                    complete: () => showLoadingState(false)
                });
            }

            function stopBatchProcessing() {
                if (!batchId) {
                    alert('No active batch to stop');
                    return;
                }

                if (!confirm('Are you sure you want to stop the current scraping process?')) {
                    return;
                }

                $stopBtn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span> Stopping...');

                $.ajax({
                    url: `/parcels/fetch/stop/${batchId}`,
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                        'Accept': 'application/json'
                    },
                    success: function(response) {
                        if (response.success) {
                            alert('Scraping process stopped successfully');
                            $statusText
                                .removeClass('badge-processing')
                                .addClass('bg-warning')
                                .text('Cancelled');
                            clearInterval(progressInterval);
                            $stopBtn.prop('disabled', true);
                        } else {
                            alert('Failed to stop scraping: ' + (response.message || 'Unknown error'));
                            resetStopButton();
                        }
                    },
                    error: function(xhr) {
                        alert('Error stopping scraping: ' + (xhr.responseJSON?.message || xhr.statusText));
                        resetStopButton();
                    }
                });
            }

            function resetStopButton() {
                $stopBtn.prop('disabled', false).html('<i class="fas fa-stop-circle me-2"></i> Stop Scraping');
            }

            function resetProgressUI() {
                $progressBar
                    .css('width', '0%')
                    .removeClass('bg-success bg-danger bg-warning')
                    .addClass('progress-bar-animated');

                $progressPercent.text('0%');
                $statusText
                    .removeClass('bg-success bg-danger bg-warning')
                    .addClass('bg-secondary')
                    .text('Pending');

                $batchErrors.addClass('d-none');
                $errorList.empty();
            }

            function showLoadingState(show) {
                $startBtn.prop('disabled', show);
                $startText.text(show ? 'Starting...' : 'Start Mass Fetching');
                $startSpinner.toggleClass('d-none', !show);
            }

            function handleStartSuccess(response) {
                if (!response.batch_id) {
                    alert("Failed to start batch - no batch ID returned");
                    return;
                }

                batchId = response.batch_id;
                startTime = new Date();

                // Enable the stop button
                $stopBtn.prop('disabled', false);

                // Initialize progress display
                $progressSection.removeClass('d-none').hide().fadeIn(300);
                $processedJobs.text('0');
                $totalJobs.text(response.total_accounts);
                $statusText
                    .removeClass('bg-secondary')
                    .addClass('badge-processing')
                    .text('Processing');

                // Start progress polling
                progressInterval = setInterval(checkProgress, 2000);
            }

            function handleStartError(xhr) {
                const error = xhr.responseJSON?.message || xhr.statusText;
                alert(`Error starting fetch: ${error}`);
            }

            function checkProgress() {
                if (!batchId) return;

                $.get(`/parcels/fetch/progress/${batchId}`)
                    .done(updateProgress)
                    .fail(handleProgressError);
            }

            function updateProgress(response) {
                // Update progress bar
                const progress = Math.floor(response.progress);
                $progressBar.css('width', progress + '%');
                $progressPercent.text(progress + '%');

                // Update counts
                $processedJobs.text(response.processedJobs);
                $totalJobs.text(response.totalJobs);

                // Update status
                updateStatus(response.status, response.failedJobs);

                // Update time estimate
                updateTimeEstimate(response.processedJobs, response.totalJobs);

                // Handle completion
                if (response.progress === 100 || ['completed', 'failed', 'cancelled'].includes(response.status)) {
                    clearInterval(progressInterval);
                    $progressBar.removeClass('progress-bar-animated');

                    if (response.failedJobs > 0) {
                        fetchBatchErrors();
                    }
                }
            }

            function updateStatus(status, failedJobs) {
                const statusMap = {
                    'processing': { class: 'badge-processing', text: 'Processing' },
                    'completed': { class: 'bg-success', text: 'Completed' + (failedJobs ? ' with errors' : '') },
                    'failed': { class: 'bg-danger', text: 'Failed' },
                    'cancelled': { class: 'bg-warning', text: 'Cancelled' }
                };

                const statusInfo = statusMap[status] || statusMap['processing'];
                $statusText
                    .removeClass('bg-secondary badge-processing bg-success bg-danger bg-warning')
                    .addClass(statusInfo.class)
                    .text(statusInfo.text);
            }

            function updateTimeEstimate(processed, total) {
                if (processed <= 0) {
                    $timeRemaining.text('calculating...');
                    return;
                }

                const elapsed = (new Date() - startTime) / 1000;
                const rate = processed / elapsed;
                const remaining = Math.max(0, Math.round((total - processed) / rate));

                $timeRemaining.text(formatTime(remaining));
            }

            function formatTime(seconds) {
                const hours = Math.floor(seconds / 3600);
                const minutes = Math.floor((seconds % 3600) / 60);
                const secs = Math.floor(seconds % 60);

                return [
                    hours > 0 ? `${hours}h ` : '',
                    minutes > 0 ? `${minutes}m ` : '',
                    `${secs}s`
                ].join('').trim() || 'less than a second';
            }

            function handleProgressError(xhr) {
                clearInterval(progressInterval);
                $statusText
                    .removeClass('bg-secondary badge-processing bg-success')
                    .addClass('bg-danger')
                    .text('Error checking progress');
            }

            function fetchBatchErrors() {
                $.get(`/parcels/fetch/errors/${batchId}`)
                    .done(displayErrors)
                    .fail(() => console.error('Failed to fetch errors'));
            }

            function displayErrors(errors) {
                if (errors.length === 0) return;

                $errorList.append(
                    errors.map(error =>
                        `<div class="mb-1">${error.property_id}: ${error.message}</div>`
                    )
                );
                $batchErrors.removeClass('d-none');
            }
        });
    </script>
@endpush
