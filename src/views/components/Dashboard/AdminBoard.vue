<!-- eslint-disable object-curly-newline -->
<template>
	<div class="dashboard">
		<!-- KPIs -->
		<section class="kpis">
			<div class="kpi">
				<div class="kpi-label">
					{{ t('empleados', 'Employees') }}
				</div>
				<div class="kpi-value">
					{{ loading ? '…' : stats.totalEmpleados }}
				</div>
			</div>
			<div class="kpi">
				<div class="kpi-label">
					{{ t('empleados', 'Areas') }}
				</div>
				<div class="kpi-value">
					{{ loading ? '…' : stats.totalAreas }}
				</div>
			</div>
			<div class="kpi">
				<div class="kpi-label">
					{{ t('empleados', 'Absences today') }}
				</div>
				<div class="kpi-value">
					{{ loading ? '…' : stats.ausenciasHoy }}
				</div>
			</div>
			<div class="kpi">
				<div class="kpi-label">
					{{ t('empleados', 'Anniversaries (30 days)') }}
				</div>
				<div class="kpi-value">
					{{ loading ? '…' : stats.aniversariosMes }}
				</div>
			</div>
		</section>

		<!-- Acciones rápidas -->
		<section class="quick">
			<h3>{{ t('empleados', 'Quick actions') }}</h3>
			<div class="quick-grid">
				<button class="nc-btn" @click="go('empleados')">
					{{ t('empleados', 'View employees') }}
				</button>
				<button class="nc-btn" @click="go('empleados/nuevo')">
					{{ t('empleados', 'New employee') }}
				</button>
				<button class="nc-btn" @click="go('areas')">
					{{ t('empleados', 'Areas and positions') }}
				</button>
				<button class="nc-btn" @click="go('ausencias')">
					{{ t('empleados', 'Manage absences') }}
				</button>
				<button class="nc-btn" @click="go('reportes')">
					{{ t('empleados', 'Reports') }}
				</button>
				<button class="nc-btn" @click="go('config')">
					{{ t('empleados', 'Settings') }}
				</button>
			</div>
		</section>

		<!-- Próximos aniversarios -->
		<section class="panel">
			<div class="panel-head">
				<h3>{{ t('empleados', 'Upcoming anniversaries (30 days)') }}</h3>
				<button class="nc-link" @click="go('aniversarios')">
					{{ t('empleados', 'View all') }}
				</button>
			</div>
			<div v-if="loading" class="empty">
				{{ t('empleados', 'Loading...') }}
			</div>
			<ul v-else-if="aniversarios.length" class="list">
				<li v-for="a in aniversarios" :key="a.id" class="item">
					<div class="item-main">
						<strong>{{ a.nombre }}</strong>
						<span class="muted">· {{ a.area }}</span>
					</div>
					<div class="item-meta">
						<span class="pill">{{ a.fecha }}</span>
						<span class="muted">{{ t('empleados', '{years} years', { years: a.years }) }}</span>
					</div>
				</li>
			</ul>
			<div v-else class="empty">
				{{ t('empleados', 'No upcoming anniversaries.') }}
			</div>
		</section>

		<!-- Ausencias hoy -->
		<section class="panel">
			<div class="panel-head">
				<h3>{{ t('empleados', 'Today absences') }}</h3>
				<button class="nc-link" @click="go('ausencias')">
					{{ t('empleados', 'Manage') }}
				</button>
			</div>
			<div v-if="loading" class="empty">
				{{ t('empleados', 'Loading...') }}
			</div>
			<ul v-else-if="ausenciasHoy.length" class="list">
				<li v-for="x in ausenciasHoy" :key="x.id" class="item">
					<div class="item-main">
						<strong>{{ x.nombre }}</strong>
						<span class="muted">· {{ x.tipo }}</span>
					</div>
					<div class="item-meta">
						<span class="pill">{{ x.de }} → {{ x.hasta }}</span>
						<span class="muted">{{ x.area }}</span>
					</div>
				</li>
			</ul>
			<div v-else class="empty">
				{{ t('empleados', 'Nobody is absent today.') }}
			</div>
		</section>

		<!-- Últimos cambios -->
		<section class="panel">
			<div class="panel-head">
				<h3>{{ t('empleados', 'Latest changes') }}</h3>
				<button class="nc-link" @click="go('actividad')">
					{{ t('empleados', 'View activity') }}
				</button>
			</div>
			<div v-if="loading" class="empty">
				{{ t('empleados', 'Loading...') }}
			</div>
			<ul v-else-if="actividad.length" class="list">
				<li v-for="e in actividad" :key="e.id" class="item">
					<div class="item-main">
						<strong>{{ e.titulo }}</strong>
						<span class="muted">· {{ e.usuario }}</span>
					</div>
					<div class="item-meta">
						<span class="muted">{{ e.fecha }}</span>
					</div>
				</li>
			</ul>
			<div v-else class="empty">
				{{ t('empleados', 'No recent activity.') }}
			</div>
		</section>
	</div>
</template>

<script>
import { translate as t } from '@nextcloud/l10n'

export default {
	methods: {
		t,
	},
}
</script>
