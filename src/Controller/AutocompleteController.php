<?php

namespace Kerrialnewham\Autocomplete\Controller;

use Kerrialnewham\Autocomplete\Provider\ChipProviderInterface;
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
    ) {}

    #[Route('/_autocomplete/{provider}', name: 'autocomplete_search', methods: ['GET'])]
    public function search(string $provider, Request $request): Response
    {
        $query = $request->query->get('query', '');
        $limit = (int) $request->query->get('limit', 10);

        $selected = $request->query->all('selected');
        if (!$selected) {
            $selected = $request->query->all('selected[]');
        }

        $theme = $this->templates->theme($request->query->get('theme'));
        $autocompleteProvider = $this->providerRegistry->get($provider);

        $results = $autocompleteProvider->search($query, $limit, $selected);

        return $this->render($this->templates->options($theme), [
            'results' => $results,
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
        $provider = $this->providerRegistry->get($provider);

        if (!$provider instanceof ChipProviderInterface) {
            throw new HttpException(
                500,
                sprintf('Provider "%s" must implement %s to render chips.', $provider, ChipProviderInterface::class)
            );
        }

        $item = $provider->get($id);
        if (!$item) {
            throw new NotFoundHttpException(sprintf('No item found for id "%s".', $id));
        }

        return $this->render($this->templates->chip($theme), [
            'item' => $item,
            'name' => $inputName,
        ]);
    }
}
