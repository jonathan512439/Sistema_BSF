<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GeneratePDFRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Solo archivistas pueden generar PDFs
        return $this->user() && in_array($this->user()->role, ['archivist', 'superadmin']);
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'images' => [
                'required',
                'array',
                'min:1',
                // SIN LÍMITE de páginas - permitir todas las necesarias
            ],
            'images.*' => [
                'required',
                'image',
                'mimes:jpeg,jpg,png',
                // SIN LÍMITE de tamaño por imagen
            ],
            'paper_size' => [
                'sometimes',
                Rule::in(['A4', 'Letter', 'Legal']),
            ],
            'orientation' => [
                'sometimes',
                Rule::in(['portrait', 'landscape']),
            ],
            'quality' => [
                'sometimes',
                'integer',
                'min:1',
                'max:100',
            ],
            'rotations' => [
                'sometimes',
                'array',
            ],
            'rotations.*' => [
                'integer',
                Rule::in([0, 90, 180, 270]),
            ],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'images.required' => 'Debe proporcionar al menos una imagen',
            'images.min' => 'Debe proporcionar al menos  una imagen',
            // Límite de imágenes removido
            'images.*.image' => 'Todos los archivos deben ser imágenes',
            'images.*.mimes' => 'Solo se permiten imágenes JPG, JPEG o PNG',
            // Límite de tamaño removido
            'paper_size.in' => 'Tamaño de papel no válido',
            'orientation.in' => 'Orientación no válida',
            'quality.integer' => 'La calidad debe ser un número',
            'quality.min' => 'La calidad mínima es 1',
            'quality.max' => 'La calidad máxima es 100',
            'rotations.*.in' => 'Rotación debe ser 0, 90, 180 o 270 grados',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Establecer valores por defecto
        $this->merge([
            'paper_size' => $this->input('paper_size', 'A4'),
            'orientation' => $this->input('orientation', 'portrait'),
            'quality' => $this->input('quality', 85),
            'rotations' => $this->input('rotations', []),
        ]);
    }
}
