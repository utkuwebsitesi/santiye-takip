<?php

namespace App\Http\Controllers;

use App\Models\AppSetting;
use App\Models\NavigationItem;
use App\Models\TransactionCategory;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SystemManagementController extends Controller
{
    public function index(): View
    {
        $settings = AppSetting::pluck('value', 'key');
        if (($settings['software_name'] ?? null) === 'Şantiye360') {
            $settings['software_name'] = 'Şantiye Takip';
        }

        $backups = collect(File::glob(config('backup.directory').DIRECTORY_SEPARATOR.'santiye360-*.sql.gz') ?: [])
            ->map(fn (string $path) => [
                'filename' => basename($path),
                'size' => File::size($path),
                'created_at' => File::lastModified($path),
                'verified' => is_file($path.'.sha256'),
            ])
            ->sortByDesc('created_at')
            ->take(10)
            ->values();

        return view('system.index', [
            'settings' => $settings,
            'categories' => TransactionCategory::orderBy('type')->orderBy('sort_order')->get(),
            'navigationItems' => NavigationItem::orderBy('sort_order')->get(),
            'backups' => $backups,
        ]);
    }

    public function updateSettings(Request $request, AuditService $audit): RedirectResponse
    {
        $data = $request->validate([
            'software_name' => ['required', 'string', 'max:60'],
            'software_tagline' => ['required', 'string', 'max:100'],
            'company_name' => ['required', 'string', 'max:150'],
        ]);

        foreach ($data as $key => $value) {
            $setting = AppSetting::firstOrCreate(['key' => $key], ['value' => $value]);
            $audit->update($setting, ['value' => trim($value)], 'Sistem ayarı güncellendi.');
        }

        return back()->with('success', 'Yazılım ve şirket bilgileri güncellendi.');
    }

    public function storeCategory(Request $request, AuditService $audit): RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(['income', 'expense'])],
            'name' => ['required', 'string', 'max:100', Rule::unique('transaction_categories')->where('type', $request->input('type'))],
        ]);
        $data['sort_order'] = TransactionCategory::where('type', $data['type'])->max('sort_order') + 10;
        $category = TransactionCategory::create($data);
        $audit->created($category, 'İşlem kategorisi oluşturuldu.');

        return back()->with('success', 'Kategori eklendi.');
    }

    public function updateCategory(Request $request, TransactionCategory $category, AuditService $audit): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('transaction_categories')->where('type', $category->type)->ignore($category)],
            'is_active' => ['nullable', 'boolean'],
        ]);
        $data['is_active'] = $request->boolean('is_active');
        $audit->update($category, $data, 'İşlem kategorisi güncellendi.');

        return back()->with('success', 'Kategori güncellendi.');
    }

    public function updateNavigation(Request $request, AuditService $audit): RedirectResponse
    {
        $data = $request->validate([
            'items' => ['required', 'array'],
            'items.*.label' => ['required', 'string', 'max:80'],
            'items.*.sort_order' => ['required', 'integer', 'between:0,1000'],
            'items.*.is_enabled' => ['nullable', 'boolean'],
        ]);

        foreach ($data['items'] as $id => $values) {
            $item = NavigationItem::findOrFail($id);
            $values['is_enabled'] = (bool) ($values['is_enabled'] ?? false);
            $audit->update($item, $values, 'Menü bölümü güncellendi.');
        }

        return back()->with('success', 'Menü bölümleri güncellendi.');
    }
}
