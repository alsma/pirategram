"use client"

import { create } from "zustand"

// Mock data for demo
const mockFriends = [
  { id: "friend-1", handle: "Sailor#5678", avatar: "", status: "online" },
  { id: "friend-2", handle: "Captain#9012", avatar: "", status: "in-game" },
  { id: "friend-3", handle: "Navigator#3456", avatar: "", status: "offline" },
]

const mockRequests = [
  { id: "request-1", handle: "Explorer#7890", avatar: "" },
  { id: "request-2", handle: "Voyager#1234", avatar: "" },
]

export const useFriendsStore = create((set, get) => ({
  friends: mockFriends,
  requests: mockRequests,
  isLoading: false,

  addFriend: (identifier) => {
    // In a real app, this would be an API call
    // This is just for demo purposes
    set({ isLoading: true })

    // Simulate API call
    setTimeout(() => {
      set({ isLoading: false })
    }, 500)
  },

  acceptRequest: (requestId) => {
    const { requests } = get()

    // Find the request
    const request = requests.find((r) => r.id === requestId)

    if (!request) return

    // In a real app, this would be an API call
    set({ isLoading: true })

    // Simulate API call
    setTimeout(() => {
      set((state) => ({
        friends: [
          ...state.friends,
          {
            id: request.id,
            handle: request.handle,
            avatar: request.avatar,
            status: "online",
          },
        ],
        requests: state.requests.filter((r) => r.id !== requestId),
        isLoading: false,
      }))
    }, 500)
  },

  rejectRequest: (requestId) => {
    // In a real app, this would be an API call
    set({ isLoading: true })

    // Simulate API call
    setTimeout(() => {
      set((state) => ({
        requests: state.requests.filter((r) => r.id !== requestId),
        isLoading: false,
      }))
    }, 500)
  },

  removeFriend: (friendId) => {
    // In a real app, this would be an API call
    set({ isLoading: true })

    // Simulate API call
    setTimeout(() => {
      set((state) => ({
        friends: state.friends.filter((f) => f.id !== friendId),
        isLoading: false,
      }))
    }, 500)
  },
}))
