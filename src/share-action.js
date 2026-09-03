import Vue from 'vue'
import wrap from '@vue/web-component-wrapper'
import { registerSidebarAction } from '@nextcloud/sharing/ui'
import WatermarkShareAction from './components/WatermarkShareAction.vue'

const tagName = 'oca_singleuseshare-sharing_action'

const webComponent = wrap(Vue, WatermarkShareAction)
// Same Vue 2 / web-component-wrapper shadow DOM workaround as the rest of
// the app - see files-sidebar.js for the reasoning.
Object.defineProperty(webComponent.prototype, 'attachShadow', { value() { return this } })
Object.defineProperty(webComponent.prototype, 'shadowRoot', { get() { return this } })
window.customElements.define(tagName, webComponent)

registerSidebarAction({
	id: 'singleuseshare',
	element: tagName,
	// Right after "Hide download" / "Note to recipient", before "Custom
	// permissions" - matches where files_downloadlimit's own action lands.
	order: 25,
	enabled(share, node) {
		// Any node type (file or folder - watermarking applies
		// recursively to a folder's contents) and any share type (public
		// link, email, internal user/group...), unlike files_downloadlimit
		// which only makes sense for a single file on a public/email link.
		return true
	},
})
