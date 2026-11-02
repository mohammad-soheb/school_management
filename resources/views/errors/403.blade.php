@extends('errors.layout')

@section('code', '403')
@section('title', 'Access denied')
@section('heading', 'You do not have access to this page')
@section('message', 'Your account is signed in, but it is not allowed to open this page. If you think this is a mistake, ask your school administrator to check your role.')
@section('icon')
    <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z"/>
@endsection

@section('actions')
    <button type="button" class="btn btn-secondary btn-lg js-only" x-data @click="window.history.length > 1 ? window.history.back() : (window.location.href = '/')">Go back</button>
@endsection
