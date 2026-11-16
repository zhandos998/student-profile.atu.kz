<?php

namespace App\Http\Controllers;

use App\Models\Faculty;
use App\Models\Specialty;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AcademicDirectoryController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorizeAdmin($request);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'faculty_id' => ['nullable', 'integer', 'exists:faculties,id'],
            'status' => ['nullable', Rule::in(['active', 'hidden', 'all'])],
        ]);

        $specialties = Specialty::query()
            ->with('faculty:id,name_ru')
            ->when($filters['faculty_id'] ?? null, fn ($query, $id) => $query->where('faculty_id', $id))
            ->when(($filters['status'] ?? 'active') !== 'all', fn ($query) => $query->where('is_active', ($filters['status'] ?? 'active') === 'active'))
            ->when($filters['search'] ?? null, function ($query, $search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('code', 'like', '%'.$search.'%')
                        ->orWhere('name_ru', 'like', '%'.$search.'%')
                        ->orWhere('name_kk', 'like', '%'.$search.'%')
                        ->orWhere('name_en', 'like', '%'.$search.'%');
                });
            })
            ->orderBy('code')->orderBy('name_ru')
            ->paginate(25)->withQueryString();

        return Inertia::render('AcademicDirectory/Index', [
            'faculties' => Faculty::query()->orderBy('name_ru')->get(['id', 'name_ru', 'name_kk', 'name_en']),
            'specialties' => $specialties,
            'filters' => [
                'search' => $filters['search'] ?? '',
                'faculty_id' => $filters['faculty_id'] ?? '',
                'status' => $filters['status'] ?? 'active',
            ],
        ]);
    }

    public function updateFaculty(Request $request, Faculty $faculty): RedirectResponse
    {
        $this->authorizeAdmin($request);
        $faculty->update($request->validate([
            'name_kk' => ['nullable', 'string', 'max:255'],
            'name_en' => ['nullable', 'string', 'max:255'],
        ]));

        return back()->with('status', 'Факультет обновлен.');
    }

    public function storeSpecialty(Request $request): RedirectResponse
    {
        $this->authorizeAdmin($request);
        $request->merge(['code' => strtoupper(trim((string) $request->input('code')))]);
        $data = $request->validate([
            'faculty_id' => ['nullable', 'integer', 'exists:faculties,id'],
            'code' => ['required', 'string', 'max:32', 'regex:/^[0-9A-Za-z]+$/', 'unique:specialties,code'],
            'name_ru' => ['required', 'string', 'max:255'],
            'name_kk' => ['nullable', 'string', 'max:255'],
            'name_en' => ['nullable', 'string', 'max:255'],
        ]);

        $data['code'] = strtoupper($data['code']);
        $data['value'] = $data['code'].' - '.trim($data['name_ru']);
        $data['is_active'] = true;
        $data['is_manual'] = true;
        Specialty::query()->create($data);

        return back()->with('status', 'Специальность добавлена.');
    }

    public function updateSpecialty(Request $request, Specialty $specialty): RedirectResponse
    {
        $this->authorizeAdmin($request);
        $data = $request->validate([
            'faculty_id' => ['nullable', 'integer', 'exists:faculties,id'],
            'name_ru' => ['required', 'string', 'max:255'],
            'name_kk' => ['nullable', 'string', 'max:255'],
            'name_en' => ['nullable', 'string', 'max:255'],
        ]);

        $specialty->update([...$data, 'is_manual' => true]);

        return back()->with('status', 'Специальность обновлена.');
    }

    public function toggleSpecialty(Request $request, Specialty $specialty): RedirectResponse
    {
        $this->authorizeAdmin($request);
        $specialty->update(['is_active' => ! $specialty->is_active]);

        return back()->with('status', $specialty->is_active ? 'Специальность показана.' : 'Специальность скрыта.');
    }

    private function authorizeAdmin(Request $request): void
    {
        abort_unless($request->user()?->canManageUsers(), 403);
    }
}
