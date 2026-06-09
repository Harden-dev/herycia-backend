@extends('emails.layout')

@section('title', 'Bienvenue sur ' . config('app.name'))
@section('badge', 'Bienvenue')
@section('recipient', $userEmail)

@section('preheader')
    <div style="display:none;max-height:0;overflow:hidden;opacity:0;color:transparent;">
        Bienvenue sur {{ config('app.name') }} — votre compte est prêt.
    </div>
@endsection

@section('content')
    <div style="padding:28px 24px 8px 24px;">
        <div style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Arial,sans-serif;font-size:22px;line-height:28px;font-weight:900;color:#1F2F29;">
            Bonjour {{ $userName }},
        </div>
        <div style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Arial,sans-serif;font-size:14px;line-height:22px;color:rgba(31,47,41,.78);margin-top:10px;">
            Votre compte est créé avec succès pour <strong style="color:#1F2F29;">{{ $userEmail }}</strong>.
        </div>
    </div>

    <div style="padding:0 24px 18px 24px;">
        <div style="padding:14px;border-radius:14px;border:1px solid rgba(31,47,41,.10);background:rgba(48,145,58,.10);">
            <div style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Arial,sans-serif;font-size:13px;line-height:20px;font-weight:800;color:#1F2F29;">
                Ce que vous pouvez faire dès maintenant
            </div>
            <div style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Arial,sans-serif;font-size:13px;line-height:20px;color:rgba(31,47,41,.85);margin-top:8px;">
                • Gérer votre profil<br>
                • Recevoir vos notifications<br>
                • Sécuriser votre compte<br>
                • Contacter le support
            </div>
        </div>
    </div>

    <div style="text-align:center;padding:0 24px 26px 24px;">
        <a
            href="{{ config('app.url') }}"
            style="display:inline-block;background:#30913A;color:#ffffff;text-decoration:none;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Arial,sans-serif;font-size:14px;line-height:18px;font-weight:900;padding:12px 18px;border-radius:12px;border:1px solid rgba(31,47,41,.12);"
        >
            Accéder à {{ config('app.name') }}
        </a>
        <div style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Arial,sans-serif;font-size:12px;line-height:18px;color:rgba(31,47,41,.60);margin-top:10px;">
            Si le bouton ne marche pas, copiez/collez ce lien :<br>
            <span style="color:#1F2F29;">{{ config('app.url') }}</span>
        </div>
    </div>

    <div style="padding:0 24px 26px 24px;">
        <div style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Arial,sans-serif;font-size:14px;line-height:22px;color:rgba(31,47,41,.82);">
            Merci de nous faire confiance.<br>
            <strong style="color:#1F2F29;">L’équipe {{ config('app.name') }}</strong>
        </div>
    </div>
@endsection
