import { create } from "zustand"
import { startSearch, cancelSearch, acceptTicket, declineTicket, clearSessionId, getSessionId, getState } from '@/api/matchmaking.js'
import { GroupStatus, SlotStatus } from '@/lib/constants/matchmaking.js'
import { toast } from 'sonner'

// Mock data for demo
const mockParty = [{ id: "current-user-id", handle: "Pirate#1234", avatar: "" }]

export const usePartyStore = create((set, get) => ({
  party: mockParty,
  leaderId: "current-user-id",

  state: GroupStatus.Idle,
  mode: null,

  // Search data
  searchStartedAt: null,
  searchExpiresAt: null,

  // Ticket data
  ticketId: null,
  readyExpiresAt: null,
  startAt: null,
  slots: [],
  yourSlot: null,

  // Match data
  matchId: null,

  addToParty: (member) => {
    set((state) => ({
      party: [...state.party, member],
    }))
  },

  removeFromParty: (memberId) => {
    set((state) => ({
      party: state.party.filter((m) => m.id !== memberId),
    }))
  },

  startQueue: async (mode) => {
    try {
      await startSearch(mode)
      // State will be updated via WebSocket event
    } catch (error) {
      console.error('Failed to start search:', error)

      // Handle multi-tab error (409 Conflict)
      if (error.status === 409 || (error.details?.message && error.details.message.includes('MULTI_TAB'))) {
        toast.error('Another tab is already searching', {
          description: 'Close other tabs or wait for the search to complete',
          duration: 5000,
        })
      } else if (error.details?.message) {
        toast.error(error.details.message)
      } else {
        toast.error('Failed to start search')
      }
    }
  },

  cancelQueue: async () => {
    try {
      await cancelSearch()
      // State will be updated via WebSocket event
    } catch (error) {
      console.error('Failed to cancel search:', error)
    }
  },

  acceptMatch: async () => {
    const { ticketId } = get()
    if (!ticketId) return

    try {
      await acceptTicket(ticketId)
      // State will be updated via WebSocket event
    } catch (error) {
      console.error('Failed to accept ticket:', error)
    }
  },

  declineMatch: async () => {
    const { ticketId } = get()
    if (!ticketId) return

    try {
      await declineTicket(ticketId)
      // State will be updated via WebSocket event
    } catch (error) {
      console.error('Failed to decline ticket:', error)
    }
  },

  // WebSocket event handlers
  handleSearchUpdated: (data) => {
    set({
      state: data.state,
      mode: data.mode || null,
      searchStartedAt: data.searchStartedAt || null,
      searchExpiresAt: data.searchExpiresAt || null,
    })
  },

  handleTicketCreated: (data) => {
    set({
      state: GroupStatus.Proposed,
      ticketId: data.ticketId,
      mode: data.mode,
      readyExpiresAt: data.readyExpiresAt,
      slots: data.slots,
      yourSlot: data.yourSlot,
    })
  },

  handleTicketUpdated: (data) => {
    set((state) => ({
      slots: state.slots.map(slot => {
        const update = data.updates.find(u => u.slot === slot.slot)
        return update ? { ...slot, ...update } : slot
      })
    }))
  },

  handleTicketExpired: (data) => {
    if (data.backToSearch) {
      // Returned to queue
      set({
        state: GroupStatus.Searching,
        ticketId: null,
        readyExpiresAt: null,
        startAt: null,
        slots: [],
        yourSlot: null,
      })
    } else {
      // Stopped (you declined or timed out)
      set({
        state: GroupStatus.Idle,
        mode: null,
        ticketId: null,
        readyExpiresAt: null,
        startAt: null,
        slots: [],
        yourSlot: null,
        searchStartedAt: null,
        searchExpiresAt: null,
      })
    }
  },

  handleStarting: (data) => {
    set({
      state: GroupStatus.Starting,
      startAt: data.startAt,
    })
  },

  handleMatchStarted: (data) => {
    set({
      state: GroupStatus.InMatch,
      matchId: data.matchId,
    })
  },

  restoreState: async () => {
    try {
      const state = await getState()

      // Update store based on backend state
      if (state.state === GroupStatus.Idle) {
        set({
          state: GroupStatus.Idle,
          mode: null,
          searchStartedAt: null,
          searchExpiresAt: null,
          ticketId: null,
          readyExpiresAt: null,
          startAt: null,
          slots: [],
          yourSlot: null,
          matchId: null,
        })
      } else if (state.state === GroupStatus.Searching) {
        set({
          state: GroupStatus.Searching,
          mode: state.mode,
          searchStartedAt: state.searchStartedAt,
          searchExpiresAt: state.searchExpiresAt,
        })
      } else if (state.state === GroupStatus.Proposed) {
        set({
          state: GroupStatus.Proposed,
          mode: state.mode,
          ticketId: state.ticketId,
          readyExpiresAt: state.readyExpiresAt,
          slots: state.slots || [],
          yourSlot: state.yourSlot || null,
        })
      } else if (state.state === GroupStatus.Starting) {
        set({
          state: GroupStatus.Starting,
          mode: state.mode,
          ticketId: state.ticketId,
          startAt: state.startAt,
          slots: state.slots || [],
          yourSlot: state.yourSlot || null,
        })
      } else if (state.state === GroupStatus.InMatch) {
        set({
          state: GroupStatus.InMatch,
          matchId: state.matchId,
        })
      }
    } catch (error) {
      console.error('Failed to restore matchmaking state:', error)
    }
  },
}))

// Helper selectors (use these in components)
export const selectQueueStatus = (state) => {
  if (state.state === GroupStatus.Searching) return 'queuing'
  if (state.state === GroupStatus.Proposed || state.state === GroupStatus.Starting) return 'lobby-ready'
  return 'idle'
}

export const selectIsReady = (state) => {
  if (!state.yourSlot || !state.slots.length) return false
  const mySlot = state.slots.find(s => s.slot === state.yourSlot)
  return mySlot?.status === SlotStatus.Accepted
}
