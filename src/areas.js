import Vue from 'vue'
import App from './views/Areas.vue'
Vue.mixin({ methods: { t, n } })

const View = Vue.extend(App)
new View().$mount('#content')
