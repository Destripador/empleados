<template>
	<div class="office-doc">
		<header class="office-doc__header">
			<div>
				<p class="office-doc__eyebrow">
					{{ t('empleados', 'Office Simulation guide') }}
				</p>
				<h2>{{ t('empleados', 'Technical documentation') }}</h2>
				<p class="office-doc__description">
					{{ t('empleados', 'Concepts, data boundaries and implementation details for understanding the simulation.') }}
				</p>
				<div class="office-doc__global-warning" role="note">
					<strong>{{ t('empleados', 'Important: this is a simulation') }}</strong>
					<span>{{ t('empleados', 'Do not use Office Simulation to evaluate productivity, attendance, relationships, mood, conduct or performance.') }}</span>
				</div>
			</div>

			<label class="office-doc__mobile-nav">
				<span>{{ t('empleados', 'Chapter') }}</span>
				<select v-model="selectedChapter" @change="scrollToChapter(selectedChapter)">
					<option v-for="chapter in chapters" :key="chapter.id" :value="chapter.id">
						{{ chapter.number }} · {{ chapter.title }}
					</option>
				</select>
			</label>
		</header>

		<div class="office-doc__layout">
			<nav class="office-doc__sidebar" :aria-label="t('empleados', 'Documentation chapters')">
				<button
					v-for="chapter in chapters"
					:key="chapter.id"
					type="button"
					:class="{ 'office-doc__nav-button--active': selectedChapter === chapter.id }"
					class="office-doc__nav-button"
					@click="scrollToChapter(chapter.id)">
					<span>{{ chapter.number }}</span>
					{{ chapter.title }}
				</button>
			</nav>

			<div
				ref="content"
				class="office-doc__content"
				tabindex="0"
				@scroll.passive="onContentScroll">
				<article
					v-for="chapter in chapters"
					:id="`office-doc-${chapter.id}`"
					:key="chapter.id"
					:data-chapter="chapter.id"
					class="office-doc__chapter">
					<header class="office-doc__chapter-header">
						<span>{{ chapter.number }}</span>
						<div>
							<h3>{{ chapter.title }}</h3>
							<p class="office-doc__chapter-summary">
								{{ chapter.summary }}
							</p>
						</div>
					</header>

					<div v-if="chapter.warning" class="office-doc__warning" role="note">
						<strong>{{ t('empleados', 'Privacy boundary') }}</strong>
						<p class="office-doc__warning-copy">
							{{ chapter.warning }}
						</p>
					</div>

					<div v-if="chapter.visual === 'emergence'" class="office-doc-visual office-doc-visual--emergence" aria-hidden="true">
						<div class="office-doc-visual__field office-doc-visual__field--scattered">
							<i v-for="index in 7" :key="`scatter-${index}`" />
						</div>
						<div class="office-doc-visual__rule">
							<span>{{ t('empleados', 'Local rules') }}</span>
							<b>→</b>
						</div>
						<div class="office-doc-visual__field office-doc-visual__field--pattern">
							<i v-for="index in 7" :key="`pattern-${index}`" />
						</div>
					</div>

					<div v-else-if="chapter.visual === 'state'" class="office-doc-visual office-doc-visual--state" aria-hidden="true">
						<span class="office-doc-state office-doc-state--input">{{ t('empleados', 'Input') }}</span>
						<span class="office-doc-state office-doc-state--working">WORKING</span>
						<span class="office-doc-state office-doc-state--social">CONVERSATION</span>
						<span class="office-doc-state office-doc-state--return">RETURNING_HOME</span>
						<svg viewBox="0 0 620 160" focusable="false">
							<defs>
								<marker
									id="office-state-arrow"
									markerWidth="8"
									markerHeight="8"
									refX="7"
									refY="4"
									orient="auto">
									<path d="M0,0 L8,4 L0,8 z" />
								</marker>
							</defs>
							<path d="M105 80 H190 M300 80 H355 M465 80 H520" />
						</svg>
					</div>

					<div
						v-else-if="chapter.visual === 'pipeline'"
						class="office-doc-visual office-doc-visual--pipeline"
						:aria-label="t('empleados', 'Data processing pipeline')">
						<template v-for="(stage, index) in pipelineStages">
							<span :key="stage" class="office-doc-pipeline__stage">{{ stage }}</span>
							<b v-if="index < pipelineStages.length - 1" :key="`${stage}-arrow`" aria-hidden="true">→</b>
						</template>
					</div>

					<div class="office-doc__sections">
						<details
							v-for="(section, sectionIndex) in chapter.sections"
							:key="section.title"
							:open="sectionIndex === 0">
							<summary>{{ section.title }}</summary>
							<div class="office-doc__details-copy">
								<p
									v-for="paragraph in section.paragraphs || []"
									:key="paragraph"
									class="office-doc__paragraph">
									{{ paragraph }}
								</p>
								<ul v-if="section.bullets">
									<li v-for="bullet in section.bullets" :key="bullet">
										{{ bullet }}
									</li>
								</ul>
								<dl v-if="section.terms" class="office-doc__terms">
									<div v-for="term in section.terms" :key="term.name">
										<dt><code>{{ term.name }}</code></dt>
										<dd>{{ term.description }}</dd>
									</div>
								</dl>
							</div>
						</details>
					</div>
				</article>
			</div>
		</div>
	</div>
</template>

<script>
import { translate as t } from '@nextcloud/l10n'
import { buildOfficeSimulationDocumentation } from '../../../utils/officeSimulationDocumentation.js'

export default {
	name: 'OfficeSimulationDocumentation',

	data() {
		return {
			selectedChapter: 'introduction',
		}
	},

	computed: {
		chapters() {
			return buildOfficeSimulationDocumentation(t).chapters
		},
		pipelineStages() {
			return [
				t('empleados', 'Data'),
				t('empleados', 'Entity state'),
				t('empleados', 'Rules'),
				t('empleados', 'Physics'),
				t('empleados', 'Interaction'),
				t('empleados', 'Rendering'),
			]
		},
	},

	methods: {
		t,
		scrollToChapter(id) {
			this.selectedChapter = id
			const chapter = this.$el.querySelector(`[data-chapter="${id}"]`)
			if (!chapter) {
				return
			}
			const content = this.$refs.content
			const top = content.scrollTop
				+ chapter.getBoundingClientRect().top
				- content.getBoundingClientRect().top
			content.scrollTo({ top, behavior: 'smooth' })
		},
		onContentScroll() {
			const contentTop = this.$refs.content.getBoundingClientRect().top
			let nearest = this.chapters[0]?.id
			let nearestDistance = Number.POSITIVE_INFINITY

			this.$el.querySelectorAll('[data-chapter]').forEach((chapter) => {
				const distance = Math.abs(chapter.getBoundingClientRect().top - contentTop - 12)
				if (distance < nearestDistance) {
					nearestDistance = distance
					nearest = chapter.dataset.chapter
				}
			})
			this.selectedChapter = nearest
		},
	},
}
</script>

<style scoped lang="scss">
.office-doc {
	display: grid;
	grid-template-rows: auto minmax(0, 1fr);
	box-sizing: border-box;
	width: 100%;
	height: 100%;
	min-width: 0;
	min-height: 0;
	overflow: hidden;
}

.office-doc__header {
	display: flex;
	gap: 20px;
	align-items: flex-end;
	justify-content: space-between;
	padding: 8px 22px 18px;
	border-bottom: 1px solid var(--color-border);
}

.office-doc__eyebrow {
	margin: 0 0 5px;
	color: var(--color-primary-element);
	font-size: 0.75rem;
	font-weight: 700;
	letter-spacing: 0.08em;
	text-transform: uppercase;
}

.office-doc__header h2 {
	margin: 0;
	font-size: clamp(1.45rem, 3vw, 2rem);
}

.office-doc__description {
	margin: 6px 0 0;
	max-width: 76ch;
	color: var(--color-text-maxcontrast);
	line-height: 1.45;
}

.office-doc__global-warning {
	display: flex;
	gap: 7px;
	align-items: baseline;
	margin-top: 10px;
	padding: 8px 11px;
	border-inline-start: 4px solid var(--color-warning);
	border-radius: 0 9px 9px 0;
	background: var(--color-warning-hover, var(--color-background-hover));
	font-size: 0.8rem;
	line-height: 1.35;
}

.office-doc__global-warning strong {
	flex: 0 0 auto;
}

.office-doc__mobile-nav {
	display: none;
}

.office-doc__layout {
	display: grid;
	grid-template-columns: 230px minmax(0, 1fr);
	min-width: 0;
	min-height: 0;
}

.office-doc__sidebar {
	display: flex;
	flex-direction: column;
	gap: 3px;
	padding: 16px 10px;
	overflow-y: auto;
	border-inline-end: 1px solid var(--color-border);
}

.office-doc__nav-button {
	display: grid;
	grid-template-columns: 26px minmax(0, 1fr);
	gap: 7px;
	align-items: center;
	width: 100%;
	min-height: 36px;
	padding: 6px 9px;
	border: 0;
	border-radius: 9px;
	background: transparent;
	color: var(--color-main-text);
	font-size: 0.82rem;
	line-height: 1.2;
	text-align: start;
	cursor: pointer;
}

.office-doc__nav-button span {
	color: var(--color-text-maxcontrast);
	font-variant-numeric: tabular-nums;
}

.office-doc__nav-button:hover,
.office-doc__nav-button:focus-visible {
	background: var(--color-background-hover);
}

.office-doc__nav-button--active {
	background: var(--color-primary-element-light);
	color: var(--color-primary-element);
	font-weight: 700;
}

.office-doc__content {
	min-width: 0;
	min-height: 0;
	padding: 0 26px 50px;
	overflow-x: hidden;
	overflow-y: auto;
	scroll-behavior: smooth;
	scroll-padding-top: 20px;
}

.office-doc__content:focus {
	outline: none;
}

.office-doc__chapter {
	max-width: 980px;
	margin: 0 auto;
	padding: 34px 0 42px;
	border-bottom: 1px solid var(--color-border);
	scroll-margin-top: 16px;
}

.office-doc__chapter:last-child {
	border-bottom: 0;
}

.office-doc__chapter-header {
	display: grid;
	grid-template-columns: 54px minmax(0, 1fr);
	gap: 14px;
	align-items: start;
}

.office-doc__chapter-header > span {
	display: grid;
	place-items: center;
	width: 46px;
	height: 46px;
	border-radius: 14px;
	background: var(--color-primary-element-light);
	color: var(--color-primary-element);
	font-weight: 800;
}

.office-doc__chapter-header h3 {
	margin: 0;
	font-size: clamp(1.3rem, 2.5vw, 1.75rem);
}

.office-doc__chapter-summary {
	margin: 7px 0 0;
	color: var(--color-text-maxcontrast);
	font-size: 0.96rem;
	line-height: 1.55;
}

.office-doc__warning {
	margin: 22px 0;
	padding: 15px 17px;
	border: 2px solid var(--color-warning);
	border-radius: 13px;
	background: var(--color-warning-hover, var(--color-background-hover));
}

.office-doc__warning strong {
	display: block;
	margin-bottom: 4px;
}

.office-doc__warning-copy {
	margin: 0;
	line-height: 1.45;
}

.office-doc__sections {
	display: grid;
	gap: 9px;
	margin-top: 22px;
}

.office-doc__sections details {
	border: 1px solid var(--color-border);
	border-radius: 12px;
	background: var(--color-main-background);
}

.office-doc__sections summary {
	padding: 13px 15px;
	font-weight: 700;
	cursor: pointer;
}

.office-doc__details-copy {
	padding: 0 16px 16px;
	color: var(--color-main-text);
	line-height: 1.55;
}

.office-doc__paragraph {
	margin: 0;
}

.office-doc__details-copy ul {
	margin: 0;
	padding-inline-start: 22px;
}

.office-doc__details-copy li + li {
	margin-top: 5px;
}

.office-doc__terms {
	display: grid;
	gap: 10px;
	margin: 0;
}

.office-doc__terms > div {
	display: grid;
	grid-template-columns: minmax(130px, 0.28fr) minmax(0, 1fr);
	gap: 14px;
	padding-bottom: 9px;
	border-bottom: 1px solid var(--color-border);
}

.office-doc__terms > div:last-child {
	padding-bottom: 0;
	border-bottom: 0;
}

.office-doc__terms dt,
.office-doc__terms dd {
	margin: 0;
}

.office-doc__terms code {
	overflow-wrap: anywhere;
	color: var(--color-primary-element);
	font-weight: 700;
}

.office-doc-visual {
	box-sizing: border-box;
	width: 100%;
	margin: 22px 0;
	padding: 18px;
	border: 1px solid var(--color-border);
	border-radius: 16px;
	background: var(--color-background-hover);
}

.office-doc-visual--emergence {
	display: grid;
	grid-template-columns: minmax(120px, 1fr) auto minmax(120px, 1fr);
	gap: 18px;
	align-items: center;
}

.office-doc-visual__field {
	position: relative;
	height: 120px;
	border: 1px dashed var(--color-border-maxcontrast);
	border-radius: 13px;
	background: var(--color-main-background);
}

.office-doc-visual__field i {
	position: absolute;
	width: 15px;
	height: 15px;
	border-radius: 50%;
	background: var(--color-primary-element);
	box-shadow: 0 0 0 4px var(--color-primary-element-light);
}

.office-doc-visual__field--scattered i:nth-child(1) { top: 18%; left: 12%; }
.office-doc-visual__field--scattered i:nth-child(2) { top: 63%; left: 18%; }
.office-doc-visual__field--scattered i:nth-child(3) { top: 38%; left: 39%; }
.office-doc-visual__field--scattered i:nth-child(4) { top: 72%; left: 55%; }
.office-doc-visual__field--scattered i:nth-child(5) { top: 12%; left: 68%; }
.office-doc-visual__field--scattered i:nth-child(6) { top: 50%; left: 82%; }
.office-doc-visual__field--scattered i:nth-child(7) { top: 75%; left: 86%; }
.office-doc-visual__field--pattern i:nth-child(1) { top: 20%; left: 35%; }
.office-doc-visual__field--pattern i:nth-child(2) { top: 39%; left: 26%; }
.office-doc-visual__field--pattern i:nth-child(3) { top: 57%; left: 36%; }
.office-doc-visual__field--pattern i:nth-child(4) { top: 35%; left: 49%; }
.office-doc-visual__field--pattern i:nth-child(5) { top: 54%; left: 61%; }
.office-doc-visual__field--pattern i:nth-child(6) { top: 26%; left: 69%; }
.office-doc-visual__field--pattern i:nth-child(7) { top: 66%; left: 72%; }

.office-doc-visual__rule {
	display: grid;
	place-items: center;
	color: var(--color-primary-element);
	font-size: 0.78rem;
	font-weight: 700;
}

.office-doc-visual__rule b {
	font-size: 1.8rem;
}

.office-doc-visual--state {
	position: relative;
	display: grid;
	grid-template-columns: repeat(4, minmax(100px, 1fr));
	gap: 44px;
	align-items: center;
	min-height: 160px;
}

.office-doc-visual--state svg {
	position: absolute;
	inset: 0;
	width: 100%;
	height: 100%;
	pointer-events: none;
}

.office-doc-visual--state path {
	fill: none;
	stroke: var(--color-border-maxcontrast);
	stroke-width: 2;
	marker-end: url(#office-state-arrow);
}

.office-doc-visual--state marker path {
	fill: var(--color-border-maxcontrast);
	stroke: none;
}

.office-doc-state {
	z-index: 1;
	display: grid;
	place-items: center;
	min-height: 64px;
	padding: 8px;
	border: 1px solid var(--color-border);
	border-radius: 12px;
	background: var(--color-main-background);
	font-size: 0.72rem;
	font-weight: 700;
	text-align: center;
}

.office-doc-state--input {
	border-color: var(--color-primary-element);
	color: var(--color-primary-element);
}

.office-doc-visual--pipeline {
	display: flex;
	gap: 8px;
	align-items: center;
	overflow-x: auto;
}

.office-doc-pipeline__stage {
	display: grid;
	place-items: center;
	flex: 1 0 102px;
	min-height: 58px;
	padding: 7px;
	border: 1px solid var(--color-primary-element);
	border-radius: 11px;
	background: var(--color-main-background);
	font-size: 0.77rem;
	font-weight: 700;
	text-align: center;
}

.office-doc-visual--pipeline b {
	color: var(--color-primary-element);
}

@media (max-width: 840px) {
	.office-doc__header {
		display: grid;
		padding: 8px 12px 14px;
	}

	.office-doc__mobile-nav {
		display: grid;
		gap: 5px;
		font-size: 0.78rem;
		font-weight: 700;
	}

	.office-doc__mobile-nav select {
		box-sizing: border-box;
		width: 100%;
		min-width: 0;
		padding: 8px 10px;
		border: 1px solid var(--color-border);
		border-radius: 9px;
		background: var(--color-main-background);
		color: var(--color-main-text);
	}

	.office-doc__layout {
		grid-template-columns: minmax(0, 1fr);
	}

	.office-doc__sidebar {
		display: none;
	}

	.office-doc__content {
		padding: 0 14px 40px;
	}

	.office-doc__chapter {
		padding: 26px 0 34px;
	}

	.office-doc__chapter-header {
		grid-template-columns: 42px minmax(0, 1fr);
		gap: 10px;
	}

	.office-doc__chapter-header > span {
		width: 38px;
		height: 38px;
		border-radius: 11px;
	}

	.office-doc-visual--state {
		grid-template-columns: repeat(2, minmax(0, 1fr));
		gap: 10px;
	}

	.office-doc-visual--state svg {
		display: none;
	}

	.office-doc-visual--pipeline {
		flex-direction: column;
		overflow-x: visible;
	}

	.office-doc-pipeline__stage {
		flex: none;
		box-sizing: border-box;
		width: 100%;
		min-height: 44px;
	}

	.office-doc-visual--pipeline b {
		transform: rotate(90deg);
	}
}

@media (max-width: 520px) {
	.office-doc__global-warning {
		display: grid;
	}

	.office-doc__header h2 {
		font-size: 1.35rem;
	}

	.office-doc__description {
		font-size: 0.86rem;
	}

	.office-doc__chapter-header {
		grid-template-columns: 1fr;
	}

	.office-doc__chapter-header > span {
		width: auto;
		height: auto;
		place-self: start;
		padding: 3px 8px;
		border-radius: 999px;
	}

	.office-doc__terms > div {
		grid-template-columns: 1fr;
		gap: 4px;
	}

	.office-doc-visual--emergence {
		grid-template-columns: 1fr;
		gap: 7px;
	}

	.office-doc-visual__rule {
		grid-auto-flow: column;
		justify-content: center;
		gap: 8px;
	}

	.office-doc-visual__rule b {
		transform: rotate(90deg);
	}
}

@media (prefers-reduced-motion: reduce) {
	.office-doc__content {
		scroll-behavior: auto;
	}
}
</style>
