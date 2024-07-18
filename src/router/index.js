import Vue from 'vue'
import Router from 'vue-router'
import { generateUrl } from '@nextcloud/router'

Vue.use(Router)

export default new Router({
	mode: 'history',
	// if index.php is in the url AND we got this far, then it's working:
	// let's keep using index.php in the url
	base: generateUrl('/apps/empleados', ''),
	linkActiveClass: 'active',
	routes: [
		{
			path: '/',
			props: true,
			name: 'root',
		},
		{
			path: '/:selectedContact',
			name: 'empleados',
		},
	],
})
