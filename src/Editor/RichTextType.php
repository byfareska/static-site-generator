<?php

declare(strict_types=1);

namespace Byfareska\StaticSite\Editor;

use Byfareska\Crud\Files\RichTextFiles;
use Byfareska\Crud\Form\CrudRichTextType;
use Byfareska\StaticSite\Text\Placeholder;
use Byfareska\StaticSite\Text\Texts;
use Symfony\Component\Asset\Packages;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * A field of HTML of the site: the rich text of byfareska/crud (CrudRichTextType) with the editor "static-site"
 * (registerStaticSiteEditor() of @byfareska/static-site, TinyMCE).
 *
 * - mode: "full" (paragraphs, headings, lists, quotes, tables, images) or "inline" (one line: bold, italic, accent,
 *   abbreviation, link);
 * - blocks: [{title, html}] of the menu "Insert a block" (static_site.editor.blocks by default);
 * - headings: the heading levels to choose from;
 * - files: images and files uploaded into the text and picked from the library of the panel - false, true or
 *   {storage, accept, max_size, browse} of a storage the site publishes (static_site.files; by default
 *   static_site.files.editor);
 * - content_css / content_style: what the text looks like in the editor (the stylesheet of the site).
 *
 * The placeholders of the texts ({email}) are offered in "Insert data".
 */
final class RichTextType extends AbstractType
{
    public const string FULL = 'full';
    public const string INLINE = 'inline';
    public const string EDITOR = 'static-site';

    /**
     * @param list<array{title: string, html: string}> $blocks
     * @param array<string, string>                    $storages static_site.files.storages
     * @param false|array<string, mixed>               $files    static_site.files.editor
     */
    public function __construct(
        private readonly Texts $texts,
        #[Autowire('%static_site.editor.blocks%')]
        private readonly array $blocks = [],
        #[Autowire('%static_site.editor.content_css%')]
        private readonly ?string $contentCss = null,
        #[Autowire('%static_site.editor.language%')]
        private readonly ?string $language = null,
        #[Autowire('%static_site.files.storages%')]
        private readonly array $storages = [],
        #[Autowire('%static_site.files.editor%')]
        private readonly false|array $files = false,
        private readonly ?Packages $packages = null,
        private readonly ?RichTextFiles $richTextFiles = null,
    ) {
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'editor' => self::EDITOR,
            'mode' => self::FULL,
            'blocks' => fn (Options $options): array => $options['mode'] === self::FULL ? $this->blocks : [],
            'headings' => ['h2', 'h3'],
            'content_css' => $this->contentCss,
            'content_style' => null,
            'placeholders' => true,
            'files' => null,
            'empty_data' => '',
        ]);
        $resolver->setAllowedValues('mode', [self::FULL, self::INLINE]);
        $resolver->setAllowedTypes('blocks', 'array');
        $resolver->setAllowedTypes('headings', 'string[]');
        $resolver->setAllowedTypes('content_css', ['null', 'string']);
        $resolver->setAllowedTypes('content_style', ['null', 'string']);
        $resolver->setAllowedTypes('placeholders', 'bool');
        // Before the normalizer of CrudRichTextType: where the files go, a storage the site publishes.
        $resolver->addNormalizer('files', fn (Options $options, mixed $files): mixed => $this->files($files), true);
    }

    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        $placeholders = $options['placeholders']
            ? array_values(array_map(
                static fn (Placeholder $p): array => ['name' => $p->name, 'description' => $p->description],
                $this->texts->placeholders(),
            ))
            : [];
        $site = [
            'static_site_mode' => $options['mode'],
            'static_site_headings' => $options['headings'],
            'static_site_blocks' => array_values($options['blocks']),
            'static_site_placeholders' => $placeholders,
        ];
        if (is_string($options['content_css']) && $options['content_css'] !== '') {
            $site['content_css'] = $this->stylesheet($options['content_css']);
        }
        if (is_string($options['content_style']) && $options['content_style'] !== '') {
            $site['content_style'] = $options['content_style'];
        }
        if (is_string($this->language) && $this->language !== '') {
            $site['language'] = $this->language;
        }
        $own = is_array($view->vars['editor_options'] ?? null) ? $view->vars['editor_options'] : [];
        $view->vars['editor_options'] = array_replace($site, $own);
        $attr = is_array($view->vars['attr'] ?? null) ? $view->vars['attr'] : [];
        $view->vars['attr'] = ['rows' => $options['mode'] === self::INLINE ? 2 : 10] + $attr;
    }

    /**
     * @return false|array<string, mixed>
     */
    private function files(mixed $files): false|array
    {
        if ($files === false || ($files === null && ($this->files === false || $this->richTextFiles === null))) {
            return false;
        }
        $default = $this->files === false ? [] : $this->files;
        $files = match (true) {
            $files === null => $default,
            $files === true => $default,
            is_array($files) => $files + ['storage' => $default['storage'] ?? array_key_first($this->storages)],
            default => throw new InvalidOptionsException('The option "files" is false, true or an array.'),
        };
        $storage = $files['storage'] ?? null;
        if (!is_string($storage) || !isset($this->storages[$storage])) {
            throw new InvalidOptionsException(sprintf(
                'A field of HTML of the site uploads into %s, a storage the site does not publish: name it in static_site.files.storages.',
                is_string($storage) ? '"' . $storage . '"' : 'no storage',
            ));
        }

        return $files;
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
        return CrudRichTextType::class;
    }

    public function getBlockPrefix(): string
    {
        return 'static_site_rich_text';
    }
}
