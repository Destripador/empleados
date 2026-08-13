import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'

const tutorialUrl = (lessonId, suffix = '') => {
	const encoded = encodeURIComponent(lessonId)
	return generateUrl(`/apps/empleados/tutoriales/${encoded}${suffix}`)
}

export async function getTutorialStatus(lessonId) {
	const response = await axios.get(tutorialUrl(lessonId))
	return response.data
}

export async function completeTutorial(lessonId) {
	const response = await axios.post(tutorialUrl(lessonId, '/complete'))
	return response.data
}

export async function resetTutorial(lessonId) {
	const response = await axios.delete(tutorialUrl(lessonId))
	return response.data
}

export async function resetAllTutorials() {
	const response = await axios.post(generateUrl('/apps/empleados/tutoriales/reset-all'))
	return response.data
}
