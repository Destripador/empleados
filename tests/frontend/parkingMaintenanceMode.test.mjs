import assert from 'node:assert/strict'
import { readFile } from 'node:fs/promises'

const routes = await readFile(new URL('../../appinfo/routes.php', import.meta.url), 'utf8')
const service = await readFile(new URL('../../lib/Service/ParkingModeService.php', import.meta.url), 'utf8')
const parkingController = await readFile(new URL('../../lib/Controller/EstacionamientoController.php', import.meta.url), 'utf8')
const spaceController = await readFile(new URL('../../lib/Controller/EspacioController.php', import.meta.url), 'utf8')
const publicView = await readFile(new URL('../../src/views/components/Estacionamiento/Estacionamiento.vue', import.meta.url), 'utf8')
const settingsView = await readFile(new URL('../../src/views/Settings/EstacionamientoSettings.vue', import.meta.url), 'utf8')
const banner = await readFile(new URL('../../src/views/components/Estacionamiento/ParkingMaintenanceBanner.vue', import.meta.url), 'utf8')
const notice = await readFile(new URL('../../src/views/components/Estacionamiento/ParkingMaintenanceNotice.vue', import.meta.url), 'utf8')
const spanish = JSON.parse(await readFile(new URL('../../l10n/es.json', import.meta.url), 'utf8')).translations

assert.match(routes, /Estacionamiento#GetParkingStatus[^\n]+\/espacios\/status[^\n]+GET/)
assert.match(routes, /Estacionamiento#ActivateMaintenance[^\n]+\/espacios\/maintenance[^\n]+POST/)
assert.match(routes, /Estacionamiento#PublishParking[^\n]+\/espacios\/publish[^\n]+POST/)

assert.match(service, /getAppValue\(/)
assert.match(service, /setAppValue\(/)
assert.match(service, /parking_mode/)
assert.match(service, /MODE_OPERATIONAL/)
assert.match(service, /MODE_MAINTENANCE/)
assert.match(service, /recordActivity\('parking_maintenance_activated'/)
assert.match(service, /recordActivity\('parking_published'/)
assert.doesNotMatch(service, /localStorage|sessionStorage|CREATE TABLE|addTable/i)

const assignmentGuard = parkingController.indexOf('public function GetEmpleadosConEspacio')
const assignmentMapper = parkingController.indexOf('getEspacioEmpleado()', assignmentGuard)
const assignmentBlock = parkingController.indexOf('sensitiveDataBlockedResponse()', assignmentGuard)
assert.ok(assignmentGuard >= 0 && assignmentBlock > assignmentGuard && assignmentBlock < assignmentMapper)

const mapGuard = spaceController.indexOf('canViewSensitiveData')
const mapMapper = spaceController.indexOf('findAllOrdered')
assert.ok(mapGuard >= 0 && mapGuard < mapMapper)

const initialStatus = publicView.indexOf('await this.loadParkingStatus()')
const initialData = publicView.indexOf('await this.prepareMap()', initialStatus)
assert.ok(initialStatus >= 0 && initialData > initialStatus)
assert.match(publicView, /window\.setInterval\(this\.refreshParkingStatus, 30000\)/)
assert.match(publicView, /v-else-if="isMaintenance && !canManage"/)
assert.match(publicView, /<ParkingMaintenanceNotice/)
assert.match(publicView, /<ParkingMaintenanceBanner/)
assert.match(publicView, /class="parking-watermark"/)
assert.match(publicView, /DRAFT · MAINTENANCE/)
assert.match(publicView, /NOT PUBLISHED/)
assert.match(publicView, /\.parking-watermark[\s\S]*pointer-events: none/)
assert.match(publicView, /<NcModal v-if="showPublishModal"/)

assert.match(settingsView, /<NcModal v-if="showActivationModal"/)
assert.match(settingsView, /<NcModal v-if="showPublishModal"/)
assert.match(settingsView, /\/espacios\/maintenance/)
assert.match(settingsView, /\/espacios\/publish/)
assert.match(settingsView, /window\.setInterval\(\(\) => this\.fetchParkingStatus\(false\), 30000\)/)

assert.match(banner, /MAINTENANCE MODE ACTIVE/)
assert.match(notice, /Assignments made during maintenance should not be considered final/)
assert.equal(spanish['DRAFT · MAINTENANCE'], 'BORRADOR · MANTENIMIENTO')
assert.equal(spanish['NOT PUBLISHED'], 'NO PUBLICADO')
assert.equal(spanish['MAINTENANCE MODE ACTIVE'], 'MODO MANTENIMIENTO ACTIVO')

assert.doesNotMatch(`${publicView}\n${settingsView}\n${banner}\n${notice}`, /localStorage|sessionStorage/i)
