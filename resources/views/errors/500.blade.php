@extends('errors.layout')

@section('code', '500')
@section('title', 'Something went wrong')
@section('heading', 'Something went wrong on our side')
@section('message', 'The problem has been recorded and we are looking into it. Please try again in a few minutes. If it keeps happening, tell your school administrator.')
@section('icon')
    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"/>
@endsection

@section('actions')
    <button type="button" class="btn btn-secondary btn-lg js-only" x-data @click="window.location.reload()">Try again</button>
@endsection
