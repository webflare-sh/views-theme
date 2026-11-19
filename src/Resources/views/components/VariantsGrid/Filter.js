import { getInstanceByElement } from '@views-theme/modules/shared/component.js'

/**
 * @extends {ShopwareComponent}
 */
export default class VariantsGridFilter extends ShopwareComponent {
    static options = {
        changeEvent: 'ViewsTheme:VariantsGrid:Filter:Change',
        groupComponent: 'ViewsTheme:Filter:Group',
        resetComponent: 'ViewsTheme:VariantsGrid:Filter:Reset',
    }

    init() {
        this._reset = this.el.querySelector(`[data-component="${this.options.resetComponent}"]`)
        this._onChange = this._onChange.bind(this)
        this._onReset = this._onReset.bind(this)

        this.el.addEventListener('change', this._onChange)
        this._reset?.addEventListener('click', this._onReset)
    }

    destroy() {
        this.el.removeEventListener('change', this._onChange)
        this._reset?.removeEventListener('click', this._onReset)
        this._reset = null
    }

    /**
     * @returns {string[]}
     */
    getValues() {
        return this._inputs()
            .filter((input) => input.checked)
            .map((input) => input.value)
    }

    /**
     * @param {string[]} optionIds
     */
    setValues(optionIds) {
        this._inputs().forEach((input) => {
            input.checked = optionIds.includes(input.value)
        })
        this._sync()
    }

    /**
     * @param {Event} event
     */
    _onChange(event) {
        if (!(event.target instanceof HTMLInputElement) || event.target.type !== 'checkbox') {
            return
        }

        this._sync()
        this._emitChange()
    }

    /**
     * @param {MouseEvent} event
     */
    _onReset(event) {
        event.preventDefault()

        this._inputs().forEach((input) => {
            input.checked = false
        })
        this._sync()
        this._emitChange()
    }

    _emitChange() {
        window.Shopware.emit(this.options.changeEvent, {
            source: this.el,
            optionIds: this.getValues(),
        })
    }

    _sync() {
        this._groups().forEach((groupEl) => {
            const count = groupEl.querySelectorAll('input[type="checkbox"]:checked').length
            getInstanceByElement(this.options.groupComponent, groupEl)?.setCount(count || null)
        })

        if (this._reset) {
            this._reset.hidden = this.getValues().length === 0
        }
    }

    _groups() {
        return Array.from(this.el.querySelectorAll(`[data-component="${this.options.groupComponent}"]`))
    }

    _inputs() {
        return Array.from(this.el.querySelectorAll('input[type="checkbox"]'))
    }
}
