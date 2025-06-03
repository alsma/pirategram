import { create } from 'zustand'

import { randomString } from '@/lib/utils'

export const ToastType = {
  Success: 'success',
  Info: 'info',
  Error: 'error',
}

const ToastTypeToVariant = {
  [ToastType.Success]: 'brawl',
  [ToastType.Info]: 'default',
  [ToastType.Error]: 'destructive',
}

const DefaultToastDuration = 10e3

export const useToastStore = create((set, get) => ({
  toasts: [],

  timeoutsByToastId: {},

  addSimpleSuccessToast: (message) => {
    get().addToast({
      type: ToastType.Success,
      title: message,
    })
  },

  addToast: (toast) => {
    toast.id = randomString()
    toast.variant = toast.variant || ToastTypeToVariant[toast.type]

    let timeout = null
    if (!toast.manualDismiss) {
      timeout = setTimeout(() => {
        get().dismissToast(toast.id)
      }, toast.duration || DefaultToastDuration)
    }

    set((state) => ({
      toasts: [...state.toasts, toast],
      timeoutsByToastId: {
        ...state.timeoutsByToastId,
        ...timeout && { [toast.id]: timeout },
      }
    }))
  },

  dismissToast: (toastId) => {
    clearTimeout(get().timeoutsByToastId[toastId])
    set((state) => ({
      toasts: state.toasts.filter((t) => t.id !== toastId),
    }))
  }
}))