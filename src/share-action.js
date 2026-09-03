import Vue from 'vue'
import wrap from '@vue/web-component-wrapper'
import { registerSidebarAction } from '@nextcloud/sharing/ui'
import WatermarkShareAction from './components/WatermarkShareAction.vue'

const tagName = 'oca_singleuseshare-sharing_action'

const webComponent = wrap(Vue, WatermarkShareAction)

/**
 * @vue/web-component-wrapper (the Vue 2 custom-element bridge; Vue 3 apps
 * like files_downloadlimit don't need this, they use Vue's own native
 * defineCustomElement) posts a MutationObserver on the *light* DOM of the
 * element to track slotted content, completely separate from the shadow
 * root Vue actually renders into - that separation is the whole point of
 * using a real shadow root.
 *
 * A previous version of this file faked `shadowRoot` as the element itself
 * (`get shadowRoot() { return this }`) so @nextcloud/vue's globally
 * injected CSS (in document.head, not scoped per shadow root) would still
 * reach the component. That made Vue render its output into the very node
 * the MutationObserver was watching for slot content: toggling the
 * watermark switch made v-if insert ~15 nodes into that node, the observer
 * fired, reassigned the wrapper's reactive slotChildren, which re-rendered
 * the wrapper, which mutated the same node again - an infinite feedback
 * loop that froze the tab. Confirmed by tracing
 * node_modules/@vue/web-component-wrapper/dist/vue-wc-wrapper.js against a
 * real reproduction (the browser hung solid on the very first click).
 *
 * Fix: use a real, isolated shadow root (don't override anything), and
 * explicitly clone the page's <link>/<style> tags into it once per
 * instance so @nextcloud/vue's component styles still apply.
 */
const realAttachShadow = HTMLElement.prototype.attachShadow
Object.defineProperty(webComponent.prototype, 'attachShadow', {
	value(init) {
		const shadowRoot = realAttachShadow.call(this, init)
		document.querySelectorAll('head link[rel="stylesheet"], head style').forEach((node) => {
			shadowRoot.appendChild(node.cloneNode(true))
		})
		return shadowRoot
	},
})

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
