<?php

declare(strict_types=1);

namespace Byfareska\StaticSite\Integration\Crud;

use Byfareska\StaticSite\Content\SiteSettings;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\FormFactoryInterface;
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
 * The settings of the site in the panel: whether every uploaded file is published or only those the pages use. A
 * change is an unpublished change of the site.
 */
final readonly class SettingsController
{
    /**
     * @param array<string, bool|null> $storagesPublishAll static_site.files.storages.<name>.publish_all
     */
    public function __construct(
        private SiteSettings $settings,
        private FormFactoryInterface $forms,
        private Environment $twig,
        private UrlGeneratorInterface $urls,
        private TranslatorInterface $translator,
        private Panel $panel,
        #[Autowire('%static_site.files.storages_publish_all%')]
        private array $storagesPublishAll,
        private ?AuthorizationCheckerInterface $authorization = null,
    ) {
    }

    /** Whether the page has anything to set: a storage of files that follows the panel. */
    public function hasSettings(): bool
    {
        return in_array(null, $this->storagesPublishAll, true);
    }

    public function __invoke(Request $request): Response
    {
        if ($this->panel->role !== null && $this->authorization?->isGranted($this->panel->role) !== true) {
            throw new AccessDeniedException();
        }
        if (!$this->hasSettings()) {
            throw new NotFoundHttpException('The site has no settings in the panel.');
        }
        $form = $this->forms->createNamedBuilder('settings')
            ->add('publishAllFiles', CheckboxType::class, [
                'label' => 'static_site.settings.publish_all_files',
                'help' => 'static_site.settings.publish_all_files_help',
                'required' => false,
                'data' => $this->settings->publishAllFiles(),
            ])
            ->getForm();
        if ($request->isMethod('POST')) {
            // Submitted whatever is sent: an unticked checkbox alone sends nothing.
            $form->submit($request->request->all('settings'));
        }

        if ($form->isSubmitted() && $form->isValid()) {
            $this->settings->setPublishAllFiles((bool) $form->get('publishAllFiles')->getData());
            $session = $request->hasSession() ? $request->getSession() : null;
            if ($session instanceof FlashBagAwareSessionInterface) {
                $session->getFlashBag()->add('toast', $this->translator->trans('static_site.settings.saved'));
            }

            return new RedirectResponse($this->urls->generate('static_site_settings'), 303);
        }

        return new Response(
            $this->twig->render('@StaticSite/crud/settings.html.twig', ['form' => $form->createView()]),
            $form->isSubmitted() ? 422 : 200,
        );
    }
}
