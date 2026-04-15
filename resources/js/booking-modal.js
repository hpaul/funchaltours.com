import { loadStripe } from '@stripe/stripe-js'

export default function bookingModal(config) {
  return {
    // --- config
    tourSlug: config.tourSlug,
    tourTitle: config.tourTitle,
    basePrice: config.basePrice,
    maxGuests: config.maxGuests,
    blockedDates: new Set(config.blockedDates || []),
    bookedDates: new Set(config.bookedDates || []),
    discounts: config.discounts || { 1: 0, 2: 20, 3: 30, 4: 30 },
    stripeKey: config.stripeKey,
    csrf: config.csrf,

    // --- state
    isOpen: false,
    step: 'date', // date | contact | payment | confirmed
    currentMonth: new Date(new Date().getFullYear(), new Date().getMonth(), 1),
    selectedDate: null,
    guests: 1,
    contact: { name: '', email: '', phone: '', notes: '' },
    errorMessage: '',
    isCreatingSession: false,
    stripe: null,
    checkout: null,

    dayLabels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],

    // --- computed
    get stepLabel() {
      return { date: 'Select date', contact: 'Your details', payment: 'Payment' }[this.step] ?? ''
    },
    get progressPercent() {
      return { date: 33, contact: 66, payment: 100, confirmed: 100 }[this.step] ?? 0
    },
    get currentMonthLabel() {
      return this.currentMonth.toLocaleDateString('en-GB', { month: 'long', year: 'numeric' })
    },
    get canGoPrev() {
      const today = new Date()
      const thisMonth = new Date(today.getFullYear(), today.getMonth(), 1)
      return this.currentMonth > thisMonth
    },
    get calendarCells() {
      const year = this.currentMonth.getFullYear()
      const month = this.currentMonth.getMonth()
      const firstDay = new Date(year, month, 1)
      const lastDay = new Date(year, month + 1, 0)
      // JS Sunday=0; we want Monday-first so shift
      const leadingBlanks = (firstDay.getDay() + 6) % 7
      const cells = []
      for (let i = 0; i < leadingBlanks; i++) {
        cells.push({ key: `b${i}`, inMonth: false, day: '' })
      }
      const today = new Date()
      today.setHours(0, 0, 0, 0)
      for (let day = 1; day <= lastDay.getDate(); day++) {
        const d = new Date(year, month, day)
        const iso = this.formatISO(d)
        const isPast = d < today
        const isBlocked = this.blockedDates.has(iso)
        const isBooked = this.bookedDates.has(iso)
        cells.push({
          key: iso,
          inMonth: true,
          day,
          date: iso,
          selectable: !isPast && !isBlocked && !isBooked,
        })
      }
      return cells
    },
    get pricing() {
      const discount = this.discounts[this.guests] ?? 30
      const subtotal = this.basePrice * this.guests
      const discountAmount = Math.round(subtotal * discount / 100)
      return {
        guests: this.guests,
        subtotal,
        discount_percent: discount,
        discount_amount: discountAmount,
        total: subtotal - discountAmount,
      }
    },
    get formattedSelectedDate() {
      if (!this.selectedDate) return ''
      const [y, m, d] = this.selectedDate.split('-').map(Number)
      return new Date(y, m - 1, d).toLocaleDateString('en-GB', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })
    },
    get canPay() {
      return this.contact.name.trim().length > 1
        && /.+@.+\..+/.test(this.contact.email)
    },

    // --- helpers
    formatISO(d) {
      const y = d.getFullYear()
      const m = String(d.getMonth() + 1).padStart(2, '0')
      const day = String(d.getDate()).padStart(2, '0')
      return `${y}-${m}-${day}`
    },

    // --- actions
    open() { this.isOpen = true },
    close() {
      this.isOpen = false
      // reset after close transition
      setTimeout(() => {
        if (this.checkout) {
          try { this.checkout.destroy() } catch (_) {}
          this.checkout = null
        }
      }, 250)
    },
    prevMonth() {
      if (!this.canGoPrev) return
      this.currentMonth = new Date(this.currentMonth.getFullYear(), this.currentMonth.getMonth() - 1, 1)
    },
    nextMonth() {
      this.currentMonth = new Date(this.currentMonth.getFullYear(), this.currentMonth.getMonth() + 1, 1)
    },
    selectDate(iso) {
      this.selectedDate = iso
    },
    incrementGuests() {
      if (this.guests < this.maxGuests) this.guests++
    },
    decrementGuests() {
      if (this.guests > 1) this.guests--
    },
    goToContact() {
      if (!this.selectedDate) return
      this.step = 'contact'
    },
    async goToPayment() {
      if (!this.canPay) return
      if (this.isCreatingSession) return // prevent double-click race
      this.errorMessage = ''
      this.step = 'payment'
      this.isCreatingSession = true

      try {
        const res = await fetch('/bookings/session', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': this.csrf,
            'Accept': 'application/json',
          },
          body: JSON.stringify({
            tour: this.tourSlug,
            date: this.selectedDate,
            guests: this.guests,
            name: this.contact.name.trim(),
            email: this.contact.email.trim(),
            phone: this.contact.phone.trim(),
            notes: this.contact.notes.trim(),
          }),
        })

        if (!res.ok) {
          const body = await res.json().catch(() => ({}))
          throw new Error(body.message || 'Could not create booking — please try again.')
        }
        const { client_secret } = await res.json()

        if (!this.stripe) this.stripe = await loadStripe(this.stripeKey)

        this.checkout = await this.stripe.createEmbeddedCheckoutPage({
          clientSecret: client_secret,
          onComplete: () => {
            this.step = 'confirmed'
          },
        })

        this.checkout.mount('#stripe-checkout-' + this.tourSlug)
      } catch (e) {
        this.errorMessage = e.message || 'Something went wrong. Please try again.'
        this.step = 'contact'
      } finally {
        this.isCreatingSession = false
      }
    },
  }
}
