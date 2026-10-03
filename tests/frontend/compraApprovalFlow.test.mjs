import assert from 'node:assert/strict'
import { readFile } from 'node:fs/promises'

const source = await readFile(new URL('../../src/utils/compraApprovalFlow.js', import.meta.url), 'utf8')
const moduleUrl = `data:text/javascript;base64,${Buffer.from(source).toString('base64')}`
const {
	canApprovePurchaseFlow,
	canRejectPurchaseFlow,
	createSingleFlight,
} = await import(moduleUrl)

assert.equal(canApprovePurchaseFlow({ can_approve: true }), true)
assert.equal(canApprovePurchaseFlow({ can_approve: false, aprobador_actual: { id_autorizador: 'actual' } }), false)
assert.equal(canApprovePurchaseFlow({ can_approve: 1 }), false)
assert.equal(canRejectPurchaseFlow({ can_reject: true }), true)
assert.equal(canRejectPurchaseFlow(null), false)

const guard = createSingleFlight()
let releaseFirstAction
let calls = 0
const firstAction = guard.run(async () => {
	calls += 1
	await new Promise((resolve) => {
		releaseFirstAction = resolve
	})
})

const duplicateStarted = await guard.run(async () => {
	calls += 1
})

assert.equal(duplicateStarted, false)
assert.equal(calls, 1)
assert.equal(guard.isPending(), true)

releaseFirstAction()
assert.equal(await firstAction, true)
assert.equal(guard.isPending(), false)

assert.equal(await guard.run(async () => {
	calls += 1
}), true)
assert.equal(calls, 2)
