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

3. Include the CSS in your base template (e.g., `templates/base.html.twig`):

```twig
<link rel="stylesheet" href="{{ asset('bundles/autocomplete/autocomplete.css') }}">
```

4. Use it in your form:

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

use Kerrialnewham\Autocomplete\Provider\Contract\AutocompleteProviderInterface;
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

#### Built-in Themes

The bundle includes four built-in themes, all packaged in a single CSS file:

- **default** - Clean, modern design with pill-shaped chips
- **dark** - Dark mode variant with muted colors
- **cards** - Card-style layout with metadata display (email, domain, role, avatar)
- **bootstrap-5** - Bootstrap 5 compatible styling

To use a theme, specify it in your form:

```php
$builder->add('country', AutocompleteType::class, [
    'provider' => 'countries',
    'theme' => 'dark', // or 'cards', 'bootstrap-5'
]);
```

Theme switching is instant - it's controlled by a `data-autocomplete-theme` attribute on the wrapper element.

#### Override Default Theme Styles

Add custom CSS in your own stylesheet. Use data attribute selectors to target specific themes:

```css
/* Override default theme */
[data-autocomplete-theme="default"] .autocomplete-input {
    border: 2px solid #4f46e5;
    border-radius: 8px;
}

/* Override dark theme */
[data-autocomplete-theme="dark"] .autocomplete-chip {
    background-color: #7c3aed;
}

/* Override all themes */
.autocomplete-option:hover {
    background-color: #eef2ff;
}
```

#### Create a Custom Theme

To create a completely custom theme, add CSS scoped to your theme name:

```css
/* My custom theme */
[data-autocomplete-theme="my-custom"] .autocomplete-wrapper {
    font-family: 'Inter', sans-serif;
}

[data-autocomplete-theme="my-custom"] .autocomplete-input {
    background: linear-gradient(to right, #667eea 0%, #764ba2 100%);
    color: white;
    border: none;
    padding: 1rem;
}

[data-autocomplete-theme="my-custom"] .autocomplete-chip {
    background-color: #667eea;
    color: white;
}
```

Then use it in your form:

```php
$builder->add('country', AutocompleteType::class, [
    'provider' => 'countries',
    'theme' => 'my-custom',
]);
```

#### Disable Bundle CSS

If you want complete control, don't include the bundle's CSS file and write your own from scratch. The bundle will still function - you'll just need to provide your own styles for the HTML structure.

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

## Entity Autocomplete

The bundle provides built-in support for Doctrine entities with automatic provider generation and data transformation.

### Installation

To use entity autocomplete features, install Doctrine ORM:

```bash
composer require doctrine/orm doctrine/doctrine-bundle
```

### Using AutocompleteEntityType

The `AutocompleteEntityType` extends Symfony's `EntityType` and provides autocomplete functionality with zero configuration:

```php
use Kerrialnewham\Autocomplete\Form\Type\AutocompleteEntityType;

$builder->add('author', AutocompleteEntityType::class, [
    'class' => User::class,
    'choice_label' => 'username',
    'placeholder' => 'Search for a user...',
]);
```

#### With Query Builder

Customize the search query:

```php
$builder->add('category', AutocompleteEntityType::class, [
    'class' => Category::class,
    'choice_label' => 'name',
    'query_builder' => function (EntityRepository $repo) {
        return $repo->createQueryBuilder('c')
            ->where('c.active = true')
            ->orderBy('c.name', 'ASC');
    },
]);
```

#### Multiple Selection

```php
$builder->add('tags', AutocompleteEntityType::class, [
    'class' => Tag::class,
    'multiple' => true,
    'choice_label' => 'name',
    'theme' => 'cards',
]);
```

#### With Callable Choice Label

```php
$builder->add('author', AutocompleteEntityType::class, [
    'class' => User::class,
    'choice_label' => fn(User $u) => $u->getFullName(),
    'theme' => 'bootstrap-5',
]);
```

#### Custom Choice Value

Use a property other than 'id' as the identifier:

```php
$builder->add('product', AutocompleteEntityType::class, [
    'class' => Product::class,
    'choice_label' => 'name',
    'choice_value' => 'uuid', // Use UUID instead of ID
]);
```

### Using EntityType with Autocomplete Extension

You can also add autocomplete to existing `EntityType` fields:

```php
use Symfony\Bridge\Doctrine\Form\Type\EntityType;

$builder->add('author', EntityType::class, [
    'class' => User::class,
    'autocomplete' => true, // Enable autocomplete
    'placeholder' => 'Search for a user...',
    'theme' => 'dark',
]);
```

This approach works with all existing EntityType options and requires minimal changes to existing forms.

### Custom Entity Providers

For complex search logic or custom metadata, create a custom provider:

```php
<?php

namespace App\Autocomplete\Provider;

use App\Entity\User;
use App\Repository\UserRepository;
use Kerrialnewham\Autocomplete\Provider\Contract\AutocompleteProviderInterface;
use Kerrialnewham\Autocomplete\Provider\Contract\ChipProviderInterface;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('autocomplete.provider')]
class UserProvider implements AutocompleteProviderInterface, ChipProviderInterface
{
    public function __construct(
        private readonly UserRepository $userRepository,
    ) {
    }

    public function getName(): string
    {
        // Override auto-generated provider
        return 'entity.App\Entity\User';
    }

    public function search(string $query, int $limit, array $selected): array
    {
        // Custom search: email, username, first/last name
        $qb = $this->userRepository->createQueryBuilder('u')
            ->where('LOWER(u.username) LIKE :query')
            ->orWhere('LOWER(u.email) LIKE :query')
            ->orWhere('LOWER(u.firstName) LIKE :query')
            ->orWhere('LOWER(u.lastName) LIKE :query')
            ->setParameter('query', '%' . strtolower($query) . '%')
            ->setMaxResults($limit);

        if (!empty($selected)) {
            $qb->andWhere('u.id NOT IN (:selected)')
                ->setParameter('selected', $selected);
        }

        $users = $qb->getQuery()->getResult();

        return array_map(fn(User $u) => [
            'id' => (string) $u->getId(),
            'label' => $u->getFullName(),
            'meta' => [
                'email' => $u->getEmail(),
                'avatar' => $u->getAvatarUrl(),
                'role' => $u->getRoleLabel(),
            ],
        ], $users);
    }

    public function get(string $id): ?array
    {
        $user = $this->userRepository->find($id);

        if (!$user) {
            return null;
        }

        return [
            'id' => (string) $user->getId(),
            'label' => $user->getFullName(),
            'meta' => [
                'email' => $user->getEmail(),
                'avatar' => $user->getAvatarUrl(),
                'role' => $user->getRoleLabel(),
            ],
        ];
    }
}
```

The custom provider will automatically override the auto-generated one thanks to the naming convention (`entity.App\Entity\User`).

### How It Works

**Entity to ID Conversion:**
- Form field works with entity objects (like standard EntityType)
- Data transformer converts entity ↔ ID for AJAX communication
- Form submission automatically loads entities from database

**Automatic Provider Generation:**
- Provider created on-the-fly based on entity class
- No manual configuration needed for simple cases
- Custom providers automatically override defaults

**Chip Rendering:**
- Selected items rendered server-side via `/_autocomplete/{provider}/chip`
- Full entity data available for rich chip templates
- Supports metadata display (avatars, emails, etc.)

## Advanced Usage

### Custom Provider with Complex Queries

```php
<?php

namespace App\Autocomplete\Provider;

use Kerrialnewham\Autocomplete\Provider\Contract\AutocompleteProviderInterface;
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
