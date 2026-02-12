# Symfony Autocomplete Bundle

## Overview

A highly customisable Autocomplete bundle for Symfony that prioritises server-side rendering and flexibility.

## Key Features

### No Tom Select Dependency
- Built without relying on Tom Select or similar third-party libraries
- Maintains full control over functionality and styling

### Stimulus Integration
- Uses Stimulus framework for client-side interactivity
- Provides clean, organised JavaScript controllers
- Progressive enhancement approach

### Server-Side Rendering (Twig Templates)
The bundle's defining feature is its use of Twig templates for rendering all UI components:

- **Options**: Rendered server-side via AJAX
- **Chips**: Rendered server-side via AJAX (fetched from `/_autocomplete/{provider}/chip`)
- **Themes**: Complete theme system with multiple built-in themes

### High Customisability

Because all rendering happens through Twig templates on the server, developers have complete control over:
- HTML structure
- CSS classes and styling
- Layout and presentation
- Option formatting
- Badge/chip appearance
- Theme selection

This approach allows projects to match their existing design systems without fighting against pre-built markup or styles.

## Architecture

- **Backend**: Symfony bundle with Twig templates
- **Frontend**: Stimulus controllers for interactivity (source ES modules, no build step)
- **Rendering**: Server-side via Twig (not JavaScript template strings)
- **Theme System**: TemplateResolver resolves theme names to template paths
- **Chip Provider Interface**: Providers must implement `get(id)` for server-side chip rendering
- **Asset Delivery**: Uses Symfony's Asset Mapper (no build step required)
- **Security**: HMAC-SHA256 signed AJAX requests with 10-minute expiry

## Components

### Backend (PHP)

1. **Bundle Infrastructure**
   - `src/AutocompleteBundle.php` - Bundle class with theme configuration
   - `src/DependencyInjection/AutocompleteExtension.php` - DI extension
   - `src/DependencyInjection/Compiler/AutocompleteProviderPass.php` - Compiler pass for auto-discovery
   - `config/services.php` - Service configuration with auto-tagging
   - `config/routes.php` - Route loading configuration

2. **Provider System**
   - `src/Provider/Contract/AutocompleteProviderInterface.php` - Provider contract for search
   - `src/Provider/Contract/ChipProviderInterface.php` - Provider contract for chip rendering
   - `src/Provider/ProviderRegistry.php` - Provider registry/locator
   - Auto-tags services implementing the interfaces
   - 6 built-in Symfony Intl providers:
     - `src/Provider/Provider/Symfony/CountryProvider.php` (`symfony_countries`)
     - `src/Provider/Provider/Symfony/LanguageProvider.php` (`symfony_languages`)
     - `src/Provider/Provider/Symfony/LocaleProvider.php` (`symfony_locales`)
     - `src/Provider/Provider/Symfony/CurrencyProvider.php` (`symfony_currencies`)
     - `src/Provider/Provider/Symfony/TimezoneProvider.php` (`symfony_timezones`)
     - `src/Provider/Provider/Symfony/DialCodeProvider.php` (`symfony_dial_codes`, delegates to `DialCodeMap`)

3. **Controllers**
   - `src/Controller/AutocompleteController.php` - Two AJAX endpoints:
     - `GET /_autocomplete/{provider}` - Search endpoint (validates HMAC signature)
     - `GET /_autocomplete/{provider}/chip` - Server-side chip rendering endpoint (validates HMAC signature)
   - Dynamic provider resolution: `entity.{ClassName}`, `enum.{EnumClass}`, or registry lookup

4. **Security**
   - `src/Security/AutocompleteSigner.php` - HMAC-SHA256 signature generation
   - `src/Twig/Extension/AutocompleteTwigExtension.php` - `autocomplete_sig()` Twig function
   - Signature binds: route name, provider, theme, translation domain, choice_label, choice_value, locale, user ID, timestamp
   - 10-minute expiry window to prevent replay attacks
   - Prevents tampering with provider/theme/domain/choice parameters

5. **Form Types** (3 types)
   - `src/Form/Type/AutocompleteType.php` - Core autocomplete form type
     - Options: `provider`, `multiple`, `placeholder`, `min_chars`, `debounce`, `limit`, `theme`, `attr`
     - Block prefix: `autocomplete`
   - `src/Form/Type/InternationalDialCodeType.php` - Pre-configured dial code selector (extends ChoiceType)
     - Options: `autocomplete` (true), `provider` (`symfony_dial_codes`), `placeholder`
     - Block prefix: `international_dial_code`
   - `src/Form/Type/PhoneNumberType.php` - Compound form type (dial code + text input)
     - Options: `theme`
     - Block prefix: `phone_number`
     - Children: `dialCode` (InternationalDialCodeType) + `number` (TextType)
     - Adds `PhoneNumberTransformer` for E.164 ↔ array conversion

6. **Form Extensions** (3 extensions)
   - `src/Form/Extension/AutocompleteFormTypeExtension.php` - Extends `FormType`
     - Adds `autocomplete`, `provider`, `min_chars`, `debounce`, `limit`, `theme` options to all form types
     - Injects `autocomplete` block prefix when `autocomplete: true`
   - `src/Form/Extension/AutocompleteChoiceTypeExtension.php` - Extends `ChoiceType`
     - Auto-detects provider from choice type (CountryType → `symfony_countries`, etc.)
     - Creates dynamic `EnumProvider` for enum types
     - Defaults `min_chars` to 0 for choice-based types
   - `src/Form/Extension/AutocompleteEntityTypeExtension.php` - Extends `EntityType`
     - Handles dynamic provider resolution/creation via `EntityProviderFactory`
     - Replaces EntityType's built-in transformers with `EntityToIdentifierTransformer`

7. **Phone Number Support**
   - `src/Phone/DialCodeMap.php` - Shared static dial code mapping with E.164 parsing
     - `all()` — returns full country code → dial code mapping
     - `dialCode(string $countryCode)` — lookup dial code by country code
     - `parseE164(string $e164)` — parse E.164 into `['countryCode', 'dialCode', 'number']`
     - Longest-prefix matching for NANP disambiguation (e.g. `+1242` → BS before `+1` → US)
   - `src/Form/DataTransformer/PhoneNumberTransformer.php` - E.164 ↔ `['dialCode', 'number']` conversion
   - `src/Doctrine/Type/PhoneNumberType.php` - DBAL type mapping E.164 to `VARCHAR(20)`
     - Conditional registration (only loads if Doctrine DBAL installed)

8. **Doctrine Integration** (Optional)
   - `src/Form/DataTransformer/EntityToIdentifierTransformer.php` - Bidirectional entity ↔ ID conversion
   - `src/Provider/Doctrine/DoctrineEntityProvider.php` - Generic auto-generated entity provider
   - `src/Provider/Doctrine/EntityProviderFactory.php` - Factory for creating entity providers on-the-fly
   - Conditional service registration (only loads if Doctrine ORM installed)
   - Supports all EntityType options: `query_builder`, `choice_label`, `choice_value`
   - Automatic provider generation with custom override support
   - Use `EntityType` with `autocomplete: true` via the form extension

### Templates (Twig)

1. **Form Widget Template**
   - `templates/form/autocomplete_widget.html.twig` - Delegates to theme-specific templates

2. **Theme Templates** (5 complete themes)
   - `templates/theme/default/` - Clean, modern design with pill-shaped chips
   - `templates/theme/dark/` - Dark mode variant with muted colors
   - `templates/theme/cards/` - Card-style layout with metadata display (email, domain, role, avatar)
   - `templates/theme/bootstrap-5/` - Bootstrap 5 compatible styling
   - `templates/theme/flowbite/` - Flowbite UI variant

   Each theme includes:
   - `autocomplete.html.twig` - Main widget template
   - `_options.html.twig` - Search results rendering
   - `_chip.html.twig` - Selected item chip/badge

All templates are fully customizable by overriding in the host application.

### Frontend (JavaScript/CSS)

1. **Asset Files** (No build step required)
   - `assets/controllers/ssr_autocomplete_controller.js` - Stimulus controller (ES module)
   - `assets/styles/autocomplete.css` - Unified CSS file with all themes
   - Delivered via Symfony's Asset Mapper - no compilation needed

2. **Stimulus Controller Features**
   - Server-side chip rendering via AJAX
   - Theme parameter propagation through all requests
   - HMAC-signed request URLs
   - Debounced search with AbortController
   - Keyboard navigation (arrows, enter, escape)
   - Single and multiple selection modes
   - Click-outside handling
   - Loading states
   - Custom events dispatching (`autocomplete:select`, `autocomplete:remove`)

3. **Styling (Theme System with Data Attributes)**
   - Single CSS file containing all themes
   - Five complete themes: default, dark, cards, bootstrap-5, flowbite
   - Theme scoping via `[data-autocomplete-theme="..."]` selector
   - Instant theme switching - just change data attribute value
   - Easy customization: add CSS scoped to custom theme names in your app's CSS

### Documentation

- `README.md` - Comprehensive usage documentation
- `CLAUDE.md` - Project context and architecture (this file)
- Follow conventions that can be found here: https://symfony.com/doc/current/bundles/best_practices.html#directory-structure

## How It Works

### Request Flow

#### Search Flow (Dropdown Options)

1. **User types** in autocomplete input field
2. **Stimulus controller** debounces input (default 300ms)
3. **AJAX request** sent to `/_autocomplete/{provider}?query=...&limit=10&theme=...&selected[]=...&ts=...&sig=...`
4. **AutocompleteController** validates HMAC-SHA256 signature (10-minute expiry)
5. **TemplateResolver** resolves theme parameter
6. **ProviderRegistry** locates the named provider (or dynamically creates entity/enum providers)
7. **Provider** searches data source and returns array of results
8. **Controller** renders `@Autocomplete/theme/{theme}/_options.html.twig` with results
9. **HTML response** sent back to client
10. **Stimulus controller** injects HTML into dropdown

#### Chip Rendering Flow (Multiple Selection Mode)

11. **User selects** an option
12. **For multiple mode:**
    a. JavaScript calls `fetchChipHtml(item.id)`
    b. `buildChipUrl()` constructs `/_autocomplete/{provider}/chip?theme=...&id=...&name=...&ts=...&sig=...`
    c. **AJAX request** sent to chip endpoint with HMAC signature
    d. **AutocompleteController::chip()** validates signature and receives request
    e. **TemplateResolver** resolves theme to template path
    f. **Provider's `get(id)` method** fetches item data with full metadata
    g. **Controller** renders `@Autocomplete/theme/{theme}/_chip.html.twig` with item
    h. **HTML response** sent to JavaScript
    i. JavaScript parses HTML and inserts chip element into chips container
    j. Selected item ID tracked in `selectedItems` Map
    k. Input field cleared and refocused
13. **For single mode:**
    a. Value stored in hidden input field
    b. Input field cleared and blurred
14. **Event dispatched** for integration (`autocomplete:select` with item details)

### Provider Interface

Providers must implement `AutocompleteProviderInterface` for search functionality:

```php
interface AutocompleteProviderInterface {
    public function getName(): string;
    public function search(string $query, int $limit, array $selected): array;
}
```

Providers that support server-side chip rendering must also implement `ChipProviderInterface`:

```php
interface ChipProviderInterface {
    public function get(string $id): ?array;
}
```

**Results format:**

```php
[
    [
        'id' => 'unique-id',
        'label' => 'Display text',
        'meta' => [
            'email' => 'user@example.com',
            'domain' => 'example.com',
            'role' => 'Admin',
            'avatar' => 'https://...',
        ], // Optional
    ],
]
```

### Theme System

Themes control the visual appearance and HTML structure of all autocomplete components.

#### How It Works

Themes are specified per form field (no global configuration needed):

```php
$builder->add('country', AutocompleteType::class, [
    'provider' => 'countries',
    'theme' => 'dark', // Optional, defaults to 'default'
]);
```

The TemplateResolver validates theme names (alphanumeric, hyphens, underscores only) to prevent path traversal attacks and defaults to 'default' for invalid/missing themes.

#### Theme Resolution Flow

1. **Form type** sets theme in view vars (defaults to 'default' if not specified)
2. **Widget template** includes theme-specific main template
3. **Main template** passes theme parameter in signed search URL
4. **JavaScript controller** carries theme to chip endpoint via `buildChipUrl()`
5. **Backend** resolves correct chip template via `TemplateResolver`

#### Available Themes

- **default**: Clean, modern design with pill-shaped chips
- **dark**: Dark mode variant with muted colors and slate backgrounds
- **cards**: Card-style layout with metadata display (email, domain, role, avatar)
- **bootstrap-5**: Bootstrap 5 compatible styling with badge classes
- **flowbite**: Flowbite UI variant

#### Overriding Theme Templates

All templates can be overridden using Symfony's standard bundle template override mechanism. Create the file at `templates/bundles/AutocompleteBundle/theme/{theme}/` and put whatever markup you want.

Each theme has three overridable templates:
- `autocomplete.html.twig` - Main widget (needs `data-autocomplete-theme` attribute and Stimulus data attributes)
- `_options.html.twig` - Dropdown results (options need `data-autocomplete-option-value` and `data-autocomplete-option-label`)
- `_chip.html.twig` - Selected item chip (needs Stimulus target attributes)

You can use any CSS framework, any classes, any HTML structure in these overrides.

#### Creating Custom Themes

**Option 1: CSS-Only**

Add CSS scoped to a custom theme name. The `default` theme's HTML is used automatically:

```css
[data-autocomplete-theme="my-custom"] .autocomplete-input {
    background: linear-gradient(to right, #667eea 0%, #764ba2 100%);
    color: white;
    border: none;
}
```

```php
$builder->add('country', AutocompleteType::class, [
    'provider' => 'countries',
    'theme' => 'my-custom',
]);
```

**Option 2: Full Custom Theme (Templates + CSS)**

For completely custom HTML structure, create templates at `templates/bundles/AutocompleteBundle/theme/my-theme/` with whatever markup you need:

```php
$builder->add('field', AutocompleteType::class, [
    'provider' => 'my-provider',
    'theme' => 'my-theme',
]);
```

### Phone Number Type

The `PhoneNumberType` is a compound form type that combines a dial code autocomplete with a phone number text input:

```php
$builder->add('phone', \Kerrialnewham\Autocomplete\Form\Type\PhoneNumberType::class, [
    'theme' => 'flowbite',
]);
```

**Data flow:**
- **Model → Form** (`transform`): E.164 string `+447911123456` → `DialCodeMap::parseE164()` → `['dialCode' => 'GB', 'number' => '7911123456']`
- **Form → Model** (`reverseTransform`): `['dialCode' => 'GB', 'number' => '7911123456']` → `DialCodeMap::dialCode('GB')` → `+447911123456`
- **Database**: Stored as `VARCHAR(20)` via the `phone_number` DBAL type

#### Doctrine Entity Usage

```php
#[ORM\Column(type: 'phone_number', nullable: true)]
private ?string $phone = null;
```

### Customization Points

1. **Provider Logic** - Implement custom search/filtering and data retrieval
2. **Options Template** - Customize dropdown result item HTML
3. **Chip Template** - Customize selected item HTML and metadata display
4. **Widget Template** - Customize entire autocomplete widget structure
5. **CSS Styling** - Override default styles or create custom themes via data attribute selectors
6. **Stimulus Controller** - Extend or customize JavaScript behavior
7. **Form Type** - Create custom form types that extend AutocompleteType
8. **Theme System** - Create completely custom themes with unique layouts
9. **Form Extension** - Add `autocomplete: true` to any existing ChoiceType or EntityType field

## Next Steps / Future Enhancements

Potential improvements:

- [ ] Add unit tests
- [ ] Add functional tests
- [ ] Support for grouped results
- [ ] Support for async/lazy loading providers
- [ ] Built-in pagination for large result sets
- [ ] Accessibility improvements (ARIA attributes)
- [ ] RTL support
- [ ] TypeScript version of Stimulus controller
- [ ] More example providers (API, Elasticsearch, etc.)
- [ ] Performance optimizations (result caching)
- [ ] GitHub Actions CI/CD
- [ ] Dynamic theme switching without page reload
