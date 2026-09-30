<template>
	<div class="member-preview" :class="`member-preview--${variant}`">
		<p v-if="visibleMembers.length === 0" class="member-preview__empty">
			{{ t('empleados', 'No members assigned.') }}
		</p>
		<ul v-else class="member-preview__list" :aria-label="t('empleados', 'Team members')">
			<li
				v-for="member in visibleMembers"
				:key="memberKey(member)"
				class="member-preview__person"
				:class="{ 'member-preview__person--inactive': member.inactivo_desde }"
				:title="memberTitle(member)"
				:aria-label="memberTitle(member)">
				<NcAvatar
					disable-menu
					aria-hidden="true"
					:user="memberUid(member)"
					:display-name="memberName(member)"
					:size="size"
					:show-user-status="false"
					:show-user-status-compact="false" />
				<span v-if="variant !== 'strip'" class="member-preview__name">
					{{ memberName(member) }}
				</span>
			</li>
			<li
				v-if="showRemaining && remainingCount > 0"
				class="member-preview__more"
				:aria-label="t('empleados', '{count} more members', { count: remainingCount })">
				{{ t('empleados', '+{count} more', { count: remainingCount }) }}
			</li>
		</ul>
	</div>
</template>

<script>
import { translate as t } from '@nextcloud/l10n'
import { NcAvatar } from '@nextcloud/vue'

export default {
	name: 'AdminMemberPreview',
	components: { NcAvatar },
	props: {
		members: { type: Array, default: () => [] },
		total: { type: Number, default: 0 },
		limit: { type: Number, default: 5 },
		size: { type: Number, default: 30 },
		showRemaining: { type: Boolean, default: true },
		variant: {
			type: String,
			default: 'list',
			validator: value => ['compact', 'list', 'strip'].includes(value),
		},
	},
	computed: {
		visibleMembers() {
			return this.members
				.filter(member => member && typeof member === 'object')
				.slice(0, Math.max(0, this.limit))
		},
		remainingCount() {
			return Math.max(0, this.number(this.total) - this.visibleMembers.length)
		},
	},
	methods: {
		t,
		number(value) {
			const parsed = Number(value)
			return Number.isFinite(parsed) ? parsed : 0
		},
		memberKey(member) {
			return String(member.id_empleado ?? member.id ?? member.id_user ?? member.Id_user ?? this.memberName(member))
		},
		memberUid(member) {
			return String(member.id_user ?? member.Id_user ?? '')
		},
		memberName(member) {
			return String(member.nombre ?? member.displayname ?? this.memberUid(member) ?? t('empleados', 'Employee'))
		},
		memberTitle(member) {
			return member.inactivo_desde
				? t('empleados', '{name} — inactivo desde {date}', {
					name: this.memberName(member),
					date: member.inactivo_desde,
				})
				: this.memberName(member)
		},
	},
}
</script>

<style scoped>
.member-preview,
.member-preview__list,
.member-preview__person {
	min-width: 0;
}

.member-preview__list {
	display: flex;
	flex-wrap: wrap;
	align-items: center;
	gap: calc(var(--default-grid-baseline) * 2);
	margin: 0;
	padding: 0;
	list-style: none;
}

.member-preview__person {
	display: inline-flex;
	align-items: center;
	gap: calc(var(--default-grid-baseline) * 1.5);
}

.member-preview__person--inactive {
	filter: grayscale(1);
	opacity: 0.65;
}

.member-preview__name {
	overflow: hidden;
	font-size: 0.8125rem;
	line-height: 1.2;
	text-overflow: ellipsis;
	white-space: nowrap;
}

.member-preview__person--inactive .member-preview__name {
	font-style: italic;
	color: var(--color-text-maxcontrast);
}

.member-preview--list .member-preview__person {
	max-width: 100%;
	padding: 2px calc(var(--default-grid-baseline) * 2) 2px 2px;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-pill, 999px);
	background: var(--color-main-background);
}

.member-preview__more {
	display: inline-flex;
	align-items: center;
	flex: 0 0 auto;
	min-height: calc(var(--default-grid-baseline) * 6);
	padding: 0 calc(var(--default-grid-baseline) * 2);
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-pill, 999px);
	color: var(--color-text-maxcontrast);
	font-size: 0.8125rem;
	font-weight: 600;
}

.member-preview--compact .member-preview__list {
	display: grid;
	grid-template-columns: repeat(2, minmax(0, 1fr));
	gap: var(--default-grid-baseline) calc(var(--default-grid-baseline) * 1.5);
}

.member-preview--compact .member-preview__person,
.member-preview--compact .member-preview__more {
	min-width: 0;
	min-height: calc(var(--default-grid-baseline) * 6.5);
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius-pill, 999px);
	background: var(--color-main-background);
}

.member-preview--compact .member-preview__person {
	padding: 1px calc(var(--default-grid-baseline) * 1.5) 1px 1px;
}

.member-preview--compact .member-preview__more {
	justify-content: center;
	padding-inline: calc(var(--default-grid-baseline) * 1.5);
	overflow: hidden;
	text-overflow: ellipsis;
	white-space: nowrap;
}

.member-preview--strip .member-preview__list {
	gap: 0;
}

.member-preview--strip .member-preview__person {
	position: relative;
	border: 2px solid var(--color-main-background);
	border-radius: 50%;
}

.member-preview--strip .member-preview__person + .member-preview__person {
	margin-left: -0.45rem;
}

.member-preview--strip .member-preview__person:hover,
.member-preview--strip .member-preview__person:focus-within {
	z-index: 1;
}

.member-preview--strip .member-preview__more {
	display: inline-flex;
	align-items: center;
	align-self: stretch;
	margin-left: 0.4rem;
	padding: 0;
	border: 0;
	min-height: 0;
}

.member-preview__empty {
	margin: 0;
	color: var(--color-text-maxcontrast);
	font-size: 0.875rem;
}

@media (max-width: 32rem) {
	.member-preview--list .member-preview__person {
		max-width: 100%;
	}
}
</style>
