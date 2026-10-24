@extends('layouts.app')
@section('title', 'The lesson list changed')
@section('content')
<div class="surface empty-state spacious"><h1 class="h3">The lesson list changed.</h1><p>Someone added, archived or reordered lessons after you opened this page. Your changes were not saved. Reload the current list and try again.</p><a class="btn btn-primary" href="{{ route('courses.lessons.order', request()->route('course')) }}">Reload lesson order</a></div>
@endsection
