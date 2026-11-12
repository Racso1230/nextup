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
    public const array SORTS = ['name', 'playtime', 'last_played', 'status'];

    public const int MAX_QUERY_LENGTH = 100;

    /** The extra `status` value for never-suggested games (not a derived status). */
    public const string HIDDEN = 'hidden';

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

    /** @return 'name'|'playtime'|'last_played'|'status' */
    public function sortColumn(): string
    {
        return match ($this->rawString('sort')) {
            'name' => 'name',
            'playtime' => 'playtime',
            'status' => 'status',
            default => 'last_played',
        };
    }

    /** @return 'asc'|'desc' */
    public function sortDirection(): string
    {
        return match ($this->rawString('direction')) {
            'asc' => 'asc',
            'desc' => 'desc',
            default => in_array($this->sortColumn(), ['name', 'status'], true) ? 'asc' : 'desc',
        };
    }

    /** The derived-status filter; null for no filter or for the separate "hidden" view. */
    public function statusFilter(): ?LibraryStatus
    {
        $value = $this->rawString('status');

        return $value === null ? null : LibraryStatus::tryFrom($value);
    }

    /** True for `status=hidden`: only the games the user said never to suggest. */
    public function hiddenOnly(): bool
    {
        return $this->rawString('status') === self::HIDDEN;
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
