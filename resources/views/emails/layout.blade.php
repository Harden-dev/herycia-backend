<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', config('app.name'))</title>
</head>
<body style="margin:0;padding:0;background:#F9F9F9;">
    @yield('preheader')

    <div style="width:100%;background:#F9F9F9;padding:32px 16px;box-sizing:border-box;">
        <div style="width:100%;max-width:600px;margin-left:auto;margin-right:auto;">
            @include('emails.partials.header', ['badge' => trim($__env->yieldContent('badge', false)) ?: null])

            <div style="background:#ffffff;border-radius:18px;box-shadow:0 10px 30px rgba(31,47,41,.12);overflow:hidden;border:1px solid rgba(31,47,41,.10);">
                @yield('content')
            </div>

            @include('emails.partials.footer', [
                'recipientEmail' => trim($__env->yieldContent('recipient', false)) ?: null,
            ])
        </div>
    </div>
</body>
</html>
