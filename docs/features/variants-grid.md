# Variants grid

Paginated variants grid on product detail pages for products with variants.

## Features

- Automatically shows a variants grid on any product detail page that has variants (when active)
- Dynamic columns based on the product's configurator groups
- Quantity input for every variant row
- Single "Add all to cart" button
- Server-side filtering of rows with zero quantity via a dedicated controller
- Seamless lazy-loading pagination via JavaScript fetch (`VariantsGrid:Pagination` → theme `Pagination`)
- Preserved quantities across pagination pages
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

It returns the rendered table rows and pagination HTML for the requested page. It accepts two optional query parameters (`rowsTemplate`, `paginationTemplate`) for custom Twig templates; both fall back to the defaults if missing or invalid.

### Storefront JavaScript

Co-located `VariantsGrid/Container.js` (`ShopwareComponent` on `data-component="ViewsTheme:VariantsGrid:Container"`) handles button-state management, AJAX pagination, quantity preservation across pages, error feedback, and the add-to-cart submit (`addEvent` option, default `ViewsTheme:Cart:Add`).

### Hooks

| Component | Attribute |
|-----------|-----------|
| Grid container | `data-component="ViewsTheme:VariantsGrid:Container"` |
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
| Loader | `src/Service/VariantsLoader.php` |
| Pagination struct | `src/Struct/VariantsGridPagination.php` |
| Page subscriber | `src/Subscriber/ProductPageSubscriber.php` |
| JS | `src/Resources/views/components/VariantsGrid/Container.js` |
| Templates | `src/Resources/views/components/VariantsGrid/` |
