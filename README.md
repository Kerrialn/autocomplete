# Symfony Autocomplete Bundle

Server-side rendered autocomplete for Symfony. No Tom Select, no build step. Twig templates + Stimulus.
Highly customizable, just override the twig template.

## Installation

```bash
composer require kerrialnewham/autocomplete
```

Add routes to `config/routes.yaml`:

```yaml
autocomplete:
    resource: '@AutocompleteBundle/config/routes.php'
```

or

```php
return static function (RoutingConfigurator $routingConfigurator): void {
    $routingConfigurator->import('@AutocompleteBundle/config/routes.php');
}
```

Add the form theme to `config/packages/twig.yaml`:

```yaml
twig:
    form_themes:
        - '@Autocomplete/form/autocomplete_widget.html.twig'
```
or

```php
return static function (ContainerConfigurator $containerConfigurator): void {
    $containerConfigurator->extension('twig', [
            'form_themes' => [
                    '@Autocomplete/form/autocomplete_widget.html.twig',
            ],
    ]);
};
```

Include the CSS:

```twig
<link rel="stylesheet" href="{{ asset('bundles/autocomplete/autocomplete.css') }}">
```

Requires `APP_SECRET` in your `.env` for HMAC-signed requests.

## Demos

![ScreenRecording2026-02-12at10 47 36-ezgif com-video-to-gif-converter](https://github.com/user-attachments/assets/0847d2ba-a545-4f1e-bec0-73fdf068225b)
![cards-2-ezgif com-video-to-gif-converter](https://github.com/user-attachments/assets/37d62029-3c18-4b6c-a6be-1439e6350a1e)

## Form Types

### AutocompleteType

```php
use Kerrialnewham\Autocomplete\Form\Type\AutocompleteType;

$builder->add('user', AutocompleteType::class, [
    'provider' => UserProvider::class,
    'placeholder' => 'Search for a user...',
]);

$builder->add('tags', AutocompleteType::class, [
    'provider' => TagProvider::class,
    'multiple' => true,
    'theme' => 'dark',
    'min_chars' => 2,
    'debounce' => 500,
    'limit' => 20,
]);
```

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `provider` | string | `null` | Provider FQCN (e.g. `UserProvider::class`) |
| `multiple` | bool | `false` | Multiple selections |
| `placeholder` | string | `'Search...'` | Placeholder text |
| `min_chars` | int | `1` | Min characters before search |
| `debounce` | int | `300` | Debounce in ms |
| `limit` | int | `10` | Max results |
| `theme` | string\|null | `null` | Theme name |
| `floating_label` | bool\|null | `null` | Bootstrap 5 floating label support |
| `extra_params` | array | `[]` | Extra query parameters passed to the provider |
| `attr` | array | `[]` | HTML attributes |

### InternationalDialCodeType

```php
use Kerrialnewham\Autocomplete\Form\Type\InternationalDialCodeType;

$builder->add('dialCode', InternationalDialCodeType::class);
```

Displays flag emoji, country name, and dial code.

### Adding Autocomplete to Existing Types

Pass `'autocomplete' => true` to any Symfony choice or entity type. Providers are resolved automatically.

```php
$builder->add('author', EntityType::class, [
    'class' => User::class,
    'autocomplete' => true,
]);

$builder->add('status', EnumType::class, [
    'class' => Status::class,
    'autocomplete' => true,
]);

$builder->add('country', CountryType::class, [
    'autocomplete' => true,
    'theme' => 'dark',
]);

$builder->add('language', LanguageType::class, [
    'autocomplete' => true,
]);

$builder->add('currency', CurrencyType::class, [
    'autocomplete' => true,
]);

$builder->add('timezone', TimezoneType::class, [
    'autocomplete' => true,
]);

$builder->add('locale', LocaleType::class, [
    'autocomplete' => true,
]);
```

**Note:** EntityType with `autocomplete: true` does not support the `query_builder` or closure-based `choice_label` options, because closures cannot survive the HTTP boundary between form rendering and the AJAX search request. Use a string `choice_label` (e.g. `'name'`) or create a [custom provider](#custom-provider) instead.

## Providers

Providers are identified by their **fully qualified class name (FQCN)**. The `AutocompleteProviderInterface` has a single `search()` method — no `getName()` required.

### Built-in

The built-in Symfony Intl providers are resolved automatically when using the corresponding form types with `autocomplete: true` (CountryType, LanguageType, etc.).

| Provider | Data |
|----------|------|
| `CountryProvider` | Countries |
| `LanguageProvider` | Languages |
| `LocaleProvider` | Locales |
| `CurrencyProvider` | Currencies |
| `TimezoneProvider` | Timezones |
| `DialCodeProvider` | Dial codes with flag emoji |

### Custom Provider

Implement `AutocompleteProviderInterface` for search. Add `ChipProviderInterface` for server-side chip rendering in multiple mode.

```php
use Kerrialnewham\Autocomplete\Provider\Contract\AutocompleteProviderInterface;
use Kerrialnewham\Autocomplete\Provider\Contract\ChipProviderInterface;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('autocomplete.provider')]
class UserProvider implements AutocompleteProviderInterface, ChipProviderInterface
{
    public function __construct(private readonly UserRepository $userRepository) {}

    public function search(string $query, int $limit, array $selected): array
    {
        return array_map(fn($user) => [
            'id' => (string) $user->getId(),
            'label' => $user->getFullName(),
            'meta' => ['email' => $user->getEmail()],
        ], $this->userRepository->searchByName($query, $limit, $selected));
    }

    public function get(string $id): ?array
    {
        $user = $this->userRepository->find($id);
        if (!$user) return null;

        return [
            'id' => (string) $user->getId(),
            'label' => $user->getFullName(),
            'meta' => ['email' => $user->getEmail()],
        ];
    }
}
```

Reference the provider by its class name:

```php
$builder->add('user', AutocompleteType::class, [
    'provider' => UserProvider::class,
]);
```

Results must return `id` (required), `label` (required), and `meta` (optional).

### Extra Parameters

Use `extra_params` to pass context to your provider via the AJAX request. This is useful for dependent/filtered selects where the provider needs runtime context (e.g. filtering cities by the selected country).

```php
$builder->add('city', AutocompleteType::class, [
    'provider' => CityProvider::class,
    'extra_params' => [
        'country' => $country->getId(),
    ],
]);
```

Read them in your provider via `RequestStack`:

```php
use Symfony\Component\HttpFoundation\RequestStack;

#[AutoconfigureTag('autocomplete.provider')]
class CityProvider implements AutocompleteProviderInterface
{
    public function __construct(
        private readonly CityRepository $cityRepository,
        private readonly RequestStack $requestStack,
    ) {}

    public function search(string $query, int $limit, array $selected): array
    {
        $request = $this->requestStack->getCurrentRequest();
        $countryId = $request?->query->get('country');

        $qb = $this->cityRepository->createQueryBuilder('c')
            ->leftJoin('c.country', 'country')
            ->orderBy('c.name', 'ASC');

        if ($countryId) {
            $qb->andWhere('country.id = :country')
                ->setParameter('country', $countryId, 'uuid');
        }

        if (!empty($selected)) {
            $qb->andWhere($qb->expr()->notIn('c.id', ':selected'))
                ->setParameter('selected', $selected);
        }

        $cities = $qb->getQuery()->getResult();

        $results = [];
        foreach ($cities as $city) {
            $label = $city->getName();

            if ($query !== '' && !str_contains(mb_strtolower($label), mb_strtolower($query))) {
                continue;
            }

            $results[] = ['id' => (string) $city->getId(), 'label' => $label];
        }

        return array_slice($results, 0, $limit);
    }
}
```

### Overriding Entity Providers

When using `EntityType` with `autocomplete: true`, a provider is auto-generated using the entity FQCN as the provider name (e.g. `App\Entity\User`). To override it, register a custom provider under the same name:

```php
#[AutoconfigureTag('autocomplete.provider')]
class UserProvider implements AutocompleteProviderInterface, ChipProviderInterface
{
    // ... your custom search/get logic
}
```

Then pass it via the `provider` option:

```php
$builder->add('author', EntityType::class, [
    'class' => User::class,
    'provider' => UserProvider::class,
    'autocomplete' => true,
]);
```

## Security

All AJAX requests are signed with HMAC-SHA256 using `APP_SECRET`. This happens automatically.

The signature binds the route name, provider, theme, translation domain, choice parameters, locale, user ID, and timestamp. Signatures expire after 10 minutes.

This prevents provider tampering, parameter injection, cross-user replay, and replay attacks. Invalid signatures return `403 Forbidden`.

## PhoneNumberType

Compound form type: dial code autocomplete + phone number text input. Stores as E.164 (e.g. `+447911123456`).

```php
use Kerrialnewham\Autocomplete\Form\Type\PhoneNumberType;

$builder->add('phone', PhoneNumberType::class, [
    'theme' => 'flowbite',
]);
```

- `GB` + `7911123456` becomes `+447911123456`
- `+447911123456` becomes dial code `GB`, number `7911123456`
- NANP: longest dial code wins (`+1242...` resolves to Bahamas, not US)

### Doctrine DBAL Type

Registers automatically when Doctrine DBAL is available.

```php
#[ORM\Column(type: 'phone_number', nullable: true)]
private ?string $phone = null;
```

Maps to `VARCHAR(20)`.

## Themes

5 built-in themes, all in one CSS file, scoped via `data-autocomplete-theme`:

| Theme | Description |
|-------|-------------|
| `default` | Clean, modern, pill-shaped chips |
| `dark` | Dark mode |
| `cards` | Card layout with metadata (avatar, email, role) |
| `bootstrap-5` | Bootstrap 5 compatible |
| `flowbite` | Flowbite UI |

```php
$builder->add('country', AutocompleteType::class, [
    'provider' => CountryProvider::class,
    'theme' => 'dark',
]);
```

### Floating Labels (Bootstrap 5)

The `bootstrap-5` theme supports Bootstrap 5 floating labels. Set `floating_label` to `true`:

```php
$builder->add('country', CountryType::class, [
    'autocomplete' => true,
    'theme' => 'bootstrap-5',
    'floating_label' => true,
]);
```

The label floats up on focus and when the input has content. Without `floating_label`, the label renders above the input as usual.

### Overriding Templates

Override any template using [Symfony's bundle template override](https://symfony.com/doc/current/bundles/override.html#templates). Create the file at `templates/bundles/AutocompleteBundle/` with whatever markup you want.

Each theme has three templates:

| Template | Override path |
|----------|--------------|
| Widget | `templates/bundles/AutocompleteBundle/theme/{theme}/autocomplete.html.twig` |
| Options | `templates/bundles/AutocompleteBundle/theme/{theme}/_options.html.twig` |
| Chip | `templates/bundles/AutocompleteBundle/theme/{theme}/_chip.html.twig` |

Example — override the options template:

```twig
{# templates/bundles/AutocompleteBundle/theme/default/_options.html.twig #}
{% if results is empty %}
    <div class="no-results">Nothing found</div>
{% else %}
    <ul>
        {% for item in results %}
            <li class="autocomplete-option"
                data-autocomplete-option-value="{{ item.id }}"
                data-autocomplete-option-label="{{ item.label|e('html_attr') }}">
                {{ item.label }}
            </li>
        {% endfor %}
    </ul>
{% endif %}
```

Use any HTML, any CSS framework. The only requirement is `data-autocomplete-option-value` / `data-autocomplete-option-label` on options and Stimulus `data-*-target` attributes on chips.

### Overriding CSS

Target a specific theme:

```css
[data-autocomplete-theme="default"] .autocomplete-input {
    border: 2px solid #4f46e5;
}
```

Target all themes:

```css
.autocomplete-option:hover {
    background-color: #eef2ff;
}
```

### Custom Theme

**CSS-only** — the `default` theme HTML is used, your CSS overrides styling:

```css
[data-autocomplete-theme="my-theme"] .autocomplete-input {
    background: #667eea;
    color: white;
}
```

```php
$builder->add('field', AutocompleteType::class, [
    'provider' => UserProvider::class,
    'theme' => 'my-theme',
]);
```

**Full custom** — create templates at `templates/bundles/AutocompleteBundle/theme/my-theme/` with any markup you want.

### Events

```javascript
document.addEventListener('autocomplete:select', (e) => console.log(e.detail.item));
document.addEventListener('autocomplete:remove', (e) => console.log(e.detail.value));
```

## Requirements

- PHP 8.1+, Symfony 6.4+, Stimulus Bundle 2.9+
- Doctrine ORM (optional, for entity autocomplete)
- Doctrine DBAL (optional, for phone number column type)

## License

MIT
