<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;

/**
 * The file's content must be the kind its name says. A script renamed to
 * "homework.pdf" passes the extension check and, being text, can pass the
 * content check on its own; together they must agree.
 *
 * Some kinds cannot be told apart by content and are treated as one:
 * a CSV is plain text, and Office documents are zip archives.
 */
class FileContentMatchesExtension implements ValidationRule
{
    /** @var list<list<string>> */
    private const SAME_KIND = [
        ['jpg', 'jpeg'],
        ['txt', 'csv'],
        ['zip', 'docx', 'xlsx', 'pptx'],
        ['doc', 'xls', 'ppt', 'bin'],
    ];

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile) {
            return;
        }

        $claimed = strtolower($value->getClientOriginalExtension());
        $detected = strtolower((string) $value->guessExtension());

        if ($claimed === $detected || $this->sameKind($claimed, $detected)) {
            return;
        }

        $fail("The file's content does not match its .{$claimed} name.");
    }

    private function sameKind(string $a, string $b): bool
    {
        foreach (self::SAME_KIND as $group) {
            if (in_array($a, $group, true) && in_array($b, $group, true)) {
                return true;
            }
        }

        return false;
    }
}
