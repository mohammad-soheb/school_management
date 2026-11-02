@extends('errors.layout')

@section('code', '404')
@section('title', 'Page not found')
@section('heading', 'We could not find that page')
@section('message', 'The link may be out of date, or the address may have a typo. Check the address, or head back to the start page.')
@section('icon')
    <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z"/>
@endsection

@section('actions')
    <button type="button" class="btn btn-secondary btn-lg js-only" x-data @click="window.history.length > 1 ? window.history.back() : (window.location.href = '/')">Go back</button>
@endsection
