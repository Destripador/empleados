import Vue from 'vue'
import App from './views/App.vue'

import router from './router/index.js'
import Router from 'vue-router'

Vue.use(Router)
Vue.mixin({ methods: { t, n } })

// Obtener los parámetros desde el DOM
const scriptElement = document.getElementById('data').getAttribute('data-parameters')
// const parameterss = scriptElement ? JSON.parse(scriptElement.textContent) : {}

const View = Vue.extend(App)

// eslint-disable-next-line no-console
console.log('hola ching: ', scriptElement)

new View({
	router,
	propsData: {
		parameters: scriptElement,
	},
}).$mount('#content')
