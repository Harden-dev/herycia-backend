@extends('emails.layout')

@section('title', 'Vérifiez votre email — ' . config('app.name'))
@section('badge', 'Vérification email')

@section('preheader')
    <div style="display:none;max-height:0;overflow:hidden;opacity:0;color:transparent;">
        Vérifiez votre adresse email — finalisez votre inscription.
    </div>
@endsection

@section('content')
    <div style="padding:28px 24px 8px 24px;">
        <div style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Arial,sans-serif;font-size:22px;line-height:28px;font-weight:900;color:#1F2F29;">
            Vérifiez votre adresse email
        </div>
        <div style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Arial,sans-serif;font-size:14px;line-height:22px;color:rgba(31,47,41,.78);margin-top:10px;">
            Pour finaliser votre inscription et accéder à toutes les fonctionnalités, veuillez confirmer votre email.
        </div>
    </div>

    <div style="text-align:center;padding:0 24px 12px 24px;">
        <a
            href="{{ $url }}"
            style="display:inline-block;background:#30913A;color:#ffffff;text-decoration:none;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Arial,sans-serif;font-size:14px;line-height:18px;font-weight:900;padding:12px 18px;border-radius:12px;border:1px solid rgba(31,47,41,.12);"
        >
            Vérifier mon email
        </a>
    </div>

    <div style="padding:0 24px 18px 24px;">
        <div style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Arial,sans-serif;font-size:12px;line-height:18px;color:rgba(31,47,41,.60);">
            Si le bouton ne marche pas, copiez/collez ce lien :<br>
            <span style="color:#1F2F29;word-break:break-all;">{{ $url }}</span>
        </div>
    </div>

    <div style="padding:0 24px 18px 24px;">
        <div style="padding:14px;border-radius:14px;border:1px solid rgba(31,47,41,.10);background:rgba(48,145,58,.10);">
            <div style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Arial,sans-serif;font-size:13px;line-height:20px;font-weight:800;color:#1F2F29;">
                Important
            </div>
            <div style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Arial,sans-serif;font-size:13px;line-height:20px;color:rgba(31,47,41,.85);margin-top:8px;">
                Ce lien expire dans 60 minutes pour des raisons de sécurité.
            </div>
        </div>
    </div>

    <div style="padding:0 24px 26px 24px;">
        <div style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Arial,sans-serif;font-size:14px;line-height:22px;color:rgba(31,47,41,.82);">
            Si vous n'avez pas créé de compte, ignorez cet email.<br>
            <strong style="color:#1F2F29;">L’équipe {{ config('app.name') }}</strong>
        </div>
    </div>
@endsection
