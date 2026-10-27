<?php

declare(strict_types=1);

namespace Bblslug\Translation;

use Bblslug\Filters\FilterManager;

/** Keep markup local and translate an explicitly keyed collection of text spans. */
final class HtmlTextDocument
{
    /** @var list<array{start:int,end:int,value:string,raw:string,context:string}> */
    private array $spans = [];
    private string $html;
    private FilterManager $opaque;

    public function __construct(string $preparedHtml)
    {
        // Reuse the filters' handling of nested tags, comments and raw-text contexts.
        $this->opaque = new FilterManager([
            'html_pre', 'html_code', 'html_script', 'html_style', 'html_xmp',
            'html_iframe', 'html_noembed', 'html_noframes', 'html_noscript',
            'html_plaintext', 'html_svg', 'html_math',
        ]);
        $this->html = $this->opaque->apply($preparedHtml);
        $this->scan();
    }

    public function getJson(): string
    {
        $values = [];
        foreach ($this->spans as $index => $span) {
            $values['s' . $index] = $span['value'];
        }
        return json_encode((object) $values, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    public function hasTexts(): bool
    {
        return $this->spans !== [];
    }

    public function restore(string $translatedJson): string
    {
        $expected = array_map(static fn (int $index): string => 's' . $index, array_keys($this->spans));
        $values = SegmentTranslations::decode($translatedJson, $expected);
        $output = '';
        $cursor = 0;
        foreach ($this->spans as $index => $span) {
            $value = $values['s' . $index];
            $replacement = $value === $span['value'] ? $span['raw'] : self::encode($value, $span['context']);
            $output .= substr($this->html, $cursor, $span['start'] - $cursor) . $replacement;
            $cursor = $span['end'];
        }
        return $this->opaque->restore($output . substr($this->html, $cursor));
    }

    private function scan(): void
    {
        $length = strlen($this->html);
        $position = 0;
        while ($position < $length) {
            $start = $position;
            // A less-than sign followed by whitespace, a digit, etc. is literal text.
            while (
                $position < $length
                && preg_match('/\G<(?:[a-z!?]|\/[a-z])/i', $this->html, $m, 0, $position) !== 1
            ) {
                $position++;
            }
            $this->addChunks($start, substr($this->html, $start, $position - $start), 'text');
            if ($position === $length) {
                break;
            }
            if (substr($this->html, $position, 9) === '<![CDATA[') {
                $end = strpos($this->html, ']]>', $position + 9);
                if ($end === false) {
                    throw new \InvalidArgumentException('Incomplete HTML CDATA in segment transport');
                }
                $position = $end + 3;
                continue;
            }
            if (substr($this->html, $position, 4) === '<!--') {
                $end = strpos($this->html, '-->', $position + 4);
                if ($end === false) {
                    throw new \InvalidArgumentException('Incomplete HTML comment in segment transport');
                }
                $position = $end + 3;
                continue;
            }
            $pattern = <<<'REGEX'
~\G(?:</?[a-z][^\s/<>"']*(?=[\s/>])|<!|<\?)(?:[^"'<>]++|"[^"]*"|'[^']*')*+>~i
REGEX;
            if (preg_match($pattern, $this->html, $tag, 0, $position) !== 1) {
                throw new \InvalidArgumentException('Incomplete or unsupported HTML tag in segment transport');
            }
            $markup = $tag[0];
            $end = $position + strlen($markup);
            if (preg_match('~\A<([a-z][^\s/<>"\']*)(?=[\s/>])~i', $markup, $name) === 1) {
                $this->addAttributes($position, $markup, strlen($name[0]));
                if (in_array(strtolower($name[1]), ['title', 'textarea'], true)) {
                    $closing = '~</' . preg_quote($name[1], '~') . '\s*>~i';
                    if (preg_match($closing, $this->html, $close, PREG_OFFSET_CAPTURE, $end) !== 1) {
                        throw new \InvalidArgumentException('Unclosed HTML RCDATA element in segment transport');
                    }
                    $this->addChunks($end, substr($this->html, $end, $close[0][1] - $end), 'text');
                    $end = $close[0][1] + strlen($close[0][0]);
                }
            }
            $position = $end;
        }
    }

    private function addAttributes(int $start, string $tag, int $position): void
    {
        $pattern = <<<'REGEX'
~\G\s+([^\s/="'<>]+)(?:\s*=\s*(?:"([^"]*)"|'([^']*)'|([^\s>]+)))?~
REGEX;
        while (preg_match($pattern, $tag, $attribute, PREG_OFFSET_CAPTURE | PREG_UNMATCHED_AS_NULL, $position) === 1) {
            $position = $attribute[0][1] + strlen($attribute[0][0] ?? '');
            if (!in_array(strtolower($attribute[1][0] ?? ''), ['title', 'alt', 'aria-label'], true)) {
                continue;
            }
            foreach ([2 => '"', 3 => "'", 4 => 'unquoted'] as $group => $context) {
                if (($attribute[$group][0] ?? null) !== null) {
                    $this->addChunks($start + $attribute[$group][1], $attribute[$group][0], $context);
                    break;
                }
            }
        }
    }

    private function addChunks(int $start, string $raw, string $context): void
    {
        $view = new HtmlTextView($raw);
        $decoded = $view->getText();
        preg_match_all('/@@[0-9]+@@/', $decoded, $markers, PREG_OFFSET_CAPTURE);
        $cursor = 0;
        foreach ($markers[0] as [$marker, $offset]) {
            $this->addSpan($start, $raw, $decoded, $view, $cursor, $offset, $context);
            $cursor = $offset + strlen($marker);
        }
        $this->addSpan($start, $raw, $decoded, $view, $cursor, strlen($decoded), $context);
    }

    private function addSpan(
        int $start,
        string $raw,
        string $decoded,
        HtmlTextView $view,
        int $from,
        int $to,
        string $context
    ): void {
        $value = substr($decoded, $from, $to - $from);
        if (
            preg_match('/\A\s*/u', $value, $leading) !== 1
            || preg_match('/\s*\z/u', $value, $trailing) !== 1
        ) {
            throw new \InvalidArgumentException('Invalid UTF-8 HTML segment');
        }
        $from += strlen($leading[0]);
        $to -= strlen($trailing[0]);
        if ($from >= $to) {
            return;
        }
        $rawStart = $view->sourceOffset($from);
        $rawEnd = $view->sourceOffset($to);
        if ($rawStart === null || $rawEnd === null) {
            throw new \InvalidArgumentException('Unsupported HTML entity boundary in segment transport');
        }
        $this->spans[] = [
            'start' => $start + $rawStart,
            'end' => $start + $rawEnd,
            'value' => substr($decoded, $from, $to - $from),
            'raw' => substr($raw, $rawStart, $rawEnd - $rawStart),
            'context' => $context,
        ];
    }

    private static function encode(string $value, string $context): string
    {
        $encoded = htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if ($context === 'unquoted') {
            $encoded = preg_replace_callback(
                '/[\s`=]/u',
                static fn (array $match): string => '&#' . mb_ord($match[0], 'UTF-8') . ';',
                $encoded
            );
            if ($encoded === null) {
                throw new \RuntimeException('Could not encode translated HTML attribute');
            }
        }
        return $encoded;
    }
}
