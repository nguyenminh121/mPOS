<?php

namespace App\Services\Tenant;

use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Str;

class ProductCodeService
{
    public function generateForName(User $user, string $name): string
    {
        $baseCode = $this->buildCodePrefix($name);
        $nextSequence = $this->resolveNextSequence($user, $baseCode);

        return $baseCode . str_pad((string) $nextSequence, 3, '0', STR_PAD_LEFT);
    }

    private function buildCodePrefix(string $name): string
    {
        $sanitizedName = $this->removeIgnoredSegments($name);
        $tokens = preg_split('/\s+/u', trim($sanitizedName)) ?: [];
        $prefix = '';

        foreach ($tokens as $token) {
            if ($token === '') {
                continue;
            }

            $cleanToken = preg_replace('/[^\pL\pN]/u', '', $token) ?? '';
            if ($cleanToken === '') {
                continue;
            }

            $asciiToken = Str::ascii($cleanToken, 'vi');
            $asciiToken = preg_replace('/[^A-Za-z0-9]/', '', $asciiToken) ?? '';
            if ($asciiToken === '') {
                continue;
            }

            if (preg_match('/^\d/', $asciiToken) === 1) {
                $prefix .= $asciiToken;
                continue;
            }

            $prefix .= strtoupper($asciiToken[0]);
        }

        $prefix = trim($prefix);
        if ($prefix === '') {
            $prefix = 'PRD';
        }

        return mb_substr($prefix, 0, 97);
    }

    private function removeIgnoredSegments(string $name): string
    {
        $withoutParentheses = preg_replace('/\([^)]*\)/u', ' ', $name) ?? $name;
        $withoutDoubleQuotes = preg_replace('/"[^"]*"/u', ' ', $withoutParentheses) ?? $withoutParentheses;
        $withoutSingleQuotes = preg_replace('/\'[^\']*\'/u', ' ', $withoutDoubleQuotes) ?? $withoutDoubleQuotes;

        $withoutSmartDoubleQuotes = preg_replace('/“[^”]*”/u', ' ', $withoutSingleQuotes) ?? $withoutSingleQuotes;
        $withoutSmartSingleQuotes = preg_replace('/‘[^’]*’/u', ' ', $withoutSmartDoubleQuotes) ?? $withoutSmartDoubleQuotes;

        return preg_replace('/\s+/u', ' ', trim($withoutSmartSingleQuotes)) ?? trim($withoutSmartSingleQuotes);
    }

    private function resolveNextSequence(User $user, string $baseCode): int
    {
        $candidates = Product::query()
            ->withTrashed()
            ->where('tenant_id', $user->tenant_id)
            ->where(function ($query) use ($baseCode) {
                $query->where('sku', 'like', $baseCode . '%')
                    ->orWhere('barcode', 'like', $baseCode . '%');
            })
            ->get(['sku', 'barcode']);

        $pattern = '/^' . preg_quote($baseCode, '/') . '(\d{3})$/i';
        $maxSequence = 0;

        foreach ($candidates as $candidate) {
            foreach ([$candidate->sku, $candidate->barcode] as $value) {
                if (! is_string($value) || $value === '') {
                    continue;
                }

                if (preg_match($pattern, $value, $matches) === 1) {
                    $maxSequence = max($maxSequence, (int) $matches[1]);
                }
            }
        }

        return $maxSequence + 1;
    }
}