<?php

namespace App\Http\Requests;

use App\Enums\LibraryStatus;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Query string for the library page. Nothing here is ever rejected: a bad or missing value falls back to
 * its default, so a stale or hand-edited URL still shows a sensible page.
 */
class LibraryIndexRequest extends FormRequest
{
    public const array SORTS = ['name', 'playtime', 'last_played'];

    public const int MAX_QUERY_LENGTH = 100;

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

    /** @return 'name'|'playtime'|'last_played' */
    public function sortColumn(): string
    {
        return match ($this->rawString('sort')) {
            'name' => 'name',
            'playtime' => 'playtime',
            default => 'last_played',
        };
    }

    /** @return 'asc'|'desc' */
    public function sortDirection(): string
    {
        return match ($this->rawString('direction')) {
            'asc' => 'asc',
            'desc' => 'desc',
            default => $this->sortColumn() === 'name' ? 'asc' : 'desc',
        };
    }

    public function statusFilter(): ?LibraryStatus
    {
        $value = $this->rawString('status');

        return $value === null ? null : LibraryStatus::tryFrom($value);
    }

    /** The trimmed search text, or null when empty or too long to be real. */
    public function searchTerm(): ?string
    {
        $value = $this->rawString('q');

        if ($value === null) {
            return null;
        }

        $value = trim($value);

        if ($value === '' || mb_strlen($value) > self::MAX_QUERY_LENGTH) {
            return null;
        }

        return $value;
    }

    private function rawString(string $key): ?string
    {
        $value = $this->query($key);

        return is_string($value) ? $value : null;
    }
}
