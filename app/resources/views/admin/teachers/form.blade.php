@php($editing = isset($teacher))
<x-errors />
<x-input name="name" label="Full name" :value="$editing ? $teacher->name : ''" maxlength="120" autocomplete="off" required autofocus />
<x-input name="email" label="Email address" type="email" :value="$editing ? $teacher->email : ''" maxlength="255" autocomplete="off" required />
@if($editing)
<div class="mb-3"><label for="is_active" class="form-label">Account access</label><select id="is_active" name="is_active" class="form-select @error('is_active') is-invalid @enderror" required><option value="1" @selected((string) old('is_active', (int) $teacher->is_active) === '1')>Active — can sign in</option><option value="0" @selected((string) old('is_active', (int) $teacher->is_active) === '0')>Inactive — access blocked</option></select><div class="form-text">Deactivation ends existing sessions. Assigned courses are retained.</div>@error('is_active')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
@endif
<x-input name="password" :label="$editing ? 'New temporary password (optional)' : 'Temporary password'" type="password" autocomplete="new-password" minlength="12" maxlength="72" :required="!$editing" hint="At least 12 characters with uppercase, lowercase, and a number. The teacher must change this at first sign-in." />
<x-input name="password_confirmation" label="Confirm temporary password" type="password" autocomplete="new-password" :required="!$editing" />
<div class="form-divider"></div>
<x-input name="current_password" label="Your administrator password" type="password" autocomplete="current-password" required hint="Confirm your identity to save these account changes." />
<div class="d-flex flex-wrap gap-2 mt-4"><button class="btn btn-primary" type="submit">{{ $editing ? 'Save changes' : 'Create teacher account' }}</button><a href="{{ route('admin.teachers.index') }}" class="btn btn-outline-secondary">Cancel</a></div>
