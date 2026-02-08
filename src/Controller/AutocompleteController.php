<?php

namespace Kerrialnewham\Autocomplete\Controller;

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

class AutocompleteController extends AbstractController
{
    public function __construct(
        private readonly ProviderRegistry $providerRegistry,
        private readonly TemplateResolver $templates,
        private readonly ?EntityProviderFactory $entityProviderFactory = null,
    ) {}

    #[Route('/_autocomplete/{provider}', name: 'autocomplete_search', methods: ['GET'])]
    public function search(string $provider, Request $request): Response
    {
        $query = (string) $request->query->get('query', '');
        $limit = (int) $request->query->get('limit', 10);

        $selected = $request->query->all('selected');
        if (!$selected) {
            $selected = $request->query->all('selected[]');
        }

        $theme = $this->templates->theme($request->query->get('theme'));
        $autocompleteProvider = $this->resolveProvider($provider);

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
        $id = (string) $request->query->get('id', '');
        if ($id === '') {
            throw new BadRequestHttpException('Missing required query parameter "id".');
        }

        $inputName = (string) $request->query->get('name', 'autocomplete');
        $theme = $this->templates->theme($request->query->get('theme'));
        $providerInstance = $this->resolveProvider($provider);

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

        return $this->render($this->templates->chip($theme), [
            'item' => $item,
            'name' => $inputName,
            'translation_domain' => $translationDomain,
        ]);
    }

    private function resolveProvider(string $providerName): AutocompleteProviderInterface
    {
        if ($this->providerRegistry->has($providerName)) {
            return $this->providerRegistry->get($providerName);
        }

        if (str_starts_with($providerName, 'entity.') && $this->entityProviderFactory !== null) {
            $entityClass = substr($providerName, 7);

            if (!class_exists($entityClass)) {
                throw new NotFoundHttpException(
                    sprintf('Entity class "%s" does not exist for provider "%s".', $entityClass, $providerName)
                );
            }

            return $this->entityProviderFactory->createProvider(
                class: $entityClass,
                queryBuilder: null,
                choiceLabel: null,
                choiceValue: null,
            );
        }

        return $this->providerRegistry->get($providerName);
    }
}
