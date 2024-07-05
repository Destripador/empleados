import Vue from 'vue'
import App from './views/navigator/Sidenavigation.vue'
Vue.mixin({ methods: { t, n } })

const View = Vue.extend(App)
new View().$mount('#navigator')
