<?php
declare(strict_types=1);

namespace MailDesk\Application\Mail;

/** Allow-list HTML sanitiser for the message body (b,i,u,ul,ol,li,a,p,br,div). */
final class HtmlSanitizer
{
    private const ALLOWED = [
        'p', 'br', 'b', 'strong', 'i', 'em', 'u', 'ul', 'ol', 'li', 'a', 'div', 'span',
    ];
    private const DROP_WITH_CONTENT = ['script', 'style', 'iframe', 'object', 'embed', 'noscript', 'template', 'head', 'title', 'svg', 'math', 'form', 'textarea', 'select'];

    public static function clean(string $html): string
    {
        if (trim($html) === '') {
            return '';
        }
        $doc = new \DOMDocument('1.0', 'UTF-8');
        $prev = libxml_use_internal_errors(true);
        $doc->loadHTML(
            '<?xml encoding="UTF-8"><html><body><div id="__root">' . $html . '</div></body></html>',
            LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NONET,
        );
        libxml_clear_errors();
        libxml_use_internal_errors($prev);

        $root = $doc->getElementById('__root');
        if ($root === null) {
            return htmlspecialchars($html, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }
        self::walk($root);

        $out = '';
        foreach (iterator_to_array($root->childNodes) as $child) {
            $out .= $doc->saveHTML($child);
        }
        return trim($out);
    }

    public static function toText(string $html): string
    {
        $html = preg_replace('#<\s*br\s*/?>#i', "\n", $html) ?? $html;
        $html = preg_replace('#</\s*(p|div|li|ul|ol)\s*>#i', "\n", $html) ?? $html;
        $html = preg_replace('#<\s*li[^>]*>#i', '- ', $html) ?? $html;
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;
        return trim($text);
    }

    private static function walk(\DOMNode $node): void
    {
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child instanceof \DOMComment || $child instanceof \DOMProcessingInstruction) {
                $node->removeChild($child);
                continue;
            }
            if (!$child instanceof \DOMElement) {
                continue;
            }
            $tag = strtolower($child->tagName);

            if (in_array($tag, self::DROP_WITH_CONTENT, true)) {
                $node->removeChild($child);
                continue;
            }

            self::walk($child);

            if (!in_array($tag, self::ALLOWED, true)) {
                // unwrap: keep (already sanitised) children, drop the element
                while ($child->firstChild !== null) {
                    $node->insertBefore($child->firstChild, $child);
                }
                $node->removeChild($child);
                continue;
            }

            $href = $tag === 'a' ? trim($child->getAttribute('href')) : '';
            foreach (iterator_to_array($child->attributes) as $attr) {
                $child->removeAttributeNode($attr);
            }
            if ($tag === 'a') {
                // strip control chars/whitespace that browsers ignore inside schemes
                $probe = preg_replace('/[\x00-\x20]+/', '', $href) ?? '';
                if (preg_match('#^(https?:|mailto:)#i', $probe) === 1) {
                    $child->setAttribute('href', $href);
                    $child->setAttribute('target', '_blank');
                    $child->setAttribute('rel', 'noopener noreferrer');
                }
            }
        }
    }
}
