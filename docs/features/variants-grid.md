# Variants grid

Paginated variants grid on product detail pages for products with variants.

## Features

- Automatically shows a variants grid on any product detail page that has variants (when active)
- Dynamic columns based on the product's configurator groups
- Variant filter: one multi-select dropdown per option group; only matching rows are shown (see [Variant filter](#variant-filter))
- Quantity input for every variant row
- Single "Add all to cart" button
- Server-side filtering of rows with zero quantity via a dedicated controller
- Seamless lazy-loading pagination via JavaScript fetch (`VariantsGrid:Pagination` → theme `Pagination`)
- Preserved quantities across pagination pages and filter changes
- Theme [cart drawer](cart-drawer.md) opens and the header badge updates after adding variants (`ViewsTheme:Cart:Add`)
- Configurable rows per page via plugin configuration
- Unavailable variants are rendered as disabled rows
- Color/option media rendered as swatches where applicable (`Column:Options` CVA `swatch`)
- Footer actions chrome via Container CVA `actions`

## Configuration

Open the plugin configuration in the Shopware administration:

| Setting | Config key | Default |
|---------|------------|---------|
| Active | `ViewsTheme.config.variantsGridActive` | off |
| Rows per page | `ViewsTheme.config.variantsGridRowsPerPage` | `10` |
| Show preview column | `ViewsTheme.config.variantsGridShowPreviewColumn` | on |
| Show product number column | `ViewsTheme.config.variantsGridShowProductNumberColumn` | on |

Both column options apply to the table header and every row, including AJAX-paginated page loads.

See [Configuration](../configuration.md).

## How it works

### Buy container integration

The variants grid is rendered inside `Product:BuyContainer` (`components/Product/BuyContainer.html.twig`) in the `buy` block when the grid is active and has variants. The buy-widget bridge forwards `page.extensions.viewsTheme.variantsGrid` (attached by `ProductPageSubscriber`). See [Buy container](buy-container.md).

`variantsGrid` is a `Webflare\ViewsTheme\Struct\VariantsGrid`:

| Field | Container prop | Content |
|-------|----------------|---------|
| `variants` | `variants` | Rows of the current page (filtered) |
| `groups` | `groups` | Every option group of the product's visible variants, with all of its options. Drives the table columns and the filter |
| `pagination` | `pagination` | `VariantsGridPagination` (page, limit, filtered total) |
| `parentId` | `parentId` | Parent product id |
| `pageParameter` | `pageParameter` | `variantsPage` |
| `selectedOptionIds` | `selectedOptionIds` | Option ids of the active filter (only ids that belong to the product) |
| `optionsParameter` | `optionsParameter` | `variantsOptions` |

A child theme that overrides the `buy` block and mounts `VariantsGrid:Container` itself must pass `selectedOptionIds` and `optionsParameter` too, otherwise a filtered URL renders the right rows but unchecked filter options.

### Grid state (server)

`VariantsGridLoader::load(parentId, request, context)` is the single source for both the PDP (`ProductPageSubscriber`) and the load XHR. It reads `variantsPage` and `variantsOptions` from the request, loads the option groups (`VariantsLoader::loadOptionGroupsByParentId`: terms aggregation on the visible variants' `options.id`, then the options with `group` and `media`, sorted like the listing property filter), counts and loads the filtered page, and clamps the page to the filtered page count.

It returns `null` only when the product has no visible variants and no filter is set. A filter that matches no variant still returns a grid without rows, so the filter and its reset stay on the page.

### Variant filter

`VariantsGrid:Filter` renders above the table (Container `filter` block, prop `showFilter = true`). It reuses the listing filter chrome without the listing store: per group a `Filter:Group` (toggle chip with a selection count) + `Filter:Group:Collapse` (popover) + `Filter:MultiSelect:Options` (checkbox chips with color / media swatches), and one `VariantsGrid:Filter:Reset` ("Reset all", hidden while nothing is selected).

| Rule | Behaviour |
|------|-----------|
| Within a group | OR (`EqualsAnyFilter('optionIds', …)`) |
| Across groups | AND (one filter per group) |
| URL | `variantsOptions=id\|id` (shareable, SSR-rendered); unknown ids are ignored |
| Change | Filter emits `ViewsTheme:VariantsGrid:Filter:Change` `{ source, optionIds }`; Container reloads page 1 and pushes the URL. The dropdown stays open, so several options can be picked in a row |
| History | Back / forward restores rows and checkbox state (`Filter.setValues`), without pushing a new entry |
| No match | `VariantsGrid:Empty` (info alert, `role="status"`) is shown below the table header |
| Quantities | Kept for rows that are filtered out and still submitted on add to cart (same as rows on other pages) |
| Announce | Live region reads `variantsGrid.filter.results` ("Variants found: %total%") after a filter change, `variantsGrid.pagination.pageLoaded` after a page change |

The filter sits outside the grid form, so its checkboxes are never posted and stay usable while rows load (`is-loading` / `aria-busy` are set on the form).

### Custom controller

The grid form posts to a dedicated controller:

```
frontend.checkout.variants-grid.add
```

This controller receives every row, ignores entries with `quantity <= 0`, creates the remaining line items, and adds them to the cart.

On submit, `Container.js` cancels the native POST, writes hidden inputs for quantities set on other pages, and emits `ViewsTheme:Cart:Add` with the form `FormData` and `url` = the form `action`. The theme `Cart` owner posts it to `frontend.checkout.variants-grid.add` (not core `line-item.add`), then the [cart drawer](cart-drawer.md) opens and the badge updates. The form does **not** use core `data-add-to-cart`: its `AddToCart` plugin only hands off to core `OffCanvasCart`, which the theme does not render, so the submit was swallowed. `redirectTo` / `redirectParameters` stay in the form for no-JS; `Cart` strips them before the fetch.

The same controller also provides an AJAX endpoint for lazy pagination:

```
frontend.checkout.variants-grid.load
```

It returns JSON `{ rows, pagination, page, total }`: the rendered table rows and pagination HTML for the requested page (`variantsPage`) and filter (`variantsOptions`), the page actually rendered (clamped) and the filtered total. It accepts two optional query parameters (`rowsTemplate`, `paginationTemplate`) for custom Twig templates; both fall back to the defaults if missing or invalid.

### Storefront JavaScript

Co-located `VariantsGrid/Container.js` (`ShopwareComponent` on `data-component="ViewsTheme:VariantsGrid:Container"`) handles button-state management, AJAX pagination and filtering (one in-flight request; a newer one aborts the older), history, quantity preservation across pages and filters, the empty state, error feedback, and the add-to-cart submit (`addEvent` option, default `ViewsTheme:Cart:Add`). Options `pageParameter` / `optionsParameter` come from `data-component-options`.

`VariantsGrid/Filter.js` owns the checkboxes, the per-group counts and the reset button, and exposes `getValues()` / `setValues(ids)`.

### Hooks

| Component | Attribute |
|-----------|-----------|
| Grid container | `data-component="ViewsTheme:VariantsGrid:Container"` |
| Variant filter | `data-component="ViewsTheme:VariantsGrid:Filter"` |
| Filter reset | `data-component="ViewsTheme:VariantsGrid:Filter:Reset"` |
| Empty state | `data-component="ViewsTheme:VariantsGrid:Empty"` |
| Filter change event | `ViewsTheme:VariantsGrid:Filter:Change` `{ source, optionIds }` |
| Quantity input | `data-component="ViewsTheme:QuantityInput"` |
| Pagination slot | `data-action="pagination"` |
| Quantity memory | `data-action="memory"` |
| Buy submit | `button[type="submit"]` |
| Error | `[role="alert"]` |
| Live region | `[aria-live]` |

See [JavaScript conventions](../conventions/javascript.md).

## Key source files

| Area | Path |
|------|------|
| Controller | `src/Controller/VariantsGridController.php` |
| Grid state | `src/Service/VariantsGridLoader.php` |
| DAL reads | `src/Service/VariantsLoader.php` |
| Grid struct | `src/Struct/VariantsGrid.php` |
| Pagination struct | `src/Struct/VariantsGridPagination.php` |
| Page subscriber | `src/Subscriber/ProductPageSubscriber.php` |
| JS | `src/Resources/views/components/VariantsGrid/Container.js`, `Filter.js` |
| Templates | `src/Resources/views/components/VariantsGrid/` |
