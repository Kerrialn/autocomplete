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

## Implementation Status

### ✅ Completed Components

#### Backend (PHP)

1. **Bundle Infrastructure**
   - `src/AutocompleteBundle.php` - Bundle class with theme configuration
   - `src/DependencyInjection/AutocompleteExtension.php` - DI extension
   - `src/DependencyInjection/Compiler/AutocompleteProviderPass.php` - Compiler pass for auto-discovery
   - `config/services.php` - Service configuration with auto-tagging
   - `config/routes.php` - Route loading configuration

2. **Provider System**
   - `src/Provider/Contract/AutocompleteProviderInterface.php` - Provider contract for search
   - `src/Provider/ChipProviderInterface.php` - Provider contract for chip rendering
   - `src/Provider/ProviderRegistry.php` - Provider registry/locator
   - `src/Provider/Provider/Symfony/CountryProvider.php` - Built-in Symfony country provider
   - Auto-tags services implementing the interfaces

3. **Controllers**
   - `src/Controller/AutocompleteController.php` - Two AJAX endpoints:
     - `/_autocomplete/{provider}` - Search endpoint
     - `/_autocomplete/{provider}/chip` - Server-side chip rendering endpoint

4. **Theme System**
   - `src/Theme/TemplateResolver.php` - Validates theme names and resolves to template paths
   - Built-in themes: default, dark, cards, bootstrap-5
   - No configuration required - specify theme per form field
   - Validates theme names to prevent path traversal attacks

5. **Form Integration**
   - `src/Form/Type/AutocompleteType.php` - Symfony FormType
   - `templates/form/autocomplete_widget.html.twig` - Form widget template

#### Templates (Twig)

1. **Form Widget Template**
   - `templates/form/autocomplete_widget.html.twig` - Delegates to theme-specific templates

2. **Theme Templates** (4 complete themes)
   - `templates/theme/default/` - Clean, modern design with pill-shaped chips
   - `templates/theme/dark/` - Dark mode variant with muted colors
   - `templates/theme/cards/` - Card-style layout with metadata display (email, domain, role, avatar)
   - `templates/theme/bootstrap-5/` - Bootstrap 5 compatible styling

   Each theme includes:
   - `autocomplete.html.twig` - Main widget template
   - `_options.html.twig` - Search results rendering
   - `_chip.html.twig` - Selected item chip/badge

All templates are fully customizable by overriding in the host application.

#### Frontend (JavaScript/CSS)

1. **Asset Files** (No build step required)
   - `assets/controllers/ssr_autocomplete_controller.js` - Stimulus controller (ES module)
   - `assets/styles/autocomplete.css` - **Unified CSS file with all themes (~11KB)**
   - `assets/styles/theme/*.css` - Original theme CSS files (kept for reference)
   - Delivered via Symfony's Asset Mapper - no compilation needed

2. **Stimulus Controller Features**
   - **Server-side chip rendering via AJAX**: Chips fetched from backend
   - **Theme parameter propagation**: Theme carried through all requests
   - **buildChipUrl() method**: Constructs chip endpoint URL with theme
   - **fetchChipHtml() async method**: Fetches server-rendered chip HTML
   - Debounced search with AbortController
   - Keyboard navigation (arrows, enter, escape)
   - Single and multiple selection modes
   - Click-outside handling
   - Loading states
   - Custom events dispatching (`autocomplete:select`, `autocomplete:remove`)

3. **Styling (Theme System with Data Attributes)**
   - **Single CSS file** containing all themes (~11KB)
   - **Four complete themes**: default, dark, cards, bootstrap-5
   - **Theme scoping**: Each theme scoped via `[data-autocomplete-theme="..."]` selector
   - **Instant theme switching**: Just change data attribute value, no CSS file reload needed
   - Cards theme includes metadata display (email, domain, role, avatar)
   - Clean, modern defaults with hover/focus states
   - **Easy customization**: Add CSS scoped to custom theme names in your app's CSS

#### Documentation

- `README.md` - Comprehensive usage documentation
- `claude.md` - Project context and architecture (this file)
- Follow conventions that can be found here: https://symfony.com/doc/current/bundles/best_practices.html#directory-structure

## How It Works

### Request Flow

#### Search Flow (Dropdown Options)

1. **User types** in autocomplete input field
2. **Stimulus controller** debounces input (default 300ms)
3. **AJAX request** sent to `/_autocomplete/{provider}?query=...&limit=10&theme=...&selected[]=...`
4. **AutocompleteController** receives request
5. **TemplateResolver** resolves theme parameter
6. **ProviderRegistry** locates the named provider
7. **Provider** searches data source and returns array of results
8. **Controller** renders `@Autocomplete/theme/{theme}/_options.html.twig` with results
9. **HTML response** sent back to client
10. **Stimulus controller** injects HTML into dropdown

#### Chip Rendering Flow (Multiple Selection Mode)

11. **User selects** an option
12. **For multiple mode:**
    a. JavaScript calls `fetchChipHtml(item.id)`
    b. `buildChipUrl()` constructs `/_autocomplete/{provider}/chip?theme=...&id=...&name=...`
    c. **AJAX request** sent to chip endpoint with theme parameter
    d. **AutocompleteController::chip()** receives request
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
            // Any custom metadata for your theme
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

The TemplateResolver validates theme names to prevent path traversal attacks and defaults to 'default' for invalid/missing themes.

#### Theme Resolution Flow

1. **Form type** sets theme in view vars (defaults to 'default' if not specified)
2. **Widget template** includes theme-specific main template
3. **Main template** passes theme parameter in search URL
4. **JavaScript controller** carries theme to chip endpoint via `buildChipUrl()`
5. **Backend** resolves correct chip template via `TemplateResolver`

#### Available Themes

- **default**: Clean, modern design with pill-shaped chips
- **dark**: Dark mode variant with muted colors and slate backgrounds
- **cards**: Card-style layout with metadata display (email, domain, role, avatar)
- **bootstrap-5**: Bootstrap 5 compatible styling with badge classes

#### Creating Custom Themes

**Option 1: CSS-Only Custom Theme (Recommended)**

Simply add CSS scoped to your custom theme name. No webpack, no bundle rebuild required:

```css
/* In your app's CSS file */
[data-autocomplete-theme="my-custom"] .autocomplete-wrapper {
    font-family: 'Inter', sans-serif;
}

[data-autocomplete-theme="my-custom"] .autocomplete-input {
    background: linear-gradient(to right, #667eea 0%, #764ba2 100%);
    color: white;
    border: none;
}

[data-autocomplete-theme="my-custom"] .autocomplete-chip {
    background-color: #667eea;
    color: white;
}
```

Then use it:
```php
$builder->add('country', AutocompleteType::class, [
    'provider' => 'countries',
    'theme' => 'my-custom',
]);
```

**Option 2: Full Custom Theme (Templates + CSS)**

For completely custom HTML structure:

1. Create theme directory: `templates/theme/my-theme/`
2. Add three templates:
   - `autocomplete.html.twig` - Main widget (must include `data-autocomplete-theme="{{ theme }}"` attribute)
   - `_options.html.twig` - Dropdown results
   - `_chip.html.twig` - Selected item chip
3. Add CSS scoped to your theme in your app's CSS file:
   ```css
   [data-autocomplete-theme="my-theme"] .autocomplete-wrapper { /* styles */ }
   ```
4. Use it in your form:
   ```php
   $builder->add('field', AutocompleteType::class, [
       'provider' => 'my-provider',
       'theme' => 'my-theme',
   ]);
   ```

### Customization Points

1. **Provider Logic** - Implement custom search/filtering and data retrieval
2. **Options Template** - Customize dropdown result item HTML
3. **Chip Template** - Customize selected item HTML and metadata display
4. **Widget Template** - Customize entire autocomplete widget structure
5. **CSS Styling** - Override default styles or create custom themes
6. **Stimulus Controller** - Extend or customize JavaScript behavior
7. **Form Type** - Create custom form types that extend AutocompleteType
8. **Theme System** - Create completely custom themes with unique layouts

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
- [ ] Form type option to override theme per-field
- [ ] Dynamic theme switching without page reload
