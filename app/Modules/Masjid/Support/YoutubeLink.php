<?php

namespace App\Modules\Masjid\Support;

/**
 * Mengambil id video dari tautan YouTube: watch, youtu.be, shorts, live, dan embed.
 */
final class YoutubeLink
{
    public static function id(string $url): ?string
    {
        $parts = parse_url(trim($url));
        $host = strtolower(preg_replace('/^(www\.|m\.|music\.)/', '', (string) ($parts['host'] ?? '')));
        $path = trim((string) ($parts['path'] ?? ''), '/');
        $id = null;

        if ($host === 'youtu.be') {
            $id = explode('/', $path)[0];
        } elseif (in_array($host, ['youtube.com', 'youtube-nocookie.com'], true)) {
            parse_str((string) ($parts['query'] ?? ''), $query);
            $segments = explode('/', $path);
            $id = $segments[0] === 'watch'
                ? ($query['v'] ?? null)
                : (in_array($segments[0], ['shorts', 'live', 'embed', 'v'], true) ? ($segments[1] ?? null) : null);
        }

        return is_string($id) && preg_match('/^[A-Za-z0-9_-]{11}$/', $id) ? $id : null;
    }
}
