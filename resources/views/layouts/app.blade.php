<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>@yield('title', 'NORFOLKAI - ADDRESS INFORMATION RESOURCE')</title>

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Custom CSS -->
    <link href="{{ asset('css/app.css') }}" rel="stylesheet">

    @stack('styles')
</head>
<body>
<div class="min-vh-100 d-flex flex-column">
    @yield('content')

    <footer class="mt-auto bg-light py-3">
        <div class="container">
            <div class="row">
                <div class="col-md-8 text-md-end">
                    <p class="mb-0">Developed for City of Norfolk - NORFOLK AIR</p>
                </div>
            </div>
        </div>
    </footer>
</div>

<!-- Bootstrap 5 JS Bundle with Popper -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

@stack('scripts')
</body>
</html>
