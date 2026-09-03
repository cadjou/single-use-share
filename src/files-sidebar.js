import Vue from 'vue'
import wrap from '@vue/web-component-wrapper'
import { getSidebar } from '@nextcloud/files'
import { translate as t } from '@nextcloud/l10n'

const tagName = 'singleuseshare-sidebar-tab'

// Filigrane / concentric circles - kept inline since iconSvgInline expects raw SVG markup, not a URL.
const icon = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="4"/></svg>'

getSidebar().registerTab({
	id: 'singleuseshare',
	displayName: t('singleuseshare', 'Filigrane'),
	iconSvgInline: icon,
	order: 25,
	tagName,

	async onInit() {
		const { default: SharingWatermarkTab } = await import('./views/SharingWatermarkTab.vue')
		const webComponent = wrap(Vue, SharingWatermarkTab)
		// Vue 2's web-component-wrapper always tries to use shadow DOM - Nextcloud's
		// own sidebar tabs (see files_sharing/src/files-sidebar.ts) disable it the
		// same way so the tab's styles/composables can reach the rest of the page.
		Object.defineProperty(webComponent.prototype, 'attachShadow', { value() { return this } })
		Object.defineProperty(webComponent.prototype, 'shadowRoot', { get() { return this } })
		window.customElements.define(tagName, webComponent)
	},
})
