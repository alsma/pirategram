import { useEffect, useState } from "react"
import { usePartyStore } from "@/store/party-store"
import { Dialog, DialogContent } from "@/components/ui/dialog"
import { Avatar, AvatarFallback, AvatarImage } from "@/components/ui/avatar"
import { Check, XCircle } from "lucide-react"
import { toast } from "sonner"
import { navigate } from 'vike/client/router'

export default function ReadyCheckModal() {
  const { party, queueStatus, isReady, setReady, cancelQueue } = usePartyStore()
  const [countdown, setCountdown] = useState(20)
  const [readyPlayers, setReadyPlayers] = useState([])

  // Simulate other players getting ready
  useEffect(() => {
    if (queueStatus === "lobby-ready") {
      const playerReadyTimers = party.map((player) => {
        // Skip current user
        if (player.id === "current-user-id") return null

        return setTimeout(
          () => {
            setReadyPlayers((prev) => [...prev, player.id])

            // If all players are ready, start the match
            if (readyPlayers.length === party.length - 1 && isReady) {
              setTimeout(() => {
                navigate("/match/123")
              }, 1000)
            }
          },
          Math.random() * 10000 + 2000,
        )
      })

      return () => {
        playerReadyTimers.forEach((timer) => timer && clearTimeout(timer))
      }
    }
  }, [queueStatus, party, isReady, readyPlayers])

  // Countdown timer
  useEffect(() => {
    if (queueStatus === "lobby-ready" && countdown > 0) {
      const timer = setTimeout(() => {
        setCountdown((prev) => prev - 1)
      }, 1000)

      return () => clearTimeout(timer)
    } else if (countdown === 0) {
      // Time expired
      cancelQueue()
      toast.error("Match cancelled - not all players were ready")
    }
  }, [countdown, queueStatus, cancelQueue])

  const handleReady = () => {
    setReady(true)

    // Check if all other players are ready
    if (readyPlayers.length === party.length - 1) {
      setTimeout(() => {
        navigate("/match/123")
      }, 1000)
    }
  }

  const handleDecline = () => {
    cancelQueue()
    toast.info("Match declined")
  }

  const isPlayerReady = (playerId) => {
    if (playerId === "current-user-id") return isReady
    return readyPlayers.includes(playerId)
  }

  return (
    <Dialog open={queueStatus === "lobby-ready"} onOpenChange={() => {}}>
      <DialogContent className="panel-texture border border-ember/30 shadow-xl max-w-md">
        <div className="flex flex-col items-center justify-center py-6">
          <h2 className="text-xl font-bold text-white mb-6">Match Found</h2>

          <div className="grid grid-cols-2 md:grid-cols-4 gap-6 mb-8">
            {party.map((player, index) => (
              <div key={index} className="flex flex-col items-center">
                <div className="relative">
                  <div
                    className="absolute inset-0 rounded-full"
                    style={{
                      background: `conic-gradient(${isPlayerReady(player.id) ? "#c0392b" : "#6366f1"} ${(countdown / 20) * 100}%, transparent 0)`,
                    }}
                  />
                  <div className="absolute inset-1 bg-gray-800 rounded-full" />
                  <Avatar className="h-16 w-16 relative border-2 border-transparent z-10">
                    <AvatarImage src={player.avatar || "/placeholder.svg"} />
                    <AvatarFallback className="bg-gray-800">{player.handle.substring(0, 2)}</AvatarFallback>
                  </Avatar>

                  {isPlayerReady(player.id) && (
                    <div className="absolute bottom-0 right-0 bg-ember rounded-full p-1 z-20 border-2 border-gray-800">
                      <Check className="h-3 w-3 text-white" />
                    </div>
                  )}
                </div>
                <span className="text-sm text-gray-300 mt-2">{player.handle}</span>
              </div>
            ))}
          </div>

          <div className="flex space-x-4">
            {!isReady ? (
              <>
                <button className="dota-button" onClick={handleReady}>
                  <Check className="mr-2 h-4 w-4 inline-block" />
                  Accept
                </button>

                <button
                  className="bg-gray-700 hover:bg-gray-600 text-ember hover:text-ember-light font-medium px-4 py-2 rounded-md shadow-md border border-ember/30 transition-all duration-200"
                  onClick={handleDecline}
                >
                  <XCircle className="mr-2 h-4 w-4 inline-block" />
                  Decline
                </button>
              </>
            ) : (
              <div className="text-ember-light font-medium">Waiting for other players... ({countdown}s)</div>
            )}
          </div>
        </div>
      </DialogContent>
    </Dialog>
  )
}
