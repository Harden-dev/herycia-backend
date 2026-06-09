<div style="text-align:center;padding:16px 8px 0 8px;">
    <div style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Arial,sans-serif;font-size:12px;line-height:18px;color:rgba(31,47,41,.60);">
        © {{ date('Y') }} {{ config('app.name') }}. Tous droits réservés.
    </div>
    @if(!empty($recipientEmail))
    <div style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Arial,sans-serif;font-size:12px;line-height:18px;color:rgba(31,47,41,.60);margin-top:4px;">
        Cet email a été envoyé à {{ $recipientEmail }}.
    </div>
    @endif
</div>
