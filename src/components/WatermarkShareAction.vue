<template>
	<div class="singleuseshare-action">
		<NcCheckboxRadioSwitch :model-value="enabled"
			@update:model-value="(value) => (enabled = value)">
			{{ t('singleuseshare', 'Filigrane') }}
		</NcCheckboxRadioSwitch>

		<div v-if="enabled" class="singleuseshare-action__options">
			<label for="singleuseshare-text">{{ t('singleuseshare', 'Texte personnalisé') }}</label>
			<textarea id="singleuseshare-text"
				v-model="customText"
				rows="2"
				:placeholder="t('singleuseshare', 'Confidentiel - Ne pas diffuser')" />

			<span class="singleuseshare-action__field-title">{{ t('singleuseshare', 'Informations dynamiques') }}</span>
			<NcCheckboxRadioSwitch v-for="(label, key) in availableDynamicFields"
				:key="key"
				:model-value="dynamicFields.includes(key)"
				@update:model-value="(checked) => toggleDynamicField(key, checked)">
				{{ label }}
			</NcCheckboxRadioSwitch>

			<label for="singleuseshare-position">{{ t('singleuseshare', 'Position') }}</label>
			<select id="singleuseshare-position" v-model="style.position">
				<option v-for="option in positionOptions" :key="option.value" :value="option.value">
					{{ option.label }}
				</option>
			</select>

			<label for="singleuseshare-opacity">
				{{ t('singleuseshare', 'Opacité') }} ({{ style.opacity }}%)
			</label>
			<input id="singleuseshare-opacity" v-model.number="style.opacity" type="range" min="1" max="100">

			<NcCheckboxRadioSwitch :model-value="style.tiled" @update:model-value="(value) => (style.tiled = value)">
				{{ t('singleuseshare', 'Répéter en mosaïque') }}
			</NcCheckboxRadioSwitch>
		</div>
	</div>
</template>

<script>
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import { translate as t } from '@nextcloud/l10n'
import NcCheckboxRadioSwitch from '@nextcloud/vue/dist/Components/NcCheckboxRadioSwitch.js'

/**
 * Rendered by @nextcloud/sharing's registerSidebarAction() directly inside
 * Nextcloud's native share settings panel (SharingDetailsTab.vue), next to
 * "Set password" / "Hide download" / "Custom permissions" - see
 * ../share-action.js for the registration itself.
 *
 * Props and the onSave contract are dictated by that package, not by us:
 * `share` starts out null while a brand new share is still being filled in
 * and becomes the real, saved share (with an id) by the time our onSave
 * callback actually runs - confirmed against SharingDetailsTab.vue's
 * saveShare()/SidebarTabExternalAction.vue's watchEffect on Nextcloud
 * 34.0.3: the share is created first, `share` is reactively updated with
 * the real id, *then* every registered action's save() is awaited. So
 * reading this.share.id inside save() is safe for both a new share and an
 * existing one being edited.
 */
export default {
	name: 'WatermarkShareAction',

	components: {
		NcCheckboxRadioSwitch,
	},

	props: {
		node: {
			type: Object,
			default: null,
		},
		share: {
			type: Object,
			default: null,
		},
		onSave: {
			type: Function,
			default: null,
		},
	},

	data() {
		return {
			enabled: false,
			customText: '',
			dynamicFields: [],
			style: {
				position: 'diagonal_tiled',
				opacity: 30,
				tiled: true,
			},
			// Sensible client-side fallback so the labels are available
			// immediately for a brand new share, before any config exists
			// server-side to fetch them from; overwritten by the server's
			// own list (source of truth) once fetchConfig() runs.
			availableDynamicFields: {
				date_heure: t('singleuseshare', 'Date et heure'),
				ip: t('singleuseshare', 'Adresse IP'),
				nom_fichier: t('singleuseshare', 'Nom du fichier'),
			},
			positionOptions: [
				{ value: 'diagonal_tiled', label: t('singleuseshare', 'Diagonal, répété') },
				{ value: 'center', label: t('singleuseshare', 'Centré') },
				{ value: 'banner_top', label: t('singleuseshare', 'Bandeau en haut') },
				{ value: 'banner_bottom', label: t('singleuseshare', 'Bandeau en bas') },
			],
		}
	},

	watch: {
		// node/share/onSave are plain properties set on the custom element
		// from outside (SidebarTabExternalAction.vue's watchEffect), not
		// necessarily populated yet by the time mounted() would normally
		// fire - a mounted()-only registration risks a race where onSave
		// arrives just after mount and our save() callback never gets
		// registered, silently dropping every watermark config save with
		// no error anywhere. Watching reactively (immediate: true covers
		// the "already available at creation" case, same as mounted())
		// handles both orderings.
		share: {
			immediate: true,
			handler(share, previousShare) {
				if (share?.id && share.id !== previousShare?.id) {
					this.fetchConfig(share.id)
				}
			},
		},
		onSave: {
			immediate: true,
			handler(fn) {
				if (typeof fn === 'function') {
					fn(this.save)
				}
			},
		},
	},

	methods: {
		t,

		toggleDynamicField(key, checked) {
			const fields = new Set(this.dynamicFields)
			if (checked) {
				fields.add(key)
			} else {
				fields.delete(key)
			}
			this.dynamicFields = Array.from(fields)
		},

		async fetchConfig(shareId) {
			const response = await axios.get(generateUrl('/apps/singleuseshare/shares/{shareId}', { shareId }))
			this.enabled = response.data.enabled
			this.customText = response.data.customText
			this.dynamicFields = response.data.dynamicFields
			this.style = {
				position: response.data.style.position || 'diagonal_tiled',
				opacity: response.data.style.opacity || 30,
				tiled: response.data.style.tiled !== false,
			}
			this.availableDynamicFields = response.data.availableDynamicFields
		},

		/**
		 * Registered via onSave() above - called by the share panel itself
		 * once the share is created/updated, whichever comes first.
		 */
		async save() {
			const shareId = this.share?.id
			if (!shareId) {
				return
			}

			await axios.put(generateUrl('/apps/singleuseshare/shares/{shareId}', { shareId }), {
				enabled: this.enabled,
				customText: this.customText,
				dynamicFields: this.dynamicFields,
				style: this.style,
			})
		},
	},
}
</script>

<style scoped lang="scss">
.singleuseshare-action {
	padding: 8px 0;

	&__options {
		display: flex;
		flex-direction: column;
		gap: 6px;
		margin: 8px 0 8px 44px;

		textarea, select {
			width: 100%;
		}
	}

	&__field-title {
		font-weight: bold;
		margin-top: 8px;
	}
}
</style>
