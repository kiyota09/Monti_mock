<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monti Textiles — {{ ucfirst($req->type) }} Request</title>
    <style>
        * { box-sizing: border-box; }
        body { margin:0; padding:0; background:#eef2f7; font-family:'Segoe UI', Arial, Helvetica, sans-serif; color:#1f2937; }
        .wrap { min-height:100vh; display:flex; align-items:center; justify-content:center; padding:32px 14px; }
        .card { width:100%; max-width:560px; background:#fff; border-radius:18px; overflow:hidden; box-shadow:0 12px 40px rgba(15,23,42,0.14); }
        .head { background-image:linear-gradient(135deg,#1e3a8a 0%,#4f46e5 55%,#7c3aed 100%); padding:28px 30px; color:#fff; }
        .head p { margin:0; text-transform:uppercase; letter-spacing:3px; font-size:11px; font-weight:700; color:#c7d2fe; }
        .head h1 { margin:8px 0 0; font-size:22px; font-weight:800; }
        .body { padding:30px; }
        .details { border:1px solid #e5e7eb; border-radius:12px; overflow:hidden; margin:0 0 22px; }
        .row { display:flex; border-bottom:1px solid #eef2f7; }
        .row:last-child { border-bottom:none; }
        .row .k { width:38%; padding:12px 16px; background:#f8fafc; font-size:11px; font-weight:800; text-transform:uppercase; letter-spacing:1px; color:#64748b; }
        .row .v { padding:12px 16px; font-size:14px; color:#0f172a; flex:1; word-break:break-word; }
        .q { text-align:center; font-size:16px; font-weight:800; margin:0 0 16px; }
        .btn { display:block; width:100%; text-align:center; padding:14px; border-radius:10px; font-size:14px; font-weight:700; text-decoration:none; border:none; cursor:pointer; }
        .btn-green { background:#059669; color:#fff; }
        .btn-amber { background:#d97706; color:#fff; }
        .btn-grey { background:#e5e7eb; color:#475569; }
        .btn + .btn { margin-top:10px; }
        .muted { color:#64748b; font-size:13px; line-height:1.6; }
        label { display:block; font-size:11px; font-weight:800; text-transform:uppercase; letter-spacing:1px; color:#64748b; margin:16px 0 6px; }
        input, textarea { width:100%; padding:12px 14px; border:1px solid #d1d5db; border-radius:10px; font-size:14px; font-family:inherit; color:#0f172a; outline:none; }
        input:focus, textarea:focus { border-color:#4f46e5; }
        .pill { display:inline-block; padding:6px 14px; border-radius:999px; font-size:12px; font-weight:800; text-transform:uppercase; letter-spacing:1px; }
        .pill-ok { background:#d1fae5; color:#047857; }
        .pill-warn { background:#fef3c7; color:#b45309; }
        .pill-bad { background:#fee2e2; color:#b91c1c; }
        .pill-pending { background:#e0e7ff; color:#4338ca; }
        .center { text-align:center; }
        .big { font-size:44px; line-height:1; margin-bottom:10px; }
        .err { color:#b91c1c; font-size:12px; font-weight:700; margin-top:6px; }
        .divider { border:none; border-top:1px solid #eef2f7; margin:26px 0 8px; }
        .toggle { display:none; }
        .toggle:checked ~ .reschedule-form { display:block; }
    </style>
</head>
<body>
<div class="wrap">
    <div class="card">
        <div class="head">
            <p>Monti Textiles</p>
            <h1>{{ ucfirst($req->type) }} Request</h1>
        </div>
        <div class="body">
            <!-- Details are always shown so the client can review the request. -->
            <div class="details">
                @if($req->opportunity)
                    <div class="row"><div class="k">Reference</div><div class="v">{{ $req->opportunity->title }}</div></div>
                @endif
                <div class="row"><div class="k">Activity</div><div class="v" style="text-transform:capitalize">{{ $req->type }}</div></div>
                @if($req->summary)
                    <div class="row"><div class="k">Subject</div><div class="v">{{ $req->summary }}</div></div>
                @endif
                <div class="row"><div class="k">Proposed Schedule</div><div class="v">{{ $req->proposed_at ? $req->proposed_at->format('l, F j, Y \a\t g:i A') : 'To be advised' }}</div></div>
                @if($req->meeting_link)
                    <div class="row"><div class="k">Meeting Link</div><div class="v"><a href="{{ $req->meeting_link }}" style="color:#4f46e5">{{ $req->meeting_link }}</a></div></div>
                @endif
            </div>

            @if($req->status === 'pending')
                <!-- Still awaiting the client's decision: show the options. -->
                <p class="q">Will you be able to make it?</p>
                <a class="btn btn-green" href="{{ route('meeting-request.confirm', $req->token) }}">&#10003;&nbsp; Yes, confirm</a>
                <a class="btn btn-grey" href="{{ route('meeting-request.decline', $req->token) }}">Sorry, I don't want to</a>

                <hr class="divider">
                <label for="rs-toggle" style="cursor:pointer; margin:0;" class="muted">
                    Need a different time{{ $req->type === 'meeting' ? ' or place' : '' }}?
                </label>
                <input type="checkbox" id="rs-toggle" class="toggle">
                <div class="reschedule-form">
                    <form method="POST" action="{{ route('meeting-request.reschedule', $req->token) }}">
                        @csrf
                        @if($req->type === 'meeting')
                            <label for="response_place">Preferred place</label>
                            <input id="response_place" name="response_place" value="{{ old('response_place') }}" placeholder="e.g. your office, a café, our showroom…">
                            @error('response_place')<p class="err">{{ $message }}</p>@enderror
                        @endif
                        <label for="preferred_at">Preferred date &amp; time</label>
                        <input id="preferred_at" name="preferred_at" type="datetime-local" min="{{ now()->format('Y-m-d\T00:00') }}" value="{{ old('preferred_at') }}">
                        <p class="muted" style="margin-top:6px; font-size:12px;">Today or a future date only — past dates can't be scheduled.</p>
                        @error('preferred_at')<p class="err">{{ $message }}</p>@enderror
                        <label for="response_message">Message (optional)</label>
                        <textarea id="response_message" name="response_message" rows="3" placeholder="Anything else we should know?">{{ old('response_message') }}</textarea>
                        @error('response_message')<p class="err">{{ $message }}</p>@enderror
                        <button type="submit" class="btn btn-amber" style="margin-top:18px">
                            {{ $req->type === 'call' ? 'Request new call schedule' : 'Send reschedule request' }}
                        </button>
                    </form>
                </div>

            @elseif($req->status === 'confirmed')
                <div class="center">
                    <div class="big">&#10004;&#65039;</div>
                    <span class="pill pill-ok">Confirmed</span>
                    <p class="muted" style="margin-top:14px">
                        Thank you! Your {{ $req->type }} is confirmed for
                        <strong>{{ $req->proposed_at ? $req->proposed_at->format('l, F j, Y \a\t g:i A') : 'the proposed schedule' }}</strong>.
                        Our team has been notified.
                    </p>
                    <p class="muted" style="margin-top:10px">You've already responded — no further action is needed.</p>
                </div>

            @elseif($req->status === 'declined')
                <div class="center">
                    <div class="big">&#128532;</div>
                    <span class="pill pill-bad">Declined</span>
                    <p class="muted" style="margin-top:14px">
                        Thank you for letting us know. We won't push through with this {{ $req->type }},
                        and our team will reach out if anything changes.
                    </p>
                    <p class="muted" style="margin-top:10px">You've already responded — no further action is needed.</p>
                </div>

            @elseif($req->status === 'reschedule')
                <div class="center">
                    <div class="big">&#128197;</div>
                    <span class="pill pill-warn">Reschedule requested</span>
                    <p class="muted" style="margin-top:14px">
                        Thank you! Our team has received your request and will arrange a new
                        {{ $req->type }} based on your preference.
                    </p>
                    @if($req->response_place)
                        <p class="muted"><strong>Preferred place:</strong> {{ $req->response_place }}</p>
                    @endif
                    @if($req->preferred_at)
                        <p class="muted"><strong>Preferred time:</strong> {{ $req->preferred_at->format('l, F j, Y \a\t g:i A') }}</p>
                    @endif
                    <p class="muted" style="margin-top:10px">You've already responded — our team will get back to you.</p>
                </div>
            @endif
        </div>
    </div>
</div>
</body>
</html>
