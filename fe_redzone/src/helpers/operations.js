export const peso = value => new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(Number(value || 0))
export const businessDate = () => new Intl.DateTimeFormat('en-CA', { timeZone: 'Asia/Manila', year: 'numeric', month: '2-digit', day: '2-digit' }).format(new Date())
export const apiError = error => Object.values(error.response?.data?.errors || {}).flat().join(' ') || error.response?.data?.message || 'Unable to complete the request. Please try again.'
