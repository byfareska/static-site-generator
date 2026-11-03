<?php

declare(strict_types=1);

namespace Byfareska\StaticSite\Editor;

use Byfareska\StaticSite\Content\SiteHtml;
use Symfony\Component\Form\FormTypeGuesserInterface;
use Symfony\Component\Form\Guess\Guess;
use Symfony\Component\Form\Guess\TypeGuess;
use Symfony\Component\Form\Guess\ValueGuess;

/**
 * A property marked #[SiteHtml] gets the editor (RichTextType) in a form that guesses its fields - the forms of
 * byfareska/crud, $builder->add('body') - inline for the "inline" sanitizer, with the headings given ids.
 */
final class SiteHtmlTypeGuesser implements FormTypeGuesserInterface
{
    public function guessType(string $class, string $property): ?TypeGuess
    {
        $html = self::attribute($class, $property);
        if ($html === null) {
            return null;
        }
        $options = ['mode' => $html->sanitizer === SiteHtml::INLINE ? RichTextType::INLINE : RichTextType::FULL];
        if ($html->headingIds !== []) {
            $options['headings'] = $html->headingIds;
        }

        return new TypeGuess(RichTextType::class, $options, Guess::VERY_HIGH_CONFIDENCE);
    }

    public function guessRequired(string $class, string $property): ?ValueGuess
    {
        return null;
    }

    public function guessMaxLength(string $class, string $property): ?ValueGuess
    {
        return null;
    }

    public function guessPattern(string $class, string $property): ?ValueGuess
    {
        return null;
    }

    private static function attribute(string $class, string $property): ?SiteHtml
    {
        if (!class_exists($class) || !property_exists($class, $property)) {
            return null;
        }
        $attributes = (new \ReflectionProperty($class, $property))->getAttributes(SiteHtml::class);

        return $attributes === [] ? null : $attributes[0]->newInstance();
    }
}
