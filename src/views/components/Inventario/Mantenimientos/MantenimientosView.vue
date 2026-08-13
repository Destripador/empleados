<template>
	<NcAppContent :name="t('empleados', 'Maintenance calendar')">
		<main class="page">
			<div v-if="!accessAllowed" class="state">
				<NcEmptyContent :name="t('empleados', 'Access denied')" :description="t('empleados', 'You do not have permission to view maintenance or the inventory module is disabled.')" />
			</div>
			<template v-else>
				<header>
					<div><h2>{{ t('empleados', 'Maintenance calendar') }}</h2><p>{{ t('empleados', 'Plan and review IT inventory maintenance campaigns.') }}</p></div><div class="header-actions">
						<NcButton @click="$router.push({ name: 'Inventario' })">
							{{ t('empleados', 'Back to inventory') }}
						</NcButton><NcButton v-if="capabilities.canAdministerMaintenance" type="primary" @click="showForm = true">
							{{ t('empleados', 'Schedule maintenance') }}
						</NcButton>
					</div>
				</header>
				<MantenimientoIndicadores :groups="groups" :overdue="overdueTotal" />
				<section class="card">
					<MantenimientoFiltros v-model="filters"
						:departments="departments"
						:technicians="technicians"
						:technicians-loading="techniciansLoading"
						:technicians-error="techniciansError"
						:show-technician="showTechnicianFilter"
						@change="filtersChanged" />
				</section>
				<NcNoteCard v-if="error" type="error" :text="error" />
				<section class="card calendar-card">
					<div v-if="loading" class="loading">
						<NcLoadingIcon :size="36" /><span>{{ t('empleados', 'Loading maintenance campaigns') }}</span>
					</div><MantenimientoCalendario :load-events="loadGroups"
						:refresh-key="refreshKey"
						@open-group="openGroup"
						@load-error="handleError" />
				</section>
				<section class="card">
					<h3>{{ t('empleados', 'Upcoming campaigns') }}</h3><ul v-if="upcoming.length" class="campaigns">
						<li v-for="group in upcoming" :key="group.id">
							<button type="button" @click="openGroup(group.id)">
								<strong>{{ group.titulo || group.departamento_nombre || t('empleados', 'Maintenance campaign') }}</strong><span>{{ formatDateRange(group.periodStart || group.fecha_inicio || group.fecha_programada, group.periodEnd || group.fecha_fin || group.fecha_programada) }} · {{ maintenanceTypeLabel(group.tipo, t) }}</span>
							</button>
						</li>
					</ul><NcEmptyContent v-else-if="!loading" :name="t('empleados', 'No maintenance campaigns in this range')" />
				</section>
				<MantenimientoGrupoForm v-if="showForm"
					:departments="departments"
					:technicians="technicians"
					:technicians-loading="techniciansLoading"
					:technicians-error="techniciansError"
					@close="showForm = false"
					@created="groupCreated" />
			</template>
		</main>
	</NcAppContent>
</template>

<script>
import { translate as t } from '@nextcloud/l10n'
import { showError, showSuccess } from '@nextcloud/dialogs'
import { NcAppContent, NcButton, NcEmptyContent, NcLoadingIcon, NcNoteCard } from '@nextcloud/vue'
import permissionsMixin from '../../../../mixins/permissions.js'
import maintenanceService from '../../../../services/mantenimientoService.js'
import { formatDateRange, maintenanceCapabilities, maintenanceTypeLabel } from '../../../../utils/mantenimientoFormatters.js'
import MantenimientoCalendario from './MantenimientoCalendario.vue'
import MantenimientoFiltros from './MantenimientoFiltros.vue'
import MantenimientoGrupoForm from './MantenimientoGrupoForm.vue'
import MantenimientoIndicadores from './MantenimientoIndicadores.vue'

export default {
	name: 'MantenimientosView',
	components: { NcAppContent, NcButton, NcEmptyContent, NcLoadingIcon, NcNoteCard, MantenimientoCalendario, MantenimientoFiltros, MantenimientoGrupoForm, MantenimientoIndicadores },
	mixins: [permissionsMixin],
	inject: { configuraciones: { default: () => ({}) } },
	data() { return { groups: [], departments: [], technicians: [], techniciansLoading: false, techniciansError: '', overdueTotal: 0, loading: false, error: '', showForm: false, refreshKey: 0, activeController: null, lastRequestKey: '', lastRequestPromise: null, filters: { departmentId: this.$route.query.departmentId || '', technicianUid: this.$route.query.technicianUid || '', type: this.$route.query.type || '', status: this.$route.query.status || '', search: this.$route.query.search || '', showOverdue: this.$route.query.showOverdue === '1' } } },
	computed: { capabilities() { return maintenanceCapabilities(this.permissions, this.configuraciones) }, accessAllowed() { return this.capabilities.moduleEnabled && this.capabilities.canViewMaintenance }, showTechnicianFilter() { return this.capabilities.canViewMaintenance && !this.capabilities.isTechnician }, upcoming() { return [...this.groups].sort((a, b) => String(a.periodStart || a.fecha_inicio || a.fecha_programada).localeCompare(String(b.periodStart || b.fecha_inicio || b.fecha_programada))).slice(0, 8) } },
	async mounted() { if (this.accessAllowed) await this.loadCatalogs() },
	beforeDestroy() { this.activeController?.abort() },
	methods: {
		t,
		formatDateRange,
		maintenanceTypeLabel,
		async loadCatalogs() { const departments = maintenanceService.getDepartments().then(items => { this.departments = Array.isArray(items) ? items : [] }).catch(this.handleError); if (this.showTechnicianFilter) await Promise.all([departments, this.loadTechnicians()]); else await departments },
		async loadTechnicians() { this.techniciansLoading = true; this.techniciansError = ''; try { this.technicians = await maintenanceService.getTechnicians() } catch (error) { this.technicians = []; this.techniciansError = t('empleados', 'Could not load technicians.'); this.handleError(error) } finally { this.techniciansLoading = false } },
		requestFilters(range) { return { ...range, departmentId: this.filters.departmentId || undefined, technicianUid: this.showTechnicianFilter ? this.filters.technicianUid || undefined : undefined, type: this.filters.type || undefined, status: this.filters.status || undefined, search: this.filters.search || undefined, limit: 200, offset: 0 } },
		async loadGroups(range) { const params = this.requestFilters(range); const key = JSON.stringify(params); if (key === this.lastRequestKey && this.lastRequestPromise) return this.lastRequestPromise; this.activeController?.abort(); this.activeController = new AbortController(); this.loading = true; this.error = ''; this.lastRequestKey = key; this.lastRequestPromise = maintenanceService.getGroups(params, this.activeController.signal).then(async data => { this.groups = data.items || []; await this.loadOverdue(); return this.groups }).catch(error => { if (!error.cancelled) this.handleError(error); throw error }).finally(() => { if (key === this.lastRequestKey) this.loading = false }); return this.lastRequestPromise },
		async loadOverdue() { if (!this.filters.showOverdue) { this.overdueTotal = 0; return } try { const data = await maintenanceService.getOverdue({ departmentId: this.filters.departmentId || undefined, technicianUid: this.showTechnicianFilter ? this.filters.technicianUid || undefined : undefined, type: this.filters.type || undefined, limit: 1, offset: 0 }); this.overdueTotal = Number(data.total || 0) } catch (error) { if (!error.cancelled) this.handleError(error) } },
		filtersChanged() { this.lastRequestKey = ''; this.lastRequestPromise = null; this.refreshKey++; this.$router.replace({ query: this.filterQuery() }).catch(() => {}) },
		filterQuery() { const query = {}; for (const key of ['departmentId', 'type', 'status', 'search']) if (this.filters[key]) query[key] = this.filters[key]; if (this.showTechnicianFilter && this.filters.technicianUid) query.technicianUid = this.filters.technicianUid; if (this.filters.showOverdue) query.showOverdue = '1'; return query },
		openGroup(id) { this.$router.push({ name: 'MantenimientoGrupo', params: { id }, query: this.filterQuery() }) },
		groupCreated(data) { this.showForm = false; showSuccess(t('empleados', 'Maintenance campaign created.')); const id = data?.group?.id || data?.id; if (id) this.openGroup(id); else { this.lastRequestKey = ''; this.refreshKey++ } },
		handleError(error) { if (error?.cancelled) return; this.error = error?.status === 401 ? t('empleados', 'Your session has expired.') : error?.status === 403 ? t('empleados', 'You do not have permission to perform this action.') : error?.status === 500 ? t('empleados', 'An internal error occurred.') : error?.message || t('empleados', 'Could not load maintenance information.'); showError(this.error) },
	},
}
</script>

<style scoped lang="scss">.page { padding:24px; width:100%; min-width:0; }header { display:flex; justify-content:space-between; gap:16px; margin-bottom:18px; }header h2 { margin:0; }header p { color:var(--color-text-maxcontrast); }.header-actions { display:flex; gap:8px; align-items:start; flex-wrap:wrap; }.card { margin-top:16px; padding:16px; border:1px solid var(--color-border); border-radius:var(--border-radius-large); background:var(--color-main-background); }.calendar-card { position:relative; }.loading { display:flex; gap:8px; align-items:center; padding-bottom:10px; }.campaigns { list-style:none; padding:0; display:grid; grid-template-columns:repeat(auto-fit,minmax(240px,1fr)); gap:10px; }.campaigns button { width:100%; padding:12px; border:1px solid var(--color-border); border-radius:var(--border-radius); background:transparent; color:var(--color-main-text); text-align:left; cursor:pointer; }.campaigns span { display:block; margin-top:4px; color:var(--color-text-maxcontrast); }.state { width:100%; }@media(max-width:700px){header{display:block}.header-actions{margin-top:12px}}</style>
