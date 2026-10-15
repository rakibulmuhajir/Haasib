import { registerSlot } from '@/extensions/slots'
import DirectDeliveryOption from './components/DirectDeliveryOption.vue'

registerSlot('invoice.form.options', { module: 'fuel_station', component: DirectDeliveryOption })
