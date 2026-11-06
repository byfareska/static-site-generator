<?php

declare(strict_types=1);

namespace Byfareska\StaticSite\Integration\Crud;

use Byfareska\Crud\Files\FileReference;
use Byfareska\Crud\Files\FileUsages;
use Byfareska\Crud\Files\Form\CrudFileType;
use Byfareska\Crud\Files\RichTextFiles;
use Byfareska\StaticSite\Editor\RichTextType;
use Byfareska\StaticSite\Text\TextItem;
use Byfareska\StaticSite\Text\TextGroup;
use Byfareska\StaticSite\Text\Texts;
use Byfareska\StaticSite\Text\TextType;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType as TextFieldType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

/**
 * The texts of the site, one page per group of the catalogs: a form with all of its texts. Saved values equal to the
 * default are dropped (the text follows the code again). Texts of files are fields of files of crud (an empty one is
 * the default again); the files the texts use are told to crud's index of usages, so that pruning keeps them.
 */
final readonly class TextsController
{
    /** The texts as a "resource" of crud's index of usages of files. */
    public const string USAGE_RESOURCE = 'static_site_text';

    public function __construct(
        private Texts $texts,
        private FormFactoryInterface $forms,
        private Environment $twig,
        private UrlGeneratorInterface $urls,
        private TranslatorInterface $translator,
        private Panel $panel,
        private ?AuthorizationCheckerInterface $authorization = null,
        /** @var false|array<string, mixed> static_site.files.editor */
        #[Autowire('%static_site.files.editor%')]
        private false|array $files = false,
        private ?FileUsages $usages = null,
        private ?RichTextFiles $richTextFiles = null,
    ) {
    }

    public function index(): Response
    {
        $this->denyUnlessAllowed();
        $first = array_key_first($this->texts->groups());
        if ($first === null) {
            return new Response($this->twig->render('@StaticSite/crud/texts.html.twig', [
                'groups' => [],
                'group' => null,
                'form' => null,
                'placeholders' => [],
            ]));
        }

        return new RedirectResponse($this->urls->generate('static_site_texts_group', ['group' => $first]));
    }

    public function edit(Request $request, string $group): Response
    {
        $this->denyUnlessAllowed();
        $textGroup = $this->texts->group($group) ?? throw new NotFoundHttpException(sprintf('No group of texts "%s".', $group));
        $form = $this->form($textGroup)->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $values = [];
            foreach ($textGroup->items as $index => $item) {
                $value = (string) $form->get('t' . $index)->getData();
                $values[$item->key] = $item->type->isFile() && $value === '' ? $item->default : $value;
            }
            $this->texts->save($values);
            $this->recordUsages($textGroup);
            $session = $request->hasSession() ? $request->getSession() : null;
            if ($session instanceof FlashBagAwareSessionInterface) {
                $session->getFlashBag()->add('toast', $this->translator->trans('static_site.texts.saved'));
            }

            return new RedirectResponse($this->urls->generate('static_site_texts_group', ['group' => $group]), 303);
        }

        return new Response($this->twig->render('@StaticSite/crud/texts.html.twig', [
            'groups' => $this->texts->groups(),
            'group' => $textGroup,
            'form' => $form->createView(),
            'placeholders' => $this->texts->placeholders(),
        ]), $form->isSubmitted() ? 422 : 200);
    }

    private function form(TextGroup $group): FormInterface
    {
        $builder = $this->forms->createNamedBuilder('texts');
        foreach ($group->items as $index => $item) {
            $value = $this->texts->raw($item->key);
            $options = [
                'label' => $item->label,
                'help' => $item->help,
                'required' => false,
                'data' => $value,
                'empty_data' => '',
                'attr' => ['data-default' => $item->default],
            ];
            match ($item->type) {
                TextType::Html => $builder->add('t' . $index, RichTextType::class, $options),
                TextType::Inline => $builder->add('t' . $index, RichTextType::class, $options + ['mode' => RichTextType::INLINE]),
                TextType::Multiline => $builder->add('t' . $index, TextareaType::class, ['attr' => $options['attr'] + ['rows' => max(3, substr_count($value, "\n") + 2)]] + $options),
                TextType::Plain => $builder->add('t' . $index, TextFieldType::class, $options),
                TextType::Image, TextType::File => $this->fileField($builder, 't' . $index, $item, $options),
            };
        }

        return $builder->getForm();
    }

    /**
     * A field of files of crud into the storage of the editor; a text field (a path of the site) without crud's files.
     *
     * @param array<string, mixed> $options
     */
    private function fileField(FormBuilderInterface $builder, string $name, TextItem $item, array $options): void
    {
        if ($this->files === false || $this->richTextFiles === null) {
            $builder->add($name, TextFieldType::class, $options);

            return;
        }
        $builder->add($name, CrudFileType::class, [
            'storage' => $this->files['storage'] ?? null,
            'accept' => $item->type === TextType::Image ? ['images'] : [],
            'empty_data' => '',
        ] + $options);
    }

    /** The files the texts of a group use now, in crud's index of usages (resource "static_site_text"). */
    private function recordUsages(TextGroup $group): void
    {
        if ($this->usages === null || !$this->usages->tracks()) {
            return;
        }
        foreach ($group->items as $item) {
            $value = $this->texts->raw($item->key);
            $references = match (true) {
                $item->type->isFile() => array_values(array_filter(
                    [FileReference::tryParse($value)],
                    static fn (?FileReference $reference): bool => $reference !== null && !$reference->isExternal(),
                )),
                $item->type->isHtml() && $this->richTextFiles !== null => $this->richTextFiles->references($value),
                default => [],
            };
            $this->usages->replace(self::USAGE_RESOURCE, $item->key, 'value', $references);
        }
    }

    private function denyUnlessAllowed(): void
    {
        if ($this->panel->role !== null && $this->authorization?->isGranted($this->panel->role) !== true) {
            throw new AccessDeniedException();
        }
    }
}
