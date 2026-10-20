<?php

namespace App\Modules\Daymark\Services;

class ReportRichText
{
    public const FIELDS = ['completed', 'progress', 'plan', 'blockers'];

    public static function sanitize(string $html): string
    {
        $doc = new \DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        try {
            $doc->loadHTML('<?xml encoding="UTF-8"><html><body>'.$html.'</body></html>', LIBXML_NONET);
            $body = $doc->getElementsByTagName('body')->item(0);
            if (! $body) {
                return '';
            }
            $clean = function ($node) use (&$clean) {
                foreach (iterator_to_array($node->childNodes) as $child) {
                    if (! $child instanceof \DOMElement) {
                        continue;
                    }$tag = strtolower($child->tagName);
                    if (in_array($tag, ['script', 'style', 'iframe', 'object', 'svg', 'math', 'template'], true)) {
                        $node->removeChild($child);

                        continue;
                    }$clean($child);
                    if (! in_array($tag, ['p', 'br', 'strong', 'b', 'em', 'i', 'u', 's', 'strike', 'h2', 'h3', 'ul', 'ol', 'li', 'blockquote', 'a'], true)) {
                        while ($child->firstChild) {
                            $node->insertBefore($child->firstChild, $child);
                        }$node->removeChild($child);

                        continue;
                    }foreach (iterator_to_array($child->attributes) as $attr) {
                        $keep = $tag === 'a' && ($attr->name === 'title' || ($attr->name === 'href' && preg_match('/^(https?:\/\/|mailto:)/i', $attr->value)));
                        if (! $keep) {
                            $child->removeAttribute($attr->name);
                        }
                    }
                }
            };
            $clean($body);
            $result = '';
            foreach ($body->childNodes as $child) {
                $result .= $doc->saveHTML($child);
            }

return $result;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    public static function html(?string $value, ?string $format = 'plain'): string
    {
        return $format === 'html'
            ? self::sanitize($value ?? '')
            : '<p>'.nl2br(e($value ?? '')).'</p>';
    }

    public static function text(?string $value, ?string $format = 'plain'): string
    {
        if ($format !== 'html') {
            return $value ?? '';
        }
        $html = self::sanitize($value ?? '');
        $document = new \DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        try {
            $document->loadHTML('<?xml encoding="UTF-8"><html><body>'.$html.'</body></html>', LIBXML_NONET);
            $body = $document->getElementsByTagName('body')->item(0);
            $text = $body ? self::nodeText($body) : '';
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        $text = str_replace("\u{00A0}", ' ', $text);
        $text = preg_replace('/[\x{200B}-\x{200D}\x{FEFF}]/u', '', $text);
        $text = preg_replace('/[ \t]+\n/u', "\n", $text);

        return trim(preg_replace('/\n{3,}/', "\n\n", $text));
    }

    private static function nodeText(\DOMNode $node): string
    {
        if ($node instanceof \DOMText) {
            return $node->textContent;
        }
        if ($node->nodeName === 'br') {
            return "\n";
        }
        $text = '';
        foreach ($node->childNodes as $child) {
            $text .= self::nodeText($child);
        }
        if ($node->nodeName === 'li') {
            $prefix = '- ';
            if ($node->parentNode?->nodeName === 'ol') {
                $number = 1;
                for ($sibling = $node->previousSibling; $sibling; $sibling = $sibling->previousSibling) {
                    if ($sibling->nodeName === 'li') {
                        $number++;
                    }
                }
                $prefix = $number.'. ';
            }

            return $prefix.trim($text)."\n";
        }
        if (in_array($node->nodeName, ['p', 'h2', 'h3', 'blockquote', 'ul', 'ol'], true)) {
            return $text."\n";
        }
        if ($node instanceof \DOMElement && $node->nodeName === 'a' && $node->getAttribute('href')) {
            $href = $node->getAttribute('href');

            return $text === $href ? $text : $text.' ('.$href.')';
        }

        return $text;
    }
}
