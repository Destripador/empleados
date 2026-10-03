<template>
	<div>
		<div class="btn-top">
			<div class="info-section">
				<div class="section-head">
					<div>
						<p class="section-label">
							{{ t('empleados', 'Company Information') }}
						</p>
					</div>
				</div>

				<div class="info-grid">
					<div class="detail-card">
						<span>{{ t('empleados', 'Legal Business Name') }}</span>
						<span class="value-text">{{ client.razon_social || '-' }}</span>
					</div>

					<div class="detail-card">
						<span>{{ t('empleados', 'Project Manager') }}</span>

						<div v-if="projectManager" class="pm-info">
							<img :src="projectManager.avatar" :alt="projectManager.label" class="pm-avatar">
							<span class="value-text">{{ projectManager.label }}</span>
						</div>

						<span v-else class="value-text">-</span>
					</div>

					<div class="detail-card">
						<span>{{ t('empleados', 'Primary Contact') }}</span>
						<span class="value-text">{{ client.nombre_contacto || '-' }}</span>
					</div>

					<div class="detail-card">
						<span>{{ t('empleados', 'Phone Number') }}</span>
						<span class="value-text">{{ client.telefono || '-' }}</span>
					</div>

					<div class="detail-card">
						<span>{{ t('empleados', 'Email Address') }}</span>
						<span class="value-text">{{ client.correo || '-' }}</span>
					</div>

					<div class="detail-card">
						<span>{{ t('empleados', 'RFC') }}</span>
						<span class="value-text">{{ client.rfc || '-' }}</span>
					</div>

					<div class="detail-card">
						<span>{{ t('empleados', 'Location') }}</span>
						<span class="value-text">{{ client.ubicacion || '-' }}</span>
					</div>

					<div class="detail-card">
						<span>{{ t('empleados', 'Special Client') }}</span>
						<span class="value-text">{{ Number(client.especial) ? t('empleados', 'Yes') : t('empleados', 'No') }}</span>
					</div>

					<div class="detail-card">
						<span>{{ t('empleados', 'Status') }}</span>
						<span class="value-text">{{ Number(client.estado) ? t('empleados', 'Active') : t('empleados', 'Inactive') }}</span>
					</div>
				</div>
			</div>
		</div>

		<div class="btn-top">
			<div class="info-section">
				<div class="section-head">
					<div>
						<p class="section-label">
							{{ t('empleados', 'Group Information') }}
						</p>
					</div>
				</div>

				<div class="details-grid">
					<div class="detail-card">
						<span>{{ t('empleados', 'Parent group') }}</span>
						<span class="value-text">{{ parentName }}</span>
					</div>

					<div class="detail-card">
						<span>{{ t('empleados', 'Sub-companies') }}</span>
						<span class="value-text">{{ childCompanies.length }}</span>
					</div>

					<div class="detail-card detail-card-wide">
						<span>{{ t('empleados', 'Hierarchy') }}</span>
						<div class="breadcrumb">
							<span>{{ parentName }}</span>
							<span class="separator">/</span>
							<span class="value-text">{{ client.nombre }}</span>
						</div>
					</div>
				</div>

				<div class="children-section">
					<div class="section-head">
						<div>
							<p class="section-label">
								{{ t('empleados', 'Sub-companies') }}
							</p>
							<h3>{{ t('empleados', 'Companies inside this group') }}</h3>
						</div>
					</div>

					<SubCompaniesGrid
						:items="childCompaniesItems"
						:empty-label="t('empleados', 'No sub-companies')"
						:empty-description="t('empleados', 'This company or group does not have registered sub-companies.')"
						@select="$emit('select-child', $event)" />
				</div>
			</div>
		</div>
	</div>
</template>

<script>
import { translate as t } from '@nextcloud/l10n'
import SubCompaniesGrid from './SubCompaniesGrid.vue'

export default {
	name: 'ClientGeneralTab',

	components: { SubCompaniesGrid },

	props: {
		client: { type: Object, required: true },
		projectManager: { type: Object, default: null },
		parentName: { type: String, default: '' },
		childCompanies: { type: Array, default: () => [] },
	},

	computed: {
		childCompaniesItems() {
			return this.childCompanies.map(child => ({
				key: child.id,
				id: child.id,
				title: child.nombre,
				subtitle: child.detalles || t('empleados', 'No description available.'),
				badge: child.child_count || 0,
			}))
		},
	},

	methods: { t },
}
</script>

<style scoped lang="scss">
.info-section,
.children-section {
	display: flex;
	flex-direction: column;
	gap: 12px;
	margin-bottom: 18px;
}

.section-head {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: 12px;

	h3 {
		margin: 2px 0 0;
		font-size: 1rem;
		font-weight: 600;
		color: var(--color-main-text);
	}
}

.section-label {
	font-size: 0.75rem;
	font-weight: 600;
	text-transform: uppercase;
	letter-spacing: 0.08em;
	color: var(--color-primary-element);
	margin: 0;
}

.details-grid,
.info-grid {
	display: grid;
	grid-template-columns: repeat(2, 1fr);
	gap: 10px;

	@media (max-width: 480px) {
		grid-template-columns: 1fr;
	}
}

.detail-card {
	display: flex;
	flex-direction: column;
	gap: 4px;
	padding: 12px 14px;
	border-radius: var(--border-radius-large);
	background: var(--color-background-soft);
	border: 1px solid var(--color-border);

	span {
		font-size: 0.72rem;
		font-weight: 600;
		text-transform: uppercase;
		letter-spacing: 0.06em;
		color: var(--color-text-maxcontrast);
	}

	.value-text {
		font-size: 0.9rem;
		font-weight: 600;
		color: var(--color-main-text);
		text-transform: none;
		letter-spacing: normal;
	}

	&.detail-card-wide {
		grid-column: span 2;

		@media (max-width: 480px) {
			grid-column: span 1;
		}
	}
}

.breadcrumb {
	display: flex;
	align-items: center;
	gap: 6px;
	font-size: 0.875rem;
	color: var(--color-main-text);

	.separator {
		color: var(--color-text-maxcontrast);
		font-weight: 400;
		text-transform: none;
		letter-spacing: normal;
	}

	.value-text {
		font-weight: 700;
	}
}

.pm-info {
	display: flex;
	align-items: center;
	gap: 10px;
}

.pm-avatar {
	width: 32px;
	height: 32px;
	border-radius: 50%;
	object-fit: cover;
	flex-shrink: 0;
}
</style>
