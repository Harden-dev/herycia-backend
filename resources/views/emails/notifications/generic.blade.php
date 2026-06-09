@extends('emails.layout')

@section('title', $title ?? config('app.name'))
@section('badge', $badge ?? 'Notification')
@section('recipient', $userEmail ?? '')

@section('preheader')
    <div style="display:none;max-height:0;overflow:hidden;opacity:0;color:transparent;">
        {{ $title ?? '' }}
    </div>
@endsection

@section('content')
    <div style="padding:28px 24px 8px 24px;">
        <div style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Arial,sans-serif;font-size:22px;line-height:28px;font-weight:900;color:#1F2F29;">
            Bonjour {{ $userName ?? 'Client' }},
        </div>
        <div style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Arial,sans-serif;font-size:16px;line-height:24px;font-weight:700;color:#1F2F29;margin-top:12px;">
            {{ $title ?? '' }}
        </div>
        <div style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Arial,sans-serif;font-size:14px;line-height:22px;color:rgba(31,47,41,.78);margin-top:10px;">
            {{ $messageText ?? '' }}
        </div>
    </div>

    <div style="text-align:center;padding:18px 24px 26px 24px;">
        <a
            href="{{ $ctaUrl ?? config('app.url') }}"
            style="display:inline-block;background:#30913A;color:#ffffff;text-decoration:none;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Arial,sans-serif;font-size:14px;line-height:18px;font-weight:900;padding:12px 18px;border-radius:12px;border:1px solid rgba(31,47,41,.12);"
        >
            {{ $ctaText ?? 'Ouvrir l\'application' }}
        </a>
    </div>
@endsection
