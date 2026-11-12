<?php

namespace App\Http\Requests;

use App\Recommender\Company;
use App\Recommender\Mood;
use App\Recommender\SessionContext;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Query string for the Play next page: how long, what mood, with whom. Nothing here is ever rejected: a bad
 * or missing value falls back to its default, so a stale or hand-edited URL still shows a sensible page.
 */
class PlayNextRequest extends FormRequest
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
        return [];
    }

    public function sessionContext(): SessionContext
    {
        return new SessionContext(
            hours: $this->hours(),
            mood: $this->mood(),
            company: $this->company(),
        );
    }

    /**
     * The current choices, as the page's controls show them.
     *
     * @return array{hours: int, mood: ?string, with: ?string}
     */
    public function filters(): array
    {
        return [
            'hours' => (int) $this->hours(),
            'mood' => $this->mood()?->value,
            'with' => $this->company()?->value,
        ];
    }

    private function hours(): float
    {
        $value = $this->rawString('hours');

        foreach (SessionContext::HOUR_CHOICES as $choice) {
            if ($value === (string) (int) $choice) {
                return $choice;
            }
        }

        return SessionContext::DEFAULT_HOURS;
    }

    private function mood(): ?Mood
    {
        $value = $this->rawString('mood');

        return $value === null ? null : Mood::tryFrom($value);
    }

    private function company(): ?Company
    {
        $value = $this->rawString('with');

        return $value === null ? null : Company::tryFrom($value);
    }

    private function rawString(string $key): ?string
    {
        $value = $this->query($key);

        return is_string($value) ? $value : null;
    }
}
