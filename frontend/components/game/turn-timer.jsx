import { Clock } from "lucide-react"

export default function TurnTimer({ timeRemaining, isPaused }) {
  const formatTime = (seconds) => {
    const mins = Math.floor(seconds / 60)
    const secs = seconds % 60
    return `${mins}:${secs.toString().padStart(2, "0")}`
  }

  const getTimerColor = () => {
    if (isPaused) return "text-burnt"
    if (timeRemaining <= 5) return "text-ember animate-pulse"
    return "text-ember-light"
  }

  return (
    <div className="flex items-center panel-texture px-4 py-2 rounded-full border border-ember/20">
      <Clock className={`h-4 w-4 mr-2 ${getTimerColor()}`} />
      <span className={`font-mono font-medium ${getTimerColor()}`}>
        {isPaused ? "PAUSED" : formatTime(timeRemaining)}
      </span>
    </div>
  )
}
