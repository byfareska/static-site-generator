<?php

declare(strict_types=1);

namespace Byfareska\StaticSite\Text;

enum TextType: string
{
    /** One line of plain text. */
    case Plain = 'plain';

    /** Plain text; a new line is a <br>. */
    case Multiline = 'multiline';

    /** One line with a little HTML: bold, italic, an accent, a link, an abbreviation. */
    case Inline = 'inline';

    /** HTML of the editor: paragraphs, lists, quotes. */
    case Html = 'html';

    public function isHtml(): bool
    {
        return $this === self::Inline || $this === self::Html;
    }
}
