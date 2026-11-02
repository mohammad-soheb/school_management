{{-- Shown (HTTP 403) to users of a school whose ERP subscription is suspended and who cannot open the subscription page. --}}
@extends('errors.layout')

@section('code', 'Paused')
@section('title', 'Access paused')
@section('heading', 'This school\'s access is paused')
@section('message', $message ?? 'Your school\'s ERP subscription is paused. No data has been deleted. Please contact your school administrator.')
@section('icon')
    <path stroke-linecap="round" stroke-linejoin="round" d="M14.25 9v6m-4.5 0V9M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
@endsection

@section('actions')
    <a href="{{ route('account.profile') }}" class="btn btn-secondary btn-lg">My account</a>
    <form method="POST" action="{{ route('logout') }}" class="mt-3">
        @csrf
        <button type="submit" class="btn btn-ghost">Sign out</button>
    </form>
@endsection
