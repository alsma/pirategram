import { create } from "zustand"

// Mock data for demo
const mockParty = [{ id: "current-user-id", handle: "Pirate#1234", avatar: "" }]

export const usePartyStore = create((set) => ({
  party: mockParty,
  leaderId: "current-user-id", // Current user is the leader
  queueStatus: "idle",
  isReady: false,

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

  startQueue: () => {
    set({ queueStatus: "queuing" })

    // Simulate finding a match after some time
    setTimeout(() => {
      set({ queueStatus: "lobby-ready" })
    }, 5000)
  },

  cancelQueue: () => {
    set({
      queueStatus: "idle",
      isReady: false,
    })
  },

  setReady: (ready) => {
    set({ isReady: ready })
  },
}))
