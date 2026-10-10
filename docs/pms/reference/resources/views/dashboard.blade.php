@extends('layouts.app')
@section('content')
    <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Welcome , {{ Auth::user()->name }}</h2>
@endsection
