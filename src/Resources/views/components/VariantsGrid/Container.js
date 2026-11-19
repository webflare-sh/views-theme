import { getInstanceByElement } from '@views-theme/modules/shared/component.js'

/**
 * @extends {ShopwareComponent}
 */
export default class VariantsGridContainer extends ShopwareComponent {
    static options = {
        quantityInputSelector: '[data-component="ViewsTheme:QuantityInput"] input[type="number"]',
        buyButtonSelector: 'button[type="submit"]',
        gridBodySelector: 'tbody',
        paginationSelector: '[data-action="pagination"]',
        memorySelector: '[data-action="memory"]',
        errorSelector: '[role="alert"]',
        liveRegionSelector: '[aria-live]',
        filterComponent: 'ViewsTheme:VariantsGrid:Filter',
        emptyComponent: 'ViewsTheme:VariantsGrid:Empty',
        filterChangeEvent: 'ViewsTheme:VariantsGrid:Filter:Change',
        pageParameter: 'variantsPage',
        optionsParameter: 'variantsOptions',
        addEvent: 'ViewsTheme:Cart:Add',
    };

    init() {
        this._buyButton = this.el.querySelector(this.options.buyButtonSelector);
        this._gridBody = this.el.querySelector(this.options.gridBodySelector);
        this._pagination = this.el.querySelector(this.options.paginationSelector);
        this._memory = this.el.querySelector(this.options.memorySelector);
        this._error = this.el.querySelector(this.options.errorSelector);
        this._liveRegion = this.el.querySelector(this.options.liveRegionSelector);
        this._filter = this.el.querySelector(`[data-component="${this.options.filterComponent}"]`);
        this._empty = this.el.querySelector(`[data-component="${this.options.emptyComponent}"]`);
        this._loadUrl = this.el.getAttribute('data-load-url');
        this._parentId = this.el.getAttribute('data-parent-id');
        this._quantityMap = new Map();
        this._loadController = null;

        const { page, optionIds } = this._getStateFromUrl(window.location.href);
        this._page = page;
        this._optionIds = optionIds;

        this._onInputChange = this._onInputChange.bind(this);
        this._onPaginationClick = this._onPaginationClick.bind(this);
        this._onFilterChange = this._onFilterChange.bind(this);
        this._onSubmit = this._onSubmit.bind(this);
        this._onPopState = this._onPopState.bind(this);

        this._registerEvents();
        this._syncVisibleInputsToMap();
        this._updateButtonState();
    }

    destroy() {
        this.el.removeEventListener('input', this._onInputChange);
        this.el.removeEventListener('change', this._onInputChange);
        if (this._pagination) {
            this._pagination.removeEventListener('click', this._onPaginationClick);
        }
        if (this._form) {
            this._form.removeEventListener('submit', this._onSubmit, true);
        }
        window.Shopware.off(this.options.filterChangeEvent, this._onFilterChange);
        window.removeEventListener('popstate', this._onPopState);
        this._loadController?.abort();
    }

    _registerEvents() {
        this.el.addEventListener('input', this._onInputChange);
        this.el.addEventListener('change', this._onInputChange);

        if (this._pagination) {
            this._pagination.addEventListener('click', this._onPaginationClick);
        }

        this._form = this.el.tagName === 'FORM' ? this.el : this.el.querySelector('form');
        if (this._form) {
            this._form.addEventListener('submit', this._onSubmit, true);
        }

        window.Shopware.on(this.options.filterChangeEvent, this._onFilterChange);
        window.addEventListener('popstate', this._onPopState);
    }

    _onInputChange(event) {
        const input = event.target.closest(this.options.quantityInputSelector);
        if (!input || !this.el.contains(input)) {
            return;
        }

        const variantId = this._getVariantIdFromInput(input);
        if (variantId) {
            this._quantityMap.set(variantId, parseInt(input.value, 10) || 0);
        }

        this._updateButtonState();
    }

    _onPaginationClick(event) {
        const link = event.target.closest('a[href]');
        if (!link || !this._pagination || !this._pagination.contains(link)) {
            return;
        }

        const { page } = this._getStateFromUrl(link.href);
        event.preventDefault();

        if (page === this._page || link.getAttribute('aria-disabled') === 'true') {
            return;
        }

        this._loadPage(page, this._optionIds, { focus: true });
    }

    /**
     * @param {{ source?: Element, optionIds?: string[] }} payload
     */
    _onFilterChange(payload) {
        if (!this._filter || payload?.source !== this._filter) {
            return;
        }

        this._loadPage(1, payload.optionIds || []);
    }

    /**
     * @param {string} urlString
     * @returns {{ page: number, optionIds: string[] }}
     */
    _getStateFromUrl(urlString) {
        const params = new URL(urlString, window.location.origin).searchParams;
        const page = parseInt(params.get(this.options.pageParameter) || '', 10);
        const optionIds = (params.get(this.options.optionsParameter) || '').split('|').filter(Boolean);

        return { page: page > 0 ? page : 1, optionIds };
    }

    /**
     * @param {number} page
     * @param {string[]} optionIds
     * @param {{ history?: boolean, focus?: boolean }} [behavior]
     */
    async _loadPage(page, optionIds, { history = true, focus = false } = {}) {
        if (!this._loadUrl || !this._gridBody || page < 1) {
            return;
        }

        this._loadController?.abort();
        const controller = new AbortController();
        this._loadController = controller;

        this._hideError();
        this._setLoading(true);

        try {
            const url = new URL(this._loadUrl, window.location.origin);
            url.searchParams.set('parentId', this._parentId);
            url.searchParams.set(this.options.pageParameter, String(page));
            if (optionIds.length > 0) {
                url.searchParams.set(this.options.optionsParameter, optionIds.join('|'));
            }

            const response = await fetch(url.toString(), {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                signal: controller.signal,
            });

            if (!response.ok) {
                throw new Error(`Failed to load page: ${response.status}`);
            }

            const data = await response.json();
            const filterChanged = !this._isSameSelection(optionIds, this._optionIds);

            this._gridBody.innerHTML = data.rows || '';

            if (this._pagination && data.pagination !== undefined) {
                this._pagination.innerHTML = data.pagination;
            }

            if (this._empty) {
                this._empty.hidden = data.total > 0;
            }

            this._page = data.page || page;
            this._optionIds = optionIds;

            this._applyQuantitiesFromMap();
            this._reinitializePlugins();

            if (history) {
                this._updateUrl();
            }

            if (focus) {
                this._setFocusToGridBody();
            }

            if (filterChanged) {
                this._announceResults(data.total || 0);
            } else {
                this._announcePageLoaded(this._page);
            }
        } catch (error) {
            if (controller.signal.aborted) {
                return;
            }

            this._showError();
            console.error('VariantsGrid: Failed to load page', error);
        } finally {
            if (this._loadController === controller) {
                this._loadController = null;
                this._setLoading(false);
            }
        }
    }

    /**
     * @param {boolean} loading
     */
    _setLoading(loading) {
        const target = this._form || this.el;

        target.classList.toggle('is-loading', loading);
        target.setAttribute('aria-busy', loading ? 'true' : 'false');
    }

    /**
     * @param {string[]} a
     * @param {string[]} b
     */
    _isSameSelection(a, b) {
        return a.length === b.length && a.every((id) => b.includes(id));
    }

    _applyQuantitiesFromMap() {
        this.el.querySelectorAll(this.options.quantityInputSelector).forEach((input) => {
            const variantId = this._getVariantIdFromInput(input);
            if (variantId && this._quantityMap.has(variantId)) {
                input.value = String(this._quantityMap.get(variantId));
            }
        });
    }

    _syncVisibleInputsToMap() {
        this.el.querySelectorAll(this.options.quantityInputSelector).forEach((input) => {
            const variantId = this._getVariantIdFromInput(input);
            if (variantId) {
                this._quantityMap.set(variantId, parseInt(input.value, 10) || 0);
            }
        });
    }

    _getVariantIdFromInput(input) {
        const name = input.getAttribute('name') || '';
        const match = name.match(/lineItems\[([^\]]+)\]/);

        return match ? match[1] : null;
    }

    _updateButtonState() {
        if (!this._buyButton) {
            return;
        }

        let hasSelection = false;

        this._quantityMap.forEach((quantity) => {
            if (quantity > 0) {
                hasSelection = true;
            }
        });

        this._buyButton.disabled = !hasSelection;
    }

    /**
     * Write off-page quantities as hidden inputs, then hand the form to the `Cart` owner
     * (`Cart:Add`), which posts it to the grid's own add route and opens the drawer.
     *
     * @param {SubmitEvent} event
     */
    _onSubmit(event) {
        event.preventDefault();

        this._syncVisibleInputsToMap();

        if (this._memory) {
            this._memory.innerHTML = '';
        }

        const visibleIds = new Set();
        this.el.querySelectorAll(this.options.quantityInputSelector).forEach((input) => {
            const variantId = this._getVariantIdFromInput(input);
            if (variantId) {
                visibleIds.add(variantId);
            }
        });

        this._quantityMap.forEach((quantity, variantId) => {
            if (quantity > 0 && !visibleIds.has(variantId) && this._memory) {
                this._memory.insertAdjacentHTML('beforeend', this._buildHiddenInputs(variantId, quantity));
            }
        });

        window.Shopware.emit(this.options.addEvent, {
            formData: new FormData(this._form),
            url: this._form.getAttribute('action'),
            source: this._form,
        });
    }

    _buildHiddenInputs(variantId, quantity) {
        const id = this._escapeHtml(variantId);
        const qty = this._escapeHtml(String(quantity));

        return `
            <input type="hidden" name="lineItems[${id}][id]" value="${id}">
            <input type="hidden" name="lineItems[${id}][type]" value="product">
            <input type="hidden" name="lineItems[${id}][referencedId]" value="${id}">
            <input type="hidden" name="lineItems[${id}][quantity]" value="${qty}">
            <input type="hidden" name="lineItems[${id}][stackable]" value="1">
            <input type="hidden" name="lineItems[${id}][removable]" value="1">
        `;
    }

    _updateUrl() {
        const url = new URL(window.location.href);
        url.searchParams.set(this.options.pageParameter, String(this._page));

        if (this._optionIds.length > 0) {
            url.searchParams.set(this.options.optionsParameter, this._optionIds.join('|'));
        } else {
            url.searchParams.delete(this.options.optionsParameter);
        }

        window.history.pushState(
            {
                [this.options.pageParameter]: this._page,
                [this.options.optionsParameter]: this._optionIds,
            },
            '',
            url.toString(),
        );
    }

    _onPopState() {
        const { page, optionIds } = this._getStateFromUrl(window.location.href);

        if (page === this._page && this._isSameSelection(optionIds, this._optionIds)) {
            return;
        }

        getInstanceByElement(this.options.filterComponent, this._filter)?.setValues(optionIds);
        this._loadPage(page, optionIds, { history: false });
    }

    _reinitializePlugins() {
        if (window.PluginManager && typeof window.PluginManager.initializePluginsInParentElement === 'function') {
            window.PluginManager.initializePluginsInParentElement(this._gridBody);
        }
    }

    _setFocusToGridBody() {
        if (!this._gridBody) {
            return;
        }

        this._gridBody.setAttribute('tabindex', '-1');
        this._gridBody.focus({ preventScroll: true });
    }

    _announcePageLoaded(page) {
        if (!this._liveRegion) {
            return;
        }

        const template = this._liveRegion.getAttribute('data-page-loaded-template') || 'Page %page% loaded';
        this._liveRegion.textContent = template.replace('%page%', String(page));
    }

    _announceResults(total) {
        if (!this._liveRegion) {
            return;
        }

        const template = this._liveRegion.getAttribute('data-results-template') || 'Variants found: %total%';
        this._liveRegion.textContent = template.replace('%total%', String(total));
    }

    _showError() {
        if (this._error) {
            this._error.classList.remove('d-none');
        }
    }

    _hideError() {
        if (this._error) {
            this._error.classList.add('d-none');
        }
    }

    _escapeHtml(value) {
        const div = document.createElement('div');
        div.textContent = value;

        return div.innerHTML;
    }
}
