import { getInstanceByElement } from '@views-theme/modules/lazy-shell.js'
import { setCookie } from '@views-theme/modules/cookie/storage.js'

/**
 * @extends {ShopwareComponent}
 */
export default class CookieConsent extends ShopwareComponent {
    static options = {
        cookieName: null,
        loginUrl: null,
        expirationDays: 30,
        drawerComponentName: 'ViewsTheme:Drawer',
        managerRequestEvent: 'ViewsTheme:Cookie:Manager:Request',
    }

    init() {
        this._onClick = this._onClick.bind(this)
        this.el.addEventListener('click', this._onClick)
    }

    destroy() {
        this.el.removeEventListener('click', this._onClick)
    }

    /**
     * @param {MouseEvent} event
     */
    _onClick(event) {
        const button = event.target.closest?.('button[name="action"]')
        if (!button) {
            return
        }

        switch (button.value) {
            case 'accept':
                setCookie(this.options.cookieName, '1', this.options.expirationDays)
                this._closeDrawer()
                break
            case 'login':
                this._closeDrawer()
                window.location.href = this.options.loginUrl
                break
            case 'cancel':
                this._closeDrawer()
                break
            case 'preferences':
                window.Shopware.emit(this.options.managerRequestEvent)
                break
        }
    }

    _closeDrawer() {
        const drawerEl = this.el.closest(`[data-component="${this.options.drawerComponentName}"]`)

        getInstanceByElement(this.options.drawerComponentName, drawerEl)?.close()
    }
}
