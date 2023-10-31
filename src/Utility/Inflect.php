<?php declare(strict_types=1);

namespace Lkrms\Utility;

/**
 * Inflect English words
 */
class Inflect
{
    /**
     * Get the indefinite article ("a" or "an") to use before a word
     *
     * Ported from PERL module `Lingua::EN::Inflexion`.
     *
     * @link https://metacpan.org/pod/Lingua::EN::Inflexion
     */
    public static function indefinite(string $word): string
    {
        $ordinalAn = '/\A [aefhilmnorsx] -?th \Z/ix';
        $ordinalA = '/\A [bcdgjkpqtuvwyz] -?th \Z/ix';
        $explicitAn = '/\A (?: euler | hour(?!i) | heir | honest | hono )/ix';
        $singleAn = '/\A [aefhilmnorsx] \Z/ix';
        $singleA = '/\A [bcdgjkpqtuvwyz] \Z/ix';

        // Strings of capitals (i.e. abbreviations) that start with a
        // "vowel-sound" consonant followed by another consonant, and which are
        // not likely to be real words
        $abbrevAn = <<<'REGEX'
            / \A (?!
              FJO | [HLMNS]Y. | RY[EO] | SQU |
              ( F[LR]? | [HL] | MN? | N | RH? | S[CHKLMNPTVW]? | X(YL)? ) [AEIOU]
            )
            [FHLMNRSX][A-Z] /xms
            REGEX;

        // English words beginning with "Y" and followed by a consonant
        $initialYAn = '/\A y (?: b[lor] | cl[ea] | fere | gg | p[ios] | rou | tt)/xi';

        // Handle ordinal forms
        if (Pcre::match($ordinalA, $word)) { return 'a'; }
        if (Pcre::match($ordinalAn, $word)) { return 'an'; }

        // Handle special cases
        if (Pcre::match($explicitAn, $word)) { return 'an'; }
        if (Pcre::match($singleAn, $word)) { return 'an'; }
        if (Pcre::match($singleA, $word)) { return 'a'; }

        // Handle abbreviations
        if (Pcre::match($abbrevAn, $word)) { return 'an'; }
        if (Pcre::match('/\A [aefhilmnorsx][.-]/xi', $word)) { return 'an'; }
        if (Pcre::match('/\A [a-z][.-]/xi', $word)) { return 'a'; }

        // Handle consonants
        if (Pcre::match('/\A [^aeiouy] /xi', $word)) { return 'a'; }

        // Handle special vowel-forms
        if (Pcre::match('/\A e [uw] /xi', $word)) { return 'a'; }
        if (Pcre::match('/\A onc?e \b /xi', $word)) { return 'a'; }
        if (Pcre::match('/\A uni (?: [^nmd] | mo) /xi', $word)) { return 'a'; }
        if (Pcre::match('/\A ut[th] /xi', $word)) { return 'an'; }
        if (Pcre::match('/\A u [bcfhjkqrst] [aeiou] /xi', $word)) { return 'a'; }

        // Handle special capitals
        if (Pcre::match('/\A U [NK] [AIEO]? /x', $word)) { return 'a'; }

        // Handle vowels
        if (Pcre::match('/\A [aeiou]/xi', $word)) { return 'an'; }

        // Handle words beginning with "Y"
        if (Pcre::match($initialYAn, $word)) { return 'an'; }

        // Otherwise, guess "a"
        return 'a';
    }
}
