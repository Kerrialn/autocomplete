# Symfony Autocomplete Bundle

Server-side rendered autocomplete for Symfony. No Tom Select, no build step. Twig templates + Stimulus.

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

## Form Types

### AutocompleteType

```php
use Kerrialnewham\Autocomplete\Form\Type\AutocompleteType;

$builder->add('user', AutocompleteType::class, [
    'provider' => 'users',
    'placeholder' => 'Search for a user...',
]);

$builder->add('tags', AutocompleteType::class, [
    'provider' => 'tags',
    'multiple' => true,
    'theme' => 'dark',
    'min_chars' => 2,
    'debounce' => 500,
    'limit' => 20,
]);
```

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `provider` | string | `'default'` | Provider name |
| `multiple` | bool | `false` | Multiple selections |
| `placeholder` | string | `'Search...'` | Placeholder text |
| `min_chars` | int | `1` | Min characters before search |
| `debounce` | int | `300` | Debounce in ms |
| `limit` | int | `10` | Max results |
| `theme` | string\|null | `null` | Theme name |
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

$builder->add('status', EnumType::class, [
    'class' => Status::class,
    'autocomplete' => true,
]);

$builder->add('author', EntityType::class, [
    'class' => User::class,
    'autocomplete' => true,
]);
```

## Providers

### Built-in

| Name | Data |
|------|------|
| `symfony_countries` | Countries |
| `symfony_languages` | Languages |
| `symfony_locales` | Locales |
| `symfony_currencies` | Currencies |
| `symfony_timezones` | Timezones |
| `symfony_dial_codes` | Dial codes with flag emoji |

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

    public function getName(): string
    {
        return 'users';
    }

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

Results must return `id` (required), `label` (required), and `meta` (optional).

### Overriding Entity Providers

Auto-generated providers use the name `entity.App\Entity\ClassName`. Create a provider with the same name to override:

```php
public function getName(): string
{
    return 'entity.App\Entity\User';
}
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
    'provider' => 'countries',
    'theme' => 'dark',
]);
```

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
    'provider' => 'users',
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
