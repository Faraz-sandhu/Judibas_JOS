@extends('layouts.app')

@section('content')
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <div class="row">
                        <div class="col-12 text-center">
                            <h1><i class="{{ $iconClass ?? 'fas fa-exclamation-triangle' }} text-warning"></i></h1>
                        </div>
                        <div class="col-12">
                            <h4 class="text-center">
                                {{ $message ?? 'You don\'t have permission to access this page' }}
                            </h4>
                        </div>
                        <div class="col-12 text-center">
                            <button class="btn btn-primary" onclick="window.history.back();"><span class="fas fa-arrow-left me-2"></span> Go Back</button>
                        </div>
                </div>
            </div>
        </div>
    </div>
@endsection
@push('page-scripts')
   
@endpush
