<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VehicleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() === true;
    }

    protected function prepareForValidation(): void
    {
        $type = $this->input('type');
        $this->merge([
            'plate' => $type === 'vehicle' ? mb_strtoupper(trim((string) $this->input('plate')), 'UTF-8') : null,
            'code' => $type === 'machine' ? mb_strtoupper(trim((string) ($this->input('code') ?? $this->input('machine_code'))), 'UTF-8') : null,
        ]);
    }

    public function rules(): array
    {
        $vehicle = $this->route('vehicle');

        return [
            'type' => ['required', Rule::in(['vehicle', 'machine'])],
            'name' => ['required', 'string', 'max:150'],
            'plate' => [
                Rule::requiredIf($this->input('type') === 'vehicle'),
                'nullable', 'string', 'max:20',
                Rule::unique('vehicles', 'plate')->ignore($vehicle),
            ],
            'code' => [
                Rule::requiredIf($this->input('type') === 'machine'),
                'nullable', 'string', 'max:50',
                Rule::unique('vehicles', 'code')->ignore($vehicle),
            ],
            'is_active' => ['nullable', 'boolean'],
            'tracks_meters' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'plate.required' => 'Araç için plaka zorunludur.',
            'plate.unique' => 'Bu plaka daha önce kaydedilmiş.',
            'code.required' => 'Makine için makine kodu zorunludur.',
            'code.unique' => 'Bu makine kodu daha önce kaydedilmiş.',
            'name.required' => 'Araç veya makine adı zorunludur.',
        ];
    }

    public function normalized(): array
    {
        $data = $this->validated();
        $data['is_active'] = $this->boolean('is_active');
        $data['tracks_meters'] = $this->has('tracks_meters')
            ? $this->boolean('tracks_meters')
            : ($this->route('vehicle')?->tracks_meters ?? true);
        $data['plate'] = $data['type'] === 'vehicle' ? $data['plate'] : null;
        $data['code'] = $data['type'] === 'machine' ? $data['code'] : null;
        $data['tracking_unit'] = $this->route('vehicle')?->tracking_unit ?? 'km';

        return $data;
    }
}
