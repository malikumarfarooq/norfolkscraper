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
                resetProgressUI();
                showLoadingState(true);

                $.ajax({
                    url: '{{ route("parcels.fetch.start") }}',
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                        'Accept': 'application/json'
                    },
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

                if (!confirm('Are you sure you want to stop the current process?')) {
                    return;
                }

                $stopBtn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm"></span> Stopping...');

                $.ajax({
                    url: `/parcels/fetch/stop/${batchId}`,
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') },
                    success: function(response) {
                        if (response.success) {
                            clearInterval(progressInterval);
                            updateStatus('cancelled');
                            $stopBtn.prop('disabled', true);
                            showAlert('Process stopped successfully', 'success');
                        } else {
                            showAlert(response.message || 'Failed to stop process', 'error');
                            resetStopButton();
                        }
                    },
                    error: function(xhr) {
                        showAlert(xhr.responseJSON?.message || xhr.statusText, 'error');
                        resetStopButton();
                    }
                });
            }

            function checkProgress() {
                if (!batchId) return;

                $.get(`/parcels/fetch/progress/${batchId}`)
                    .done(function(response) {
                        if (response && response.success) {
                            // Update to use the new response structure
                            updateProgressUI({
                                progress: response.progress,
                                processedJobs: response.processedJobs,
                                totalJobs: response.totalJobs,
                                status: response.status,
                                failedJobs: response.failedJobs
                            });
                        } else {
                            console.error('Invalid progress response:', response);
                            showAlert('Invalid progress response', 'error');
                        }
                    })
                    .fail(function(xhr) {
                        console.error('Progress check failed:', xhr);
                        let errorMessage = xhr.statusText;
                        if (xhr.responseJSON && xhr.responseJSON.message) {
                            errorMessage = xhr.responseJSON.message;
                        }
                        showAlert('Failed to check progress: ' + errorMessage, 'error');
                    });
            }

            function updateProgressUI({ progress, processedJobs, totalJobs, status, failedJobs }) {
                const safeProgress = Math.min(100, Math.max(0, progress || 0));
                $progressBar.css('width', safeProgress + '%');
                $progressPercent.text(safeProgress + '%');

                $processedJobs.text(processedJobs || 0);
                $totalJobs.text(totalJobs || 0);

                updateStatus(status || 'pending', failedJobs || 0);

                if (status === 'processing') {
                    updateTimeEstimate(processedJobs, totalJobs);
                }

                if (safeProgress === 100 || ['completed', 'completed_with_errors', 'failed', 'cancelled'].includes(status)) {
                    clearInterval(progressInterval);
                    $progressBar.removeClass('progress-bar-animated');
                    $stopBtn.prop('disabled', true);

                    if (failedJobs > 0) {
                        showAlert(`Completed with ${failedJobs} failed jobs`, 'warning');
                    }
                }
            }


            function updateStatus(status, failedJobs = 0) {
                const statusMap = {
                    'processing': { class: 'bg-info', text: 'Processing' },
                    'completed': { class: 'bg-success', text: 'Completed' },
                    'completed_with_errors': { class: 'bg-warning', text: 'Completed with errors' },
                    'failed': { class: 'bg-danger', text: 'Failed' },
                    'cancelled': { class: 'bg-secondary', text: 'Cancelled' },
                    'pending': { class: 'bg-secondary', text: 'Pending' }
                };

                const statusInfo = statusMap[status.toLowerCase()] || statusMap['pending'];

                $statusText
                    .removeClass()
                    .addClass('badge ' + statusInfo.class)
                    .text(statusInfo.text);
            }


            function updateTimeEstimate(processed, total) {
                if (processed <= 0 || !startTime) {
                    $timeRemaining.text('calculating...');
                    return;
                }

                const now = new Date();
                const elapsed = (now - startTime) / 1000; // in seconds
                const rate = processed / elapsed;
                const remaining = Math.max(0, Math.round((total - processed) / rate));

                // Format as HH:MM:SS
                const hours = Math.floor(remaining / 3600);
                const minutes = Math.floor((remaining % 3600) / 60);
                const seconds = Math.floor(remaining % 60);

                $timeRemaining.text(
                    `${hours.toString().padStart(2, '0')}:` +
                    `${minutes.toString().padStart(2, '0')}:` +
                    `${seconds.toString().padStart(2, '0')}`
                );
            }

            function handleStartSuccess(response) {
                if (!response.batch_id) {
                    showAlert('Failed to start - no batch ID returned', 'error');
                    return;
                }

                batchId = response.batch_id;
                startTime = new Date();

                // Enable stop button and show progress
                $stopBtn.prop('disabled', false);
                $progressSection.removeClass('d-none').hide().fadeIn(300);

                // Initialize counters
                $processedJobs.text('0');
                $totalJobs.text(response.total_jobs || response.total_accounts);
                updateStatus('pending');

                // Start progress polling
                progressInterval = setInterval(checkProgress, 2000);
                showAlert('Processing started successfully', 'success');
            }

            function handleStartError(xhr) {
                const error = xhr.responseJSON?.message || xhr.statusText;
                showAlert(`Error starting process: ${error}`, 'error');
            }

            // Helper functions
            function resetProgressUI() {
                $progressBar
                    .css('width', '0%')
                    .removeClass('bg-success bg-danger bg-warning')
                    .addClass('progress-bar-animated');

                $progressPercent.text('0%');
                $statusText
                    .removeClass()
                    .addClass('badge bg-secondary')
                    .text('Pending');

                $batchErrors.addClass('d-none');
                $errorList.empty();
                $timeRemaining.text('calculating...');
            }

            function showLoadingState(show) {
                $startBtn.prop('disabled', show);
                $startText.text(show ? 'Starting...' : 'Start Mass Fetching');
                $startSpinner.toggleClass('d-none', !show);
            }

            function resetStopButton() {
                $stopBtn.prop('disabled', false).html('<i class="fas fa-stop-circle me-2"></i> Stop Scraping');
            }

            function showAlert(message, type = 'info') {
                const alertClass = {
                    'success': 'alert-success',
                    'error': 'alert-danger',
                    'warning': 'alert-warning',
                    'info': 'alert-info'
                }[type] || 'alert-info';

                const $alert = $(`
                    <div class="alert ${alertClass} alert-dismissible fade show" role="alert">
                        ${message}
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                `).prependTo('.card-body');

                setTimeout(() => $alert.alert('close'), 5000);
            }
        });
    </script>
@endpush
