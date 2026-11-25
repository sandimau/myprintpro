<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMNode;

class SopHtml
{
    private const TAGS = [
        'p', 'div', 'br', 'h1', 'h2', 'h3', 'h4', 'strong', 'b', 'em', 'i', 'u',
        's', 'ul', 'ol', 'li', 'blockquote', 'a', 'img', 'table', 'thead',
        'tbody', 'tfoot', 'tr', 'th', 'td', 'hr', 'pre', 'code', 'sub', 'sup',
    ];

    public static function clean(string $html): string
    {
        $doc = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        try {
            $doc->loadHTML('<?xml encoding="UTF-8"?><div id="sop-root">' . $html . '</div>', LIBXML_NONET | LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        $root = $doc->getElementById('sop-root');
        if (!$root) {
            return e($html);
        }

        foreach (iterator_to_array($root->childNodes) as $child) {
            self::sanitize($child);
        }

        $result = '';
        foreach ($root->childNodes as $child) {
            $result .= $doc->saveHTML($child);
        }

        return $result;
    }

    private static function sanitize(DOMNode $node): void
    {
        if ($node->nodeType === XML_COMMENT_NODE) {
            $node->parentNode->removeChild($node);
            return;
        }
        if ($node->nodeType === XML_TEXT_NODE && trim($node->nodeValue) === ''
            && $node->parentNode instanceof DOMElement
            && in_array(strtolower($node->parentNode->tagName), ['ol', 'ul', 'li', 'table', 'thead', 'tbody', 'tfoot', 'tr', 'td', 'th'], true)) {
            $node->parentNode->removeChild($node);
            return;
        }
        if (!$node instanceof DOMElement) {
            return;
        }

        $tag = strtolower($node->tagName);
        if (in_array($tag, ['script', 'style', 'iframe', 'object', 'embed', 'form', 'svg', 'math'], true)) {
            $node->parentNode->removeChild($node);
            return;
        }

        foreach (iterator_to_array($node->childNodes) as $child) {
            self::sanitize($child);
        }

        if ($tag === 'ol') {
            foreach ($node->childNodes as $item) {
                if ($item instanceof DOMElement && strtolower($item->tagName) === 'li') {
                    self::removeWrittenNumber($item);
                }
            }
        }

        // Banyak dump lama membungkus daftar dalam tabel 1 sel untuk tata letak.
        // Lepas pembungkus itu agar aturan tidak tampil dalam kotak tabel kosong.
        if ($tag === 'table' && $node->getElementsByTagName('tr')->length === 1) {
            $cells = $node->getElementsByTagName('td');
            if ($cells->length === 1 && $node->getElementsByTagName('th')->length === 0) {
                $cell = $cells->item(0);
                while ($cell->firstChild) {
                    $node->parentNode->insertBefore($cell->firstChild, $node);
                }
                $node->parentNode->removeChild($node);
                return;
            }
        }

        if (!in_array($tag, self::TAGS, true)) {
            while ($node->firstChild) {
                $node->parentNode->insertBefore($node->firstChild, $node);
            }
            $node->parentNode->removeChild($node);
            return;
        }

        foreach (iterator_to_array($node->attributes) as $attribute) {
            $name = strtolower($attribute->name);
            $value = trim($attribute->value);
            $allowed = ($tag === 'a' && $name === 'href' && self::safeUrl($value))
                || ($tag === 'img' && $name === 'src' && self::safeUrl($value))
                || ($tag === 'img' && $name === 'alt')
                || ($tag === 'img' && in_array($name, ['width', 'height'], true) && ctype_digit($value))
                || (in_array($tag, ['td', 'th'], true) && in_array($name, ['colspan', 'rowspan'], true) && ctype_digit($value));
            if (!$allowed) {
                $node->removeAttributeNode($attribute);
            }
        }
    }

    private static function safeUrl(string $url): bool
    {
        return (bool) preg_match('~^(?:https?://[^\s<>"\']+|mailto:[^\s<>"\']+|/(?!/)[^\s<>"\']*|\#[^\s<>"\']+)$~i', $url);
    }

    private static function removeWrittenNumber(DOMNode $node): void
    {
        foreach ($node->childNodes as $child) {
            if ($child->nodeType === XML_TEXT_NODE) {
                if (trim($child->nodeValue) === '') {
                    continue;
                }
                if (preg_match('/^\s*\d+[.)][\s\x{00A0}]*/u', $child->nodeValue, $match)) {
                    $child->nodeValue = mb_substr($child->nodeValue, mb_strlen($match[0]));
                }
                // Hanya teks pertama pada item daftar yang mungkin memuat nomor manual.
                return;
            }
            if ($child instanceof DOMElement) {
                self::removeWrittenNumber($child);
                return;
            }
        }
    }
}
