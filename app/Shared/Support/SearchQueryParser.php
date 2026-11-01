<?php

namespace App\Shared\Support;

/**
 * Turns a buyer's free-text search into the facets a catalogue can filter on:
 * a keyword term, a place, and a naira budget.
 *
 * Deliberately deterministic and framework-free. The AI intent extractor runs on
 * the same message, and this class is what the search falls back to when the AI
 * provider is unavailable — so the fallback must not depend on the AI either.
 */
final class SearchQueryParser
{
    /**
     * Words that carry no product meaning on their own. They are dropped from
     * keyword tokens so "ankara for my wedding" searches ankara + wedding, and
     * from message parsing so a filler phrase never becomes the search term.
     */
    private const STOPWORDS = [
        'a', 'an', 'the', 'and', 'or', 'but', 'if', 'then',
        'i', 'me', 'my', 'mine', 'we', 'our', 'us', 'you', 'your', 'yours',
        'is', 'are', 'am', 'was', 'were', 'be', 'been', 'being',
        'do', 'does', 'did', 'have', 'has', 'had',
        'it', 'its', 'this', 'that', 'these', 'those',
        'of', 'to', 'in', 'on', 'at', 'for', 'from', 'by', 'with',
        'near', 'around', 'within', 'under', 'over', 'above', 'below',
        'please', 'kindly', 'thanks', 'thank',
        'looking', 'find', 'show', 'get', 'give', 'buy', 'purchase', 'order', 'search',
        'need', 'want', 'wish', 'would', 'like', 'something', 'anything', 'some', 'any',
        'where', 'what', 'which', 'who', 'how', 'when', 'why', 'there', 'here',
        'now', 'today', 'tomorrow', 'available', 'sale', 'stock', 'ready',
        'affordable', 'cheap', 'budget', 'quality', 'nice', 'good', 'best',
        'beautiful', 'lovely', 'great', 'fine', 'discount',
    ];

    /** Nigerian states, so a place no store lists yet is still recognised as a place. */
    private const STATES = [
        'Abia', 'Adamawa', 'Akwa Ibom', 'Anambra', 'Bauchi', 'Bayelsa', 'Benue', 'Borno',
        'Cross River', 'Delta', 'Ebonyi', 'Edo', 'Ekiti', 'Enugu', 'Gombe', 'Imo', 'Jigawa',
        'Kaduna', 'Kano', 'Kebbi', 'Kogi', 'Kwara', 'Lagos', 'Nasarawa', 'Niger', 'Ogun',
        'Ondo', 'Osun', 'Oyo', 'Plateau', 'Rivers', 'Sokoto', 'Taraba', 'Yobe', 'Zamfara',
        'FCT',
    ];

    /** Cities and districts buyers name that no state filter would ever catch. */
    private const CITIES = [
        'ikeja', 'lekki', 'victoria island', 'ikoyi', 'surulere', 'yaba', 'ajah', 'gbagada',
        'festac', 'maryland', 'ketu', 'berger', 'ojo', 'badagry', 'ikorodu', 'epe', 'mushin',
        'apapa', 'alimosho', 'agege', 'ogba', 'sangotedo', 'chevron', 'igando',
        'gwagwalada', 'gwarinpa', 'wuse', 'maitama', 'asokoro', 'jabi', 'lugbe', 'nyanya',
        'karu', 'kubwa', 'life camp', 'jahi',
        'port harcourt', 'obio-akpor', 'warri', 'benin city', 'onitsha', 'aba', 'oyo',
        'ibadan', 'ogbomoso', 'ilorin', 'abeokuta', 'ijebu-ode', 'ado-ekiti', 'osogbo',
        'akure', 'enugu', 'nsukka', 'awka', 'owerri', 'abakaliki', 'asaba', 'yenagoa',
        'calabar', 'uyo', 'jos', 'zaria', 'kano', 'kaduna', 'bauchi', 'gombe', 'maiduguri',
        'yola', 'jalingo', 'lafia', 'makurdi', 'minna', 'sokoto', 'katsina', 'gusau',
        'damaturu', 'birnin kebbi', 'gboko', 'otukpo',
    ];

    /** Spellings buyers type that are not the stored name of a place. */
    private const ALIASES = [
        'abuja' => 'fct',
        'fct' => 'fct',
        'federal capital territory' => 'fct',
        'ph' => 'port harcourt',
        'p h' => 'port harcourt',
        'lagos state' => 'lagos',
        'rivers state' => 'rivers',
    ];

    /**
     * Extract every facet the message supports.
     *
     * @param  array{states?: list<string>, cities?: list<string>}  $places
     * @return array{search_term: string, location: string, budget_min: int|null, budget_max: int|null}
     */
    public function parse(string $message, array $places = []): array
    {
        $text = ' '.preg_replace('/\s+/u', ' ', trim($message)).' ';
        $budgetMin = null;
        $budgetMax = null;

        // A range first: "between 10k and 20k", "from 10,000 to 20,000".
        if (preg_match('/\b(?:between|from)\s*(?:₦|ngn\s*|naira\s*)?(\d[\d.,]*)\s*(k|m|thousand|million)?\s*(?:and|to|-|–)\s*(?:₦|ngn\s*|naira\s*)?(\d[\d.,]*)\s*(k|m|thousand|million)?\b/iu', $text, $match)) {
            $budgetMin = $this->numberize($match[1], $match[2]);
            $budgetMax = $this->numberize($match[3], $match[4]);
            $text = str_replace($match[0], ' ', $text);
        } else {
            // A ceiling: "under 20k", "within 15,000", "budget of 30k".
            if (preg_match('/\b(?:under|below|less\s+than|within|up\s+to|max(?:imum)?|not\s+more\s+than|budget(?:\s+(?:of|is|around|about))?)\s*(?:₦|ngn\s*|naira\s*)?(\d[\d.,]*)\s*(k|m|thousand|million)?\b/iu', $text, $match)) {
                $budgetMax = $this->numberize($match[1], $match[2]);
                $text = str_replace($match[0], ' ', $text);
            }

            // A floor: "from 15k", "above 10,000", "at least 5k".
            if (preg_match('/\b(?:over|above|more\s+than|from|min(?:imum)?|at\s+least|not\s+less\s+than|starting(?:\s+from)?)\s*(?:₦|ngn\s*|naira\s*)?(\d[\d.,]*)\s*(k|m|thousand|million)?\b/iu', $text, $match)) {
                $budgetMin = $this->numberize($match[1], $match[2]);
                $text = str_replace($match[0], ' ', $text);
            }
        }

        // A bare price: "₦20,000" or "20k". Only when neither bound is known, so
        // "from ₦15,000" does not silently collapse into an exact-price filter.
        if ($budgetMin === null && $budgetMax === null) {
            if (preg_match('/(?:₦|ngn\s+)(\d[\d.,]*)\s*(k|m)?\b/iu', $text, $match)) {
                $budgetMax = $this->numberize($match[1], $match[2]);
                $text = str_replace($match[0], ' ', $text);
            } elseif (preg_match('/\b(\d[\d.,]*)\s*(k|thousand|million|naira|ngn)\b/iu', $text, $match)) {
                $budgetMax = $this->numberize($match[1], $match[2]);
                $text = str_replace($match[0], ' ', $text);
            }
        }

        $location = $this->extractLocation($text, $places);

        // Standalone noise that would otherwise become keyword tokens.
        $text = preg_replace('/\s+(?:in|inside)\s+(?:stock|stores?|inventory)\b/iu', ' ', $text) ?? $text;
        $text = preg_replace('/\s+near\s+me\b/iu', ' ', $text) ?? $text;
        $text = preg_replace('/\s+(?:for\s+)?sale\b/iu', ' ', $text) ?? $text;

        // Buyer filler: none of it belongs in a keyword.
        $text = preg_replace(
            '/\b(?:i\s+(?:want\s+to\s+buy|would\s+like\s+(?:to\s+)?buy|need|want|am\s+looking\s+for|\'?m\s+looking\s+for|d\s+like\s+to\s+buy)|looking\s+for|search(?:ing)?\s+for|find\s+me|show\s+me|get\s+me|give\s+me|do\s+you\s+have|have\s+you\s+(?:got|any)|buy|purchase|order|need|want|please)\b/iu',
            ' ',
            $text,
        ) ?? $text;

        $searchTerm = mb_substr(implode(' ', self::tokens($text)), 0, 120);

        return [
            'search_term' => $searchTerm,
            'location' => $location,
            'budget_min' => $budgetMin,
            'budget_max' => $budgetMax,
        ];
    }

    /**
     * Split a place the buyer (or the AI) named into the filters a store page
     * can be filtered by: "Lekki, Lagos" becomes a city and a state, "abuja"
     * resolves through the alias table, and a place nobody lists still comes
     * back as a filter rather than quietly widening the search.
     *
     * @param  array{states?: list<string>, cities?: list<string>}  $places
     * @return array{state: string|null, city: string|null}|null
     */
    public function place(string $value, array $places = []): ?array
    {
        $parts = array_values(array_filter(array_map('trim', explode(',', $value)), fn (string $part): bool => $part !== ''));

        if ($parts === []) {
            return null;
        }

        $resolved = ['state' => null, 'city' => null];

        foreach ($parts as $part) {
            $match = $this->resolvePlace($part, $places);

            if ($match !== null) {
                $resolved = array_merge($resolved, $match);

                continue;
            }

            if ($resolved['city'] === null) {
                $resolved['city'] = $part;
            } else {
                $resolved['state'] ??= $part;
            }
        }

        return $resolved;
    }

    /**
     * A naira amount in any shape a buyer or model might write it: "20000",
     * "20,000", "₦20,000", "20k", "1.5m".
     */
    public function amount(mixed $value): ?int
    {
        $raw = mb_strtolower(trim((string) $value));

        if ($raw === '') {
            return null;
        }

        $digits = preg_replace('/[^0-9.]/', '', $raw);

        if ($digits === '' || $digits === '.') {
            return null;
        }

        return $this->numberize($digits, str_ends_with($raw, 'k') ? 'k' : (str_ends_with($raw, 'm') ? 'm' : ''));
    }

    /**
     * Keyword tokens for a search term: whitespace-split, edge punctuation
     * trimmed, stopwords dropped. Tokens come back lowercased; the catalogue
     * scopes match them against LOWER(...) columns so the comparison is
     * case-insensitive on every database engine.
     *
     * @return list<string>
     */
    public static function tokens(string $term): array
    {
        $words = preg_split('/\s+/u', trim($term), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $tokens = [];

        foreach ($words as $word) {
            $word = trim($word, ".,;:!?\"'()[]{}<>/\\|@#&*+=~`^");
            $word = mb_strtolower($word);

            if ($word === '' || mb_strlen($word) < 2 || in_array($word, self::STOPWORDS, true)) {
                continue;
            }

            $tokens[] = $word;
        }

        return $tokens;
    }

    /**
     * The trailing " in …" clause, but only when the words really are a place —
     * "chair in wood finish" keeps its words instead of losing them to a
     * location filter that would return nothing.
     *
     * @param  array{states?: list<string>, cities?: list<string>}  $places
     */
    private function extractLocation(string &$text, array $places = []): string
    {
        // Trailing politeness first, so "…in Lagos please" still resolves.
        $text = preg_replace('/[\s.,!?]+(?:please|pls|thanks|thank\s+you|kindly|asap|now|today|soon|this\s+week(?:end)?)\s*$/iu', ' ', $text) ?? $text;

        if (! preg_match('/\s+(in|within|near|around|at|by)\s+(\S+(?:\s+\S+){0,3})\s*$/u', $text, $clause)) {
            return '';
        }

        $words = preg_split('/\s+/', trim($clause[2])) ?: [];

        // Prefer the longest run of words that resolves as a place: "in Lagos"
        // wins even when a longer capture such as "Lagos State" does not.
        for ($count = count($words); $count >= 1; $count--) {
            $candidate = implode(' ', array_slice($words, 0, $count));

            if ($this->resolvePlace($candidate, $places) === null) {
                continue;
            }

            $text = $this->removeFirst($text, ' '.$clause[1].' '.$candidate);

            return $candidate;
        }

        return '';
    }

    /**
     * @param  array{states?: list<string>, cities?: list<string>}  $places
     * @return array{state: string}|array{city: string}|null
     */
    private function resolvePlace(string $part, array $places): ?array
    {
        $needle = mb_strtolower(trim($part));

        if ($needle === '') {
            return null;
        }

        // "Lagos State" and "Abuja (FCT)" are how buyers type, not how rows are stored.
        $needle = trim(preg_replace('/\s*\([^)]*\)/', '', $needle) ?? $needle);
        $needle = preg_replace('/\s+state$/', '', $needle) ?? $needle;

        foreach (array_unique(array_merge([$needle], [self::ALIASES[$needle] ?? null])) as $candidate) {
            if ($candidate === null || $candidate === '') {
                continue;
            }

            foreach (['states' => 'state', 'cities' => 'city'] as $group => $filter) {
                foreach ($places[$group] ?? [] as $stored) {
                    if (is_string($stored) && mb_strtolower($stored) === $candidate) {
                        return [$filter => $stored];
                    }
                }
            }

            foreach (self::STATES as $state) {
                if (mb_strtolower($state) === $candidate) {
                    return ['state' => $state];
                }
            }

            foreach (self::CITIES as $city) {
                if ($city === $candidate) {
                    return ['city' => ucfirst($city)];
                }
            }
        }

        return null;
    }

    private function numberize(string $number, string $suffix): ?int
    {
        $value = (float) str_replace(',', '', $number);

        if ($value <= 0) {
            return null;
        }

        $suffix = mb_strtolower(trim($suffix));

        if (in_array($suffix, ['k', 'thousand'], true)) {
            $value *= 1000;
        } elseif (in_array($suffix, ['m', 'million'], true)) {
            $value *= 1_000_000;
        }

        $result = (int) round($value);

        // A twelve-digit "price" is a phone number or an id, not a budget.
        return $result > 100_000_000 ? null : $result;
    }

    private function removeFirst(string $haystack, string $needle): string
    {
        $position = strpos($haystack, $needle);

        return $position === false ? $haystack : substr_replace($haystack, ' ', $position, strlen($needle));
    }
}
