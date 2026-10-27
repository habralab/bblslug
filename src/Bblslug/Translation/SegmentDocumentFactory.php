<?php

declare(strict_types=1);

namespace Bblslug\Translation;

/** @internal Builds segment projections from text after placeholder protection. */
final class SegmentDocumentFactory
{
    public static function create(
        string $prepared,
        string $htmlTransport,
        string $textTransport
    ): HtmlTextDocument|TextDocument|null {
        if ($htmlTransport === 'segments') {
            return new HtmlTextDocument($prepared);
        }
        if ($textTransport === 'segments') {
            return new TextDocument($prepared);
        }
        return null;
    }
}
