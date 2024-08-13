import Vue from 'vue'
import App from './views/App.vue'

import router from './router/index.js'
import Router from 'vue-router'

Vue.use(Router)
Vue.mixin({ methods: { t, n } })

// Obtener los parámetros desde el DOM
const configuraciones = document.getElementById('data').getAttribute('data-parameters')
// const parameterss = scriptElement ? JSON.parse(scriptElement.textContent) : {}

// eslint-disable-next-line no-console
console.log('DATAS: ', configuraciones)
const View = Vue.extend(App)

new View({
	router,
	propsData: {
		parameters: configuraciones,
	},
}).$mount('#content')
