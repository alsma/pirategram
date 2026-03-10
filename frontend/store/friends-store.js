import { create } from "zustand"
import {
  getFriends,
  getFriendRequests,
  searchUsers,
  sendFriendRequest,
  acceptFriendRequest,
  declineFriendRequest,
  removeFriend,
  sendHeartbeat,
  sendAway
} from '@/api/friends.js'
import { UserPresenceStatus } from '@/lib/constants/social.js'
import { toast } from 'sonner'

export const useFriendsStore = create((set, get) => ({
  // State
  friends: [], // [{ userHash, username, status, friendsSince }]
  incomingRequests: [], // [{ requesterHash, username, requestedAt }]
  outgoingRequests: [], // [{ recipientHash, username, requestedAt }]
  isLoading: true,
  heartbeatInterval: null,

  // Actions
  sendRequest: async (friendHash) => {
    try {
      await sendFriendRequest(friendHash)
      toast.success('Friend request sent!')
      // Refresh outgoing requests to show the new one
      await get().refreshRequests()
    } catch (error) {
      console.error('Failed to send friend request:', error)
      toast.error(error.details?.message || 'Failed to send friend request')
    }
  },

  acceptRequest: async (requesterHash) => {
    try {
      await acceptFriendRequest(requesterHash)
      toast.success('Friend request accepted!')
      // Refresh both friends and requests
      await Promise.all([
        get().refreshFriends(),
        get().refreshRequests()
      ])
    } catch (error) {
      console.error('Failed to accept friend request:', error)
      toast.error(error.details?.message || 'Failed to accept friend request')
    }
  },

  declineRequest: async (requesterHash) => {
    try {
      await declineFriendRequest(requesterHash)
      toast.info('Friend request declined')
      // Update state locally
      set((state) => ({
        incomingRequests: state.incomingRequests.filter(req => req.requesterHash !== requesterHash)
      }))
    } catch (error) {
      console.error('Failed to decline friend request:', error)
      toast.error(error.details?.message || 'Failed to decline friend request')
    }
  },

  removeFriend: async (friendHash) => {
    try {
      await removeFriend(friendHash)
      toast.info('Friend removed')
      // Update state locally
      set((state) => ({
        friends: state.friends.filter(friend => friend.userHash !== friendHash)
      }))
    } catch (error) {
      console.error('Failed to remove friend:', error)
      toast.error(error.details?.message || 'Failed to remove friend')
    }
  },

  searchForUsers: async (query) => {
    try {
      const response = await searchUsers(query)
      return response.results || []
    } catch (error) {
      console.error('Failed to search users:', error)
      toast.error(error.details?.message || 'Failed to search users')
      return []
    }
  },

  refreshFriends: async () => {
    try {
      set({ isLoading: true })
      const response = await getFriends()
      set({ friends: response.friends || [], isLoading: false })
    } catch (error) {
      console.error('Failed to fetch friends:', error)
      set({ isLoading: false })
    }
  },

  refreshRequests: async () => {
    try {
      const response = await getFriendRequests()
      set({
        incomingRequests: response.incoming || [],
        outgoingRequests: response.outgoing || []
      })
    } catch (error) {
      console.error('Failed to fetch friend requests:', error)
    }
  },

  restoreState: async () => {
    await Promise.all([
      get().refreshFriends(),
      get().refreshRequests()
    ])
  },

  // WebSocket handlers
  handleRequestReceived: (data) => {
    const { requesterHash, username } = data
    set((state) => ({
      incomingRequests: [
        ...state.incomingRequests,
        {
          requesterHash,
          username,
          requestedAt: new Date().toISOString()
        }
      ]
    }))
    toast.info(`${username} sent you a friend request`)
  },

  handleRequestAccepted: (data) => {
    const { userHash, username, friendsSince, status } = data

    // Remove from outgoing requests if present
    set((state) => ({
      outgoingRequests: state.outgoingRequests.filter(req => req.recipientHash !== userHash),
      friends: [
        ...state.friends,
        {
          userHash,
          username,
          status: status ?? UserPresenceStatus.Offline,
          friendsSince
        }
      ]
    }))
    toast.success(`${username} accepted your friend request!`)
  },

  handleRequestDeclined: (data) => {
    const { declinerHash } = data

    // Remove from outgoing requests
    set((state) => ({
      outgoingRequests: state.outgoingRequests.filter(req => req.recipientHash !== declinerHash)
    }))
  },

  handleFriendRemoved: (data) => {
    const { friendHash } = data

    // Remove from friends list
    set((state) => ({
      friends: state.friends.filter(friend => friend.userHash !== friendHash)
    }))
  },

  handleStatusChanged: (data) => {
    const { friendHash, status } = data

    // Update friend status
    set((state) => ({
      friends: state.friends.map(friend =>
        friend.userHash === friendHash
          ? { ...friend, status }
          : friend
      )
    }))
  },

  // Heartbeat system
  startHeartbeat: () => {
    // Clear any existing interval first to allow restart
    const { heartbeatInterval } = get()
    if (heartbeatInterval) {
      clearInterval(heartbeatInterval)
    }

    // Send initial heartbeat
    sendHeartbeat().catch(error => {
      console.error('Failed to send heartbeat:', error)
    })

    // Set up 2-minute interval (120000ms)
    const intervalId = setInterval(() => {
      sendHeartbeat().catch(error => {
        console.error('Failed to send heartbeat:', error)
      })
    }, 120000)

    set({ heartbeatInterval: intervalId })
  },

  stopHeartbeat: () => {
    const { heartbeatInterval } = get()

    if (heartbeatInterval) {
      clearInterval(heartbeatInterval)
      set({ heartbeatInterval: null })
    }
  },

  goAway: () => {
    get().stopHeartbeat()
    sendAway().catch(error => {
      console.error('Failed to send away status:', error)
    })
  }
}))
