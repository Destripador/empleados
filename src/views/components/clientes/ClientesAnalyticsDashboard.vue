<template>
	<div class="clientes-analytics-dashboard">
		<div v-if="loading" class="dashboard-state" role="status">
			<NcLoadingIcon :size="40" />
			<span>{{ t('empleados', 'Loading customers dashboard...') }}</span>
		</div>

		<div v-else-if="error" class="dashboard-state dashboard-state--empty">
			<strong>{{ t('empleados', 'The customers dashboard could not be loaded.') }}</strong>
			<span>{{ error }}</span>
		</div>

		<div v-else-if="!hasCatalog" class="dashboard-state dashboard-state--empty">
			<strong>{{ t('empleados', 'No customers for this selection.') }}</strong>
			<span>{{ t('empleados', 'Try another period or change the customer filters.') }}</span>
		</div>

		<div v-else class="dashboard-content">
			<section class="dashboard-section" aria-labelledby="clientes-kpis-heading">
				<header class="section-heading">
					<div>
						<h2 id="clientes-kpis-heading">
							{{ t('empleados', 'Customer overview') }}
						</h2>
					</div>
				</header>

				<div class="compliance-grid">
					<article v-for="item in catalogCards"
						:key="item.key"
						class="compliance-card"
						:class="item.status">
						<div class="compliance-card__heading">
							<div class="compliance-card__title">
								<h3>{{ item.label }}</h3>
								<p>{{ item.hint }}</p>
							</div>
							<strong class="compliance-card__percent">{{ item.value }}</strong>
						</div>
					</article>
				</div>
			</section>

			<section class="dashboard-section" aria-labelledby="clientes-fees-heading">
				<header class="section-heading">
					<div>
						<p class="section-eyebrow">
							{{ t('empleados', 'Fees') }}
						</p>
						<h2 id="clientes-fees-heading">
							{{ t('empleados', 'Outstanding fees') }}
						</h2>
					</div>
					<label v-if="currencyOptions.length > 1" class="compliance-mode">
						<span class="visually-hidden">{{ t('empleados', 'Currency') }}</span>
						<select v-model="selectedCurrency" class="compliance-mode__select">
							<option v-for="option in currencyOptions" :key="option" :value="option">
								{{ option }}
							</option>
						</select>
					</label>
				</header>

				<div v-if="activeCurrency" class="compliance-grid">
					<article class="compliance-card status-neutral">
						<div class="compliance-card__heading">
							<div class="compliance-card__title">
								<h3>{{ t('empleados', 'Total fees') }}</h3>
								<p>{{ activeCurrency.moneda }}</p>
							</div>
							<strong class="compliance-card__percent">{{ formatMoney(activeCurrency.total, activeCurrency.moneda) }}</strong>
						</div>
						<p class="compliance-card__hours">
							<span>{{ t('empleados', 'Generated installments') }}</span>
						</p>
					</article>

					<article class="compliance-card status-complete">
						<div class="compliance-card__heading">
							<div class="compliance-card__title">
								<h3>{{ t('empleados', 'Collected') }}</h3>
								<p>{{ activeCurrency.moneda }}</p>
							</div>
							<strong class="compliance-card__percent">{{ formatMoney(activeCurrency.pagado, activeCurrency.moneda) }}</strong>
						</div>
						<div class="progress-track"
							role="progressbar"
							:aria-valuenow="collectedPercent"
							aria-valuemin="0"
							aria-valuemax="100">
							<div class="progress-value" :style="{ width: `${collectedPercent}%` }" />
						</div>
					</article>

					<article class="compliance-card status-warning">
						<div class="compliance-card__heading">
							<div class="compliance-card__title">
								<h3>{{ t('empleados', 'Outstanding fees') }}</h3>
								<p>{{ activeCurrency.moneda }}</p>
							</div>
							<strong class="compliance-card__percent">{{ formatMoney(activeCurrency.pendiente, activeCurrency.moneda) }}</strong>
						</div>
						<div class="progress-track"
							role="progressbar"
							:aria-valuenow="pendingPercent"
							aria-valuemin="0"
							aria-valuemax="100">
							<div class="progress-value" :style="{ width: `${pendingPercent}%` }" />
						</div>
						<div class="compliance-card__footer">
							<span>{{ t('empleados', 'Pending share') }}: {{ formatPercent(activeCurrency.porcentaje_pendiente) }}</span>
							<span>{{ t('empleados', 'Customers with outstanding balance') }}: {{ activeCurrency.clientes_con_pendiente }}</span>
						</div>
					</article>
				</div>

				<div v-else class="inline-state">
					{{ t('empleados', 'No fee installments were found for this selection.') }}
				</div>

				<p class="chart-note">
					{{ t('empleados', 'Outstanding fees are unpaid installments. Overdue receivables are not shown because there is no due date in the current data.') }}
				</p>
			</section>

			<section class="rankings-grid" :aria-label="t('empleados', 'Customer fee rankings')">
				<article class="ranking-card">
					<header class="section-heading section-heading--compact">
						<div>
							<p class="section-eyebrow">
								{{ t('empleados', 'Outstanding fees') }}
							</p>
							<h2>{{ t('empleados', 'Customers with the highest outstanding balance') }}</h2>
						</div>
					</header>

					<ol v-if="pendingRanking.length > 0" class="ranking-list">
						<li
							v-for="(item, index) in pendingRanking"
							:key="item.id"
							class="ranking-item ranking-item--clickable"
							@click="selectClient(item.id)">
							<span class="ranking-position">{{ index + 1 }}</span>
							<ClienteLogo :id="item.id"
								:logo="item.logo"
								size="sm"
								:alt="item.nombre" />
							<div class="ranking-item__content">
								<div class="ranking-item__heading">
									<strong>{{ item.nombre }}</strong>
									<span>{{ formatMoney(item.pendiente) }}</span>
								</div>
								<div class="ranking-track">
									<div class="ranking-value ranking-value--client" :style="{ width: `${item.relativeWidth}%` }" />
								</div>
								<small>{{ t('empleados', 'Collected') }}: {{ formatMoney(item.pagado) }}</small>
							</div>
						</li>
					</ol>
					<div v-else class="inline-state inline-state--compact">
						{{ t('empleados', 'No outstanding fees for this selection.') }}
					</div>
				</article>

				<article class="ranking-card services-card">
					<header class="section-heading section-heading--compact">
						<div>
							<p class="section-eyebrow">
								{{ t('empleados', 'Services') }}
							</p>
							<h2>{{ t('empleados', 'Fees by service type') }}</h2>
						</div>
					</header>

					<div v-if="serviceCurrencyOptions.length > 1 || serviceYearOptions.length > 0"
						class="service-toolbar">
						<div v-if="serviceCurrencyOptions.length > 1"
							class="currency-tabs"
							role="tablist"
							:aria-label="t('empleados', 'Currency')">
							<button v-for="option in serviceCurrencyOptions"
								:key="option"
								type="button"
								role="tab"
								class="currency-tab"
								:class="{ 'currency-tab--active': option === activeServiceCurrencyCode }"
								:aria-selected="String(option === activeServiceCurrencyCode)"
								@click="selectedServiceCurrency = option">
								{{ option }}
							</button>
						</div>

						<label v-if="serviceYearOptions.length > 0" class="compliance-mode service-toolbar__year">
							<span class="visually-hidden">{{ t('empleados', 'Year') }}</span>
							<select v-model="selectedServiceYear" class="compliance-mode__select">
								<option value="all">
									{{ t('empleados', 'All years') }}
								</option>
								<option v-for="year in serviceYearOptions" :key="year" :value="String(year)">
									{{ year }}
								</option>
							</select>
						</label>
					</div>

					<ul v-if="serviceItemsDisplay.length > 0" class="distribution-list service-list">
						<li v-for="(item, index) in serviceItemsDisplay"
							:key="item.key"
							class="service-item"
							:class="{ 'service-item--open': isServiceOpen(item.key) }">
							<button type="button"
								class="service-toggle"
								:disabled="item.clientes.length === 0"
								:aria-expanded="String(isServiceOpen(item.key))"
								:aria-controls="'service-clients-' + index"
								@click="toggleService(item.key)">
								<ChevronRight :size="18"
									class="service-chevron"
									:class="{
										'service-chevron--open': isServiceOpen(item.key),
										'service-chevron--hidden': item.clientes.length === 0,
									}" />
								<span class="service-toggle__main">
									<span class="service-toggle__title">
										<span class="distribution-dot" :class="{ 'distribution-dot--complete': item.es_fijo, 'distribution-dot--suma': item.es_suma }" />
										<strong>{{ item.servicio }}</strong>
									</span>
									<small v-if="item.clientes.length > 0" class="service-count">
										{{ n('empleados', '%n customer', '%n customers', item.clientes.length) }}
									</small>
								</span>
								<span class="service-toggle__amount">
									{{ formatMoney(item.displayTotal) }}
									<small v-if="activeServiceGroup" class="service-currency">{{ activeServiceGroup.moneda }}</small>
								</span>
							</button>

							<div class="distribution-track">
								<div class="distribution-value"
									:class="{ 'distribution-value--complete': item.es_fijo, 'distribution-value--suma': item.es_suma }"
									:style="{ width: `${serviceTotalSum > 0 ? Math.min(100, (item.displayTotal / serviceTotalSum) * 100) : 0}%` }" />
							</div>

							<div :id="'service-clients-' + index"
								class="service-collapse"
								:class="{ 'service-collapse--open': isServiceOpen(item.key) }">
								<div class="service-collapse__inner">
									<ul class="service-clients">
										<li v-for="cliente in item.clientes" :key="cliente.id">
											<button type="button"
												class="service-client"
												:title="t('empleados', 'View fees for {name}', { name: cliente.nombre })"
												@click="selectClient(cliente.id)">
												<ClienteLogo :id="cliente.id"
													:logo="cliente.logo"
													size="sm"
													:alt="cliente.nombre" />
												<span class="service-client__content">
													<span class="service-client__heading">
														<strong>{{ cliente.nombre }}</strong>
														<span class="service-client__amount">{{ formatMoney(cliente.displayTotal) }}</span>
													</span>
													<span class="service-client__bar">
														<span class="ranking-track service-client__track">
															<span class="ranking-value ranking-value--client"
																:style="{ width: `${sharePercent(cliente.displayTotal, item.displayTotal)}%` }" />
														</span>
														<small class="service-client__percent">
															{{ formatPercent(sharePercent(cliente.displayTotal, item.displayTotal)) }}
														</small>
													</span>
												</span>
											</button>
										</li>
									</ul>
								</div>
							</div>
						</li>
					</ul>
					<div v-else class="inline-state inline-state--compact">
						{{ t('empleados', 'No fees found for this selection.') }}
					</div>
				</article>
			</section>

			<section v-if="evolucion.length > 0" class="dashboard-section" aria-labelledby="clientes-trend-heading">
				<header class="section-heading">
					<div>
						<p class="section-eyebrow">
							{{ t('empleados', 'Trend') }}
						</p>
						<h2 id="clientes-trend-heading">
							{{ t('empleados', 'Generated vs collected by month') }}
						</h2>
					</div>
				</header>

				<ul class="distribution-list">
					<li v-for="item in evolucion" :key="item.mes" class="distribution-item">
						<div class="distribution-item__heading">
							<strong>{{ item.mes }}</strong>
							<span>{{ formatMoney(item.generado) }} / {{ formatMoney(item.cobrado) }}</span>
						</div>
						<div class="distribution-track">
							<div class="distribution-value distribution-value--client" :style="{ width: `${item.generatedWidth}%` }" />
						</div>
						<div class="distribution-track">
							<div class="distribution-value distribution-value--complete" :style="{ width: `${item.collectedWidth}%` }" />
						</div>
					</li>
				</ul>
			</section>

			<section class="dashboard-section" aria-labelledby="clientes-table-heading">
				<header class="section-heading section-heading--with-control">
					<div>
						<p class="section-eyebrow">
							{{ t('empleados', 'Detail') }}
						</p>
						<h2 id="clientes-table-heading">
							{{ t('empleados', 'Fees by customer') }}
						</h2>
					</div>
					<label class="compliance-mode">
						<span class="visually-hidden">{{ t('empleados', 'Sort') }}</span>
						<select v-model="sortKey" class="compliance-mode__select">
							<option value="nombre">
								{{ t('empleados', 'Name (grouped)') }}
							</option>
							<option value="pendiente">
								{{ t('empleados', 'Outstanding balance') }}
							</option>
							<option value="total">
								{{ t('empleados', 'Total fees') }}
							</option>
						</select>
					</label>
				</header>

				<!-- Vista agrupada: Groups / Individual companies, con padres + hijos con sangría -->
				<div v-if="sortKey === 'nombre'" class="employee-grid" role="table">
					<div class="employee-grid__head" role="row">
						<span role="columnheader">{{ t('empleados', 'Customer') }}</span>
						<span role="columnheader">{{ t('empleados', 'Total') }}</span>
						<span role="columnheader">{{ t('empleados', 'Collected') }}</span>
						<span role="columnheader">{{ t('empleados', 'Outstanding') }}</span>
						<span role="columnheader" class="visually-hidden">{{ t('empleados', 'Details') }}</span>
					</div>

					<template v-for="group in groupedSections">
						<div :key="'header-' + group.key"
							class="employee-grid__row employee-grid__row--section"
							role="row"
							@click="toggleSeccion(group.key)">
							<span role="cell" class="employee-section-header">
								<span class="employee-section-icon" :class="{ 'employee-section-icon--open': !group.collapsed }">▸</span>
								{{ group.label }} ({{ group.count }})
							</span>
						</div>

						<div :key="'wrap-' + group.key"
							class="employee-collapse"
							:class="{ 'employee-collapse--open': !group.collapsed }">
							<div class="employee-collapse__inner">
								<template v-for="item in group.items">
									<div :key="item.id"
										class="employee-grid__row employee-grid__row--clickable"
										:class="{ 'employee-grid__row--fx-open': item.hasFx && fxAbierto[item.id] }"
										role="row">
										<span role="cell" class="employee-cell" :style="{ paddingLeft: `${item.level * 1.25}rem` }">
											<button v-if="item.hasFx"
												type="button"
												class="fx-toggle"
												:class="{ 'fx-toggle--open': fxAbierto[item.id] }"
												:aria-expanded="String(!!fxAbierto[item.id])"
												:aria-label="t('empleados', 'Show currency conversion breakdown')"
												@click.stop="toggleFx(item.id)">
												<ChevronRight :size="16" />
											</button>
											<span v-else-if="item.level > 0" class="employee-indent-marker">›</span>
											<strong>{{ item.nombre }}</strong>
										</span>
										<span role="cell" class="employee-metric">
											<span v-for="m in item.montos" :key="m.moneda" class="money-line">
												{{ formatMoney(m.total) }}<small v-if="isForeignCurrency(m.moneda)" class="currency-tag">({{ m.moneda }})</small>
											</span>
											<span v-if="!item.montos.length" class="money-line">0.00</span>
										</span>
										<span role="cell" class="employee-metric">
											<span v-for="m in item.montos" :key="m.moneda" class="money-line">
												{{ formatMoney(m.pagado) }}<small v-if="isForeignCurrency(m.moneda)" class="currency-tag">({{ m.moneda }})</small>
											</span>
											<span v-if="!item.montos.length" class="money-line">0.00</span>
										</span>
										<span role="cell" class="employee-metric">
											<span v-for="m in item.montos" :key="m.moneda" class="money-line">
												{{ formatMoney(m.pendiente) }}<small v-if="isForeignCurrency(m.moneda)" class="currency-tag">({{ m.moneda }})</small>
											</span>
											<span v-if="!item.montos.length" class="money-line">0.00</span>
										</span>
										<span role="cell" class="employee-table__action">
											<NcButton type="secondary" class="details-button" @click="selectClient(item.id)">
												{{ t('empleados', 'Details') }}
											</NcButton>
										</span>
									</div>

									<div v-if="item.hasFx"
										:key="'fx-' + item.id"
										class="fx-collapse"
										:class="{ 'fx-collapse--open': fxAbierto[item.id] }">
										<div class="fx-collapse__inner">
											<div class="fx-panel" :style="{ marginLeft: `${item.level * 1.25}rem` }">
												<section v-for="fx in item.fx" :key="fx.moneda" class="fx-block">
													<header class="fx-block__head">
														<strong>{{ fx.moneda }} → {{ fx.moneda_destino || 'MXN' }}</strong>
														<span>{{ fx.convertidas }} {{ t('empleados', 'converted installments') }}</span>
													</header>

													<div class="fx-stats">
														<div class="fx-stat">
															<span>{{ t('empleados', 'Paid in') }} {{ fx.moneda }}</span>
															<strong>{{ formatMoney(fx.importe_origen, fx.moneda) }}</strong>
														</div>
														<div class="fx-stat">
															<span>{{ t('empleados', 'Received in MXN') }}</span>
															<strong>{{ formatMoney(fx.importe_mxn, 'MXN') }}</strong>
														</div>
														<div class="fx-stat">
															<span>{{ t('empleados', 'Weighted average rate') }}</span>
															<strong>{{ formatRate(fx.tipo_cambio_promedio) }}</strong>
														</div>
														<div v-if="fx.convertidas > 0" class="fx-stat">
															<span>{{ t('empleados', 'Rate range') }}</span>
															<strong>{{ formatRate(fx.tipo_cambio_min) }} – {{ formatRate(fx.tipo_cambio_max) }}</strong>
														</div>
													</div>

													<div v-for="grupo in fx.servicios" :key="grupo.servicio" class="fx-service-group">
														<header class="fx-service-group__head">
															<strong>{{ grupo.servicio }}</strong>
															<span>
																{{ formatMoney(grupo.subtotal_origen, fx.moneda) }} → {{ formatMoney(grupo.subtotal_mxn, 'MXN') }}
																<span v-if="grupo.subtotal_diferencia !== 0" :class="fxDiffClass(grupo.subtotal_diferencia)">
																	({{ formatSignedMoney(grupo.subtotal_diferencia) }})
																</span>
															</span>
														</header>

														<div class="fx-table-wrap">
															<table class="fx-table">
																<thead>
																	<tr>
																		<th>#</th>
																		<th>{{ t('empleados', 'Invoice date') }}</th>
																		<th>{{ t('empleados', 'Payment date') }}</th>
																		<th class="fx-num">
																			{{ fx.moneda }}
																		</th>
																		<th class="fx-num">
																			{{ t('empleados', 'Rate (invoice)') }}
																		</th>
																		<th class="fx-num">
																			{{ t('empleados', 'MXN (invoice)') }}
																		</th>
																		<th class="fx-num">
																			{{ t('empleados', 'Rate (paid)') }}
																		</th>
																		<th class="fx-num">
																			{{ t('empleados', 'MXN (paid)') }}
																		</th>
																		<th class="fx-num">
																			{{ t('empleados', 'Gain/loss') }}
																		</th>
																	</tr>
																</thead>
																<tbody>
																	<tr v-for="p in grupo.parcialidades" :key="p.id_parcialidad">
																		<td>#{{ p.numero }}</td>
																		<td>{{ p.fecha_factura || '-' }}</td>
																		<td>{{ p.fecha_pago || '-' }}</td>
																		<td class="fx-num">
																			{{ formatMoney(p.importe) }}
																		</td>
																		<td class="fx-num">
																			{{ p.tipo_cambio_factura !== null ? formatRate(p.tipo_cambio_factura) : '-' }}
																		</td>
																		<td class="fx-num">
																			{{ p.importe_mxn_factura !== null ? formatMoney(p.importe_mxn_factura) : '-' }}
																		</td>
																		<td class="fx-num">
																			{{ p.tipo_cambio_pago !== null ? formatRate(p.tipo_cambio_pago) : '-' }}
																		</td>
																		<td class="fx-num fx-num--strong">
																			{{ p.importe_mxn_pago !== null ? formatMoney(p.importe_mxn_pago) : '-' }}
																		</td>
																		<td class="fx-num fx-num--strong" :class="fxDiffClass(p.diferencia_cambiaria)">
																			{{ p.diferencia_cambiaria !== null ? formatSignedMoney(p.diferencia_cambiaria) : '-' }}
																		</td>
																	</tr>
																</tbody>
															</table>
														</div>
													</div>

													<p v-if="fx.detalle_truncado" class="fx-note">
														{{ t('empleados', 'Showing the {n} most recent payments.', { n: fx.mostradas }) }}
													</p>
													<p v-if="fx.sin_tipo_cambio > 0" class="fx-note">
														{{ t('empleados', '{n} installment(s) have no exchange rate recorded and are not included in the MXN totals.', { n: fx.sin_tipo_cambio }) }}
													</p>
												</section>
											</div>
										</div>
									</div>
								</template>
							</div>
						</div>
					</template>
				</div>

				<!-- Vista plana: cuando se ordena por Total u Outstanding -->
				<div v-else-if="sortedTable.length > 0" class="employee-table-wrap">
					<table class="employee-table">
						<thead>
							<tr>
								<th>{{ t('empleados', 'Customer') }}</th>
								<th>{{ t('empleados', 'Total') }}</th>
								<th>{{ t('empleados', 'Collected') }}</th>
								<th>{{ t('empleados', 'Outstanding') }}</th>
								<th class="employee-table__action-heading">
									<span class="visually-hidden">{{ t('empleados', 'Details') }}</span>
								</th>
							</tr>
						</thead>
						<tbody>
							<template v-for="row in sortedTable">
								<tr :key="row.id"
									class="employee-row"
									:class="{ 'employee-row--fx-open': row.hasFx && fxAbierto[row.id] }">
									<td :data-label="t('empleados', 'Customer')" class="employee-cell">
										<button v-if="row.hasFx"
											type="button"
											class="fx-toggle"
											:class="{ 'fx-toggle--open': fxAbierto[row.id] }"
											:aria-expanded="String(!!fxAbierto[row.id])"
											:aria-label="t('empleados', 'Show currency conversion breakdown')"
											@click.stop="toggleFx(row.id)">
											<ChevronRight :size="16" />
										</button>
										<strong>{{ row.nombre }}</strong>
									</td>
									<td :data-label="t('empleados', 'Total')" class="employee-metric">
										<span v-for="m in row.montos" :key="m.moneda" class="money-line">
											{{ formatMoney(m.total) }}<small v-if="isForeignCurrency(m.moneda)" class="currency-tag">({{ m.moneda }})</small>
										</span>
										<span v-if="!row.montos.length" class="money-line">0.00</span>
									</td>
									<td :data-label="t('empleados', 'Collected')" class="employee-metric">
										<span v-for="m in row.montos" :key="m.moneda" class="money-line">
											{{ formatMoney(m.pagado) }}<small v-if="isForeignCurrency(m.moneda)" class="currency-tag">({{ m.moneda }})</small>
										</span>
										<span v-if="!row.montos.length" class="money-line">0.00</span>
									</td>
									<td :data-label="t('empleados', 'Outstanding')" class="employee-metric">
										<span v-for="m in row.montos" :key="m.moneda" class="money-line">
											{{ formatMoney(m.pendiente) }}<small v-if="isForeignCurrency(m.moneda)" class="currency-tag">({{ m.moneda }})</small>
										</span>
										<span v-if="!row.montos.length" class="money-line">0.00</span>
									</td>
									<td class="employee-table__action">
										<NcButton type="secondary" class="details-button" @click="selectClient(row.id)">
											{{ t('empleados', 'Details') }}
										</NcButton>
									</td>
								</tr>

								<tr v-if="row.hasFx" :key="'fx-' + row.id" class="fx-row">
									<td colspan="5" class="fx-row__cell">
										<div class="fx-collapse" :class="{ 'fx-collapse--open': fxAbierto[row.id] }">
											<div class="fx-collapse__inner">
												<div class="fx-panel">
													<section v-for="fx in item.fx" :key="fx.moneda" class="fx-block">
														<header class="fx-block__head">
															<strong>{{ fx.moneda }} → {{ fx.moneda_destino || 'MXN' }}</strong>
															<span>{{ fx.convertidas }} {{ t('empleados', 'converted installments') }}</span>
														</header>

														<div class="fx-stats">
															<div class="fx-stat">
																<span>{{ t('empleados', 'Paid in') }} {{ fx.moneda }}</span>
																<strong>{{ formatMoney(fx.importe_origen, fx.moneda) }}</strong>
															</div>
															<div class="fx-stat">
																<span>{{ t('empleados', 'Received in MXN') }}</span>
																<strong>{{ formatMoney(fx.importe_mxn, 'MXN') }}</strong>
															</div>
															<div class="fx-stat">
																<span>{{ t('empleados', 'Weighted average rate') }}</span>
																<strong>{{ formatRate(fx.tipo_cambio_promedio) }}</strong>
															</div>
															<div v-if="fx.convertidas > 0" class="fx-stat">
																<span>{{ t('empleados', 'Rate range') }}</span>
																<strong>{{ formatRate(fx.tipo_cambio_min) }} – {{ formatRate(fx.tipo_cambio_max) }}</strong>
															</div>
															<div class="fx-stat fx-stat--diff" :class="fxDiffClass(fx.ganancia_cambiaria)">
																<span>{{ t('empleados', 'Exchange gain/loss') }}</span>
																<strong>{{ formatSignedMoney(fx.ganancia_cambiaria) }}</strong>
															</div>
														</div>

														<div v-for="grupo in fx.servicios" :key="grupo.servicio" class="fx-service-group">
															<header class="fx-service-group__head">
																<strong>{{ grupo.servicio }}</strong>
																<span>
																	{{ formatMoney(grupo.subtotal_origen, fx.moneda) }} → {{ formatMoney(grupo.subtotal_mxn, 'MXN') }}
																	<span v-if="grupo.subtotal_diferencia !== 0" :class="fxDiffClass(grupo.subtotal_diferencia)">
																		({{ formatSignedMoney(grupo.subtotal_diferencia) }})
																	</span>
																</span>
															</header>

															<div class="fx-table-wrap">
																<table class="fx-table">
																	<thead>
																		<tr>
																			<th>#</th>
																			<th>{{ t('empleados', 'Invoice date') }}</th>
																			<th>{{ t('empleados', 'Payment date') }}</th>
																			<th class="fx-num">
																				{{ fx.moneda }}
																			</th>
																			<th class="fx-num">
																				{{ t('empleados', 'Rate (invoice)') }}
																			</th>
																			<th class="fx-num">
																				{{ t('empleados', 'MXN (invoice)') }}
																			</th>
																			<th class="fx-num">
																				{{ t('empleados', 'Rate (paid)') }}
																			</th>
																			<th class="fx-num">
																				{{ t('empleados', 'MXN (paid)') }}
																			</th>
																			<th class="fx-num">
																				{{ t('empleados', 'Gain/loss') }}
																			</th>
																		</tr>
																	</thead>
																	<tbody>
																		<tr v-for="p in grupo.parcialidades" :key="p.id_parcialidad">
																			<td>#{{ p.numero }}</td>
																			<td>{{ p.fecha_factura || '-' }}</td>
																			<td>{{ p.fecha_pago || '-' }}</td>
																			<td class="fx-num">
																				{{ formatMoney(p.importe) }}
																			</td>
																			<td class="fx-num">
																				{{ p.tipo_cambio_factura !== null ? formatRate(p.tipo_cambio_factura) : '-' }}
																			</td>
																			<td class="fx-num">
																				{{ p.importe_mxn_factura !== null ? formatMoney(p.importe_mxn_factura) : '-' }}
																			</td>
																			<td class="fx-num">
																				{{ p.tipo_cambio_pago !== null ? formatRate(p.tipo_cambio_pago) : '-' }}
																			</td>
																			<td class="fx-num fx-num--strong">
																				{{ p.importe_mxn_pago !== null ? formatMoney(p.importe_mxn_pago) : '-' }}
																			</td>
																			<td class="fx-num fx-num--strong" :class="fxDiffClass(p.diferencia_cambiaria)">
																				{{ p.diferencia_cambiaria !== null ? formatSignedMoney(p.diferencia_cambiaria) : '-' }}
																			</td>
																		</tr>
																	</tbody>
																</table>
															</div>
														</div>

														<p v-if="fx.detalle_truncado" class="fx-note">
															{{ t('empleados', 'Showing the {n} most recent payments.', { n: fx.mostradas }) }}
														</p>
														<p v-if="fx.sin_tipo_cambio > 0" class="fx-note">
															{{ t('empleados', '{n} installment(s) have no exchange rate recorded and are not included in the MXN totals.', { n: fx.sin_tipo_cambio }) }}
														</p>
													</section>
												</div>
											</div>
										</div>
									</td>
								</tr>
							</template>
						</tbody>
					</table>
				</div>

				<div v-else class="inline-state">
					{{ t('empleados', 'No customers match the current filters.') }}
				</div>
			</section>
		</div>
	</div>
</template>

<script>
import { NcButton, NcLoadingIcon } from '@nextcloud/vue'
import { translate as t, translatePlural as n } from '@nextcloud/l10n'
import ChevronRight from 'vue-material-design-icons/ChevronRight.vue'
import ClienteLogo from '../../../components/clientes/ClienteLogo.vue'

export default {
	name: 'ClientesAnalyticsDashboard',

	components: {
		NcButton,
		NcLoadingIcon,
		ChevronRight,
		ClienteLogo,
	},

	props: {
		resumen: {
			type: Object,
			default: () => ({}),
		},
		loading: {
			type: Boolean,
			default: false,
		},
		error: {
			type: String,
			default: '',
		},
	},

	emits: ['select-client'],

	data() {
		return {
			selectedCurrency: '',
			selectedServiceYear: 'all',
			sortKey: 'nombre',
			seccionesColapsadas: {
				grupos: false,
				individuales: false,
			},
			fxAbierto: {},
			serviceOpen: {},
			selectedServiceCurrency: 'MXN',
		}
	},

	computed: {
		hasCatalog() {
			return Number(this.resumen?.catalogo?.total || 0) > 0
		},

		catalog() {
			return this.resumen?.catalogo || {}
		},

		catalogCards() {
			return [
				{
					key: 'total',
					label: t('empleados', 'Total records'),
					hint: t('empleados', 'Companies and groups'),
					value: this.catalog.total || 0,
					status: 'status-neutral',
				},
				{
					key: 'activos',
					label: t('empleados', 'Active'),
					hint: t('empleados', 'Enabled customers'),
					value: this.catalog.activos || 0,
					status: 'status-complete',
				},
				{
					key: 'grupos',
					label: t('empleados', 'Main groups'),
					hint: t('empleados', 'Parent companies'),
					value: this.catalog.grupos || 0,
					status: 'status-neutral',
				},
				{
					key: 'sub',
					label: t('empleados', 'Sub-companies'),
					hint: t('empleados', 'Linked companies'),
					value: this.catalog.subempresas || 0,
					status: 'status-neutral',
				},
			]
		},

		monedas() {
			return Array.isArray(this.resumen?.monedas) ? this.resumen.monedas : []
		},

		currencyOptions() {
			return this.monedas.map((row) => row.moneda).filter(Boolean)
		},

		activeCurrency() {
			if (this.monedas.length === 0) {
				return null
			}
			return this.monedas.find((row) => row.moneda === this.selectedCurrency) || this.monedas[0]
		},

		servicios() {
			return Array.isArray(this.resumen?.servicios) ? this.resumen.servicios : []
		},

		serviceCurrencyOptions() {
			return this.servicios
				.map((group) => group.moneda)
				.filter(Boolean)
				.sort((a, b) => {
					if (a === 'MXN') return -1
					if (b === 'MXN') return 1
					return a.localeCompare(b)
				})
		},

		activeServiceCurrencyCode() {
			return this.serviceCurrencyOptions.includes(this.selectedServiceCurrency)
				? this.selectedServiceCurrency
				: (this.serviceCurrencyOptions[0] || '')
		},

		activeServiceGroup() {
			return this.servicios.find((group) => group.moneda === this.activeServiceCurrencyCode) || null
		},

		serviceYearOptions() {
			const years = new Set()
			;(this.activeServiceGroup?.items || []).forEach((item) => {
				(item.anios || []).forEach((a) => {
					if (a.anio) years.add(a.anio)
				})
			})
			return Array.from(years).sort((a, b) => b - a)
		},

		serviceItemsDisplay() {
			const items = this.activeServiceGroup?.items || []
			const year = this.selectedServiceYear

			const withTotals = items
				.map((item) => {
					let total = Number(item.total || 0)
					if (year !== 'all') {
						const found = (item.anios || []).find((a) => String(a.anio) === String(year))
						total = found ? Number(found.total || 0) : 0
					}
					return {
						...item,
						key: item.servicio,
						displayTotal: total,
						clientes: this.buildServiceClients(item.clientes, year),
					}
				})
				.filter((item) => year === 'all' || item.displayTotal > 0)

			const auditoriaNames = ['Auditoria Fiscal y Financiera', 'Auditoria Financiera', 'Auditoria Fiscal']
			const auditoriaItems = withTotals.filter((item) => auditoriaNames.includes(item.servicio))

			if (auditoriaItems.length === 0) {
				return withTotals
			}

			const auditoriaSum = auditoriaItems.reduce((sum, item) => sum + Number(item.displayTotal || 0), 0)

			// Une los clientes de las 3 auditorías en un solo desglose
			const merged = new Map()
			auditoriaItems.forEach((item) => {
				item.clientes.forEach((c) => {
					const cur = merged.get(c.id) || { ...c, displayTotal: 0 }
					cur.displayTotal += c.displayTotal
					merged.set(c.id, cur)
				})
			})
			const sumaClientes = Array.from(merged.values()).sort((a, b) => b.displayTotal - a.displayTotal)

			return [
				{
					key: '__suma__',
					servicio: t('empleados', 'Suma Auditoria'),
					displayTotal: auditoriaSum,
					es_suma: true,
					clientes: sumaClientes,
				},
				...withTotals,
			]
		},

		serviceTotalSum() {
			return this.serviceItemsDisplay
				.filter((item) => !item.es_suma)
				.reduce((sum, item) => sum + Number(item.displayTotal || 0), 0)
		},

		pendingPercent() {
			return this.clampPercentage(this.activeCurrency?.porcentaje_pendiente || 0)
		},

		collectedPercent() {
			const total = Number(this.activeCurrency?.total || 0)
			if (total <= 0) {
				return 0
			}
			return this.clampPercentage((Number(this.activeCurrency?.pagado || 0) / total) * 100)
		},

		pendingRanking() {
			const rows = Array.isArray(this.resumen?.ranking_pendiente) ? this.resumen.ranking_pendiente : []
			const max = Math.max(...rows.map((row) => Number(row.pendiente || 0)), 0)
			return rows.map((row) => ({
				...row,
				relativeWidth: max > 0 ? Math.max(6, (Number(row.pendiente || 0) / max) * 100) : 0,
			}))
		},

		evolucion() {
			const rows = Array.isArray(this.resumen?.evolucion) ? this.resumen.evolucion : []
			const max = Math.max(...rows.flatMap((row) => [Number(row.generado || 0), Number(row.cobrado || 0)]), 0)
			return rows.map((row) => ({
				...row,
				generatedWidth: max > 0 ? (Number(row.generado || 0) / max) * 100 : 0,
				collectedWidth: max > 0 ? (Number(row.cobrado || 0) / max) * 100 : 0,
			}))
		},

		sortedTable() {
			const rows = (this.resumen?.tabla || []).map((row) => ({
				...row,
				montos: this.sortMontos(this.rowMontos(row)),
				fx: Array.isArray(row.fx) ? row.fx : [],
				hasFx: Array.isArray(row.fx) && row.fx.length > 0,
			}))
			const key = this.sortKey
			rows.sort((a, b) => {
				if (key === 'nombre') {
					return String(a.nombre || '').localeCompare(String(b.nombre || ''), undefined, { sensitivity: 'base' })
				}
				return Number(b[key] || 0) - Number(a[key] || 0)
			})
			return rows
		},

		groupedSections() {
			const rows = [...(this.resumen?.tabla || [])]
			const idsInSet = new Set(rows.map((r) => Number(r.id)))
			const byParent = new Map()
			const byId = new Map(rows.map((r) => [Number(r.id), r]))

			rows.forEach((item) => {
				const rawParent = Number(item.cliente_padre || 0)
				const parentId = idsInSet.has(rawParent) ? rawParent : 0
				if (!byParent.has(parentId)) byParent.set(parentId, [])
				byParent.get(parentId).push(item)
			})

			const compareNames = (a, b) =>
				String(a.nombre || '').localeCompare(String(b.nombre || ''), undefined, { sensitivity: 'base' })

			const addMontos = (target, montos) => {
				montos.forEach((m) => {
					const cur = target.get(m.moneda) || { moneda: m.moneda, total: 0, pagado: 0, pendiente: 0 }
					cur.total += m.total
					cur.pagado += m.pagado
					cur.pendiente += m.pendiente
					target.set(m.moneda, cur)
				})
			}

			const sumSubtree = (id) => {
				const acc = new Map()
				const visit = (nodeId) => {
					const own = byId.get(Number(nodeId))
					if (own) addMontos(acc, this.rowMontos(own))
					;(byParent.get(Number(nodeId)) || []).forEach((child) => visit(child.id))
				}
				visit(id)
				return this.sortMontos(Array.from(acc.values()))
			}

			const buildRow = (item, level) => ({
				...item,
				montos: sumSubtree(item.id),
				fx: Array.isArray(item.fx) ? item.fx : [],
				hasFx: Array.isArray(item.fx) && item.fx.length > 0,
				level,
			})

			const flatten = (parentId, level) => {
				const children = (byParent.get(parentId) || []).slice().sort(compareNames)
				const out = []
				children.forEach((child) => {
					out.push(buildRow(child, level))
					out.push(...flatten(Number(child.id), level + 1))
				})
				return out
			}

			const roots = byParent.get(0) || []
			const gruposRoots = roots.filter((r) => (byParent.get(Number(r.id)) || []).length > 0)
			const individualesRoots = roots.filter((r) => (byParent.get(Number(r.id)) || []).length === 0)

			gruposRoots.sort(compareNames)
			individualesRoots.sort(compareNames)

			const gruposItems = gruposRoots.flatMap((root) => [
				buildRow(root, 0),
				...flatten(Number(root.id), 1),
			])

			const individualesItems = individualesRoots.map((root) => buildRow(root, 0))

			return [
				{
					key: 'grupos',
					label: t('empleados', 'Groups'),
					count: gruposRoots.length,
					collapsed: this.seccionesColapsadas.grupos,
					items: this.seccionesColapsadas.grupos ? [] : gruposItems,
				},
				{
					key: 'individuales',
					label: t('empleados', 'Individual companies'),
					count: individualesRoots.length,
					collapsed: this.seccionesColapsadas.individuales,
					items: this.seccionesColapsadas.individuales ? [] : individualesItems,
				},
			]
		},
	},

	watch: {
		currencyOptions: {
			immediate: true,
			handler(options) {
				if (!options.includes(this.selectedCurrency)) {
					this.selectedCurrency = options[0] || ''
				}
			},
		},
		serviceYearOptions(options) {
			if (this.selectedServiceYear !== 'all' && !options.map(String).includes(this.selectedServiceYear)) {
				this.selectedServiceYear = 'all'
			}
		},
	},

	methods: {
		t,
		n,

		isForeignCurrency(moneda) {
			return String(moneda || 'MXN').toUpperCase() !== 'MXN'
		},

		toggleFx(id) {
			this.fxAbierto = {
				...this.fxAbierto,
				[id]: !this.fxAbierto[id],
			}
		},

		formatRate(value) {
			if (value === null || value === undefined) {
				return '—'
			}
			return Number(value).toLocaleString('es-MX', {
				minimumFractionDigits: 4,
				maximumFractionDigits: 4,
			})
		},

		formatSignedMoney(value) {
			const n = Number(value || 0)
			const sign = n > 0.004 ? '+' : ''
			return `${sign}${this.formatMoney(n)}`
		},

		fxDiffClass(value) {
			const n = Number(value || 0)
			if (n > 0.004) return 'fx-diff--gain'
			if (n < -0.004) return 'fx-diff--loss'
			return 'fx-diff--neutral'
		},

		rowMontos(row) {
			return Array.isArray(row?.monedas)
				? row.monedas.map((m) => ({
					moneda: String(m.moneda || 'MXN').toUpperCase(),
					total: Number(m.total || 0),
					pagado: Number(m.pagado || 0),
					pendiente: Number(m.pendiente || 0),
				}))
				: []
		},

		sortMontos(montos) {
			return [...montos].sort((a, b) => {
				if (a.moneda === 'MXN') return -1
				if (b.moneda === 'MXN') return 1
				return a.moneda.localeCompare(b.moneda)
			})
		},

		selectClient(id) {
			const clientId = Number(id)
			if (!Number.isFinite(clientId) || clientId <= 0) {
				return
			}
			this.$emit('select-client', clientId)
		},

		formatMoney(value, currency = '') {
			const amount = Number(value || 0).toLocaleString('es-MX', {
				minimumFractionDigits: 2,
				maximumFractionDigits: 2,
			})
			return currency ? `${amount} ${currency}` : amount
		},

		formatPercent(value) {
			return `${Number(value || 0).toLocaleString('es-MX', {
				minimumFractionDigits: 1,
				maximumFractionDigits: 1,
			})}%`
		},

		clampPercentage(value) {
			const number = Number(value || 0)
			if (Number.isNaN(number) || number < 0) {
				return 0
			}
			return Math.min(100, number)
		},

		toggleSeccion(key) {
			this.seccionesColapsadas = {
				...this.seccionesColapsadas,
				[key]: !this.seccionesColapsadas[key],
			}
		},

		isServiceOpen(key) {
			return !!this.serviceOpen[key]
		},

		toggleService(key) {
			this.serviceOpen = {
				...this.serviceOpen,
				[key]: !this.serviceOpen[key],
			}
		},

		buildServiceClients(clientes, year) {
			return (Array.isArray(clientes) ? clientes : [])
				.map((c) => {
					let total = Number(c.total || 0)
					if (year !== 'all') {
						const found = (c.anios || []).find((a) => String(a.anio) === String(year))
						total = found ? Number(found.total || 0) : 0
					}
					return { id: c.id, nombre: c.nombre, logo: c.logo, displayTotal: total }
				})
				.filter((c) => c.displayTotal > 0)
				.sort((a, b) => b.displayTotal - a.displayTotal)
		},

		sharePercent(part, whole) {
			const w = Number(whole || 0)
			return w > 0 ? this.clampPercentage((Number(part || 0) / w) * 100) : 0
		},
	},
}
</script>

<style scoped>
.clientes-analytics-dashboard {
	--dash-radius: var(--border-radius-large);
	--dash-gap: 1.25rem;
	--dash-card-padding: 1.125rem;
	width: 100%;
	box-sizing: border-box;
	color: var(--color-main-text);
}

.clientes-analytics-dashboard,
.clientes-analytics-dashboard *,
.clientes-analytics-dashboard *::before,
.clientes-analytics-dashboard *::after {
	box-sizing: border-box;
}

.dashboard-content {
	display: grid;
	gap: var(--dash-gap);
}

.dashboard-state,
.dashboard-section,
.ranking-card {
	min-width: 0;
	border: 1px solid var(--color-border);
	border-radius: var(--dash-radius);
	background: var(--color-main-background);
}

.dashboard-state {
	display: flex;
	align-items: center;
	justify-content: center;
	gap: 0.75rem;
	min-height: 11rem;
	padding: 1.5rem;
	color: var(--color-text-maxcontrast);
	text-align: center;
}

.dashboard-state--empty {
	flex-direction: column;
}

.dashboard-section,
.ranking-card {
	padding: var(--dash-card-padding);
}

.section-heading {
	display: flex;
	flex-wrap: wrap;
	align-items: flex-start;
	justify-content: space-between;
	gap: 0.75rem 1rem;
	margin-bottom: 1rem;
}

.section-heading--compact,
.section-heading--with-control {
	margin-bottom: 0.75rem;
}

.section-heading h2,
.section-heading p {
	margin: 0;
}

.section-heading h2 {
	font-size: clamp(1rem, 0.85rem + 0.4vw, 1.2rem);
	line-height: 1.3;
}

.section-eyebrow {
	margin: 0 0 0.25rem !important;
	color: var(--color-text-maxcontrast);
	font-size: 0.72rem;
	font-weight: 700;
	letter-spacing: 0.04em;
	text-transform: uppercase;
}

.compliance-grid,
.rankings-grid {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(15rem, 1fr));
	gap: 0.875rem;
}

.rankings-grid {
	grid-template-columns: repeat(auto-fit, minmax(min(100%, 26rem), 1fr));
}

.compliance-card,
.ranking-card {
	display: flex;
	flex-direction: column;
	gap: 0.75rem;
	min-width: 0;
}

.compliance-card {
	padding: 1.125rem;
	border: 1px solid var(--color-border);
	border-left-width: 4px;
	border-radius: var(--dash-radius);
	background: var(--color-background-hover);
}

.status-complete { border-left-color: var(--color-success); }
.status-warning { border-left-color: var(--color-warning); }
.status-neutral { border-left-color: var(--color-text-maxcontrast); }

.compliance-card__heading {
	display: flex;
	align-items: flex-start;
	justify-content: space-between;
	gap: 0.75rem;
}

.compliance-card__heading h3 {
	margin: 0;
	font-size: 0.92rem;
}

.compliance-card__percent {
	font-size: clamp(1rem, 0.9rem + 0.4vw, 1.3rem);
	line-height: 1.1;
	text-align: right;
}

.compliance-card__hours,
.compliance-card__footer,
.chart-note,
.inline-state {
	color: var(--color-text-maxcontrast);
	font-size: 0.82rem;
}

.compliance-card__footer {
	display: flex;
	flex-wrap: wrap;
	justify-content: space-between;
	gap: 0.4rem 0.75rem;
}

.progress-track,
.distribution-track,
.ranking-track {
	overflow: hidden;
	height: 0.5rem;
	border-radius: 999px;
	background: var(--color-background-darker);
}

.progress-value,
.distribution-value,
.ranking-value {
	height: 100%;
	border-radius: inherit;
	background: var(--color-primary-element);
}

.status-complete .progress-value,
.distribution-value--complete {
	background: var(--color-success);
}

.status-warning .progress-value {
	background: var(--color-warning);
}

.ranking-list,
.distribution-list {
	display: flex;
	flex-direction: column;
	gap: 0.65rem;
	margin: 0;
	padding: 0;
	list-style: none;
}

.ranking-item {
	display: grid;
	grid-template-columns: 1.5rem auto minmax(0, 1fr);
	gap: 0.6rem;
	align-items: center;
}

.ranking-item--clickable {
	cursor: pointer;
	padding: 0.25rem;
	border-radius: var(--border-radius);
}

.ranking-item--clickable:hover {
	background: var(--color-background-hover);
}

.ranking-item__heading {
	display: flex;
	justify-content: space-between;
	gap: 0.5rem;
	font-size: 0.85rem;
}

.ranking-position {
	color: var(--color-text-maxcontrast);
	font-weight: 700;
}

.distribution-item__heading {
	display: flex;
	justify-content: space-between;
	gap: 0.75rem;
	margin-bottom: 0.35rem;
	font-size: 0.85rem;
}

.distribution-item__label {
	display: flex;
	align-items: center;
	gap: 0.4rem;
}

.distribution-dot {
	width: 0.55rem;
	height: 0.55rem;
	border-radius: 50%;
	background: var(--color-primary-element);
}

.distribution-dot--suma,
.distribution-value--suma {
	background: #5347fc;
}

.distribution-dot--complete,
.distribution-value--complete {
	background: #ff9100;
}

.compliance-mode__select {
	min-width: 9.375rem;
	height: 2rem;
	padding: 0 0.5rem;
	border: 1px solid var(--color-border);
	border-radius: var(--border-radius);
	background: var(--color-main-background);
	color: var(--color-main-text);
}

.visually-hidden {
	position: absolute;
	width: 1px;
	height: 1px;
	overflow: hidden;
	clip: rect(0 0 0 0);
}

/* ── Vista plana (tabla real, orden por Total / Outstanding) ── */
.employee-table-wrap {
	overflow: auto;
}

.employee-table {
	width: 100%;
	border-collapse: collapse;
}

.employee-table th,
.employee-table td {
	padding: 0.65rem 0.5rem;
	border-bottom: 1px solid var(--color-border);
	text-align: left;
	font-size: 0.85rem;
}

.employee-metric {
	font-variant-numeric: tabular-nums;
	white-space: nowrap;
}

.employee-cell {
	display: flex;
	align-items: center;
}

@media (max-width: 720px) {
	.employee-table-wrap {
		overflow: visible;
	}

	.employee-table {
		display: block;
		width: 100%;
		border-collapse: separate;
		border-spacing: 0;
	}

	.employee-table thead {
		display: none;
	}

	.employee-table tbody {
		display: flex;
		flex-direction: column;
		gap: 0.7rem;
	}

	.employee-row {
		display: flex !important;
		align-items: center;
		justify-content: space-between;
		gap: 0.85rem;
		padding: 1rem 1.1rem;
		border: 1px solid var(--color-border);
		border-radius: 14px;
		background: var(--color-background-hover);
		transition: border-color 0.15s ease, transform 0.15s ease;
	}

	.employee-row:hover {
		border-color: var(--color-primary-element);
	}

	.employee-table td {
		padding: 0;
		border: 0;
	}

	.employee-table td.employee-metric,
	.employee-table th:not(:first-child):not(.employee-table__action-heading) {
		display: none !important;
	}

	.employee-cell strong {
		display: block;
		font-size: 1rem;
		font-weight: 600;
		letter-spacing: 0.01em;
		white-space: nowrap;
		overflow: hidden;
		text-overflow: ellipsis;
	}

	.employee-table__action {
		flex: 0 0 auto;
	}

	.employee-table__action :deep(button) {
		white-space: nowrap;
		font-weight: 600;
		color: #000000;
	}
}

/* ── Vista agrupada: Groups / Individual companies con jerarquía ── */
.employee-grid {
	display: flex;
	flex-direction: column;
}

.employee-grid__head,
.employee-grid__row {
	display: grid;
	grid-template-columns: minmax(0, 2fr) 1fr 1fr 1fr auto;
	align-items: center;
	gap: 0.5rem;
	padding: 0.65rem 0.5rem;
	border-bottom: 1px solid var(--color-border);
	font-size: 0.85rem;
}

.employee-grid__head {
	color: var(--color-text-maxcontrast);
	font-weight: 600;
}

.employee-grid__row--section {
	cursor: pointer;
	background: var(--color-background-hover);
	grid-template-columns: 1fr;
}

.employee-grid__row--section:hover {
	background: var(--color-background-darker);
}

.employee-section-header {
	display: flex;
	align-items: center;
	gap: 0.4rem;
	font-size: 0.78rem;
	font-weight: 700;
	letter-spacing: 0.03em;
	text-transform: uppercase;
	color: var(--color-text-maxcontrast);
}

.employee-section-icon {
	display: inline-block;
	transition: transform 0.2s ease;
}

.employee-section-icon--open {
	transform: rotate(90deg);
}

.employee-indent-marker {
	display: inline-block;
	margin-right: 0.3rem;
	color: var(--color-text-maxcontrast);
}

/* Transición limpia al colapsar/expandir, sin medir alturas en JS */
.employee-collapse {
	display: grid;
	grid-template-rows: 0fr;
	transition: grid-template-rows 0.25s ease;
}

.employee-collapse--open {
	grid-template-rows: 1fr;
}

.employee-collapse__inner {
	overflow: hidden;
	min-height: 0;
}

@media (max-width: 720px) {
	.employee-grid__head {
		display: none;
	}

	.employee-grid__row--clickable {
		grid-template-columns: 1fr auto;
		border: 1px solid var(--color-border);
		border-radius: 14px;
		background: var(--color-background-hover);
		margin-bottom: 0.5rem;
		padding: 1rem 1.1rem;
	}

	.employee-grid__row--clickable [role='cell'].employee-metric {
		display: none;
	}
}

.currency-tag {
	margin-left: 0.25rem;
	font-size: 0.68rem;
	font-weight: 600;
	color: var(--color-text-maxcontrast);
}

.details-button {
	background-color: #000000 !important;
	color: #ffffff !important;
	font-weight: 500;
}

.details-button:hover:not(:disabled) {
	background-color: #464545 !important;
}

.money-line {
	display: block;
}

.fx-toggle {
	display: inline-flex;
	align-items: center;
	justify-content: center;
	flex: 0 0 auto;
	width: 1.375rem;
	height: 1.375rem;
	margin-right: 0.35rem;
	padding: 0;
	border: none;
	border-radius: 50%;
	background: transparent;
	color: var(--color-text-maxcontrast);
	cursor: pointer;
	transition: transform 0.25s cubic-bezier(0.4, 0, 0.2, 1),
		background-color 0.15s ease, color 0.15s ease;
}

.fx-toggle:hover {
	background: var(--color-background-darker);
	color: var(--color-primary-element);
}

.fx-toggle--open {
	transform: rotate(90deg);
	color: var(--color-primary-element);
}

.employee-grid__row--fx-open,
.employee-row--fx-open {
	background: var(--color-background-hover);
}

.fx-row td {
	padding: 0 !important;
	border-bottom: none !important;
}

.fx-row__cell {
	border-bottom: 1px solid var(--color-border) !important;
}

/* Misma técnica de 0fr → 1fr que ya usas para las secciones */
.fx-collapse {
	display: grid;
	grid-template-rows: 0fr;
	opacity: 0;
	transition: grid-template-rows 0.28s cubic-bezier(0.4, 0, 0.2, 1),
		opacity 0.2s ease;
}

.fx-collapse--open {
	grid-template-rows: 1fr;
	opacity: 1;
}

.fx-collapse__inner {
	overflow: hidden;
	min-height: 0;
}

.fx-panel {
	display: flex;
	flex-direction: column;
	gap: 0.75rem;
	margin: 0.25rem 0 0.6rem;
	padding: 0.85rem 1rem;
	border: 1px solid var(--color-border);
	border-left: 3px solid var(--color-primary-element);
	border-radius: 12px;
	background: var(--color-background-hover);
}

.fx-block__head {
	display: flex;
	flex-wrap: wrap;
	align-items: baseline;
	justify-content: space-between;
	gap: 0.5rem;
	margin-bottom: 0.6rem;
	font-size: 0.85rem;
}

.fx-block__head span {
	color: var(--color-text-maxcontrast);
	font-size: 0.75rem;
}

.fx-service-group {
	margin-bottom: 0.75rem;
}

.fx-service-group:last-child {
	margin-bottom: 0;
}

.fx-service-group__head {
	display: flex;
	flex-wrap: wrap;
	align-items: baseline;
	justify-content: space-between;
	gap: 0.5rem;
	margin-bottom: 0.35rem;
	font-size: 0.8rem;
}

.fx-service-group__head strong {
	color: var(--color-main-text);
}

.fx-service-group__head span {
	color: var(--color-text-maxcontrast);
	font-size: 0.72rem;
	font-variant-numeric: tabular-nums;
}

.fx-stats {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(9.5rem, 1fr));
	gap: 0.5rem;
	margin-bottom: 0.7rem;
}

.fx-stat {
	display: flex;
	flex-direction: column;
	gap: 0.15rem;
	padding: 0.5rem 0.65rem;
	border: 1px solid var(--color-border);
	border-radius: 10px;
	background: var(--color-main-background);
}

.fx-stat span {
	color: var(--color-text-maxcontrast);
	font-size: 0.7rem;
	text-transform: uppercase;
	letter-spacing: 0.03em;
}

.fx-stat strong {
	font-size: 0.9rem;
	font-variant-numeric: tabular-nums;
}

.fx-table-wrap {
	overflow-x: auto;
}

.fx-table {
	width: 100%;
	border-collapse: collapse;
	font-size: 0.78rem;
}

.fx-table th,
.fx-table td {
	padding: 0.35rem 0.5rem;
	border-bottom: 1px solid var(--color-border);
	text-align: left;
	white-space: nowrap;
}

.fx-table th {
	color: var(--color-text-maxcontrast);
	font-weight: 600;
}

.fx-table tr:last-child td {
	border-bottom: none;
}

.fx-num {
	text-align: right !important;
	font-variant-numeric: tabular-nums;
}

.fx-num--strong {
	font-weight: 600;
}

.fx-note {
	margin: 0.5rem 0 0;
	color: var(--color-text-maxcontrast);
	font-size: 0.75rem;
}

@media (max-width: 720px) {
	.fx-panel {
		margin-left: 0 !important;
	}
}

.services-card {
	container-type: inline-size;
	container-name: services;
}

.service-toolbar {
	display: flex;
	flex-wrap: wrap;
	align-items: center;
	justify-content: space-between;
	gap: 0.5rem 0.75rem;
}

.service-toolbar__year {
	margin-left: auto;
}

.service-toolbar .compliance-mode__select {
	min-width: 8rem;
	margin: 0;
}

.currency-tabs {
	display: inline-flex;
	align-items: center;
	gap: 2px;
	width: fit-content;
	padding: 3px;
	border-radius: var(--border-radius-large);
	background: var(--color-background-dark);
}

.currency-tab {
	display: inline-flex;
	align-items: center;
	justify-content: center;
	min-width: 3rem;
	min-height: 0;
	height: 1.75rem;
	margin: 0;
	padding: 0 0.75rem;
	border: none;
	border-radius: var(--border-radius);
	background: transparent;
	box-shadow: none;
	color: var(--color-text-maxcontrast);
	font: inherit;
	font-size: 0.78rem;
	font-weight: 600;
	letter-spacing: 0.04em;
	line-height: 1;
	cursor: pointer;
	transition: background-color 0.15s ease, color 0.15s ease;
}

.currency-tab:hover {
	background: var(--color-background-darker);
	color: var(--color-main-text);
}

.currency-tab--active,
.currency-tab--active:hover {
	background: var(--color-primary-element);
	color: var(--color-primary-element-text);
}

.currency-tab:focus-visible {
	outline: 2px solid var(--color-primary-element);
	outline-offset: 2px;
}

.service-list {
	gap: 0.35rem;
}

.service-item {
	padding: 0.75rem 0.85rem;
	border: 1px solid transparent;
	border-radius: var(--border-radius-large);
	transition: background-color 0.15s ease, border-color 0.15s ease;
}

.service-item:hover {
	background: var(--color-background-hover);
}

.service-item--open {
	border-color: var(--color-border);
	background: var(--color-background-hover);
}

.service-toggle {
	display: grid;
	grid-template-columns: 1.25rem minmax(0, 1fr) auto;
	column-gap: 0.5rem;
	align-items: start;
	width: 100%;
	min-height: 0;
	margin: 0 0 0.55rem;
	padding: 0;
	border: none;
	border-radius: var(--border-radius);
	background: transparent;
	box-shadow: none;
	color: inherit;
	font: inherit;
	font-size: 0.88rem;
	text-align: left;
	cursor: pointer;
}

.service-toggle:hover,
.service-toggle:active {
	background: transparent;
}

.service-toggle:disabled {
	color: inherit;
	opacity: 1;
	cursor: default;
}

.service-toggle:focus-visible {
	outline: 2px solid var(--color-primary-element);
	outline-offset: 2px;
}

.service-chevron--open {
	transform: rotate(90deg);
	color: var(--color-primary-element);
}

.service-chevron {
	display: inline-flex;
	align-items: center;
	justify-content: center;
	width: 1.25rem;
	height: 1.4rem;
	color: var(--color-text-maxcontrast);
	transition: transform 0.25s cubic-bezier(0.4, 0, 0.2, 1), color 0.15s ease;
}

.service-toggle:not(:disabled):hover .service-chevron {
	color: var(--color-primary-element);
}

.service-chevron--hidden {
	visibility: hidden;
}

.service-toggle__main {
	display: flex;
	flex-direction: column;
	gap: 0.15rem;
	min-width: 0;
}

.service-toggle__title {
	display: flex;
	align-items: flex-start;
	gap: 0.45rem;
	min-width: 0;
}

.service-toggle__title strong {
	font-weight: 600;
	line-height: 1.4;
	overflow-wrap: anywhere;
}

.service-toggle__title .distribution-dot {
	flex: 0 0 auto;
	margin-top: 0.42rem;
}

.service-count {
	padding-left: 1rem;
	color: var(--color-text-maxcontrast);
	font-size: 0.72rem;
	white-space: nowrap;
}

.service-toggle__amount {
	font-weight: 600;
	font-variant-numeric: tabular-nums;
	line-height: 1.4;
	text-align: right;
	white-space: nowrap;
}

.service-currency {
	margin-left: 0.25rem;
	color: var(--color-text-maxcontrast);
	font-size: 0.68rem;
	font-weight: 600;
	letter-spacing: 0.03em;
}

.service-collapse {
	display: grid;
	grid-template-rows: 0fr;
	visibility: hidden;
	transition: grid-template-rows 0.25s ease, visibility 0s linear 0.25s;
}

.service-collapse--open {
	grid-template-rows: 1fr;
	visibility: visible;
	transition: grid-template-rows 0.25s ease, visibility 0s;
}

.service-collapse__inner {
	overflow: hidden;
	min-height: 0;
}

.service-clients {
	display: flex;
	flex-direction: column;
	gap: 0.15rem;
	margin: 0.75rem 0 0 0.6rem;
	padding: 0 0 0 0.75rem;
	list-style: none;
	border-left: 2px solid var(--color-border);
}

.service-client {
	display: grid;
	grid-template-columns: auto minmax(0, 1fr);
	gap: 0.65rem;
	align-items: center;
	width: 100%;
	min-height: 0;
	margin: 0;
	padding: 0.45rem 0.5rem;
	border: none;
	border-radius: var(--border-radius);
	background: transparent;
	box-shadow: none;
	color: inherit;
	font: inherit;
	font-size: 0.82rem;
	text-align: left;
	cursor: pointer;
}

.service-client:hover,
.service-client:focus-visible {
	background: var(--color-background-dark);
}

.service-client:focus-visible {
	outline: 2px solid var(--color-primary-element);
}

.service-client__content {
	display: flex;
	flex-direction: column;
	gap: 0.35rem;
	min-width: 0;
}

.service-client__heading {
	display: flex;
	align-items: baseline;
	justify-content: space-between;
	gap: 0.75rem;
	min-width: 0;
}

.service-client__heading strong {
	min-width: 0;
	overflow: hidden;
	font-weight: 600;
	text-overflow: ellipsis;
	white-space: nowrap;
}

.service-client__amount {
	flex: 0 0 auto;
	font-variant-numeric: tabular-nums;
}

.service-client__bar {
	display: flex;
	align-items: center;
	gap: 0.6rem;
}

.service-client__track {
	display: block;
	flex: 1 1 auto;
	min-width: 0;
}

.service-client__track .ranking-value {
	display: block;
}

.service-client__percent {
	flex: 0 0 auto;
	min-width: 2.9rem;
	color: var(--color-text-maxcontrast);
	font-size: 0.72rem;
	font-variant-numeric: tabular-nums;
	text-align: right;
}

@container services (max-width: 24rem) {
	.service-item {
		padding: 0.65rem 0.55rem;
	}

	.service-clients {
		margin-left: 0.3rem;
		padding-left: 0.5rem;
	}

	.service-client {
		gap: 0.5rem;
		padding: 0.4rem 0.3rem;
	}
}

@container services (max-width: 19rem) {
	.service-toggle {
		grid-template-columns: 1.25rem minmax(0, 1fr);
	}

	.service-toggle__amount {
		grid-column: 2;
		margin-top: 0.15rem;
		text-align: left;
	}
}

@media (prefers-reduced-motion: reduce) {
	.service-collapse,
	.service-chevron,
	.service-item {
		transition: none;
	}
}

.fx-diff--gain {
	color: #005f08;
}

.fx-diff--loss {
	color: #770000;
}

.fx-diff--neutral {
	color: #000000;
}

.fx-stat--diff strong {
	font-size: 0.9rem;
}
</style>
