<template>
	<div class="singleuseshare-tab">
		<p v-if="loading" class="singleuseshare-tab__hint">
			{{ t('singleuseshare', 'Chargement…') }}
		</p>
		<p v-else-if="shares.length === 0" class="singleuseshare-tab__hint">
			{{ t('singleuseshare', 'Aucun partage pour cet élément.') }}
		</p>

		<ul v-else class="singleuseshare-tab__list">
			<li v-for="share in shares" :key="share.id" class="singleuseshare-tab__share">
				<div class="singleuseshare-tab__share-header">
					<span class="singleuseshare-tab__share-label">{{ shareLabel(share) }}</span>
					<NcCheckboxRadioSwitch v-if="configs[share.id]"
						:model-value="configs[share.id].enabled"
						type="switch"
						@update:model-value="(value) => toggleWatermark(share.id, value)">
						{{ t('singleuseshare', 'Filigrane') }}
					</NcCheckboxRadioSwitch>
				</div>

				<div v-if="configs[share.id] && configs[share.id].enabled" class="singleuseshare-tab__share-options">
					<label :for="'sus-text-' + share.id">{{ t('singleuseshare', 'Texte personnalisé') }}</label>
					<textarea :id="'sus-text-' + share.id"
						v-model="configs[share.id].customText"
						rows="2"
						:placeholder="t('singleuseshare', 'Confidentiel - Ne pas diffuser')" />

					<span class="singleuseshare-tab__field-title">{{ t('singleuseshare', 'Informations dynamiques') }}</span>
					<NcCheckboxRadioSwitch v-for="(label, key) in availableDynamicFields"
						:key="key"
						:model-value="configs[share.id].dynamicFields.includes(key)"
						@update:model-value="(checked) => toggleDynamicField(share.id, key, checked)">
						{{ label }}
					</NcCheckboxRadioSwitch>

					<label :for="'sus-position-' + share.id">{{ t('singleuseshare', 'Position') }}</label>
					<select :id="'sus-position-' + share.id" v-model="configs[share.id].style.position">
						<option v-for="option in positionOptions" :key="option.value" :value="option.value">
							{{ option.label }}
						</option>
					</select>

					<label :for="'sus-opacity-' + share.id">
						{{ t('singleuseshare', 'Opacité') }} ({{ configs[share.id].style.opacity }}%)
					</label>
					<input :id="'sus-opacity-' + share.id"
						v-model.number="configs[share.id].style.opacity"
						type="range"
						min="1"
						max="100">

					<NcCheckboxRadioSwitch :model-value="configs[share.id].style.tiled"
						@update:model-value="(value) => (configs[share.id].style.tiled = value)">
						{{ t('singleuseshare', 'Répéter en mosaïque') }}
					</NcCheckboxRadioSwitch>
				</div>

				<NcButton v-if="configs[share.id]"
					type="primary"
					:disabled="saving[share.id]"
					@click="save(share.id)">
					{{ saving[share.id] ? t('singleuseshare', 'Enregistrement…') : t('singleuseshare', 'Enregistrer') }}
				</NcButton>
			</li>
		</ul>
	</div>
</template>

<script>
import axios from '@nextcloud/axios'
import { generateUrl, generateOcsUrl } from '@nextcloud/router'
import { translate as t } from '@nextcloud/l10n'
import NcCheckboxRadioSwitch from '@nextcloud/vue/dist/Components/NcCheckboxRadioSwitch.js'
import NcButton from '@nextcloud/vue/dist/Components/NcButton.js'

export default {
	name: 'SharingWatermarkTab',

	components: {
		NcCheckboxRadioSwitch,
		NcButton,
	},

	// Props required by the Files sidebar's tab web-component contract
	// (see OCA\Files_Sharing's own FilesSidebarTab.vue on Nextcloud 34).
	props: {
		node: {
			type: Object,
			default: null,
		},
		active: {
			type: Boolean,
			default: false,
		},
		folder: {
			type: Object,
			default: null,
		},
		view: {
			type: Object,
			default: null,
		},
	},

	data() {
		return {
			loading: false,
			shares: [],
			configs: {},
			saving: {},
			availableDynamicFields: {},
			positionOptions: [
				{ value: 'diagonal_tiled', label: t('singleuseshare', 'Diagonal, répété') },
				{ value: 'center', label: t('singleuseshare', 'Centré') },
				{ value: 'banner_top', label: t('singleuseshare', 'Bandeau en haut') },
				{ value: 'banner_bottom', label: t('singleuseshare', 'Bandeau en bas') },
			],
		}
	},

	watch: {
		active(isActive) {
			if (isActive) {
				this.fetchShares()
			}
		},
		node() {
			if (this.active) {
				this.fetchShares()
			}
		},
	},

	mounted() {
		if (this.active) {
			this.fetchShares()
		}
	},

	methods: {
		t,

		shareLabel(share) {
			return share.share_with_displayname || share.share_with || t('singleuseshare', 'Lien public')
		},

		async fetchShares() {
			if (!this.node) {
				return
			}
			this.loading = true
			try {
				const response = await axios.get(generateOcsUrl('apps/files_sharing/api/v1/shares'), {
					params: { path: this.node.path, reshares: true },
				})
				this.shares = response.data.ocs.data
				await Promise.all(this.shares.map((share) => this.fetchConfig(share.id)))
			} finally {
				this.loading = false
			}
		},

		async fetchConfig(shareId) {
			const response = await axios.get(generateUrl('/apps/singleuseshare/shares/{shareId}', { shareId }))
			this.$set(this.configs, shareId, {
				enabled: response.data.enabled,
				customText: response.data.customText,
				dynamicFields: response.data.dynamicFields,
				style: {
					position: response.data.style.position || 'diagonal_tiled',
					opacity: response.data.style.opacity || 30,
					tiled: response.data.style.tiled !== false,
				},
			})
			if (Object.keys(this.availableDynamicFields).length === 0) {
				this.availableDynamicFields = response.data.availableDynamicFields
			}
		},

		toggleDynamicField(shareId, key, checked) {
			const fields = new Set(this.configs[shareId].dynamicFields)
			if (checked) {
				fields.add(key)
			} else {
				fields.delete(key)
			}
			this.$set(this.configs[shareId], 'dynamicFields', Array.from(fields))
		},

		async toggleWatermark(shareId, enabled) {
			this.configs[shareId].enabled = enabled
			await this.save(shareId)
		},

		async save(shareId) {
			const config = this.configs[shareId]
			this.$set(this.saving, shareId, true)
			try {
				await axios.put(generateUrl('/apps/singleuseshare/shares/{shareId}', { shareId }), {
					enabled: config.enabled,
					customText: config.customText,
					dynamicFields: config.dynamicFields,
					style: config.style,
				})
			} finally {
				this.$set(this.saving, shareId, false)
			}
		},
	},
}
</script>

<style scoped lang="scss">
.singleuseshare-tab {
	padding: 12px 16px;

	&__hint {
		color: var(--color-text-maxcontrast);
	}

	&__list {
		display: flex;
		flex-direction: column;
		gap: 16px;
	}

	&__share {
		border-bottom: 1px solid var(--color-border);
		padding-bottom: 12px;
	}

	&__share-header {
		display: flex;
		align-items: center;
		justify-content: space-between;
		gap: 8px;
	}

	&__share-label {
		font-weight: bold;
	}

	&__share-options {
		display: flex;
		flex-direction: column;
		gap: 6px;
		margin: 8px 0;

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
