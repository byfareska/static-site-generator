<?php

declare(strict_types=1);

namespace Byfareska\StaticSite\Editor;

use Byfareska\StaticSite\Text\Placeholder;
use Byfareska\StaticSite\Text\Texts;
use Symfony\Component\Asset\Packages;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * A field of HTML edited with TinyMCE (the controller static-site-editor of @byfareska/static-site).
 *
 * - mode: "full" (paragraphs, headings, lists, quotes, tables, images) or "inline" (one line: bold, italic, accent,
 *   abbreviation, link);
 * - blocks: [{title, html}] of the menu "Insert a block" (static_site.editor.blocks by default);
 * - headings: the heading levels to choose from;
 * - files_url: a URL answering [{title, value}] - the files to link to and the images to insert;
 * - content_css / content_style: what the text looks like in the editor (the stylesheet of the site).
 *
 * The placeholders of the texts ({email}) are offered in "Insert data".
 */
final class RichTextType extends AbstractType
{
    public const string FULL = 'full';
    public const string INLINE = 'inline';

    /**
     * @param list<array{title: string, html: string}> $blocks
     */
    public function __construct(
        private readonly Texts $texts,
        #[Autowire('%static_site.editor.blocks%')]
        private readonly array $blocks = [],
        #[Autowire('%static_site.editor.content_css%')]
        private readonly ?string $contentCss = null,
        #[Autowire('%static_site.editor.language%')]
        private readonly ?string $language = null,
        private readonly ?Packages $packages = null,
    ) {
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'mode' => self::FULL,
            'blocks' => fn (Options $options): array => $options['mode'] === self::FULL ? $this->blocks : [],
            'headings' => ['h2', 'h3'],
            'files_url' => null,
            'content_css' => $this->contentCss,
            'content_style' => null,
            'placeholders' => true,
            'required' => false,
            'empty_data' => '',
        ]);
        $resolver->setAllowedValues('mode', [self::FULL, self::INLINE]);
        $resolver->setAllowedTypes('blocks', 'array');
        $resolver->setAllowedTypes('headings', 'string[]');
        $resolver->setAllowedTypes('files_url', ['null', 'string']);
        $resolver->setAllowedTypes('content_css', ['null', 'string']);
        $resolver->setAllowedTypes('content_style', ['null', 'string']);
        $resolver->setAllowedTypes('placeholders', 'bool');
    }

    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        $placeholders = $options['placeholders']
            ? array_values(array_map(
                static fn (Placeholder $p): array => ['name' => $p->name, 'description' => $p->description],
                $this->texts->placeholders(),
            ))
            : [];
        $attr = [
            'data-controller' => trim(($view->vars['attr']['data-controller'] ?? '') . ' static-site-editor'),
            'data-static-site-editor-mode-value' => $options['mode'],
            'data-static-site-editor-headings-value' => json_encode($options['headings'], JSON_THROW_ON_ERROR),
            'data-static-site-editor-blocks-value' => json_encode(array_values($options['blocks']), JSON_THROW_ON_ERROR),
            'data-static-site-editor-placeholders-value' => json_encode($placeholders, JSON_THROW_ON_ERROR),
            'rows' => $options['mode'] === self::INLINE ? 2 : 10,
        ];
        foreach (['files_url' => 'files', 'content_css' => 'content-css', 'content_style' => 'content-style'] as $option => $name) {
            if (is_string($options[$option]) && $options[$option] !== '') {
                $attr['data-static-site-editor-' . $name . '-value'] = $option === 'content_css'
                    ? $this->stylesheet($options[$option])
                    : $options[$option];
            }
        }
        if (is_string($this->language) && $this->language !== '') {
            $attr['data-static-site-editor-language-value'] = $this->language;
        }
        $view->vars['attr'] = array_merge($view->vars['attr'], $attr);
    }

    /** An asset of the application ("build/site-editor.css", versioned by its manifest), or an address as it is. */
    private function stylesheet(string $css): string
    {
        if ($this->packages === null || str_starts_with($css, '/') || preg_match('#^[a-z][a-z0-9+.-]*:#i', $css) === 1) {
            return $css;
        }

        return $this->packages->getUrl($css);
    }

    public function getParent(): string
    {
        return TextareaType::class;
    }

    public function getBlockPrefix(): string
    {
        return 'static_site_rich_text';
    }
}
