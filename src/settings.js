import Vue from 'vue'
import App from './views/Settings/Settings.vue'

import mitt from 'mitt'

Vue.mixin({ methods: { t, n } })

const View = Vue.extend(App)

const emitter = mitt()
Vue.prototype.$bus = emitter

new View().$mount('#admin')
