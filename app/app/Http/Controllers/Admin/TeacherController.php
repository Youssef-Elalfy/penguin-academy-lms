<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Accounts\TeacherAccounts;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTeacherRequest;
use App\Http\Requests\UpdateTeacherRequest;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class TeacherController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', User::class);
        $search = $request->validate(['q' => ['nullable', 'string', 'max:120']])['q'] ?? '';
        $teachers = User::where('role', UserRole::Teacher)
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->where('name', 'like', '%'.$search.'%')->orWhere('email', 'like', '%'.$search.'%')))
            ->withCount('taughtCourses')->orderBy('name')->paginate(12)->withQueryString();

        return view('admin.teachers.index', compact('teachers', 'search'));
    }

    public function create(): View
    {
        Gate::authorize('create', User::class);

        return view('admin.teachers.create');
    }

    public function store(StoreTeacherRequest $request, TeacherAccounts $accounts): RedirectResponse
    {
        $teacher = $accounts->create($request->validated());

        return redirect()->route('admin.teachers.edit', $teacher)
            ->with('status', 'Teacher account created. Share the temporary password privately; it must be changed at first sign-in.');
    }

    public function edit(User $teacher): View
    {
        Gate::authorize('update', $teacher);

        return view('admin.teachers.edit', compact('teacher'));
    }

    public function update(UpdateTeacherRequest $request, User $teacher, TeacherAccounts $accounts): RedirectResponse
    {
        $accounts->update($teacher, $request->validated());

        return redirect()->route('admin.teachers.edit', $teacher)->with('status', 'Teacher account updated.');
    }
}
