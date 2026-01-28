import { useCallback, useEffect, useState } from "react"
import { usePartyStore, selectQueueStatus } from "@/store/party-store"
import { Dialog, DialogContent, DialogDescription, DialogTitle } from "@/components/ui/dialog"
import { Loader2, XCircle } from "lucide-react"

export default function MatchmakingModal() {
  const { searchStartedAt, searchExpiresAt, cancelQueue } = usePartyStore()
  const queueStatus = usePartyStore(selectQueueStatus)
  const [elapsed, setElapsed] = useState(0)
  const [pending, setPending] = useState(false)

  const cancelSearch = useCallback(async () => {
    if (pending) return

    setPending(true)
    try {
      await cancelQueue()
    } finally {
      setPending(false)
    }
  }, [setPending, pending, cancelQueue])

  // Update elapsed time
  useEffect(() => {
    if (!searchStartedAt) return

    const interval = setInterval(() => {
      const now = Math.floor(Date.now() / 1000)
      setElapsed(now - searchStartedAt)
    }, 1000)

    return () => clearInterval(interval)
  }, [searchStartedAt])

  // Calculate remaining time
  const remaining = searchExpiresAt && searchStartedAt
    ? Math.max(0, searchExpiresAt - Math.floor(Date.now() / 1000))
    : null

  return (
    <Dialog open={queueStatus === "queuing"}>
      <DialogTitle className="sr-only">Game search dialog</DialogTitle>
      <DialogContent
        closable={false}
        className="panel-texture border border-ember/30 shadow-xl max-w-md"
      >
        <DialogDescription className="sr-only">
          Searching game for you...
        </DialogDescription>
        <div className="flex flex-col items-center justify-center py-6">
          <div className="relative mb-6">
            <Loader2 className="h-16 w-16 text-ember animate-spin" />
            <div className="absolute inset-0 flex items-center justify-center">
              <span className="text-sm font-medium text-white">{elapsed}s</span>
            </div>
          </div>

          <h2 className="text-xl font-bold text-white mb-2">Finding Match</h2>
          <p className="text-gray-300 text-center mb-6">Searching for players of similar skill level...</p>

          {remaining !== null && (
            <div className="bg-gray-800/50 border border-ember/20 rounded-lg px-4 py-2 mb-6">
              <span className="text-ember-light">Search timeout in: </span>
              <span className="text-white font-medium">{Math.floor(remaining / 60)}m {remaining % 60}s</span>
            </div>
          )}

          <button className="dota-button" onClick={cancelSearch} disabled={pending}>
            <XCircle className="mr-2 h-4 w-4 inline-block" />
            Cancel Search
          </button>
        </div>
      </DialogContent>
    </Dialog>
  )
}
