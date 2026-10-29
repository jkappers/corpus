<?php

namespace App\Http\Requests;

use App\Enums\AttachmentKind;
use App\Models\Attachment;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;

class StoreAttachmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', [Attachment::class, $this->route('page')]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $extension = $this->file('file')?->getClientOriginalExtension() ?? '';

        return [
            'file' => [
                'required',
                File::types(AttachmentKind::acceptedExtensions())
                    ->max(AttachmentKind::maxKilobytesFor($extension)),
            ],
        ];
    }
}
