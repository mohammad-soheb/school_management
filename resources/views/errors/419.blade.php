@extends('errors.layout')

@section('code', '419')
@section('title', 'Page expired')
@section('heading', 'This page has expired')
@section('message', 'For your security the form timed out before it was sent. Go back, reload the page and try again. Nothing was saved.')
@section('icon')
    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
@endsection

@section('actions')
    <button type="button" class="btn btn-secondary btn-lg js-only" x-data @click="window.history.length > 1 ? window.history.back() : (window.location.href = '/')">Go back</button>
@endsection
