<template id="content">
	<NcAppContent name="Ejemplo – Dashboard">
		<!-- Contenido del ejemplo -->
		<div class="wrap">
			<div class="main-content">
				<div class="parking">
					<!-- Lado izquierdo -->
					<div class="side">
						<div class="slot medium empty" />
						<div class="slot small" />
						<div class="slot small" />
						<div class="slot small" />
						<div class="slot small" />
						<div class="slot medium" />
						<div class="slot medium" />
						<div class="slot medium" />
						<div class="slot medium" />
						<div class="slot medium" />
					</div>

					<!-- Centro -->
					<div class="center">
						<div class="top-grid">
							<div class="slot empty" />
							<div class="slot empty" />
							<div class="slot empty" />
							<div class="slot empty" />
							<div class="slot empty" />
							<div class="slot empty" />
							<div class="slot empty" />
							<div class="slot empty" />
							<div class="slot empty" />
						</div>

						<div class="road">
							<div class="car">
								entrada
							</div>
						</div>
					</div>

					<!-- Lado derecho -->
					<div class="side">
						<div class="slot medium empty" />
						<div class="slot small empty" />
						<div class="slot small" />
						<div class="slot small" />
						<div class="slot small" />
						<div class="slot small" />
						<div class="slot medium" />
						<div class="slot medium" />
						<div class="slot small" />
						<div class="slot medium" />
						<div class="slot medium" />
					</div>
				</div>
			</div>
			<div class="side-navigator">
				Navegador lateral
			</div>
		</div>
	</NcAppContent>
</template>

<script>
// import { showError } from '@nextcloud/dialogs'
import { // generateUrl
	 } from '@nextcloud/router'
// import axios from '@nextcloud/axios'
import {
	NcAppContent, // NcEmptyContent, NcButton, NcNoteCard
} from '@nextcloud/vue'

// public imports
// i18n: usa t('empleados', '...') si tienes appId = 'empleados'
import { t } from '@nextcloud/l10n'

export default {
	name: 'Ejemplo',
	components: {
		NcAppContent, // NcEmptyContent, NcButton, NcNoteCard
	},
	data() {
		return {
			loading: true,
		}
	},
	methods: {
		t,
	},
}
</script>

<style scoped lang="scss">
.ejemplo {
	padding: 24px 32px;
	display: grid;
	grid-template-columns: 1fr;
	gap: 20px;
}

.wrap {
	display: flex;
	flex-direction: row;
	gap: 20px;
}

.main-content {
	flex: 3;
	padding: 24px 32px;
	background: var(--color-background-darker);
	border: 1px solid var(--color-border);
	border-radius: 12px;
}

.side-navigator {
	flex: 1;
	margin-right: 20px; /* Mover este estilo aquí */
	padding: 24px 32px;
	background: var(--color-background-darker);
	border: 1px solid var(--color-border);
	border-radius: 12px;
	margin-top: 20px;
}
    .parking {
      width: 760px;
      margin: 20px auto;
      background: #dcdcdc;
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      gap: 20px;
      padding: 10px;
      box-sizing: border-box;
    }

    .side {
      width: 170px;
      display: flex;
      flex-direction: column;
      gap: 10px;
    }

    .slot {
      background: #efefef;
      border: 1px solid #222;
      box-sizing: border-box;
      display: flex;
      align-items: center;
      justify-content: center;
      text-align: center;
      color: #000;
      font-size: 18px;
      min-height: 78px;
    }

    .slot.small {
      min-height: 70px;
    }

    .slot.medium {
      min-height: 90px;
    }

    .slot.tall {
      min-height: 120px;
    }

    .slot.empty {
      color: red;
      font-size: 22px;
      font-weight: normal;
    }

    .center {
      flex: 1;
      min-height: 1100px;
      display: flex;
      flex-direction: column;
      align-items: center;
    }

    .top-grid {
      display: grid;
      grid-template-columns: repeat(3, 80px);
      grid-template-rows: repeat(3, 140px);
      gap: 4px;
      margin-top: 0;
    }

    .top-grid .slot {
      min-height: auto;
      height: 140px;
      width: 80px;
    }

    .road {
      flex: 1;
      width: 100%;
      position: relative;
    }

    .car {
      position: absolute;
      bottom: 40px;
      left: 50%;
      transform: translateX(-50%);
      font-size: 90px;
      line-height: 1;
    }
</style>
