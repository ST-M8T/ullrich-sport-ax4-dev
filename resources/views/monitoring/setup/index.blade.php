@extends('layouts.admin', [
    'pageTitle' => 'System-Setup',
    'currentSection' => 'monitoring-health',
])

@section('content')
    @include('monitoring.setup.partials.system-overview', ['status' => $status ?? []])
@endsection

