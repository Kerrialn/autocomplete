<?php

namespace Kerrialnewham\Autocomplete\Controller;

use Kerrialnewham\Autocomplete\Provider\Choice\ChoicesProvider;
use Kerrialnewham\Autocomplete\Provider\Choice\EnumProvider;
use Kerrialnewham\Autocomplete\Provider\Contract\AutocompleteProviderInterface;
use Kerrialnewham\Autocomplete\Provider\Contract\ChipProviderInterface;
use Kerrialnewham\Autocomplete\Provider\Doctrine\EntityProviderFactory;
use Kerrialnewham\Autocomplete\Provider\ProviderRegistry;
use Kerrialnewham\Autocomplete\Theme\TemplateResolver;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\LocaleAwareInterface;
use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final class AutocompleteController extends AbstractController
{
    public function __construct(
        private readonly ProviderRegistry $providerRegistry,
        private readonly TemplateResolver $templates,
        private readonly ?EntityProviderFactory $entityProviderFactory = null,
        private readonly string $signingSecret = '',
        private readonly ?TranslatorInterface $translator = null,
    ) {}

    #[Route('/_autocomplete/{provider}', name: 'autocomplete_search', methods: ['GET'])]
    public function search(string $provider, Request $request): Response
    {
        $this->assertSigned($request, $provider, 'autocomplete_search');
        $this->applyLocale($request);

        $query = (string) $request->query->get('query', '');
        $limit = (int) $request->query->get('limit', 10);

        $selected = $request->query->all('selected');
        if (!$selected) {
            $selected = $request->query->all('selected[]');
        }

        $theme = $this->templates->theme($request->query->get('theme'));
        $autocompleteProvider = $this->resolveProvider($provider, $request);

        $results = $autocompleteProvider->search($query, $limit, $selected);

        $translationDomain = $request->query->get('translation_domain');
        if ($translationDomain === '') {
            $translationDomain = null;
        }

        return $this->render($this->templates->options($theme), [
            'results' => $results,
            'translation_domain' => $translationDomain,
        ]);
    }

    #[Route('/_autocomplete/{provider}/chip', name: 'autocomplete_chip', methods: ['GET'])]
    public function chip(string $provider, Request $request): Response
    {
        $this->assertSigned($request, $provider, 'autocomplete_search');
        $this->applyLocale($request);

        $id = (string) $request->query->get('id', '');
        if ($id === '') {
            throw new BadRequestHttpException('Missing required query parameter "id".');
        }

        $inputName = (string) $request->query->get('name', 'autocomplete');
        $theme = $this->templates->theme($request->query->get('theme'));
        $providerInstance = $this->resolveProvider($provider, $request);

        if (!$providerInstance instanceof ChipProviderInterface) {
            throw new HttpException(
                500,
                sprintf('Provider "%s" must implement %s to render chips.', $provider, ChipProviderInterface::class)
            );
        }

        $item = $providerInstance->get($id);
        if (!$item) {
            throw new NotFoundHttpException(sprintf('No item found for id "%s".', $id));
        }

        $translationDomain = $request->query->get('translation_domain');
        if ($translationDomain === '') {
            $translationDomain = null;
        }

        $chipSize = (string) $request->query->get('chip_size', 'md');

        return $this->render($this->templates->chip($theme), [
            'item' => $item,
            'name' => $inputName,
            'translation_domain' => $translationDomain,
            'chip_size' => $chipSize,
        ]);
    }

    private function resolveProvider(string $providerName, Request $request): AutocompleteProviderInterface
    {
        // 1. Direct registry lookup
        if ($this->providerRegistry->has($providerName)) {
            return $this->providerRegistry->get($providerName);
        }

        // 2. Static choices encoded in provider name (choices.{base64url-json})
        if (str_starts_with($providerName, 'choices.')) {
            $encoded = substr($providerName, strlen('choices.'));
            try {
                $choices = json_decode(base64_decode(strtr($encoded, '-_', '+/')), true, 512, \JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                throw new BadRequestHttpException('Invalid choices provider encoding.');
            }
            if (!\is_array($choices)) {
                throw new BadRequestHttpException('Invalid choices provider encoding.');
            }

            return new ChoicesProvider($choices);
        }

        // 3. Doctrine entity: class exists and is a managed entity
        if ($this->entityProviderFactory !== null && class_exists($providerName) && $this->isDoctrineEntity($providerName)) {
            $choiceLabel = (string) $request->query->get('choice_label', '');
            $choiceValue = (string) $request->query->get('choice_value', '');

            return $this->entityProviderFactory->createProvider(
                class: $providerName,
                queryBuilder: null,
                choiceLabel: $choiceLabel !== '' ? $choiceLabel : null,
                choiceValue: $choiceValue !== '' ? $choiceValue : null,
            );
        }

        // 4. Backed enum
        if (enum_exists($providerName) && is_subclass_of($providerName, \BackedEnum::class)) {
            $choiceLabel = (string) $request->query->get('choice_label', '');
            $translationDomain = $request->query->get('translation_domain');
            if ($translationDomain === '') {
                $translationDomain = null;
            }

            $isTranslatable = is_subclass_of($providerName, TranslatableInterface::class);

            $provider = new EnumProvider(
                enumClass: $providerName,
                choiceLabel: $choiceLabel !== '' ? $choiceLabel : null,
                translator: ($translationDomain !== null || $isTranslatable) ? $this->translator : null,
                translationDomain: $translationDomain,
            );

            $this->providerRegistry->register($provider, $providerName);

            return $provider;
        }

        // 5. Fall through to registry (throws)
        return $this->providerRegistry->get($providerName);
    }

    private function isDoctrineEntity(string $class): bool
    {
        if ($this->entityProviderFactory === null) {
            return false;
        }

        $managerRegistry = $this->entityProviderFactory->getRegistry();

        return $managerRegistry->getManagerForClass($class) !== null;
    }

    private function applyLocale(Request $request): void
    {
        $locale = (string) $request->query->get('locale', '');
        if ($locale !== '') {
            $request->setLocale($locale);
            if ($this->translator instanceof LocaleAwareInterface) {
                $this->translator->setLocale($locale);
            }
        }
    }

    /**
     * Signed request validation (HMAC) to prevent tampering with provider/theme/domain/choice_*.
     *
     * Required query params:
     *  - ts (unix timestamp)
     *  - sig (hex HMAC-SHA256)
     */
    private function assertSigned(Request $request, string $provider, string $routeName): void
    {
        if ($this->signingSecret === '') {
            throw new HttpException(500, 'Autocomplete signing secret is not configured.');
        }

        $ts = (int) $request->query->get('ts', 0);
        $sig = (string) $request->query->get('sig', '');

        if ($ts <= 0 || $sig === '') {
            throw new HttpException(403, 'Missing signature.');
        }

        // 10 minute expiry window
        if (abs(time() - $ts) > 600) {
            throw new HttpException(403, 'Signature expired.');
        }

        // bind signature to stable params that must not be tampered with
        $theme  = (string) $request->query->get('theme', '');
        $td     = (string) $request->query->get('translation_domain', '');
        $cl     = (string) $request->query->get('choice_label', '');
        $cv     = (string) $request->query->get('choice_value', '');
        $locale = (string) $request->query->get('locale', '');

        // optionally bind to user (prevents sharing signed URL between users)
        $userId = $this->getUser()?->getUserIdentifier() ?? '';

        $payload = implode('|', [
            $routeName,
            $provider,
            $theme,
            $td,
            $cl,
            $cv,
            $locale,
            $userId,
            (string) $ts,
        ]);

        $expected = hash_hmac('sha256', $payload, $this->signingSecret);

        if (!hash_equals($expected, $sig)) {
            throw new HttpException(403, 'Invalid signature.');
        }
    }
}
