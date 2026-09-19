<?php

namespace App\Support;

/**
 * Filter kata kasar untuk komentar rating.
 *
 * Sengaja sederhana dan deterministik (bukan ML): tujuannya menyaring komentar
 * yang jelas-jelas tidak layak tampil, lalu menyisakannya untuk moderasi manual
 * admin. Komentar yang tersaring TIDAK dibuang — hanya diarahkan ke antrean
 * moderasi dengan catatan, supaya pemilik situs tetap bisa memutuskan.
 */
class ProfanityFilter
{
    /**
     * Daftar kata dasar. Pencocokan memakai batas kata agar "kasar" tidak
     * memicu "kasari", dan varian berulang (a***) tetap tertangkap lewat
     * normalisasi karakter berulang.
     */
    public const WORDS = [
        'anjing', 'anjir', 'bangsat', 'babi', 'bajingan', 'bedebah', 'bego', 'bocah',
        'brengsek', 'burik', 'cibai', 'cok', 'goblok', 'goblog', 'idiot', 'jancuk',
        'keparat', 'kontol', 'kunyuk', 'memek', 'monyet', 'ngehe', 'ngentot', 'pantek',
        'pepek', 'pukimak', 'setan', 'sialan', 'tai', 'tolol', 'tolo', 'titit',
        'bokep', 'kimak', 'jembut', 'tempik', 'pelacur', 'sundal', 'fuck', 'shit',
        'bitch', 'asshole', 'bastard', 'dick', 'pussy', 'cunt', 'whore', 'scam',
        'penipu', 'tipu', 'bodong',
    ];

    public static function contains(string $text): bool
    {
        $normalized = static::normalize($text);

        if ($normalized === '') {
            return false;
        }

        foreach (static::WORDS as $word) {
            if (preg_match('/(?<![a-z])'.preg_quote($word, '/').'(?![a-z])/', $normalized) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<int, string> kata kasar yang terdeteksi
     */
    public static function matches(string $text): array
    {
        $normalized = static::normalize($text);

        if ($normalized === '') {
            return [];
        }

        return array_values(array_filter(
            static::WORDS,
            fn (string $word): bool => preg_match('/(?<![a-z])'.preg_quote($word, '/').'(?![a-z])/', $normalized) === 1,
        ));
    }

    /**
     * Huruf kecil, buang aksen, dan rangkai ulang huruf berulang supaya
     * "anjiiing" / "b4ngsat" tetap terbaca sebagai kata yang sama.
     */
    protected static function normalize(string $text): string
    {
        $text = mb_strtolower($text);
        $text = str_replace(['4', '3', '1', '0', '5', '7', '@', '$'], ['a', 'e', 'i', 'o', 's', 't', 'a', 's'], $text);
        $text = preg_replace('/[^a-z\s]/', ' ', $text) ?? '';
        $text = preg_replace('/([a-z])\1{2,}/', '$1', $text) ?? '';

        return trim(preg_replace('/\s+/', ' ', $text) ?? '');
    }
}
