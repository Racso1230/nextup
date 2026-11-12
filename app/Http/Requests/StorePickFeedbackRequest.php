<?php

namespace App\Http\Requests;

use App\Enums\FeedbackKind;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePickFeedbackRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'kind' => ['required', 'string', Rule::enum(FeedbackKind::class)],
        ];
    }

    public function kind(): FeedbackKind
    {
        return FeedbackKind::from($this->string('kind')->toString());
    }
}
