import { useEffect, useRef } from "react"

export default function MoveLog({ moves }) {
  const logRef = useRef(null)

  useEffect(() => {
    if (logRef.current) {
      logRef.current.scrollTop = logRef.current.scrollHeight
    }
  }, [moves])

  const formatTimestamp = (timestamp) => {
    const date = new Date(timestamp)
    return date.toLocaleTimeString([], { hour: "2-digit", minute: "2-digit", second: "2-digit" })
  }

  const getPlayerColor = (playerId) => {
    switch (playerId) {
      case "player1":
        return "text-ember-light"
      case "player2":
        return "text-burnt"
      case "player3":
        return "text-brawl"
      case "player4":
        return "text-slate-light"
      default:
        return "text-gray-400"
    }
  }

  const formatCoordinates = (x, y) => {
    // Convert to chess-like notation (A1, B2, etc.)
    const col = String.fromCharCode(65 + x)
    const row = y + 1
    return `${col}${row}`
  }

  return (
    <div ref={logRef} className="flex-1 overflow-y-auto space-y-2 pr-2">
      {moves.length === 0 ? (
        <div className="text-gray-400 text-center py-4">No moves yet</div>
      ) : (
        moves.map((move) => (
          <div key={move.id} className="bg-gray-800/50 border border-ember/10 rounded-lg p-2 text-sm">
            <div className="flex justify-between items-start">
              <span className={`font-medium ${getPlayerColor(move.playerId)}`}>{move.playerHandle}</span>
              <span className="text-xs text-gray-400">{formatTimestamp(move.timestamp)}</span>
            </div>

            <div className="mt-1 text-gray-300">
              {move.piece} {formatCoordinates(move.from.x, move.from.y)} → {formatCoordinates(move.to.x, move.to.y)}
              {move.capture && <span className="text-ember"> (captured {move.capture})</span>}
            </div>
          </div>
        ))
      )}
    </div>
  )
}
