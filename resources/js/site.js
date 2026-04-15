import Alpine from 'alpinejs'
import collapse from '@alpinejs/collapse'
import focus from '@alpinejs/focus'
import morph from '@alpinejs/morph'
import persist from '@alpinejs/persist'
import precognition from 'laravel-precognition-alpine';
import bookingModal from './booking-modal.js'

// Register Alpine components on window (accessed via x-data="bookingModal(...)")
window.bookingModal = bookingModal

// Call Alpine.
window.Alpine = Alpine
Alpine.plugin([collapse, focus, morph, persist, precognition])
Alpine.start()
