<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMNode;

final class RichText
{
    private const ALLOWED_TAGS = ['p', 'div', 'br', 'strong', 'b', 'em', 'i', 'u', 's', 'span', 'ul', 'ol', 'li', 'blockquote', 'pre', 'code', 'a', 'h2', 'h3', 'img', 'table', 'thead', 'tbody', 'tr', 'th', 'td'];

    private const DROP_CONTENT_TAGS = ['script', 'style', 'iframe', 'object', 'embed', 'svg', 'math'];

    public static function sanitize(?string $html): string
    {
        if (blank($html)) {
            return '';
        }

        $previous = libxml_use_internal_errors(true);
        $document = new DOMDocument('1.0', 'UTF-8');
        $document->loadHTML('<?xml encoding="utf-8" ?><div id="rich-text-root">'.$html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $document->getElementById('rich-text-root');

        if (! $root) {
            return '';
        }

        self::cleanChildren($root);

        $output = '';
        foreach ($root->childNodes as $child) {
            $output .= $document->saveHTML($child);
        }

        return $output;
    }

    public static function plainText(?string $html): string
    {
        $safe = self::sanitize($html);
        $text = preg_replace('/<\/(p|div|li|blockquote|pre|h[2-3])>/i', "\n", $safe) ?? $safe;

        return trim(html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    public static function isEmpty(?string $html): bool
    {
        return self::plainText($html) === '';
    }

    private static function cleanChildren(DOMNode $parent): void
    {
        foreach (iterator_to_array($parent->childNodes) as $child) {
            if ($child instanceof \DOMText) {
                continue;
            }

            if (! $child instanceof DOMElement) {
                $parent->removeChild($child);

                continue;
            }

            $tag = strtolower($child->tagName);

            if (in_array($tag, self::DROP_CONTENT_TAGS, true)) {
                $parent->removeChild($child);

                continue;
            }

            self::cleanChildren($child);

            if (! in_array($tag, self::ALLOWED_TAGS, true)) {
                while ($child->firstChild) {
                    $parent->insertBefore($child->firstChild, $child);
                }
                $parent->removeChild($child);

                continue;
            }

            $href = $tag === 'a' ? trim($child->getAttribute('href')) : '';
            $src = $tag === 'img' ? trim($child->getAttribute('src')) : '';
            $alt = $tag === 'img' ? $child->getAttribute('alt') : '';
            $color = $tag === 'span' && preg_match('/^#[0-9a-f]{6}$/i', $child->getAttribute('data-text-color'))
                ? strtolower($child->getAttribute('data-text-color'))
                : '';
            while ($child->attributes->length > 0) {
                $child->removeAttributeNode($child->attributes->item(0));
            }

            if ($tag === 'a' && self::safeLink($href)) {
                $child->setAttribute('href', $href);
                $child->setAttribute('rel', 'nofollow noopener noreferrer');
            }

            if ($tag === 'img' && self::safeImage($src)) {
                $child->setAttribute('src', $src);
                $child->setAttribute('alt', $alt);
                $child->setAttribute('loading', 'lazy');
            } elseif ($tag === 'img') {
                $parent->removeChild($child);
            }

            if ($tag === 'span' && $color !== '') {
                $child->setAttribute('data-text-color', $color);
            }
        }
    }

    private static function safeLink(string $href): bool
    {
        if ($href === '' || str_starts_with($href, '//') || preg_match('/[\x00-\x20\x7f]/', $href)) {
            return false;
        }

        $scheme = parse_url($href, PHP_URL_SCHEME);

        return $scheme === null || in_array(strtolower($scheme), ['http', 'https', 'mailto'], true);
    }

    private static function safeImage(string $src): bool
    {
        if ($src === '' || preg_match('/[\x00-\x20\x7f]/', $src)) {
            return false;
        }

        return in_array(strtolower((string) parse_url($src, PHP_URL_SCHEME)), ['http', 'https'], true);
    }
}
