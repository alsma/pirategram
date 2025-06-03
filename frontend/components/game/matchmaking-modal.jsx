import { useEffect, useState } from "react"
import { usePartyStore } from "@/store/party-store"
import { Dialog, DialogContent } from "@/components/ui/dialog"
import { Loader2, XCircle } from "lucide-react"

export default function MatchmakingModal() {
  const { queueStatus, cancelQueue } = usePartyStore()
  const [eta, setEta] = useState(60)
  const [elapsed, setElapsed] = useState(0)

  useEffect(() => {
    const interval = setInterval(() => {
      setElapsed((prev) => prev + 1)

      // Simulate ETA updates from server
      if (elapsed % 10 === 0) {
        setEta((prev) => Math.max(prev - Math.floor(Math.random() * 10), 10))
      }
    }, 1000)

    return () => clearInterval(interval)
  }, [elapsed])

  // Simulate finding a match after some time
  useEffect(() => {
    const timeout = setTimeout(() => {
      // This would normally come from the server via WebSocket
      if (queueStatus === "queuing") {
        // In a real app, this would be handled by the WebSocket event
      }
    }, 15000)

    return () => clearTimeout(timeout)
  }, [queueStatus])

  return (
    <Dialog open={queueStatus === "queuing"} onOpenChange={() => {}}>
      <DialogContent className="panel-texture border border-ember/30 shadow-xl max-w-md">
        <div className="flex flex-col items-center justify-center py-6">
          <div className="relative mb-6">
            <Loader2 className="h-16 w-16 text-ember animate-spin" />
            <div className="absolute inset-0 flex items-center justify-center">
              <span className="text-sm font-medium text-white">{elapsed}s</span>
            </div>
          </div>

          <h2 className="text-xl font-bold text-white mb-2">Finding Match</h2>
          <p className="text-gray-300 text-center mb-6">Searching for players of similar skill level...</p>

          <div className="bg-gray-800/50 border border-ember/20 rounded-lg px-4 py-2 mb-6">
            <span className="text-ember-light">Estimated wait time: </span>
            <span className="text-white font-medium">{eta} seconds</span>
          </div>

          <button className="dota-button" onClick={cancelQueue}>
            <XCircle className="mr-2 h-4 w-4 inline-block" />
            Cancel Search
          </button>
        </div>
      </DialogContent>
    </Dialog>
  )
}
