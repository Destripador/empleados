import assert from 'node:assert/strict'
import { readFile } from 'node:fs/promises'

const row = await readFile(new URL('../../src/views/components/Helpers/Lists/ReportRow.vue', import.meta.url), 'utf8')

assert.match(row, /this\.source\?\.origen === 'soporte_ti'/)
assert.match(row, /if \(this\.isSupportReport\) return false/)
assert.match(row, /v-if="isSupportReport" class="origin-badge"/)
assert.match(row, /v-if="isSupportReport && source\.id_equipo"/)
assert.match(row, /query: \{ deviceId: String\(this\.source\.id_equipo\) \}/)
