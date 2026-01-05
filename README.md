# Symfony Autocomplete Bundle

A highly customizable autocomplete bundle for Symfony that uses server-side rendering with Twig templates and Stimulus for interactivity. Unlike other solutions, this bundle doesn't depend on Tom Select or similar libraries, giving you complete control over styling and behavior.

## Features

- Server-side rendering with Twig templates for maximum customizability
- Stimulus-based client-side interactivity
- Support for single and multiple selection modes
- Keyboard navigation (arrow keys, enter, escape)
- Debounced search requests
- Extensible provider system
- Customizable templates for options, chips, and the entire widget
- Built for Symfony 7+ and PHP 8.2+

## Installation

Install the bundle via Composer:

```bash
composer require kerrialnewham/autocomplete
```

### Configuration

Add the bundle routes to your `config/routes.php`:

```php
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

return function (RoutingConfigurator $routes) {
    $routes->import('@AutocompleteBundle/config/routes.php');
};
```

Or in `config/routes.yaml`:

```yaml
autocomplete:
    resource: '@AutocompleteBundle/config/routes.php'
```

## Quick Start

The bundle includes example providers to help you get started quickly:

### Try the Country Provider

1. Register the example provider in `config/services.yaml`:

```yaml
services:
    Kerrialnewham\Autocomplete\Example\CountryProvider:
        tags:
            - { name: 'autocomplete.provider' }
```

2. Add the form theme to `config/packages/twig.yaml`:

```yaml
twig:
    form_themes:
        - '@Autocomplete/form/autocomplete_widget.html.twig'
```

3. Use it in your form:

```php
use Kerrialnewham\Autocomplete\Form\AutocompleteType;

$builder->add('country', AutocompleteType::class, [
    'provider' => 'countries',
    'placeholder' => 'Search for a country...',
]);
```

### Live Demo Page

Want to see it in action first? The bundle includes a complete demo page:

1. Follow the steps above to register the CountryProvider and form theme
2. Import the demo routes in `config/routes.php`:

```php
$routes->import('@AutocompleteBundle/src/Example/demo_routes.php');
```

3. Visit `/autocomplete/demo` in your browser

The demo showcases single selection, multiple selection with chips, custom configurations, and form submission handling.

See the [Example Providers](src/Example/README.md) for more examples including database integration.

## Usage

### 1. Create an Autocomplete Provider

Providers define how to search and retrieve data for your autocomplete fields. Create a class that implements `AutocompleteProviderInterface`:

```php
<?php

namespace App\Autocomplete\Provider;

use Kerrialnewham\Autocomplete\Autocomplete\Provider\AutocompleteProviderInterface;
use App\Repository\UserRepository;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('autocomplete.provider')]
class UserProvider implements AutocompleteProviderInterface
{
    public function __construct(
        private readonly UserRepository $userRepository,
    ) {
    }

    public function getName(): string
    {
        return 'users';
    }

    public function search(string $query, int $limit, array $selected): array
    {
        $users = $this->userRepository->searchByName($query, $limit, $selected);

        return array_map(function ($user) {
            return [
                'id' => (string) $user->getId(),
                'label' => $user->getFullName(),
                'meta' => [
                    'email' => $user->getEmail(),
                ],
            ];
        }, $users);
    }
}
```

The `search()` method must return an array of items with this structure:

```php
[
    [
        'id' => 'unique-id',
        'label' => 'Display Label',
        'meta' => [...], // Optional additional data
    ],
    // ...
]
```

### 2. Use in Forms

#### Using AutocompleteType

```php
<?php

namespace App\Form;

use Kerrialnewham\Autocomplete\Form\AutocompleteType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;

class MyFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void  
    {
        $builder
            ->add('user', AutocompleteType::class, [
                'provider' => 'users',
                'placeholder' => 'Search for a user...',
                'multiple' => false,
            ])
            ->add('tags', AutocompleteType::class, [
                'provider' => 'tags',
                'placeholder' => 'Add tags...',
                'multiple' => true,
                'min_chars' => 2,
                'debounce' => 500,
                'limit' => 20,
            ]);
    }
}
```

#### Available Options

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `provider` | string | 'default' | Name of the autocomplete provider |
| `multiple` | bool | false | Allow multiple selections |
| `placeholder` | string | 'Search...' | Input placeholder text |
| `min_chars` | int | 1 | Minimum characters before search |
| `debounce` | int | 300 | Debounce delay in milliseconds |
| `limit` | int | 10 | Maximum results to fetch |
| `attr` | array | [] | Additional HTML attributes |

### 3. Register the Form Theme

Add the autocomplete form theme to your `config/packages/twig.yaml`:

```yaml
twig:
    form_themes:
        - '@Autocomplete/form/autocomplete_widget.html.twig'
```

### 4. Use Directly in Twig (Without Forms)

You can also use the autocomplete widget directly in Twig templates:

```twig
{% include '@Autocomplete/autocomplete/autocomplete.html.twig' with {
    name: 'user_id',
    provider: 'users',
    multiple: false,
    placeholder: 'Search for a user...',
    min_chars: 1,
    debounce: 300,
    limit: 10,
    value: null
} %}
```

## Customization

### Customize Templates

The bundle provides three main templates that you can override:

#### 1. Options Template (`_options.html.twig`)

Override this to customize how search results are displayed:

```twig
{# templates/autocomplete/_options.html.twig #}
{% for result in results %}
    <div class="autocomplete-option custom-option"
         role="option"
         data-autocomplete-option-value="{{ result.id }}"
         data-autocomplete-option-label="{{ result.label }}"
         {% if result.meta is defined %}data-autocomplete-option-meta="{{ result.meta|json_encode }}"{% endif %}
         tabindex="-1">
        <div class="option-content">
            <strong>{{ result.label }}</strong>
            {% if result.meta.email is defined %}
                <small>{{ result.meta.email }}</small>
            {% endif %}
        </div>
    </div>
{% else %}
    <div class="autocomplete-no-results">No users found</div>
{% endfor %}
```

#### 2. Chip Template (`_chip.html.twig`)

Override this to customize selected item badges:

```twig
{# templates/autocomplete/_chip.html.twig #}
<div class="autocomplete-chip custom-chip"
     data-autocomplete-chip-value="{{ item.id }}"
     data-autocomplete-target="chip">
    <span class="chip-avatar">{{ item.label|first }}</span>
    <span class="autocomplete-chip-label">{{ item.label }}</span>
    <button type="button"
            class="autocomplete-chip-remove"
            data-action="autocomplete#removeChip"
            aria-label="Remove {{ item.label }}">
        ✕
    </button>
    <input type="hidden"
           name="{{ name }}[]"
           value="{{ item.id }}"
           data-autocomplete-target="selectedInput">
</div>
```

#### 3. Widget Template (`autocomplete.html.twig`)

Override the entire widget template for complete control.

### Customize Styling

You can override the default styles by creating your own CSS:

```css
/* Custom styling */
.autocomplete-input {
    border: 2px solid #4f46e5;
    border-radius: 8px;
}

.autocomplete-option:hover {
    background-color: #eef2ff;
}

.autocomplete-chip {
    background-color: #4f46e5;
    color: white;
}
```

### Stimulus Controller Events

The autocomplete controller dispatches custom events:

```javascript
// Listen for selection events
document.addEventListener('autocomplete:select', (event) => {
    console.log('Selected:', event.detail.item);
});

// Listen for removal events
document.addEventListener('autocomplete:remove', (event) => {
    console.log('Removed:', event.detail.value);
});
```

## Advanced Usage

### Custom Provider with Complex Queries

```php
<?php

namespace App\Autocomplete\Provider;

use Kerrialnewham\Autocomplete\Autocomplete\Provider\AutocompleteProviderInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('autocomplete.provider')]
class ProductProvider implements AutocompleteProviderInterface
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function getName(): string
    {
        return 'products';
    }

    public function search(string $query, int $limit, array $selected): array
    {
        $qb = $this->em->createQueryBuilder();
        $qb->select('p')
            ->from(Product::class, 'p')
            ->where('p.name LIKE :query OR p.sku LIKE :query')
            ->andWhere('p.active = true')
            ->setParameter('query', '%' . $query . '%')
            ->setMaxResults($limit);

        if (!empty($selected)) {
            $qb->andWhere('p.id NOT IN (:selected)')
                ->setParameter('selected', $selected);
        }

        $products = $qb->getQuery()->getResult();

        return array_map(function ($product) {
            return [
                'id' => (string) $product->getId(),
                'label' => sprintf('%s (%s)', $product->getName(), $product->getSku()),
                'meta' => [
                    'price' => $product->getPrice(),
                    'stock' => $product->getStock(),
                ],
            ];
        }, $products);
    }
}
```

## Architecture

### Server-Side Rendering

Unlike traditional autocomplete libraries that render results with JavaScript, this bundle uses Twig templates to render HTML on the server. This approach provides:

- **Complete control** over HTML structure and styling
- **Better SEO** and accessibility
- **Consistency** with your existing Twig templates
- **Type safety** with Symfony's templating system

### How It Works

1. User types in the input field
2. Stimulus controller debounces input and sends AJAX request to `/autocomplete/{provider}`
3. Controller fetches results from the provider
4. Results are rendered using `_options.html.twig`
5. HTML is returned and injected into the dropdown
6. User selects an option
7. In multiple mode, a chip is created using `_chip.html.twig`

## Requirements

- PHP 8.2 or higher
- Symfony 7.0 or higher
- Stimulus Bundle 2.9 or higher

## License

This bundle is released under the MIT License.

## Contributing

Contributions are welcome! Please submit pull requests or open issues on GitHub.
