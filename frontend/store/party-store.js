import { create } from "zustand"
import { startSearch, cancelSearch, acceptTicket, declineTicket, clearSessionId, getSessionId, getState } from '@/api/matchmaking.js'
import {
  getPartyState,
  createPartyInvite,
  acceptPartyInvite,
  declinePartyInvite,
  leaveParty,
  disbandParty,
  kickPartyMember,
  promotePartyMember,
  startPartySearch,
  cancelPartySearch
} from '@/api/party.js'
import { GroupStatus, SlotStatus, PartyAction } from '@/lib/constants/matchmaking.js'
import { toast } from 'sonner'

export const usePartyStore = create((set, get) => ({
  // Party state
  party: null, // { partyHash, leaderId, leaderHash, mode, members: [{ userId, userHash, username }], maxPlayers }
  pendingInvites: [], // [{ leaderHash, leaderUsername, mode, partyHash? }]
  isLoadingParty: true,

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

  // Party actions
  sendInvite: async (userHash, mode, username = null) => {
    try {
      await createPartyInvite(userHash, mode)
      if (username) {
        toast.success(`Party invite sent to ${username}!`)
      } else {
        toast.success('Party invite sent!')
      }
    } catch (error) {
      console.error('Failed to send invite:', error)
      toast.error(error.details?.message || 'Failed to send invite')
    }
  },

  acceptInvite: async (leaderHash) => {
    try {
      const partyData = await acceptPartyInvite(leaderHash)
      // Update state immediately with returned party data
      set((state) => ({
        party: partyData,
        pendingInvites: state.pendingInvites.filter(inv => inv.leaderHash !== leaderHash)
      }))
      toast.success('Invite accepted!')
    } catch (error) {
      console.error('Failed to accept invite:', error)
      toast.error(error.details?.message || 'Failed to accept invite')
    }
  },

  declineInvite: async (leaderHash) => {
    try {
      await declinePartyInvite(leaderHash)
      set((state) => ({
        pendingInvites: state.pendingInvites.filter(inv => inv.leaderHash !== leaderHash)
      }))
      toast.info('Invite declined')
    } catch (error) {
      console.error('Failed to decline invite:', error)
      toast.error(error.details?.message || 'Failed to decline invite')
    }
  },

  leaveParty: async () => {
    try {
      await leaveParty()
      // State will be updated via WebSocket event
      toast.info('Left the party')
    } catch (error) {
      console.error('Failed to leave party:', error)
      toast.error(error.details?.message || 'Failed to leave party')
    }
  },

  disbandParty: async () => {
    try {
      await disbandParty()
      // State will be updated via WebSocket event
      toast.info('Party disbanded')
    } catch (error) {
      console.error('Failed to disband party:', error)
      toast.error(error.details?.message || 'Failed to disband party')
    }
  },

  kickMember: async (memberHash) => {
    try {
      const { party } = get()
      if (!party) return

      await kickPartyMember(memberHash)
      // State will be updated via WebSocket event
      toast.info('Member kicked from party')
    } catch (error) {
      console.error('Failed to kick member:', error)
      toast.error(error.details?.message || 'Failed to kick member')
    }
  },

  promoteMember: async (memberHash) => {
    try {
      const { party } = get()
      if (!party) return

      await promotePartyMember(memberHash)
      // State will be updated via WebSocket event
      toast.success('Leadership transferred')
    } catch (error) {
      console.error('Failed to promote member:', error)
      toast.error(error.details?.message || 'Failed to promote member')
    }
  },

  startQueue: async (mode) => {
    const { party } = get()

    try {
      // If in a party, use party search, otherwise solo search
      if (party) {
        await startPartySearch(party.partyHash)
      } else {
        await startSearch(mode)
      }
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
    const { party } = get()

    try {
      // If in a party, use party cancel, otherwise solo cancel
      if (party) {
        await cancelPartySearch()
      } else {
        await cancelSearch()
      }
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

  // Party WebSocket event handlers
  handlePartyUpdated: (data) => {
    const { action, state } = data

    switch (action) {
      case PartyAction.Created:
      case PartyAction.MemberJoined:
      case PartyAction.MemberLeft:
      case PartyAction.MemberKicked:
      case PartyAction.LeaderChanged:
      case PartyAction.ModeChanged:
        set({ party: state })
        break
      case PartyAction.Disbanded:
        set({ party: null })
        toast.info('Party disbanded')
        break
      default:
        break
    }
  },

  handleInviteReceived: (data) => {
    set((state) => ({
      pendingInvites: [
        ...state.pendingInvites,
        {
          leaderHash: data.leaderHash,
          leaderUsername: data.leaderUsername,
          mode: data.mode,
          partyHash: data.partyHash,
        }
      ]
    }))
    toast.info(`Party invite from ${data.leaderUsername}`, {
      description: `Mode: ${data.mode}`,
      duration: 10000,
    })
  },

  handleInviteDeclined: (data) => {
    toast.info(`${data.username} declined your party invite`)
  },

  // Matchmaking WebSocket event handlers
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
      set({ isLoadingParty: true })
      // Fetch party state and matchmaking state in parallel
      const [partyData, mmState] = await Promise.all([
        getPartyState(),
        getState()
      ])

      // Restore party state (ensure it's null or has proper structure)
      const validParty = partyData && partyData.partyHash && partyData.members ? partyData : null
      set({ party: validParty, isLoadingParty: false })

      // Update store based on backend matchmaking state
      if (mmState.state === GroupStatus.Idle) {
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
      } else if (mmState.state === GroupStatus.Searching) {
        set({
          state: GroupStatus.Searching,
          mode: mmState.mode,
          searchStartedAt: mmState.searchStartedAt,
          searchExpiresAt: mmState.searchExpiresAt,
        })
      } else if (mmState.state === GroupStatus.Proposed) {
        set({
          state: GroupStatus.Proposed,
          mode: mmState.mode,
          ticketId: mmState.ticketId,
          readyExpiresAt: mmState.readyExpiresAt,
          slots: mmState.slots || [],
          yourSlot: mmState.yourSlot || null,
        })
      } else if (mmState.state === GroupStatus.Starting) {
        set({
          state: GroupStatus.Starting,
          mode: mmState.mode,
          ticketId: mmState.ticketId,
          startAt: mmState.startAt,
          slots: mmState.slots || [],
          yourSlot: mmState.yourSlot || null,
        })
      } else if (mmState.state === GroupStatus.InMatch) {
        set({
          state: GroupStatus.InMatch,
          matchId: mmState.matchId,
        })
      }
    } catch (error) {
      console.error('Failed to restore matchmaking state:', error)
      set({ isLoadingParty: false })
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
