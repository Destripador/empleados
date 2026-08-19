import assert from 'node:assert/strict'
import { readFile } from 'node:fs/promises'

const service = await readFile(new URL('../../src/services/simulacionOficinaService.js', import.meta.url), 'utf8')
const view = await readFile(new URL('../../src/views/components/SimulacionOficina/SimulacionOficina.vue', import.meta.url), 'utf8')
const intro = await readFile(new URL('../../src/views/components/SimulacionOficina/OfficeSimulationIntro.vue', import.meta.url), 'utf8')
const help = await readFile(new URL('../../src/views/components/SimulacionOficina/OfficeSimulationHelp.vue', import.meta.url), 'utf8')
const documentation = await readFile(new URL('../../src/views/components/SimulacionOficina/OfficeSimulationDocumentation.vue', import.meta.url), 'utf8')
const canvas = await readFile(new URL('../../src/views/components/SimulacionOficina/OfficeSimulationCanvas.vue', import.meta.url), 'utf8')
const contentSource = await readFile(new URL('../../src/utils/officeSimulationDocumentation.js', import.meta.url), 'utf8')
const contentUrl = `data:text/javascript;base64,${Buffer.from(contentSource).toString('base64')}`
const { buildOfficeSimulationDocumentation } = await import(contentUrl)
const content = buildOfficeSimulationDocumentation((app, text) => text)
const steps = content.introSteps

assert.match(service, /\/simulacion-oficina\/onboarding'/)
assert.match(service, /\/simulacion-oficina\/onboarding\/complete'/)
assert.match(service, /\{ version \}/)
assert.doesNotMatch(service, /localStorage|sessionStorage|cookie/i)

assert.match(view, /onboardingState: 'loading'/)
assert.match(view, /onboardingState = status\.completed \? 'completed' : 'required'/)
assert.match(view, /this\.load\(\)[\s\S]*this\.loadOnboardingStatus\(\)/)
assert.match(view, /<OfficeSimulationIntro/)
assert.match(view, /<OfficeSimulationHelp/)
assert.match(view, /@click="openHelp"/)
assert.match(view, /How Office Simulation works/)
assert.match(view, /return this\.paused \|\| this\.showIntro \|\| this\.showHelp/)
assert.match(view, /:paused="simulationPaused"/)
assert.match(view, /console\.error\('Could not load Office Simulation onboarding status'/)

assert.equal(steps.length, 6)
assert.deepEqual(steps.map(step => step.id), ['welcome', 'data', 'emergence', 'states', 'events', 'privacy'])
assert.equal(content.chapters.length, 13)
assert.deepEqual(content.chapters.map(chapter => chapter.id), [
	'introduction',
	'inspiration',
	'principles',
	'employees',
	'spaces',
	'movement',
	'nextcloud',
	'social',
	'events',
	'emergence',
	'architecture',
	'privacy',
	'vision',
])

assert.match(intro, /canClose: this\.manual/)
assert.match(intro, /embedded/)
assert.match(intro, /currentStepIndex -= 1/)
assert.match(intro, /currentStepIndex \+= 1/)
assert.match(intro, /!this\.manual && !this\.accepted/)
assert.match(intro, /if \(this\.manual\) \{[\s\S]*this\.\$emit\('close'\)[\s\S]*return[\s\S]*completeOfficeSimulationOnboarding\(this\.version\)/)
assert.match(intro, /await completeOfficeSimulationOnboarding\(this\.version\)/)
assert.match(intro, /prefers-reduced-motion: reduce/)

assert.match(help, /How it works/)
assert.match(help, /Technical documentation/)
assert.match(help, /<OfficeSimulationIntro/)
assert.match(help, /:manual="true"/)
assert.match(help, /:embedded="true"/)
assert.match(help, /<OfficeSimulationDocumentation/)
assert.match(help, /View introduction again/)
assert.doesNotMatch(help, /completeOfficeSimulationOnboarding|getOfficeSimulationOnboardingStatus/)

assert.match(documentation, /office-doc__sidebar/)
assert.match(documentation, /office-doc__mobile-nav/)
assert.match(documentation, /<details/)
assert.match(documentation, /chapter\.visual === 'emergence'/)
assert.match(documentation, /chapter\.visual === 'state'/)
assert.match(documentation, /chapter\.visual === 'pipeline'/)
assert.doesNotMatch(documentation, /iframe|fetch\(/i)
assert.doesNotMatch(canvas, /onboarding|OfficeSimulationIntro/)
