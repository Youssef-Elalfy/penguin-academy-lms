<x-errors />
<x-input name="title" label="Lesson title" :value="$lesson->title" required maxlength="180" />
<x-textarea name="content" label="Lesson content" :value="$lesson->content" rows="16" required maxlength="100000" hint="Plain text with line breaks. HTML is displayed as text, keeping lessons safe to read." />
<div class="form-actions"><button class="btn btn-primary" type="submit">{{ $lesson->exists ? 'Save lesson' : 'Create draft lesson' }}</button><a class="btn btn-outline-secondary" href="{{ route('courses.show', $course) }}">Cancel</a></div>
