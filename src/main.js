import Vue from 'vue'
import App from './views/App.vue'

import router from './router/index.js'
import Router from 'vue-router'

Vue.use(Router)
Vue.mixin({ methods: { t, n } })

const View = Vue.extend(App)

new View({ router }).$mount('#content')
