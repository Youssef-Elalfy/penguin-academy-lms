@extends('layouts.app')
@section('title', 'Reorder lessons')
@section('content')
<a class="back-link" href="{{ route('courses.show', $course) }}">← {{ $course->title }}</a>
<div class="page-heading"><p class="eyebrow">GIVE YOUR COURSE A CLEAR FLOW</p><h1>Reorder lessons<span class="accent-dot">.</span></h1><p>Use the arrows to move lessons, or select a lesson for each position. Include every lesson once.</p></div>
<x-errors />
@if($lessons->isNotEmpty())<form class="surface editor-surface" method="POST" action="{{ route('courses.lessons.reorder', $course) }}" data-submit data-order-form>@csrf @method('PUT')<input type="hidden" name="order_version" value="{{ $course->lesson_order_version }}">
<div class="order-list">@foreach($lessons as $lesson)<div class="order-row"><label for="order-{{ $loop->index }}" class="form-label mb-0">Position {{ $loop->iteration }}</label><select id="order-{{ $loop->index }}" name="lesson_ids[]" class="form-select">@foreach($lessons as $option)<option value="{{ $option->id }}" @selected(old('lesson_ids.'.$loop->parent->index, $lesson->id) == $option->id)>{{ $option->title }}</option>@endforeach</select><div class="order-controls" hidden><button type="button" class="btn btn-outline-secondary btn-sm" data-order-up aria-label="Move position {{ $loop->iteration }} up">↑</button><button type="button" class="btn btn-outline-secondary btn-sm" data-order-down aria-label="Move position {{ $loop->iteration }} down">↓</button></div></div>@endforeach</div>
<p class="form-text mt-3">If someone changes the lesson list while you are editing, you will be asked to reload before saving.</p><p class="visually-hidden" aria-live="polite" data-order-status></p><div class="form-actions"><button type="submit" class="btn btn-primary">Save lesson order</button><a class="btn btn-outline-secondary" href="{{ route('courses.show', $course) }}">Cancel</a></div></form>
@else<div class="surface empty-state"><h3>No lessons to reorder.</h3><a class="btn btn-primary" href="{{ route('courses.lessons.create', $course) }}">Add a lesson</a></div>@endif
@endsection
