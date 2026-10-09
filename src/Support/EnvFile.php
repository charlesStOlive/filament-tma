<?php

namespace CharlesStOlive\FilamentTma\Support;

/** Écrire des variables dans le `.env` de l'application : remplacer la ligne si elle existe, l'ajouter sinon. */
final class EnvFile
{
    public static function path(): string
    {
        return app()->environmentFilePath();
    }

    public static function writable(): bool
    {
        return is_file(self::path()) && is_writable(self::path());
    }

    /** @param  array<string, string>  $values */
    public static function set(array $values): void
    {
        $content = (string) file_get_contents(self::path());

        foreach ($values as $key => $value) {
            $line = $key.'='.self::quote($value);
            $pattern = '/^'.preg_quote($key, '/').'=.*$/m';

            $content = preg_match($pattern, $content)
                ? preg_replace($pattern, str_replace(['\\', '$'], ['\\\\', '\\$'], $line), $content)
                : rtrim($content, "\n")."\n".$line."\n";
        }

        file_put_contents(self::path(), $content);
    }

    /** @param  array<string, string>  $values */
    public static function lines(array $values): string
    {
        return collect($values)->map(fn (string $value, string $key): string => $key.'='.self::quote($value))->implode("\n");
    }

    private static function quote(string $value): string
    {
        return preg_match('/[\s#"\'=]/', $value) ? '"'.addcslashes($value, '"\\').'"' : $value;
    }
}
