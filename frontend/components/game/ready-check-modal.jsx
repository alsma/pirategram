import { useEffect, useState } from "react"
import { usePartyStore, selectQueueStatus } from "@/store/party-store"
import { GroupStatus, SlotStatus } from '@/lib/constants/matchmaking.js'
import { Dialog, DialogContent } from "@/components/ui/dialog"
import { Avatar, AvatarFallback, AvatarImage } from "@/components/ui/avatar"
import { Check, XCircle } from "lucide-react"
import { navigate } from 'vike/client/router'

export default function ReadyCheckModal() {
  const {
    state,
    slots,
    yourSlot,
    readyExpiresAt,
    startAt,
    matchId,
    acceptMatch,
    declineMatch,
  } = usePartyStore()
  const queueStatus = usePartyStore(selectQueueStatus)

  const [countdown, setCountdown] = useState(15)
  const [pending, setPending] = useState(false)

  // Update countdown based on readyExpiresAt or startAt
  useEffect(() => {
    if (state === GroupStatus.Proposed && readyExpiresAt) {
      const interval = setInterval(() => {
        const now = Math.floor(Date.now() / 1000)
        const remaining = readyExpiresAt - now
        setCountdown(Math.max(0, remaining))
      }, 1000)

      return () => clearInterval(interval)
    } else if (state === GroupStatus.Starting && startAt) {
      const interval = setInterval(() => {
        const now = Math.floor(Date.now() / 1000)
        const remaining = startAt - now
        setCountdown(Math.max(0, remaining))
      }, 1000)

      return () => clearInterval(interval)
    }
  }, [state, readyExpiresAt, startAt])

  // Navigate to match when it starts
  useEffect(() => {
    if (state === GroupStatus.InMatch && matchId) {
      navigate(`/match/${matchId}`)
    }
  }, [state, matchId])

  const handleAccept = async () => {
    if (pending) return
    setPending(true)
    try {
      await acceptMatch()
    } finally {
      setPending(false)
    }
  }

  const handleDecline = async () => {
    if (pending) return
    setPending(true)
    try {
      await declineMatch()
    } finally {
      setPending(false)
    }
  }

  const getSlotStatus = (slot) => {
    return slot.status
  }

  const isMySlot = (slot) => {
    return slot.slot === yourSlot
  }

  const mySlotData = slots.find(s => s.slot === yourSlot)
  const isReady = mySlotData?.status === SlotStatus.Accepted

  return (
    <Dialog open={queueStatus === "lobby-ready"} onOpenChange={() => {}}>
      <DialogContent className="panel-texture border border-ember/30 shadow-xl max-w-md">
        <div className="flex flex-col items-center justify-center py-6">
          <h2 className="text-xl font-bold text-white mb-6">
            {state === GroupStatus.Starting ? 'Match Starting' : 'Match Found'}
          </h2>

          <div className="grid grid-cols-2 md:grid-cols-4 gap-6 mb-8">
            {slots.map((slot) => (
              <div key={slot.slot} className="flex flex-col items-center">
                <div className="relative">
                  <div
                    className="absolute inset-0 rounded-full"
                    style={{
                      background: `conic-gradient(${
                        slot.status === SlotStatus.Accepted ? "#c0392b" :
                        slot.status === SlotStatus.Declined ? "#555" :
                        "#6366f1"
                      } ${(countdown / 15) * 100}%, transparent 0)`,
                    }}
                  />
                  <div className="absolute inset-1 bg-gray-800 rounded-full" />
                  <Avatar className="h-16 w-16 relative border-2 border-transparent z-10">
                    <AvatarImage src="/placeholder.svg" />
                    <AvatarFallback className="bg-gray-800">
                      {isMySlot(slot) ? 'You' : `P${slot.slot}`}
                    </AvatarFallback>
                  </Avatar>

                  {slot.status === SlotStatus.Accepted && (
                    <div className="absolute bottom-0 right-0 bg-ember rounded-full p-1 z-20 border-2 border-gray-800">
                      <Check className="h-3 w-3 text-white" />
                    </div>
                  )}
                  {slot.status === SlotStatus.Declined && (
                    <div className="absolute bottom-0 right-0 bg-gray-600 rounded-full p-1 z-20 border-2 border-gray-800">
                      <XCircle className="h-3 w-3 text-white" />
                    </div>
                  )}
                </div>
                <span className="text-sm text-gray-300 mt-2">
                  {isMySlot(slot) ? 'You' : `Player ${slot.slot}`}
                  {slot.team_id && ` (Team ${slot.team_id})`}
                </span>
              </div>
            ))}
          </div>

          <div className="flex space-x-4">
            {state === GroupStatus.Proposed && !isReady ? (
              <>
                <button
                  className="dota-button"
                  onClick={handleAccept}
                  disabled={pending}
                >
                  <Check className="mr-2 h-4 w-4 inline-block" />
                  Accept
                </button>

                <button
                  className="bg-gray-700 hover:bg-gray-600 text-ember hover:text-ember-light font-medium px-4 py-2 rounded-md shadow-md border border-ember/30 transition-all duration-200"
                  onClick={handleDecline}
                  disabled={pending}
                >
                  <XCircle className="mr-2 h-4 w-4 inline-block" />
                  Decline
                </button>
              </>
            ) : state === GroupStatus.Starting ? (
              <div className="text-ember-light font-medium">
                Starting in {countdown}s...
              </div>
            ) : (
              <div className="text-ember-light font-medium">
                Waiting for other players... ({countdown}s)
              </div>
            )}
          </div>
        </div>
      </DialogContent>
    </Dialog>
  )
}
