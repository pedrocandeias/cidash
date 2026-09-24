<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class PersonRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $required = $this->isMethod('post') ? 'required' : 'sometimes';

        return [
            'name' => [$required, 'string', 'max:255'],
            'academic_title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'affiliation' => ['sometimes', 'nullable', 'string', 'max:255'],
            'short_bio' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'bio' => ['sometimes', 'nullable', 'string', 'max:20000'],
            'keywords' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'languages' => ['sometimes', 'nullable', 'string', 'max:255'],
            'email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:50'],
            'media_notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'consent_at' => ['sometimes', 'nullable', 'date'],
            'last_reviewed_at' => ['sometimes', 'nullable', 'date'],
            'areas' => ['sometimes', 'array', 'max:20'],
            'areas.*' => ['string', 'max:100'],
            'photo' => ['sometimes', 'nullable', 'image', 'max:4096'],
            'remove_photo' => ['sometimes', 'boolean'],
            'career' => ['sometimes', 'nullable', 'string', 'max:20000'],
            'cv' => ['sometimes', 'nullable', 'file', 'max:10240', 'extensions:pdf,doc,docx,odt'],
            'remove_cv' => ['sometimes', 'boolean'],
            'obituary' => ['sometimes', 'nullable', 'string', 'max:20000'],
            'deceased_on' => ['sometimes', 'nullable', 'date', 'before_or_equal:today'],
        ];
    }
}
