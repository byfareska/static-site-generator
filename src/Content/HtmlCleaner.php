<?php

declare(strict_types=1);

namespace Byfareska\StaticSite\Content;

use Psr\Container\ContainerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\AutowireLocator;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface;
use Symfony\Component\String\Slugger\AsciiSlugger;

/**
 * Cleans HTML of the editor and gives its headings ids, the anchors of a table of contents ("#pytanie-1").
 */
final readonly class HtmlCleaner
{
    public function __construct(
        #[AutowireLocator([
            SiteHtml::CONTENT => new Autowire(service: 'html_sanitizer.sanitizer.static_site.content'),
            SiteHtml::INLINE => new Autowire(service: 'html_sanitizer.sanitizer.static_site.inline'),
        ])]
        private ContainerInterface $sanitizers,
    ) {
    }

    /** @param list<string> $headingIds */
    public function clean(string $html, string $sanitizer = SiteHtml::CONTENT, array $headingIds = []): string
    {
        $service = $this->sanitizers->get($sanitizer);
        if (!$service instanceof HtmlSanitizerInterface) {
            throw new \LogicException(sprintf('"%s" is no HTML sanitizer.', $sanitizer));
        }
        $clean = $service->sanitize($html);

        return $headingIds === [] ? $clean : self::headingIds($clean, $headingIds);
    }

    /** @param list<string> $tags */
    public static function headingIds(string $html, array $tags): string
    {
        $slugger = new AsciiSlugger('pl');
        $used = [];
        preg_match_all('/\bid="([^"]+)"/', $html, $existing);
        foreach ($existing[1] as $id) {
            $used[$id] = true;
        }
        $pattern = sprintf('#<(%s)(\s[^>]*)?>(.*?)</\1>#is', implode('|', array_map(preg_quote(...), $tags)));

        return (string) preg_replace_callback($pattern, static function (array $m) use ($slugger, &$used): string {
            $attributes = $m[2] ?? '';
            if (preg_match('/\bid=/i', $attributes) === 1) {
                return $m[0];
            }
            $text = html_entity_decode(strip_tags($m[3]), ENT_QUOTES | ENT_HTML5);
            $base = strtolower((string) $slugger->slug(mb_substr($text, 0, 60)));
            $base = $base === '' ? 'sekcja' : $base;
            $id = $base;
            for ($n = 2; isset($used[$id]); ++$n) {
                $id = $base . '-' . $n;
            }
            $used[$id] = true;

            return sprintf('<%s id="%s"%s>%s</%s>', $m[1], $id, $attributes, $m[3], $m[1]);
        }, $html);
    }
}
