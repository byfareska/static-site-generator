<?php

declare(strict_types=1);

namespace Byfareska\StaticSite\Text;

use Byfareska\StaticSite\Content\HtmlCleaner;
use Byfareska\StaticSite\Content\SiteHtml;
use Byfareska\StaticSite\Entity\SiteText;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Contracts\Service\ResetInterface;

/**
 * The texts of the site: the catalogs of the application and the values changed in the panel.
 */
final class Texts implements ResetInterface
{
    /** @var array<string, TextGroup>|null */
    private ?array $groups = null;

    /** @var array<string, TextItem>|null */
    private ?array $items = null;

    /** @var array<string, string>|null */
    private ?array $values = null;

    /** @var array<string, Placeholder>|null */
    private ?array $placeholders = null;

    /**
     * @param iterable<TextCatalog>         $catalogs
     * @param iterable<PlaceholderProvider> $placeholderProviders
     */
    public function __construct(
        private readonly EntityManagerInterface $em,
        #[AutowireIterator(TextCatalog::TEXT_CATALOG_TAG)]
        private readonly iterable $catalogs,
        #[AutowireIterator(PlaceholderProvider::PLACEHOLDER_PROVIDER_TAG)]
        private readonly iterable $placeholderProviders,
        private readonly ?HtmlCleaner $cleaner = null,
    ) {
    }

    /** @return array<string, TextGroup> */
    public function groups(): array
    {
        if ($this->groups === null) {
            $this->groups = [];
            $this->items = [];
            foreach ($this->catalogs as $catalog) {
                foreach ($catalog->groups() as $group) {
                    if (isset($this->groups[$group->key])) {
                        throw new \LogicException(sprintf('Two groups of texts have the key "%s".', $group->key));
                    }
                    $this->groups[$group->key] = $group;
                    foreach ($group->items as $item) {
                        if (isset($this->items[$item->key])) {
                            throw new \LogicException(sprintf('Two texts have the key "%s".', $item->key));
                        }
                        $this->items[$item->key] = $item;
                    }
                }
            }
        }

        return $this->groups;
    }

    public function group(string $key): ?TextGroup
    {
        return $this->groups()[$key] ?? null;
    }

    /** @return array<string, TextItem> */
    public function items(): array
    {
        $this->groups();

        return $this->items ?? [];
    }

    public function item(string $key): TextItem
    {
        return $this->items()[$key] ?? throw UnknownText::key($key);
    }

    /** The value as kept: changed in the panel, else the default. Placeholders are not replaced. */
    public function raw(string $key): string
    {
        $item = $this->item($key);

        return $this->values()[$key] ?? $item->default;
    }

    /**
     * The text with its placeholders replaced, ready for the template: plain types as text (Twig escapes them),
     * HTML types as HTML.
     *
     * @param array<string, scalar|\Stringable|null> $vars more placeholders, for this text only
     */
    public function get(string $key, array $vars = []): string
    {
        return $this->fill($this->raw($key), $vars, $this->item($key)->type->isHtml());
    }

    /**
     * Replaces the placeholders of a text ("{email}") and the variables given ("{name}"). For HTML the values are
     * escaped (or their HTML form is used).
     *
     * @param array<string, scalar|\Stringable|null> $vars
     */
    public function fill(string $text, array $vars = [], bool $html = false): string
    {
        if (!str_contains($text, '{') && !str_contains($text, '%7B')) {
            return $text;
        }
        $replace = [];
        foreach ($this->placeholders() as $name => $placeholder) {
            $replace['{' . $name . '}'] = $html
                ? ($placeholder->html ?? htmlspecialchars($placeholder->value, ENT_QUOTES))
                : $placeholder->value;
            // In an address the editor may have written it encoded: href="mailto:%7Bemail%7D".
            $replace['%7B' . $name . '%7D'] = $html ? htmlspecialchars($placeholder->value, ENT_QUOTES) : $placeholder->value;
        }
        foreach ($vars as $name => $value) {
            $value = (string) $value;
            $replace['{' . $name . '}'] = $html ? htmlspecialchars($value, ENT_QUOTES) : $value;
        }

        return strtr($text, $replace);
    }

    /** @return array<string, Placeholder> */
    public function placeholders(): array
    {
        if ($this->placeholders === null) {
            $this->placeholders = [];
            foreach ($this->placeholderProviders as $provider) {
                foreach ($provider->placeholders() as $placeholder) {
                    $this->placeholders[$placeholder->name] = $placeholder;
                }
            }
        }

        return $this->placeholders;
    }

    /**
     * Saves the values of texts; a value equal to the default is not kept (the text follows the code again).
     *
     * @param array<string, string> $values
     */
    public function save(array $values): void
    {
        $repository = $this->em->getRepository(SiteText::class);
        foreach ($values as $key => $value) {
            $item = $this->item($key);
            $value = $this->clean($item, $value);
            $text = $repository->find($key);
            if ($value === $item->default) {
                if ($text !== null) {
                    $this->em->remove($text);
                }
                continue;
            }
            if ($text === null) {
                $this->em->persist(new SiteText($key, $value));
            } else {
                $text->change($value);
            }
        }
        $this->em->flush();
        $this->values = null;
    }

    /** HTML of the editor as the sanitizers of the site let it be; plain texts as they are (Twig escapes them). */
    private function clean(TextItem $item, string $value): string
    {
        if ($this->cleaner === null || $value === $item->default) {
            return $value;
        }

        return match ($item->type) {
            TextType::Html => $this->cleaner->clean($value, SiteHtml::CONTENT),
            TextType::Inline => $this->cleaner->clean($value, SiteHtml::INLINE),
            default => $value,
        };
    }

    /** @return list<string> keys kept in the database but in no catalog any more */
    public function orphans(): array
    {
        return array_values(array_diff(array_keys($this->values()), array_keys($this->items())));
    }

    public function reset(): void
    {
        $this->values = null;
        $this->placeholders = null;
    }

    /** @return array<string, string> */
    private function values(): array
    {
        if ($this->values === null) {
            $this->values = [];
            foreach ($this->em->getRepository(SiteText::class)->findAll() as $text) {
                $this->values[$text->key] = $text->value;
            }
        }

        return $this->values;
    }
}
