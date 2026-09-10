@extends('errors::minimal')
@section('title', 'Restricted section')
@section('code', '403')
@section('message')
    @if(auth()->user()?->isAudit())
        This section requires additional access. Open your event's Food operations &rarr; Approval codes to request approval for an eligible section. Billing, accommodation, company settings and staff management remain restricted.
        <a href="{{ route('dashboard') }}" style="display:block;margin-top:1rem;text-decoration:underline">Return to food operations</a>
    @else
        You do not have permission to access this section.
    @endif
@endsection
