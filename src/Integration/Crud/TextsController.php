<?php

declare(strict_types=1);

namespace Byfareska\StaticSite\Integration\Crud;

use Byfareska\StaticSite\Editor\RichTextType;
use Byfareska\StaticSite\Text\TextGroup;
use Byfareska\StaticSite\Text\Texts;
use Byfareska\StaticSite\Text\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType as TextFieldType;
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
 * default are dropped (the text follows the code again).
 */
final readonly class TextsController
{
    public function __construct(
        private Texts $texts,
        private FormFactoryInterface $forms,
        private Environment $twig,
        private UrlGeneratorInterface $urls,
        private TranslatorInterface $translator,
        private Panel $panel,
        private ?AuthorizationCheckerInterface $authorization = null,
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
                $values[$item->key] = (string) $form->get('t' . $index)->getData();
            }
            $this->texts->save($values);
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
            };
        }

        return $builder->getForm();
    }

    private function denyUnlessAllowed(): void
    {
        if ($this->panel->role !== null && $this->authorization?->isGranted($this->panel->role) !== true) {
            throw new AccessDeniedException();
        }
    }
}
