<?php

declare(strict_types=1);

namespace Byfareska\StaticSite\Tests\Unit;

use Byfareska\Crud\Form\CrudRichTextType;
use Byfareska\StaticSite\Editor\RichTextType;
use Byfareska\StaticSite\Text\Placeholder;
use Byfareska\StaticSite\Text\PlaceholderProvider;
use Byfareska\StaticSite\Text\Texts;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\Forms;
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;

final class RichTextTypeTest extends TestCase
{
    public function testTheFieldIsTheRichTextOfCrudWithTheEditorOfTheSite(): void
    {
        $view = $this->forms()->create(RichTextType::class, null, ['mode' => RichTextType::INLINE, 'content_css' => '/site.css'])->createView();

        self::assertSame('static-site', $view->vars['editor']);
        self::assertContains('crud_rich_text', $view->vars['block_prefixes'], 'Drawn by the widget of crud.');
        $options = $view->vars['editor_options'];
        self::assertSame('inline', $options['static_site_mode']);
        self::assertSame([], $options['static_site_blocks'], 'An inline field has no blocks.');
        self::assertSame([['name' => 'email', 'description' => 'Adres']], $options['static_site_placeholders']);
        self::assertSame('/site.css', $options['content_css']);
        self::assertSame('pl', $options['language']);
        self::assertSame(2, $view->vars['attr']['rows']);
        self::assertNull($view->vars['upload_url'], 'Without the files of crud nothing is uploaded.');
    }

    public function testTheBlocksAndOptionsOfTheField(): void
    {
        $view = $this->forms()->create(RichTextType::class, null, ['editor_options' => ['min_height' => 500], 'headings' => ['h3']])->createView();

        self::assertSame([['title' => 'Cytat', 'html' => '<blockquote></blockquote>']], $view->vars['editor_options']['static_site_blocks']);
        self::assertSame(['h3'], $view->vars['editor_options']['static_site_headings']);
        self::assertSame(500, $view->vars['editor_options']['min_height']);
    }

    public function testAFieldUploadsOnlyIntoAStorageTheSitePublishes(): void
    {
        $this->expectException(InvalidOptionsException::class);
        $this->expectExceptionMessage('"private"');
        $this->forms()->create(RichTextType::class, null, ['files' => ['storage' => 'private']]);
    }

    private function forms(): FormFactoryInterface
    {
        $placeholders = new class implements PlaceholderProvider {
            public function placeholders(): iterable
            {
                yield new Placeholder('email', 'a@example.com', 'Adres');
            }
        };
        $texts = new Texts($this->createStub(EntityManagerInterface::class), [], [$placeholders]);

        return Forms::createFormFactoryBuilder()
            ->addType(new CrudRichTextType(['editor' => 'tinymce', 'options' => [], 'files' => false]))
            ->addType(new RichTextType(
                $texts,
                [['title' => 'Cytat', 'html' => '<blockquote></blockquote>']],
                language: 'pl',
                storages: ['public' => 'uploads'],
                files: ['storage' => 'public'],
            ))
            ->getFormFactory();
    }
}
