<div style="text-align:center;padding:0 0 16px 0;">
    @include('emails.partials.logo')
    <div style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Arial,sans-serif;font-size:18px;line-height:24px;font-weight:900;color:#1F2F29;margin-top:10px;">
        {{ config('app.name') }}
    </div>
    @if(!empty($badge))
    <div style="margin-top:10px;display:inline-block;background:#30913A;color:#ffffff;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Arial,sans-serif;font-size:12px;line-height:16px;font-weight:800;padding:6px 10px;border-radius:999px;">
        {{ $badge }}
    </div>
    @endif
</div>
