<?php

if (!function_exists('mi_text_normalize')) {
    function mi_text_normalize(string $value): string {
        $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $map = [
            'İ' => 'i', 'I' => 'i', 'ı' => 'i', 'Ğ' => 'g', 'ğ' => 'g',
            'Ü' => 'u', 'ü' => 'u', 'Ş' => 's', 'ş' => 's', 'Ö' => 'o',
            'ö' => 'o', 'Ç' => 'c', 'ç' => 'c',
        ];
        $value = strtr($value, $map);
        $value = mb_strtolower($value, 'UTF-8');
        $value = preg_replace('/[^a-z0-9]+/u', ' ', $value) ?? '';
        return trim(preg_replace('/\s+/', ' ', $value) ?? '');
    }
}

if (!function_exists('mi_compact_key')) {
    function mi_compact_key(string $value): string {
        return preg_replace('/[^a-z0-9]+/', '', mi_text_normalize($value)) ?? '';
    }
}

if (!function_exists('mi_slugify')) {
    function mi_slugify(string $value): string {
        $slug = preg_replace('/[^a-z0-9]+/', '-', mi_text_normalize($value)) ?? '';
        $slug = trim($slug, '-');
        return $slug !== '' ? $slug : 'market-' . time();
    }
}

if (!function_exists('mi_market_stop_words')) {
    function mi_market_stop_words(): array {
        return [
            'market', 'supermarket', 'hipermarket', 'grospermarket', 'grosper',
            'marketler', 'marketleri', 'toptan', 'satis', 'magazalari',
            'avm', 'gida', 'gross', 'gros', 'cash', 'carry',
        ];
    }
}

if (!function_exists('mi_market_alias_map')) {
    function mi_market_alias_map(): array {
        return [
            'a101' => ['a101'],
            'bim' => ['bim'],
            'sok' => ['sok', 'sokmarket'],
            'carrefoursa' => ['carrefoursa', 'carrefour'],
            'file' => ['file', 'filemarket'],
            'hakmar-ekspres' => ['hakmarekspres', 'hakmarexpress', 'hakmarexpres'],
            'hakmar' => ['hakmar'],
            'macrocenter' => ['macrocenter', 'macro'],
            'metro' => ['metro', 'metromarket', 'metrotr'],
            'migros' => ['migros', 'migroskop', '5mmigros'],
            'bizim-toptan' => ['bizimtoptan', 'bizimtoptansatis', 'bizimtoptansatismagazalari'],
            'tespo-cash-carry' => ['tespo', 'tespocashcarry'],
            'show-hipermarket' => ['show', 'showmarket', 'showhipermarket'],
            'essen-market' => ['essen', 'essenavm', 'essenmarket'],
            'snowy-ulu-kardesler' => ['snowy', 'snowymarket', 'ulukardesler', 'snowyulukardesler'],
            'pehlivanoglu' => ['muharrempehlivanoglu', 'pehlivanoglu'],
            'hanifi-pehlivanoglu' => ['hanifipehlivanoglu'],
            'basgimpa' => ['basgimpa', 'basgimpasupermarket'],
            'basdas-market' => ['basdas', 'basdasmarket'],
            'bicen-market' => ['bicen', 'bicenmarket'],
            'cagri-market' => ['cagri', 'cagrihipermarket', 'cagrimarket'],
            'cagdas-market' => ['cagdas', 'cagdasmarket'],
            'baris-gross-market' => ['barisgross', 'barisgrossmarket'],
            'akyurt-supermarket' => ['akyurt', 'akyurtsupermarket'],
            'altun-market' => ['altun', 'altunmarket'],
            'altunbilekler-market' => ['altunbilekler', 'altunbileklermarket'],
            'arden-market' => ['arden', 'ardenmarket'],
            'aypa-market' => ['aypa', 'aypamarket'],
            'esenlik-market' => ['esenlik', 'esenlikmarket'],
            'onur-market' => ['onur', 'onurmarket'],
            'seyhanlar-market' => ['seyhanlar', 'seyhanlarmarket'],
            'sehzade-market' => ['sehzade', 'sehzademarket'],
            'ucler-market' => ['ucler', 'uclermarket'],
            'yunus-market' => ['yunus', 'yunusmarket'],
            'tahtakale-spot' => ['tahtakale', 'tahtakalespot'],
            'kim' => ['kim', 'kimmarket'],
            'mopas' => ['mopas'],
            'groseri' => ['groseri'],
            'gurmar' => ['gurmar'],
            'ideal' => ['ideal', 'idealmarket'],
            'ozkuruslar' => ['ozkuruslar', 'ozkuruslargida'],
            'pekdemir' => ['pekdemir', 'pekdemirmarket'],
        ];
    }
}

if (!function_exists('mi_market_aliases')) {
    function mi_market_aliases(array $market): array {
        $slug = (string)($market['slug'] ?? '');
        $name = (string)($market['name'] ?? '');
        $aliases = [mi_compact_key($slug), mi_compact_key($name)];

        $clean_name = mi_text_normalize($name);
        foreach (mi_market_stop_words() as $word) {
            $clean_name = preg_replace('/\b' . preg_quote($word, '/') . '\b/u', ' ', $clean_name) ?? $clean_name;
        }
        $aliases[] = mi_compact_key($clean_name);

        $known = mi_market_alias_map();
        if (isset($known[$slug])) {
            $aliases = array_merge($aliases, $known[$slug]);
        }

        $aliases = array_values(array_unique(array_filter($aliases, fn($a) => strlen($a) >= 2)));
        usort($aliases, fn($a, $b) => strlen($b) <=> strlen($a));
        return $aliases;
    }
}

if (!function_exists('mi_find_market_by_name')) {
    function mi_find_market_by_name(string $raw_name, array $markets): ?array {
        $raw_key = mi_compact_key($raw_name);
        if ($raw_key === '') return null;

        $known = mi_market_alias_map();
        foreach ($known as $slug => $aliases) {
            if (in_array($raw_key, $aliases, true)) {
                foreach ($markets as $market) {
                    if (($market['slug'] ?? '') === $slug) {
                        return $market;
                    }
                }
            }
        }

        foreach ($markets as $market) {
            foreach (mi_market_aliases($market) as $alias) {
                if ($raw_key === $alias) {
                    return $market;
                }
            }
        }

        foreach ($markets as $market) {
            foreach (mi_market_aliases($market) as $alias) {
                if (strlen($alias) >= 4 && (str_contains($raw_key, $alias) || str_contains($alias, $raw_key))) {
                    return $market;
                }
            }
        }

        return null;
    }
}

if (!function_exists('mi_market_canonical_key')) {
    function mi_market_canonical_key(array $market): string {
        $aliases = mi_market_aliases($market);
        $key = end($aliases) ?: mi_compact_key((string)($market['name'] ?? ''));

        foreach (mi_market_alias_map() as $slug => $known_aliases) {
            if (($market['slug'] ?? '') === $slug || in_array($key, $known_aliases, true)) {
                return $slug;
            }
        }

        return $key;
    }
}

if (!function_exists('mi_parse_price')) {
    function mi_parse_price($value): ?float {
        if ($value === null) return null;
        $text = trim((string)$value);
        if ($text === '') return null;
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/[^0-9,.\-]+/u', '', $text) ?? '';
        if ($text === '') return null;

        if (str_contains($text, ',') && str_contains($text, '.')) {
            $text = str_replace('.', '', $text);
            $text = str_replace(',', '.', $text);
        } elseif (str_contains($text, ',')) {
            $text = str_replace(',', '.', $text);
        }

        return is_numeric($text) ? (float)$text : null;
    }
}

if (!function_exists('mi_product_tokens')) {
    function mi_product_tokens(string $name): array {
        $normalized = mi_text_normalize($name);
        $stop = [
            've', 'ile', 'icin', 'ozel', 'cesitleri', 'cesit', 'adet', 'li', 'lu',
            'gr', 'g', 'kg', 'ml', 'lt', 'l', 'litre', 'gram', 'paket', 'boy',
            'buyuk', 'kucuk', 'orta', 'kampanya', 'indirim', 'urun', 'urunleri',
        ];
        $tokens = preg_split('/\s+/', $normalized) ?: [];
        $tokens = array_values(array_filter($tokens, function($token) use ($stop) {
            if (strlen($token) < 2) return false;
            if (in_array($token, $stop, true)) return false;
            return true;
        }));
        return array_values(array_unique($tokens));
    }
}

if (!function_exists('mi_product_measures')) {
    function mi_product_measures(string $name): array {
        $text = mi_text_normalize($name);
        preg_match_all('/(\d+(?:[.,]\d+)?)\s*(kg|gr|g|ml|lt|l|litre|adet|li|lu|lu|paket)\b/u', $text, $matches, PREG_SET_ORDER);
        $measures = [];
        foreach ($matches as $m) {
            $num = str_replace(',', '.', $m[1]);
            $unit = $m[2];
            if ($unit === 'g') $unit = 'gr';
            if ($unit === 'l') $unit = 'lt';
            $measures[] = rtrim(rtrim($num, '0'), '.') . $unit;
        }
        return array_values(array_unique($measures));
    }
}

if (!function_exists('mi_product_match_score')) {
    function mi_product_match_score(string $query, string $candidate): int {
        $q_compact = mi_compact_key($query);
        $c_compact = mi_compact_key($candidate);
        if ($q_compact === '' || $c_compact === '') return 0;
        if ($q_compact === $c_compact) return 100;

        $q_tokens = mi_product_tokens($query);
        $c_tokens = mi_product_tokens($candidate);
        if (!$q_tokens || !$c_tokens) return 0;

        $common = array_intersect($q_tokens, $c_tokens);
        $union = array_unique(array_merge($q_tokens, $c_tokens));
        $score = count($union) ? (count($common) / count($union)) * 55 : 0;

        if (str_contains($c_compact, $q_compact) || str_contains($q_compact, $c_compact)) {
            $score += 18;
        }

        if (($q_tokens[0] ?? '') !== '' && ($q_tokens[0] ?? '') === ($c_tokens[0] ?? '')) {
            $score += 12;
        }

        $q_measures = mi_product_measures($query);
        $c_measures = mi_product_measures($candidate);
        if ($q_measures && $c_measures) {
            $measure_common = array_intersect($q_measures, $c_measures);
            $score += $measure_common ? 15 : -18;
        }

        similar_text($q_compact, $c_compact, $pct);
        $score += min(15, $pct / 7);

        if (count($common) < 2 && $score < 72) {
            $score -= 20;
        }

        return max(0, min(100, (int)round($score)));
    }
}

if (!function_exists('mi_is_amazon_brochure')) {
    function mi_is_amazon_brochure(array $brochure): bool {
        return (($brochure['source_name'] ?? '') === 'amazon')
            || (($brochure['market_slug'] ?? '') === 'amazon');
    }
}

if (!function_exists('mi_brochure_cover_src')) {
    function mi_brochure_cover_src(?string $cover_image): string {
        $cover_image = trim((string)$cover_image);
        if ($cover_image === '') {
            return '';
        }

        if (str_starts_with($cover_image, 'http://') || str_starts_with($cover_image, 'https://') || str_starts_with($cover_image, 'data:image')) {
            return $cover_image;
        }

        $cover_image = ltrim($cover_image, '/');
        if (str_starts_with($cover_image, 'uploads/brochures/')) {
            return $cover_image;
        }

        return 'uploads/brochures/' . $cover_image;
    }
}

if (!function_exists('mi_price_label')) {
    function mi_price_label($price): string {
        if ($price === null || $price === '') {
            return '';
        }

        $parsed = is_numeric($price) ? (float)$price : mi_parse_price($price);
        if ($parsed === null) {
            return '';
        }

        return number_format($parsed, 2, ',', '.') . ' TL';
    }
}

if (!function_exists('mi_rating_label')) {
    function mi_rating_label($rating): string {
        if ($rating === null || $rating === '') {
            return '';
        }

        $text = str_replace(',', '.', (string)$rating);
        if (preg_match('/\d+(?:\.\d+)?/', $text, $m)) {
            return number_format((float)$m[0], 1, ',', '.') . '/5';
        }

        return trim((string)$rating);
    }
}
